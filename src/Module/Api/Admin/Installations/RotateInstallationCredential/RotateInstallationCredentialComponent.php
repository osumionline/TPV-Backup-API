<?php

declare(strict_types=1);

namespace Osumi\OsumiFramework\App\Module\Api\Admin\Installations\RotateInstallationCredential;

use RuntimeException;
use Osumi\OsumiFramework\Core\OComponent;
use Osumi\OsumiFramework\App\DTO\RotateInstallationCredentialDTO;
use Osumi\OsumiFramework\App\Service\InstallationCredentialService;
use Osumi\OsumiFramework\App\Service\InstallationService;
use Osumi\OsumiFramework\App\Service\AuditLogService;

class RotateInstallationCredentialComponent extends OComponent {
  private ?InstallationService $installation_service = null;
  private ?InstallationCredentialService $credential_service = null;
  private ?AuditLogService $audit_log_service = null;

  public string $status = 'error';
  public ?string $public_id = null;
  public ?string $key_id = null;
  public ?string $secret = null;
  public string $message = '';

  /**
   * Initializes component dependencies.
   */
  public function __construct() {
    parent::__construct();

    $this->installation_service = inject(InstallationService::class);
    $this->credential_service = inject(InstallationCredentialService::class);
    $this->audit_log_service = inject(AuditLogService::class);
  }

  /**
   * Rotates the active credential of an installation.
   *
   * @param RotateInstallationCredentialDTO $dto Installation data.
   *
   * @return void
   */
  public function run(RotateInstallationCredentialDTO $dto): void {
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

    try {
      $credential_data = $this->credential_service->rotate(
        $installation
      );
    }
    catch (RuntimeException) {
      $this->message = 'Installation credential could not be rotated.';
      $core->setHttpStatus(500);
      return;
    }

    $this->audit_log_service?->recordAdminAction(
      AuditLogService::ACTION_INSTALLATION_CREDENTIAL_ROTATE,
      AuditLogService::ENTITY_INSTALLATION,
      $installation->public_id,
      [
        'keyId' => $credential_data['credential']->key_id
      ]
    );

    $this->status = 'ok';
    $this->public_id = $installation->public_id;
    $this->key_id = $credential_data['credential']->key_id;
    $this->secret = $credential_data['secret'];
  }
}
