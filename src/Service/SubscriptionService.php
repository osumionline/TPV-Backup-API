<?php

declare(strict_types=1);

namespace Osumi\OsumiFramework\App\Service;

use Osumi\OsumiFramework\Core\OService;
use Osumi\OsumiFramework\App\Model\Subscription;
use Osumi\OsumiFramework\App\Utils\Uuid;

class SubscriptionService extends OService {
  /**
   * Gets all subscriptions ordered by name.
   *
   * @return Subscription[] Subscription list.
   */
  public function getAll(): array {
    return Subscription::all([
      'order_by' => 'name#ASC'
    ]);
  }

  /**
   * Gets a subscription by its public identifier.
   *
   * @param string $public_id Subscription public identifier.
   *
   * @return Subscription|null Subscription or null when it does not exist.
   */
  public function getByPublicId(string $public_id): ?Subscription {
    return Subscription::findOne([
      'public_id' => $public_id
    ]);
  }

  /**
   * Creates a new subscription.
   *
   * @param string      $name                         Subscription name.
   * @param string|null $contact_email                Contact email.
   * @param string|null $expires_at                   Expiration date.
   * @param int         $max_installations            Maximum installations.
   * @param int         $max_backups_per_installation Maximum backups per installation.
   *
   * @return Subscription Created subscription.
   */
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

  /**
   * Updates the editable data of an existing subscription.
   *
   * @param Subscription $subscription                 Subscription to update.
   * @param string       $name                         Subscription name.
   * @param string|null  $contact_email                Contact email.
   * @param string|null  $expires_at                   Expiration date.
   * @param int          $max_installations            Maximum installations.
   * @param int          $max_backups_per_installation Maximum backups per installation.
   *
   * @return Subscription Updated subscription.
   */
  public function update(
    Subscription $subscription,
    string $name,
    ?string $contact_email,
    ?string $expires_at,
    int $max_installations,
    int $max_backups_per_installation
  ): Subscription {
    $subscription->name = $name;
    $subscription->contact_email = $contact_email;
    $subscription->expires_at = $expires_at;
    $subscription->max_installations = $max_installations;
    $subscription->max_backups_per_installation = $max_backups_per_installation;
    $subscription->save();

    return $subscription;
  }

  /**
   * Enables or disables an existing subscription.
   *
   * @param Subscription $subscription Subscription to update.
   * @param bool         $active       New active state.
   *
   * @return Subscription Updated subscription.
   */
  public function setActive(
    Subscription $subscription,
    bool $active
  ): Subscription {
    if ($subscription->active === $active) {
      return $subscription;
    }

    $subscription->active = $active;
    $subscription->save();

    return $subscription;
  }

  /**
 * Deletes an existing subscription.
 *
 * @param Subscription $subscription Subscription to delete.
 *
 * @return bool True when the subscription was deleted successfully.
 */
  public function delete(Subscription $subscription): bool {
    return $subscription->delete();
  }
}
