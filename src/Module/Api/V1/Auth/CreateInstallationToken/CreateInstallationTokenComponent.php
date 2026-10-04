<?php

declare(strict_types=1);

namespace Osumi\OsumiFramework\App\Module\Api\V1\Auth\CreateInstallationToken;

use RuntimeException;
use Osumi\OsumiFramework\App\DTO\CreateInstallationTokenDTO;
use Osumi\OsumiFramework\App\Service\AuditLogService;
use Osumi\OsumiFramework\App\Service\InstallationAuthService;
use Osumi\OsumiFramework\Core\OComponent;

class CreateInstallationTokenComponent extends OComponent {
  private ?InstallationAuthService $auth_service = null;
  private ?AuditLogService $audit_log_service = null;

  public string $status = 'error';
  public string $message = '';

  public string $token = '';
  public int $expires_at = 0;

  public ?string $installation_public_id = null;
  public ?string $installation_name = null;

  public ?string $subscription_public_id = null;
  public ?string $subscription_name = null;
  public ?string $subscription_status = null;

  public bool $can_upload = false;

  /**
   * Initializes component dependencies.
   */
  public function __construct() {
    parent::__construct();

    $this->auth_service = inject(
      InstallationAuthService::class
    );

    $this->audit_log_service = inject(
      AuditLogService::class
    );
  }

  /**
   * Authenticates an installation credential and issues an access token.
   *
   * @param CreateInstallationTokenDTO $dto Installation credential data.
   *
   * @return void
   */
  public function run(
    CreateInstallationTokenDTO $dto
  ): void {
    global $core;

    if (
      !$dto->isValid() ||
      is_null($dto->keyId) ||
      is_null($dto->secret)
    ) {
      $this->message =
        'Missing required fields.';

      $core->setHttpStatus(
        400
      );

      return;
    }

    try {
      $result = $this
        ->auth_service
        ->login(
          $dto->keyId,
          $dto->secret
        );
    }
    catch (RuntimeException) {
      $this->message =
        'Installation authentication could not be completed.';

      $core->setHttpStatus(
        500
      );

      return;
    }

    if (is_null($result)) {
      $this->message =
        'Invalid installation credentials.';

      $core->setHttpStatus(
        401
      );

      return;
    }

    $installation =
      $result['installation'];

    $subscription =
      $result['subscription'];

    $credential =
      $result['credential'];

    $this->audit_log_service
      ?->recordInstallationAction(
        $installation,
        AuditLogService::ACTION_INSTALLATION_AUTHENTICATE,
        AuditLogService::ENTITY_INSTALLATION,
        $installation->public_id,
        [
          'keyId' => $credential->key_id
        ]
      );

    $this->status = 'ok';
    $this->token = $result['token'];
    $this->expires_at =
      $result['expires_at'];

    $this->installation_public_id =
      $installation->public_id;

    $this->installation_name =
      $installation->name;

    $this->subscription_public_id =
      $subscription->public_id;

    $this->subscription_name =
      $subscription->name;

    $this->subscription_status =
      $result['subscription_status'];

    $this->can_upload =
      $result['can_upload'];
  }
}
