<?php

declare(strict_types=1);

namespace Osumi\OsumiFramework\App\Service;

use DateTimeImmutable;
use DateTimeZone;
use RuntimeException;
use Throwable;
use Osumi\OsumiFramework\Core\OService;
use Osumi\OsumiFramework\ORM\ODB;
use Osumi\OsumiFramework\App\Exception\BackupConflictException;
use Osumi\OsumiFramework\App\Exception\InvalidOtpvPackageException;
use Osumi\OsumiFramework\App\Model\Backup;
use Osumi\OsumiFramework\App\Model\Installation;
use Osumi\OsumiFramework\App\Model\Subscription;
use Osumi\OsumiFramework\App\Utils\Uuid;
use Osumi\OsumiFramework\App\Result\BackupCreateResult;

class BackupService extends OService {
  private OtpvV3InspectorService $inspector_service;
  private BackupStorageService $storage_service;
  private ?AuditLogService $audit_log_service = null;

  /**
   * Initializes backup service dependencies.
   */
  public function __construct() {
    $this->inspector_service = inject(
      OtpvV3InspectorService::class
    );

    $this->storage_service = inject(
      BackupStorageService::class
    );

    $this->audit_log_service = inject(
      AuditLogService::class
    );
  }

  /**
   * Gets a backup by the identifier declared in its OTPV manifest.
   *
   * @param string $backup_id Manifest backup identifier.
   *
   * @return Backup|null Backup or null when it does not exist.
   */
  public function getByBackupId(
    string $backup_id
  ): ?Backup {
    return Backup::findOne([
      'backup_id' => $backup_id
    ]);
  }

  /**
   * Gets all backups ordered from newest to oldest by client creation date.
   *
   * @return Backup[] Backup list.
   */
  public function getAll(): array {
    return Backup::all([
      'order_by' => 'created_at_client#DESC'
    ]);
  }

  /**
   * Gets all backups registered for an installation.
   *
   * @param Installation $installation Installation whose backups are requested.
   *
   * @return Backup[] Backups registered for the installation.
   *
   * @throws RuntimeException When the installation is not persisted.
   */
  public function getByInstallation(
    Installation $installation
  ): array {
    if (is_null($installation->id)) {
      throw new RuntimeException(
        'Installation must be persisted before loading its backups.'
      );
    }

    return Backup::where(
      [
        'id_installation' => $installation->id
      ],
      [
        'order_by' => 'created_at_client#DESC'
      ]
    );
  }

  /**
   * Gets the configured backup retention limit for an installation.
   *
   * @param Installation $installation Installation whose retention limit is requested.
   *
   * @return int Maximum number of backups retained for the installation.
   *
   * @throws RuntimeException When the installation has no valid subscription.
   */
  public function getRetentionLimit(
    Installation $installation
  ): int {
    if (is_null($installation->id_subscription)) {
      throw new RuntimeException(
        'Installation subscription is required to resolve backup retention.'
      );
    }

    $subscription = Subscription::findOne([
      'id' => $installation->id_subscription
    ]);

    if (is_null($subscription)) {
      throw new RuntimeException(
        'Installation subscription could not be found.'
      );
    }

    if (
      is_null($subscription->max_backups_per_installation) ||
      $subscription->max_backups_per_installation < 1
    ) {
      throw new RuntimeException(
        'Subscription backup retention limit is invalid.'
      );
    }

    return $subscription->max_backups_per_installation;
  }

  /**
   * Gets a backup by its public server identifier.
   *
   * @param string $public_id Backup public identifier.
   *
   * @return Backup|null Backup or null when it does not exist.
   */
  public function getByPublicId(
    string $public_id
  ): ?Backup {
    return Backup::findOne([
      'public_id' => $public_id
    ]);
  }

  /**
   * Opens the stored backup for sequential reading.
   *
   * Metadata and stored file size are checked before returning the stream.
   *
   * @param Backup $backup Backup to open.
   *
   * @return resource Readable backup stream.
   *
   * @throws RuntimeException When the backup metadata or stored object is inconsistent.
   */
  public function openReadStream(
    Backup $backup
  ): mixed {
    if (
      is_null($backup->storage_key) ||
      is_null($backup->size_bytes)
    ) {
      throw new RuntimeException(
        'Backup storage metadata is incomplete.'
      );
    }

    if (
      !$this->storage_service->exists(
        $backup->storage_key
      )
    ) {
      throw new RuntimeException(
        'Backup stored file does not exist.'
      );
    }

    $stored_size = $this->storage_service->getSize(
      $backup->storage_key
    );

    if ($stored_size !== $backup->size_bytes) {
      throw new RuntimeException(
        'Backup metadata and stored file size are inconsistent.'
      );
    }

    return $this->storage_service->openReadStream(
      $backup->storage_key
    );
  }

