<?php

declare(strict_types=1);

namespace Osumi\OsumiFramework\App\Component\Model\Installation;

use Osumi\OsumiFramework\App\Model\Installation;
use Osumi\OsumiFramework\App\Model\Subscription;
use Osumi\OsumiFramework\Core\OComponent;
use Osumi\OsumiFramework\App\Model\InstallationCredential;

class InstallationComponent extends OComponent {
  public ?Installation $installation = null;
  public ?string $subscription_public_id = null;
  public ?string $subscription_name = null;
  public bool $has_active_credential = false;
  public ?string $credential_key_id = null;
  public ?string $credential_last_used_at = null;
  public ?string $credential_created_at = null;

  /**
   * Loads the subscription and active credential information associated with the installation.
   *
   * @return void
   */
  public function run(): void {
    if (
      is_null($this->installation) ||
      is_null($this->installation->id_subscription)
    ) {
      return;
    }

    $subscription = Subscription::findOne([
      'id' => $this->installation->id_subscription
    ]);

    if (is_null($subscription)) {
      return;
    }

    $this->subscription_public_id = $subscription->public_id;
    $this->subscription_name = $subscription->name;

    if (is_null($this->installation->id)) {
      return;
    }

    $credentials = InstallationCredential::where(
      [
        'id_installation' => $this->installation->id,
        'revoked_at' => null
      ],
      [
        'order_by' => 'created_at#DESC',
        'limit' => 1
      ]
    );

    $credential = $credentials[0] ?? null;

    if (is_null($credential)) {
      return;
    }

    $this->has_active_credential = true;
    $this->credential_key_id = $credential->key_id;
    $this->credential_last_used_at = $credential->last_used_at;
    $this->credential_created_at = $credential->created_at;
  }
}
