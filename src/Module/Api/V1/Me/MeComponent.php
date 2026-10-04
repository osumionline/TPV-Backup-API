<?php

declare(strict_types=1);

namespace Osumi\OsumiFramework\App\Module\Api\V1\Me;

use Osumi\OsumiFramework\App\DTO\InstallationMeDTO;
use Osumi\OsumiFramework\Core\OComponent;

class MeComponent extends OComponent {
  public string $status = 'error';

  public string $installation_public_id = '';
  public string $installation_name = '';

  public string $subscription_public_id = '';
  public string $subscription_name = '';
  public string $subscription_status = '';

  public bool $can_upload = false;

  /**
   * Loads authenticated installation data from trusted Middleware context.
   *
   * @param InstallationMeDTO $dto Authenticated installation context.
   *
   * @return void
   */
  public function run(
    InstallationMeDTO $dto
  ): void {
    global $core;

    if (!$dto->isValid()) {
      $core->setHttpStatus(
        500
      );

      return;
    }

    $this->status = 'ok';

    $this->installation_public_id =
      $dto->installationPublicId
      ?? '';

    $this->installation_name =
      $dto->installationName
      ?? '';

    $this->subscription_public_id =
      $dto->subscriptionPublicId
      ?? '';

    $this->subscription_name =
      $dto->subscriptionName
      ?? '';

    $this->subscription_status =
      $dto->subscriptionStatus
      ?? '';

    $this->can_upload =
      $dto->canUpload
      ?? false;
  }
}
