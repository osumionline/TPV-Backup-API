<?php

declare(strict_types=1);

namespace Osumi\OsumiFramework\App\Component\Model\Installation;

use Osumi\OsumiFramework\App\Model\Installation;
use Osumi\OsumiFramework\App\Model\Subscription;
use Osumi\OsumiFramework\Core\OComponent;

class InstallationComponent extends OComponent {
  public ?Installation $installation = null;
  public ?string $subscription_public_id = null;
  public ?string $subscription_name = null;

  /**
   * Loads the subscription information associated with the installation.
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
  }
}
