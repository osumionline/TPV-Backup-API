<?php

declare(strict_types=1);

namespace Osumi\OsumiFramework\App\Module\Api\V1\Backups\CreateBackup;

use RuntimeException;
use Osumi\OsumiFramework\App\DTO\CreateBackupDTO;
use Osumi\OsumiFramework\App\Exception\BackupConflictException;
use Osumi\OsumiFramework\App\Exception\InvalidOtpvPackageException;
use Osumi\OsumiFramework\App\Service\AuditLogService;
use Osumi\OsumiFramework\App\Service\BackupService;
use Osumi\OsumiFramework\App\Service\InstallationService;
use Osumi\OsumiFramework\Core\OComponent;

class CreateBackupComponent extends OComponent {
  private const MAX_UPLOAD_SIZE = 8 * 1024 * 1024 * 1024;

  private ?BackupService $backup_service = null;
  private ?InstallationService $installation_service = null;
  private ?AuditLogService $audit_log_service = null;

  public string $status = 'error';
  public string $message = '';

  public ?string $public_id = null;
  public ?string $backup_id = null;
  public ?string $created_at_client = null;
  public ?string $original_filename = null;
  public ?int $size_bytes = null;
  public ?string $sha256 = null;

  /**
   * Initializes component dependencies.
   */
  public function __construct() {
    parent::__construct();

    $this->backup_service = inject(
      BackupService::class
    );

    $this->installation_service = inject(
      InstallationService::class
    );

    $this->audit_log_service = inject(
      AuditLogService::class
    );
  }

  /**
   * Stores an OTPV backup uploaded by the authenticated installation.
   *
   * @param CreateBackupDTO $dto Authenticated upload data.
   *
   * @return void
   */
  public function run(
    CreateBackupDTO $dto
  ): void {
    global $core;

    if (
      !$dto->isValid() ||
      is_null($dto->file) ||
      is_null($dto->installationId) ||
      is_null($dto->canUpload)
    ) {
      $this->message = 'Missing required fields.';
      $core->setHttpStatus(400);
      return;
    }

    if ($dto->canUpload !== true) {
      $this->message =
        'Backup upload is not allowed for this subscription.';

      $core->setHttpStatus(403);
      return;
    }

    $file = $dto->file;

    $upload_error = $file['error']
      ?? null;

    if (!is_int($upload_error)) {
      $this->message = 'Invalid uploaded file.';
      $core->setHttpStatus(400);
      return;
    }

    if (
      $upload_error === UPLOAD_ERR_INI_SIZE ||
      $upload_error === UPLOAD_ERR_FORM_SIZE
    ) {
      $this->message = 'Backup file is too large.';
      $core->setHttpStatus(413);
      return;
    }

    if (
      $upload_error === UPLOAD_ERR_NO_FILE ||
      $upload_error === UPLOAD_ERR_PARTIAL
    ) {
      $this->message = 'A complete backup file is required.';
      $core->setHttpStatus(400);
      return;
    }

    if ($upload_error !== UPLOAD_ERR_OK) {
      $this->message = 'Backup upload could not be completed.';
      $core->setHttpStatus(500);
      return;
    }

    $temporary_path = $file['tmp_name']
      ?? null;

    $original_filename = $file['name']
      ?? null;

    if (
      !is_string($temporary_path) ||
      $temporary_path === '' ||
      !is_string($original_filename) ||
      $original_filename === '' ||
      !$this->isUploadedFile(
        $temporary_path
      )
    ) {
      $this->message = 'Invalid uploaded file.';
      $core->setHttpStatus(400);
      return;
    }

    $actual_size = $this->getFileSize(
      $temporary_path
    );

    if (
      is_null($actual_size) ||
      $actual_size <= 0
    ) {
      $this->message = 'Invalid uploaded file.';
      $core->setHttpStatus(400);
      return;
    }

    if (
      $actual_size >
      self::MAX_UPLOAD_SIZE
    ) {
      $this->message = 'Backup file is too large.';
      $core->setHttpStatus(413);
      return;
    }

    $installation =
      $this->installation_service->getById(
        $dto->installationId
      );

    if (is_null($installation)) {
      $this->message =
        'Authenticated installation is not available.';

      $core->setHttpStatus(403);
      return;
    }

    try {
      $backup =
        $this->backup_service->createFromFile(
          $installation,
          $temporary_path,
          $original_filename
        );
    }
    catch (InvalidOtpvPackageException) {
      $this->message =
        'Uploaded file is not a valid OTPV backup.';

      $core->setHttpStatus(422);
      return;
    }
    catch (BackupConflictException) {
      $this->message =
        'Backup identifier conflicts with an existing backup.';

      $core->setHttpStatus(409);
      return;
    }
    catch (RuntimeException) {
      $this->message =
        'Backup could not be stored.';

      $core->setHttpStatus(500);
      return;
    }

    $this->audit_log_service
      ?->recordInstallationAction(
        $installation,
        AuditLogService::ACTION_BACKUP_CREATE,
        AuditLogService::ENTITY_BACKUP,
        $backup->public_id,
        [
          'backupId' => $backup->backup_id,
          'originalFilename' =>
            $backup->original_filename,
          'sizeBytes' => $backup->size_bytes
        ]
      );

    $this->status = 'ok';
    $this->public_id = $backup->public_id;
    $this->backup_id = $backup->backup_id;
    $this->created_at_client =
      $backup->created_at_client;
    $this->original_filename =
      $backup->original_filename;
    $this->size_bytes = $backup->size_bytes;
    $this->sha256 = $backup->sha256;
  }

  /**
   * Checks whether a path belongs to a genuine PHP HTTP upload.
   *
   * @param string $file_path Uploaded temporary file path.
   *
   * @return bool True when the file was uploaded through PHP.
   */
  protected function isUploadedFile(
    string $file_path
  ): bool {
    return is_uploaded_file(
      $file_path
    );
  }

  /**
   * Gets the actual size of an uploaded temporary file.
   *
   * @param string $file_path Uploaded temporary file path.
   *
   * @return int|null File size or null when it cannot be determined.
   */
  protected function getFileSize(
    string $file_path
  ): ?int {
    $size = filesize(
      $file_path
    );

    return $size === false
      ? null
      : $size;
  }
}
