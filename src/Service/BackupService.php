<?php

declare(strict_types=1);

namespace Osumi\OsumiFramework\App\Service;

use DateTimeImmutable;
use DateTimeZone;
use RuntimeException;
use Throwable;
use Osumi\OsumiFramework\App\Exception\BackupConflictException;
use Osumi\OsumiFramework\App\Exception\InvalidOtpvPackageException;
use Osumi\OsumiFramework\App\Model\Backup;
use Osumi\OsumiFramework\App\Model\Installation;
use Osumi\OsumiFramework\App\Utils\Uuid;
use Osumi\OsumiFramework\Core\OService;

class BackupService extends OService {
  private OtpvV3InspectorService $inspector_service;
  private BackupStorageService $storage_service;

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
   * Inspects, stores and persists an OTPV backup.
   *
   * Re-uploading the same backup for the same installation is idempotent when
   * the backup identifier and complete file SHA-256 match.
   *
   * @param Installation $installation     Installation that owns the backup.
   * @param string       $file_path        Local temporary OTPV path.
   * @param string       $original_filename Original uploaded file name.
   *
   * @return Backup Persisted or previously existing backup.
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
  ): Backup {
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
      return $this->resolveExistingBackup(
        $existing_backup,
        $installation,
        $inspection
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

      if (!$backup->save()) {
        throw new RuntimeException(
          'Backup metadata could not be persisted.'
        );
      }

      return $backup;
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
        return $this->resolveExistingBackup(
          $existing_backup,
          $installation,
          $inspection
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
