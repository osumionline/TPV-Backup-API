<?php

declare(strict_types=1);

namespace Osumi\OsumiFramework\App\Module\Api\V1\Backups\DeleteBackup;

use RuntimeException;
use Osumi\OsumiFramework\App\DTO\InstallationBackupDTO;
use Osumi\OsumiFramework\App\Service\AuditLogService;
use Osumi\OsumiFramework\App\Service\BackupService;
use Osumi\OsumiFramework\App\Service\InstallationService;
use Osumi\OsumiFramework\Core\OComponent;

class DeleteBackupComponent extends OComponent {
  private ?BackupService $backup_service = null;
  private ?InstallationService $installation_service = null;
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

    $this->installation_service = inject(
      InstallationService::class
    );

    $this->audit_log_service = inject(
      AuditLogService::class
    );
  }

  /**
   * Deletes a backup owned by the authenticated installation.
   *
   * @param InstallationBackupDTO $dto Authenticated backup request.
   *
   * @return void
   */
  public function run(
    InstallationBackupDTO $dto
  ): void {
    global $core;

    $public_id = is_null(
      $dto->publicId
    )
      ? ''
      : trim(
        $dto->publicId
      );

    if ($public_id === '') {
      $this->message =
        'Missing required fields.';

      $core->setHttpStatus(400);
      return;
    }

    if (
      !$dto->isValid() ||
      is_null($dto->installationId)
    ) {
      $this->message =
        'Authenticated installation context is not available.';

      $core->setHttpStatus(500);
      return;
    }

    $backup =
      $this->backup_service->getByPublicId(
        $public_id
      );

    if (
      is_null($backup) ||
      $backup->id_installation !==
        $dto->installationId
    ) {
      $this->message =
        'Backup not found.';

      $core->setHttpStatus(404);
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

    $backup_public_id =
      $backup->public_id;

    $backup_id =
      $backup->backup_id;

    $original_filename =
      $backup->original_filename;

    try {
      $this->backup_service->delete(
        $backup
      );
    }
    catch (RuntimeException) {
      $this->message =
        'Backup could not be deleted.';

      $core->setHttpStatus(500);
      return;
    }

    $this->audit_log_service
      ?->recordInstallationAction(
        $installation,
        AuditLogService::ACTION_BACKUP_DELETE,
        AuditLogService::ENTITY_BACKUP,
        $backup_public_id,
        [
          'backupId' =>
            $backup_id,
          'originalFilename' =>
            $original_filename
        ]
      );

    $this->status = 'ok';
    $this->public_id =
      $backup_public_id;
  }
}
