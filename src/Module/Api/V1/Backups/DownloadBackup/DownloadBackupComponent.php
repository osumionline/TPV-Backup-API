<?php

declare(strict_types=1);

namespace Osumi\OsumiFramework\App\Module\Api\V1\Backups\DownloadBackup;

use RuntimeException;
use Osumi\OsumiFramework\App\DTO\InstallationBackupDTO;
use Osumi\OsumiFramework\App\Model\Backup;
use Osumi\OsumiFramework\App\Service\AuditLogService;
use Osumi\OsumiFramework\App\Service\BackupService;
use Osumi\OsumiFramework\App\Service\InstallationService;
use Osumi\OsumiFramework\Core\OComponent;
use Osumi\OsumiFramework\Web\OStreamResponse;

class DownloadBackupComponent extends OComponent {
  private ?BackupService $backup_service = null;
  private ?InstallationService $installation_service = null;
  private ?AuditLogService $audit_log_service = null;

  public string $status = 'error';
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
   * Streams a backup owned by the authenticated installation.
   *
   * @param InstallationBackupDTO $dto Authenticated backup request.
   *
   * @return OStreamResponse|null Streamed backup response or null on error.
   */
  public function run(
    InstallationBackupDTO $dto
  ): ?OStreamResponse {
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
      return null;
    }

    if (
      !$dto->isValid() ||
      is_null($dto->installationId)
    ) {
      $this->message =
        'Authenticated installation context is not available.';

      $core->setHttpStatus(500);
      return null;
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
      return null;
    }

    $installation =
      $this->installation_service->getById(
        $dto->installationId
      );

    if (is_null($installation)) {
      $this->message =
        'Authenticated installation is not available.';

      $core->setHttpStatus(403);
      return null;
    }

    try {
      $stream =
        $this->backup_service->openReadStream(
          $backup
        );
    }
    catch (RuntimeException) {
      $this->message =
        'Backup file could not be opened.';

      $core->setHttpStatus(500);
      return null;
    }

    $this->audit_log_service
      ?->recordInstallationAction(
        $installation,
        AuditLogService::ACTION_BACKUP_DOWNLOAD,
        AuditLogService::ENTITY_BACKUP,
        $backup->public_id,
        [
          'backupId' => $backup->backup_id,
          'originalFilename' =>
            $backup->original_filename,
          'sizeBytes' => $backup->size_bytes
        ]
      );

    return new OStreamResponse(
      $stream,
      [
        'Content-Type' =>
          'application/octet-stream',

        'Content-Length' =>
          strval(
            $backup->size_bytes
          ),

        'Content-Disposition' =>
          $this->buildContentDisposition(
            $backup
          ),

        'Access-Control-Expose-Headers' =>
          'Content-Disposition'
      ]
    );
  }

  /**
   * Builds a safe Content-Disposition header for a backup download.
   *
   * @param Backup $backup Backup being downloaded.
   *
   * @return string Content-Disposition header value.
   */
  private function buildContentDisposition(
    Backup $backup
  ): string {
    $filename =
      $backup->original_filename
      ?? 'backup.otpv';

    return 'attachment; filename="backup.otpv"; filename*=UTF-8\'\''
      . rawurlencode(
        $filename
      );
  }
}
