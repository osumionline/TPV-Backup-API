<?php

declare(strict_types=1);

namespace Osumi\OsumiFramework\App\Module\Api\Admin\Installations\GetInstallations;

use Osumi\OsumiFramework\App\Component\Model\InstallationList\InstallationListComponent;
use Osumi\OsumiFramework\App\Service\InstallationService;
use Osumi\OsumiFramework\Core\OComponent;

class GetInstallationsComponent extends OComponent {
  private ?InstallationService $installation_service = null;

  public string $status = 'ok';
  public ?InstallationListComponent $list = null;

  /**
   * Initializes component dependencies.
   */
  public function __construct() {
    parent::__construct();

    $this->installation_service = inject(InstallationService::class);
    $this->list = new InstallationListComponent();
  }

  /**
   * Loads all registered installations.
   *
   * @return void
   */
  public function run(): void {
    $this->list->list = $this->installation_service->getAll();
  }
}