  /**
   * Enforces the maximum number of stored backups for an installation.
   *
   * Backups are considered oldest by the creation date declared by the client.
   * The internal database identifier is used as a deterministic tie breaker.
   *
   * @param Installation $installation Installation whose backups must be limited.
   *
   * @return void
   *
   * @throws RuntimeException When a backup cannot be deleted.
   */
  public function enforceRetention(
    Installation $installation
  ): void {
    $retention_limit = $this->getRetentionLimit(
      $installation
    );

    $backups = $this->getByInstallation(
      $installation
    );

    if (
      count($backups) <=
      $retention_limit
    ) {
      return;
    }

    usort(
      $backups,
      static function(
        Backup $first,
        Backup $second
      ): int {
        $date_comparison = strcmp(
          $first->created_at_client ?? '',
          $second->created_at_client ?? ''
        );

        if ($date_comparison !== 0) {
          return $date_comparison;
        }

        return (
          $first->id ??
          PHP_INT_MAX
        ) <=> (
          $second->id ??
          PHP_INT_MAX
        );
      }
    );

    $delete_count = count($backups) - $retention_limit;

    for (
      $index = 0;
      $index < $delete_count;
      $index++
    ) {
      $backup = $backups[$index];

      $public_id = $backup->public_id;
      $backup_id = $backup->backup_id;
      $created_at_client = $backup->created_at_client;

      $this->delete(
        $backup
      );

      $this->audit_log_service?->recordSystemAction(
        AuditLogService::ACTION_BACKUP_RETENTION_DELETE,
        AuditLogService::ENTITY_BACKUP,
        $public_id,
        [
          'installationPublicId' => $installation->public_id,
          'backupId' => $backup_id,
          'createdAtClient' => $created_at_client
        ]
      );
    }
  }

  /**
   * Deletes a backup metadata record and its stored object.
   *
   * The database deletion is committed before removing the stored object so a
   * database rollback can never restore metadata pointing to an already deleted
   * backup file.
   *
   * A missing stored object is tolerated so orphan metadata can still be removed.
   * If storage cleanup fails after the database commit, the metadata remains
   * deleted and an orphan stored object may remain for later cleanup.
   *
   * @param Backup $backup Backup to delete.
   *
   * @return void
   *
   * @throws RuntimeException When the deletion cannot be completed.
   */
  public function delete(
    Backup $backup
  ): void {
    if (
      is_null($backup->id) ||
      is_null($backup->storage_key)
    ) {
      throw new RuntimeException(
        'Backup must be persisted before deletion.'
      );
    }

    $storage_key = $backup->storage_key;
    $db = ODB::getInstance();

    try {
      $db->beginTransaction();

      if (!$backup->delete()) {
        throw new RuntimeException(
          'Backup metadata could not be deleted.'
        );
      }

      $db->commit();
    }
    catch (Throwable $exception) {
      if ($db->inTransaction()) {
        $db->rollBack();
      }

      throw new RuntimeException(
        'Backup metadata could not be deleted.',
        0,
        $exception
      );
    }

    try {
      if (
        $this->storage_service->exists(
          $storage_key
        )
      ) {
        $this->storage_service->delete(
          $storage_key
        );
      }
    }
    catch (Throwable $exception) {
      throw new RuntimeException(
        'Backup metadata was deleted but stored file cleanup failed.',
        0,
        $exception
      );
    }
  }

