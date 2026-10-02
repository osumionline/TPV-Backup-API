<?php

declare(strict_types=1);

namespace Osumi\OsumiFramework\App\Module\Api\Admin\Backups\GetBackups;

use Osumi\OsumiFramework\App\Component\Model\BackupList\BackupListComponent;
use Osumi\OsumiFramework\App\Service\BackupService;
use Osumi\OsumiFramework\Core\OComponent;

class GetBackupsComponent extends OComponent {
  private ?BackupService $backup_service = null;

  public string $status = 'ok';
  public ?BackupListComponent $list = null;

  /**
   * Initializes component dependencies.
   */
  public function __construct() {
    parent::__construct();

    $this->backup_service = inject(
      BackupService::class
    );

    $this->list = new BackupListComponent();
  }

  /**
   * Loads all registered backups.
   *
   * @return void
   */
  public function run(): void {
    $this->list->list = $this->backup_service->getAll();
  }
}
