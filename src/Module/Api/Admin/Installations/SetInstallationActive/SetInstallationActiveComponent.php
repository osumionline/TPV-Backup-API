<?php

declare(strict_types=1);

namespace Osumi\OsumiFramework\App\Module\Api\Admin\Installations\SetInstallationActive;

use RuntimeException;
use Osumi\OsumiFramework\Core\OComponent;
use Osumi\OsumiFramework\App\DTO\SetInstallationActiveDTO;
use Osumi\OsumiFramework\App\Service\InstallationService;
use Osumi\OsumiFramework\App\Service\AuditLogService;

class SetInstallationActiveComponent extends OComponent {
  private ?InstallationService $installation_service = null;
  private ?AuditLogService $audit_log_service = null;

  public string $status = 'error';
  public ?string $public_id = null;
  public ?bool $active = null;
  public string $message = '';

  /**
   * Initializes component dependencies.
   */
  public function __construct() {
    parent::__construct();

    $this->installation_service = inject(InstallationService::class);
    $this->audit_log_service = inject(AuditLogService::class);
  }

  /**
   * Enables or disables an installation.
   *
   * @param SetInstallationActiveDTO $dto Installation state data.
   *
   * @return void
   */
  public function run(SetInstallationActiveDTO $dto): void {
    global $core;

    if (
      !$dto->isValid() ||
      is_null($dto->publicId) ||
      is_null($dto->active)
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

    try {
      $installation = $this->installation_service->setActive(
        $installation,
        $dto->active
      );
    }
    catch (RuntimeException) {
      $this->message = 'Installation active state could not be updated.';
      $core->setHttpStatus(500);
      return;
    }

    $this->audit_log_service?->recordAdminAction(
      AuditLogService::ACTION_INSTALLATION_SET_ACTIVE,
      AuditLogService::ENTITY_INSTALLATION,
      $installation->public_id,
      [
        'active' => $installation->active
      ]
    );

    $this->status = 'ok';
    $this->public_id = $installation->public_id;
    $this->active = $installation->active;
  }
}
