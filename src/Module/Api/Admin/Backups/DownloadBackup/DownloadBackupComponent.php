<?php

declare(strict_types=1);

namespace Osumi\OsumiFramework\App\Module\Api\Admin\Backups\DownloadBackup;

use RuntimeException;
use Osumi\OsumiFramework\App\DTO\DownloadBackupDTO;
use Osumi\OsumiFramework\App\Model\Backup;
use Osumi\OsumiFramework\App\Service\BackupService;
use Osumi\OsumiFramework\Core\OComponent;
use Osumi\OsumiFramework\Web\OStreamResponse;

class DownloadBackupComponent extends OComponent {
  private ?BackupService $backup_service = null;

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
  }

  /**
   * Streams a stored OTPV backup to the authenticated administrator.
   *
   * When validation or storage checks fail, null is returned so the component
   * JSON template can render the corresponding error response.
   *
   * @param DownloadBackupDTO $dto Backup download data.
   *
   * @return OStreamResponse|null Streamed backup response or null on error.
   */
  public function run(
    DownloadBackupDTO $dto
  ): ?OStreamResponse {
    global $core;

    if (
      !$dto->isValid() ||
      is_null($dto->publicId)
    ) {
      $this->message = 'Missing required fields.';
      $core->setHttpStatus(400);

      return null;
    }

    $backup = $this->backup_service->getByPublicId(
      trim(
        $dto->publicId
      )
    );

    if (is_null($backup)) {
      $this->message = 'Backup not found.';
      $core->setHttpStatus(404);

      return null;
    }

    try {
      $stream = $this->backup_service->openReadStream(
        $backup
      );
    }
    catch (RuntimeException) {
      $this->message = 'Backup file could not be opened.';
      $core->setHttpStatus(500);

      return null;
    }

    return new OStreamResponse(
      $stream,
      [
        'Content-Type' => 'application/octet-stream',
        'Content-Length' => strval(
          $backup->size_bytes
        ),
        'Content-Disposition' => $this->buildContentDisposition(
          $backup
        )
      ]
    );
  }

  /**
   * Builds a safe Content-Disposition header for a backup download.
   *
   * A simple ASCII fallback is provided while the original UTF-8 filename is
   * exposed through the RFC 5987 filename* parameter.
   *
   * @param Backup $backup Backup being downloaded.
   *
   * @return string Content-Disposition header value.
   */
  private function buildContentDisposition(
    Backup $backup
  ): string {
    $filename = $backup->original_filename
      ?? 'backup.otpv';

    return 'attachment; filename="backup.otpv"; filename*=UTF-8\'\''
      . rawurlencode(
        $filename
      );
  }
}