  /**
   * Inspects, stores and persists an OTPV backup.
   *
   * Re-uploading the same backup for the same installation is idempotent when
   * the backup identifier and complete file SHA-256 match.
   *
   * @param Installation $installation     Installation that owns the backup.
   * @param string       $file_path        Local temporary OTPV path.
   * @param string       $original_filename Original uploaded file name.
   *
   * @return BackupCreateResult Backup result including whether it was newly created.
   *
   * @throws InvalidOtpvPackageException When the package or original filename is invalid.
   * @throws BackupConflictException When the backup identifier is already used
   *                                 by different content or another installation.
   * @throws RuntimeException When storage or persistence fails.
   */
   public function createFromFile(
     Installation $installation,
     string $file_path,
     string $original_filename
   ): BackupCreateResult {
    if (
      is_null($installation->id) ||
      is_null($installation->public_id)
    ) {
      throw new RuntimeException(
        'Installation must be persisted before storing a backup.'
      );
    }

    $original_filename = $this->normalizeOriginalFilename(
      $original_filename
    );

    $inspection = $this->inspector_service->inspect(
      $file_path
    );

    $existing_backup = $this->getByBackupId(
      $inspection['backupId']
    );

    if (!is_null($existing_backup)) {
      $resolved_backup = $this->resolveExistingBackup(
        $existing_backup,
        $installation,
        $inspection
      );

      $this->enforceRetentionSafely(
        $installation
      );

      return new BackupCreateResult(
        $resolved_backup,
        false
      );
    }

    $public_id = Uuid::v4();

    $storage_key = $this->buildStorageKey(
      $installation,
      $public_id
    );

    $backup = new Backup();
    $backup->public_id = $public_id;
    $backup->id_installation = $installation->id;
    $backup->backup_id = $inspection['backupId'];
    $backup->created_at_client = $this->normalizeClientDate(
      $inspection['createdAt']
    );
    $backup->format_version = $inspection['formatVersion'];
    $backup->application = $inspection['application'];
    $backup->application_version = $inspection['applicationVersion'];
    $backup->database_schema_version = $inspection['databaseSchemaVersion'];
    $backup->original_filename = $original_filename;
    $backup->storage_key = $storage_key;
    $backup->size_bytes = $inspection['sizeBytes'];
    $backup->sha256 = $inspection['sha256'];

    $stored = false;

    try {
      $this->storage_service->storeFile(
        $file_path,
        $storage_key
      );

      $stored = true;

      $stored_size = $this->storage_service->getSize(
        $storage_key
      );

      if ($stored_size !== $inspection['sizeBytes']) {
        throw new RuntimeException(
          'Stored backup size does not match the inspected file.'
        );
      }

      $stored_sha256 =
        $this->storage_service->getSha256(
          $storage_key
        );

      if (
        !hash_equals(
          $inspection['sha256'],
          $stored_sha256
        )
      ) {
        throw new RuntimeException(
          'Stored backup SHA-256 does not match the inspected file.'
        );
      }

      if (!$backup->save()) {
        throw new RuntimeException(
          'Backup metadata could not be persisted.'
        );
      }
    }
    catch (Throwable $exception) {
      if ($stored) {
        try {
          $this->storage_service->delete(
            $storage_key
          );
        }
        catch (Throwable $cleanup_exception) {
          throw new RuntimeException(
            'Backup persistence failed and stored file cleanup also failed.',
            0,
            $cleanup_exception
          );
        }
      }

      /*
       * Another request may have persisted the same backup between our initial
       * existence check and the INSERT. Resolve that race as an idempotent
       * retry when installation and SHA-256 are identical.
       */
      $existing_backup = $this->getByBackupId(
        $inspection['backupId']
      );

      if (!is_null($existing_backup)) {
        $resolved_backup = $this->resolveExistingBackup(
          $existing_backup,
          $installation,
          $inspection
        );

        $this->enforceRetentionSafely(
          $installation
        );

        return new BackupCreateResult(
          $resolved_backup,
          false
        );
      }

      if ($exception instanceof BackupConflictException) {
        throw $exception;
      }

      throw new RuntimeException(
        'Backup could not be persisted.',
        0,
        $exception
      );
    }

    $this->enforceRetentionSafely(
      $installation
    );

    return new BackupCreateResult(
      $backup,
      true
    );
  }

  /**
   * Applies backup retention without invalidating an already persisted backup.
   *
   * Retention cleanup is best-effort. A failure is logged so the successful
   * backup remains available and a later upload or retry can attempt cleanup
   * again.
   *
   * @param Installation $installation Installation whose retention must be enforced.
   *
   * @return void
   */
  private function enforceRetentionSafely(
    Installation $installation
  ): void {
    try {
      $this->enforceRetention(
        $installation
      );
    }
    catch (Throwable $exception) {
      $installation_identifier =
        $installation->public_id
        ?? strval(
          $installation->id
          ?? 'unknown'
        );

      $this->log?->error(
        'Backup retention cleanup failed for installation '
        . "'{$installation_identifier}': "
        . $exception->getMessage()
      );
    }
  }

