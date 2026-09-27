<?php

declare(strict_types=1);

namespace Osumi\OsumiFramework\App\Module\Api\Admin\Installations\CreateInstallation;

use Throwable;
use Osumi\OsumiFramework\App\DTO\CreateInstallationDTO;
use Osumi\OsumiFramework\App\Model\Installation;
use Osumi\OsumiFramework\App\Service\InstallationCredentialService;
use Osumi\OsumiFramework\App\Service\InstallationService;
use Osumi\OsumiFramework\App\Service\SubscriptionService;
use Osumi\OsumiFramework\Core\OComponent;

class CreateInstallationComponent extends OComponent {
  private ?InstallationService $installation_service = null;
  private ?InstallationCredentialService $credential_service = null;
  private ?SubscriptionService $subscription_service = null;

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
    $this->subscription_service = inject(SubscriptionService::class);
  }

  /**
   * Creates an installation and its initial machine credential.
   *
   * @param CreateInstallationDTO $dto Installation data.
   *
   * @return void
   */
  public function run(CreateInstallationDTO $dto): void {
    global $core;

    if (
      !$dto->isValid() ||
      is_null($dto->subscriptionPublicId) ||
      is_null($dto->name)
    ) {
      $this->message = 'Missing required fields.';
      $core->setHttpStatus(400);
      return;
    }

    $subscription = $this->subscription_service->getByPublicId(
      trim($dto->subscriptionPublicId)
    );

    if (is_null($subscription)) {
      $this->message = 'Subscription not found.';
      $core->setHttpStatus(404);
      return;
    }

    $name = trim($dto->name);

    if ($name === '') {
      $this->message = 'Name cannot be empty.';
      $core->setHttpStatus(400);
      return;
    }

    $installation_count = $this->installation_service->countBySubscription(
      $subscription
    );

    if (
      is_null($subscription->max_installations) ||
      $installation_count >= $subscription->max_installations
    ) {
      $this->message = 'Subscription installation limit has been reached.';
      $core->setHttpStatus(409);
      return;
    }

    $installation = null;

    try {
      $installation = $this->installation_service->create(
        $subscription,
        $name
      );

      $credential_data = $this->credential_service->create($installation);
    }
    catch (Throwable) {
      if ($installation instanceof Installation) {
        $installation->delete();
      }

      $this->message = 'Installation could not be created.';
      $core->setHttpStatus(500);
      return;
    }

    $this->status = 'ok';
    $this->public_id = $installation->public_id;
    $this->key_id = $credential_data['credential']->key_id;
    $this->secret = $credential_data['secret'];

    $core->setHttpStatus(201);
  }
}
