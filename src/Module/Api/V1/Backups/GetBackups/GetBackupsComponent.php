<?php

declare(strict_types=1);

namespace Osumi\OsumiFramework\App\Module\Api\V1\Backups\GetBackups;

use Osumi\OsumiFramework\App\Component\Api\V1\RemoteBackupList\RemoteBackupListComponent;
use Osumi\OsumiFramework\App\DTO\InstallationBackupsDTO;
use Osumi\OsumiFramework\App\Service\BackupService;
use Osumi\OsumiFramework\App\Service\InstallationService;
use Osumi\OsumiFramework\Core\OComponent;

class GetBackupsComponent extends OComponent {
  private ?BackupService $backup_service = null;
  private ?InstallationService $installation_service = null;

  public string $status = 'error';
  public string $message = '';
  public ?RemoteBackupListComponent $list = null;

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

    $this->list =
      new RemoteBackupListComponent();
  }

  /**
   * Loads backups owned by the authenticated installation.
   *
   * @param InstallationBackupsDTO $dto Authenticated installation context.
   *
   * @return void
   */
  public function run(
    InstallationBackupsDTO $dto
  ): void {
    global $core;

    if (
      !$dto->isValid() ||
      is_null($dto->installationId)
    ) {
      $this->message =
        'Authenticated installation context is not available.';

      $core->setHttpStatus(500);
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

    $this->list->list =
      $this->backup_service->getByInstallation(
        $installation
      );

    $this->status = 'ok';
  }
}