  /**
   * Resolves an already persisted backup against an incoming OTPV file.
   *
   * Identical retries are accepted. Reusing the backup identifier for another
   * installation or different file contents is rejected.
   *
   * @param Backup              $backup       Existing backup.
   * @param Installation        $installation Incoming installation.
   * @param array{
   *   formatVersion: int,
   *   application: string,
   *   applicationVersion: string,
   *   databaseSchemaVersion: int,
   *   backupId: string,
   *   createdAt: string,
   *   cryptoSuite: string,
   *   sizeBytes: int,
   *   sha256: string
   * } $inspection Inspected OTPV metadata.
   *
   * @return Backup Existing backup when the request is an exact retry.
   *
   * @throws BackupConflictException When the backup identifier conflicts.
   * @throws RuntimeException When persisted metadata and storage are inconsistent.
   */
  private function resolveExistingBackup(
    Backup $backup,
    Installation $installation,
    array $inspection
  ): Backup {
    if (
      $backup->id_installation !== $installation->id ||
      is_null($backup->sha256) ||
      !hash_equals(
        $backup->sha256,
        $inspection['sha256']
      )
    ) {
      throw new BackupConflictException(
        'Backup identifier is already used by another backup.'
      );
    }

    if (
      is_null($backup->storage_key) ||
      !$this->storage_service->exists(
        $backup->storage_key
      )
    ) {
      throw new RuntimeException(
        'Backup metadata exists but its stored file is missing.'
      );
    }

    $stored_size = $this->storage_service->getSize(
      $backup->storage_key
    );

    if (
      is_null($backup->size_bytes) ||
      $stored_size !== $backup->size_bytes ||
      $stored_size !== $inspection['sizeBytes']
    ) {
      throw new RuntimeException(
        'Backup metadata and stored file size are inconsistent.'
      );
    }

    $stored_sha256 =
      $this->storage_service->getSha256(
        $backup->storage_key
      );

    if (
      is_null($backup->sha256) ||
      !hash_equals(
        $backup->sha256,
        $stored_sha256
      )
    ) {
      throw new RuntimeException(
        'Backup metadata and stored file SHA-256 are inconsistent.'
      );
    }

    return $backup;
  }

  /**
   * Builds the logical storage key for a backup.
   *
   * The server-generated public identifier is used instead of the client
   * filename or manifest backup identifier to avoid collisions and unsafe paths.
   *
   * @param Installation $installation Owner installation.
   * @param string       $public_id     Server-generated backup identifier.
   *
   * @return string Logical storage key.
   *
   * @throws RuntimeException When the installation public identifier is missing.
   */
  private function buildStorageKey(
    Installation $installation,
    string $public_id
  ): string {
    if (
      is_null($installation->public_id) ||
      $installation->public_id === ''
    ) {
      throw new RuntimeException(
        'Installation public identifier is required.'
      );
    }

    return 'installations/'
      . $installation->public_id
      . '/'
      . $public_id
      . '.otpv';
  }

  /**
   * Normalizes and validates the original uploaded filename.
   *
   * @param string $filename Original uploaded filename.
   *
   * @return string Safe basename suitable for metadata persistence.
   *
   * @throws InvalidOtpvPackageException When the filename is invalid.
   */
  private function normalizeOriginalFilename(
    string $filename
  ): string {
    $filename = basename(
      str_replace(
        '\\',
        '/',
        trim($filename)
      )
    );

    if (
      $filename === '' ||
      $filename === '.' ||
      $filename === '..' ||
      strlen($filename) > 255 ||
      preg_match(
        '/[\x00-\x1F\x7F]/',
        $filename
      ) === 1
    ) {
      throw new InvalidOtpvPackageException(
        'Invalid OTPV original filename.'
      );
    }

    if (
      strtolower(
        pathinfo(
          $filename,
          PATHINFO_EXTENSION
        )
      ) !== 'otpv'
    ) {
      throw new InvalidOtpvPackageException(
        'Backup file must use the .otpv extension.'
      );
    }

    return $filename;
  }

  /**
   * Converts the client ISO-8601 timestamp to UTC database format.
   *
   * @param string $created_at Client timestamp.
   *
   * @return string UTC timestamp in Y-m-d H:i:s format.
   */
  private function normalizeClientDate(
    string $created_at
  ): string {
    return (new DateTimeImmutable($created_at))
      ->setTimezone(
        new DateTimeZone('UTC')
      )
      ->format('Y-m-d H:i:s');
  }
}
