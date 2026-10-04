<?php

declare(strict_types=1);

namespace Osumi\OsumiFramework\App\Module\Api\Admin\Installations\RevokeInstallationCredential;

use RuntimeException;
use Osumi\OsumiFramework\Core\OComponent;
use Osumi\OsumiFramework\App\DTO\RevokeInstallationCredentialDTO;
use Osumi\OsumiFramework\App\Service\InstallationCredentialService;
use Osumi\OsumiFramework\App\Service\InstallationService;
use Osumi\OsumiFramework\App\Service\AuditLogService;

class RevokeInstallationCredentialComponent extends OComponent {
  private ?InstallationService $installation_service = null;
  private ?InstallationCredentialService $credential_service = null;
  private ?AuditLogService $audit_log_service = null;

  public string $status = 'error';
  public ?string $public_id = null;
  public int $revoked_count = 0;
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
   * Revokes the active credential of an installation.
   *
   * @param RevokeInstallationCredentialDTO $dto Installation data.
   *
   * @return void
   */
  public function run(RevokeInstallationCredentialDTO $dto): void {
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
      $this->revoked_count = $this->credential_service->revokeActive(
        $installation
      );
    }
    catch (RuntimeException) {
      $this->message = 'Installation credential could not be revoked.';
      $core->setHttpStatus(500);
      return;
    }

    $this->audit_log_service?->recordAdminAction(
      AuditLogService::ACTION_INSTALLATION_CREDENTIAL_REVOKE,
      AuditLogService::ENTITY_INSTALLATION,
      $installation->public_id,
      [
        'revokedCount' => $this->revoked_count
      ]
    );

    $this->status = 'ok';
    $this->public_id = $installation->public_id;
  }
}
