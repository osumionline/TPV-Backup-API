<?php

declare(strict_types=1);

namespace Osumi\OsumiFramework\App\Service;

use RuntimeException;
use Osumi\OsumiFramework\App\Model\Installation;
use Osumi\OsumiFramework\App\Model\Subscription;
use Osumi\OsumiFramework\App\Utils\Uuid;
use Osumi\OsumiFramework\Core\OService;

class InstallationService extends OService {
  /**
   * Gets all installations ordered by name.
   *
   * @return Installation[] Installation list.
   */
  public function getAll(): array {
    return Installation::all([
      'order_by' => 'name#ASC'
    ]);
  }

  /**
   * Gets an installation by its public identifier.
   *
   * @param string $public_id Installation public identifier.
   *
   * @return Installation|null Installation or null when it does not exist.
   */
  public function getByPublicId(string $public_id): ?Installation {
    return Installation::findOne([
      'public_id' => $public_id
    ]);
  }

  /**
   * Counts the installations registered for a subscription.
   *
   * @param Subscription $subscription Subscription to inspect.
   *
   * @return int Number of registered installations.
   */
  public function countBySubscription(Subscription $subscription): int {
    return Installation::count([
      'id_subscription' => $subscription->id
    ]);
  }

  /**
   * Creates a new installation for a subscription.
   *
   * @param Subscription $subscription Parent subscription.
   * @param string       $name         Installation name.
   *
   * @return Installation Created installation.
   *
   * @throws RuntimeException When the installation cannot be persisted.
   */
  public function create(
    Subscription $subscription,
    string $name
  ): Installation {
    $installation = new Installation();
    $installation->public_id = Uuid::v4();
    $installation->id_subscription = $subscription->id;
    $installation->name = $name;
    $installation->active = true;
    $installation->last_seen_at = null;

    if (!$installation->save()) {
      throw new RuntimeException('Installation could not be created.');
    }

    return $installation;
  }
}
