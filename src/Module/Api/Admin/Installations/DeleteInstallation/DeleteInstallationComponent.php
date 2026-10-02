<?php

declare(strict_types=1);

namespace Osumi\OsumiFramework\App\Module\Api\Admin\Installations\DeleteInstallation;

use DomainException;
use RuntimeException;
use Osumi\OsumiFramework\App\DTO\DeleteInstallationDTO;
use Osumi\OsumiFramework\App\Service\InstallationService;
use Osumi\OsumiFramework\Core\OComponent;

class DeleteInstallationComponent extends OComponent {
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
   * Deletes an installation when it has no registered backups.
   *
   * @param DeleteInstallationDTO $dto Installation data.
   *
   * @return void
   */
  public function run(DeleteInstallationDTO $dto): void {
    global $core;

    if (
      !$dto->isValid() ||
      is_null($dto->publicId)
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

    $public_id = $installation->public_id;

    try {
      $this->installation_service->delete($installation);
    }
    catch (DomainException) {
      $this->message = 'Installation cannot be deleted while it has backups.';
      $core->setHttpStatus(409);
      return;
    }
    catch (RuntimeException) {
      $this->message = 'Installation could not be deleted.';
      $core->setHttpStatus(500);
      return;
    }

    $this->status = 'ok';
    $this->public_id = $public_id;
  }
}
