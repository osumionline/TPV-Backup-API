<?php

declare(strict_types=1);

namespace Osumi\OsumiFramework\App\Component\Model\Subscription;

use Osumi\OsumiFramework\Core\OComponent;
use Osumi\OsumiFramework\App\Model\Installation;
use Osumi\OsumiFramework\App\Model\Subscription;

class SubscriptionComponent extends OComponent {
  public ?Subscription $subscription = null;
  public string $status = 'active';
  public int $installation_count = 0;

  public function run(): void {
    if (is_null($this->subscription) || is_null($this->subscription->id)) {
      return;
    }

    $this->installation_count = Installation::count([
      'id_subscription' => $this->subscription->id
    ]);

    if ($this->subscription->active !== true) {
      $this->status = 'disabled';
      return;
    }

    if (
      !is_null($this->subscription->expires_at) &&
      strtotime($this->subscription->expires_at) < time()
    ) {
      $this->status = 'expired';
    }
  }
}
