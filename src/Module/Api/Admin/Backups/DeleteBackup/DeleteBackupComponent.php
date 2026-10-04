<?php

declare(strict_types=1);

namespace Osumi\OsumiFramework\App\Module\Api\Admin\Backups\DeleteBackup;

use RuntimeException;
use Osumi\OsumiFramework\App\DTO\DeleteBackupDTO;
use Osumi\OsumiFramework\App\Service\BackupService;
use Osumi\OsumiFramework\Core\OComponent;
use Osumi\OsumiFramework\App\Service\AuditLogService;

class DeleteBackupComponent extends OComponent {
  private ?BackupService $backup_service = null;
  private ?AuditLogService $audit_log_service = null;

  public string $status = 'error';
  public ?string $public_id = null;
  public string $message = '';

  /**
   * Initializes component dependencies.
   */
  public function __construct() {
    parent::__construct();

    $this->backup_service = inject(
      BackupService::class
    );
    $this->audit_log_service = inject(
      AuditLogService::class
    );
  }

  /**
   * Deletes a registered backup and its stored OTPV file.
   *
   * @param DeleteBackupDTO $dto Backup data.
   *
   * @return void
   */
  public function run(DeleteBackupDTO $dto): void {
    global $core;

    if (
      !$dto->isValid() ||
      is_null($dto->publicId)
    ) {
      $this->message = 'Missing required fields.';
      $core->setHttpStatus(400);
      return;
    }

    $backup = $this->backup_service->getByPublicId(
      trim($dto->publicId)
    );

    if (is_null($backup)) {
      $this->message = 'Backup not found.';
      $core->setHttpStatus(404);
      return;
    }

    $public_id = $backup->public_id;
    $backup_id = $backup->backup_id;
    $original_filename = $backup->original_filename;

    try {
      $this->backup_service->delete(
        $backup
      );
    }
    catch (RuntimeException) {
      $this->message = 'Backup could not be deleted.';
      $core->setHttpStatus(500);
      return;
    }

    $this->audit_log_service?->recordAdminAction(
      AuditLogService::ACTION_BACKUP_DELETE,
      AuditLogService::ENTITY_BACKUP,
      $public_id,
      [
        'backupId' => $backup_id,
        'originalFilename' => $original_filename
      ]
    );

    $this->status = 'ok';
    $this->public_id = $public_id;
  }
}
