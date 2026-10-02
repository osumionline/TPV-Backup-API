<?php

declare(strict_types=1);

namespace Osumi\OsumiFramework\App\Module\Api\Admin\Installations\UpdateInstallation;

use RuntimeException;
use Osumi\OsumiFramework\App\DTO\UpdateInstallationDTO;
use Osumi\OsumiFramework\App\Service\InstallationService;
use Osumi\OsumiFramework\Core\OComponent;

class UpdateInstallationComponent extends OComponent {
  private ?InstallationService $installation_service = null;

  public string $status = 'error';
  public ?string $public_id = null;
  public string $message = '';

  /**
   * Initializes component dependencies.
   */
  public function __construct() {
    parent::__construct();

    $this->installation_service = inject(InstallationService::class);
  }

  /**
   * Updates an existing installation.
   *
   * @param UpdateInstallationDTO $dto Installation data.
   *
   * @return void
   */
  public function run(UpdateInstallationDTO $dto): void {
    global $core;

    if (
      !$dto->isValid() ||
      is_null($dto->publicId) ||
      is_null($dto->name)
    ) {
      $this->message = 'Missing required fields.';
      $core->setHttpStatus(400);
      return;
    }

    $installation = $this->installation_service->getByPublicId(
      trim($dto->publicId)
    );

    if (is_null($installation)) {
      $this->message = 'Installation not found.';
      $core->setHttpStatus(404);
      return;
    }

    $name = trim($dto->name);

    if ($name === '') {
      $this->message = 'Name cannot be empty.';
      $core->setHttpStatus(400);
      return;
    }

    try {
      $installation = $this->installation_service->update(
        $installation,
        $name
      );
    }
    catch (RuntimeException) {
      $this->message = 'Installation could not be updated.';
      $core->setHttpStatus(500);
      return;
    }

    $this->status = 'ok';
    $this->public_id = $installation->public_id;
  }
}
