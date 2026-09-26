<?php

declare(strict_types=1);

namespace Osumi\OsumiFramework\App\Service;

use Osumi\OsumiFramework\Core\OService;
use Osumi\OsumiFramework\App\Model\Subscription;
use Osumi\OsumiFramework\App\Utils\Uuid;

class SubscriptionService extends OService {
  /**
   * @return Subscription[]
   */
  public function getAll(): array {
    return Subscription::all([
      'order_by' => 'name#ASC'
    ]);
  }

  public function create(
    string $name,
    ?string $contact_email,
    ?string $expires_at,
    int $max_installations,
    int $max_backups_per_installation
  ): Subscription {
    $subscription = new Subscription();
    $subscription->public_id = Uuid::v4();
    $subscription->name = $name;
    $subscription->contact_email = $contact_email;
    $subscription->active = true;
    $subscription->expires_at = $expires_at;
    $subscription->max_installations = $max_installations;
    $subscription->max_backups_per_installation = $max_backups_per_installation;
    $subscription->save();

    return $subscription;
  }
}
