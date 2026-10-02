<?php

declare(strict_types=1);

namespace Osumi\OsumiFramework\App\Service;

use RuntimeException;
use DomainException;
use Throwable;
use Osumi\OsumiFramework\Core\OService;
use Osumi\OsumiFramework\ORM\ODB;
use Osumi\OsumiFramework\App\Model\Installation;
use Osumi\OsumiFramework\App\Model\Subscription;
use Osumi\OsumiFramework\App\Utils\Uuid;
use Osumi\OsumiFramework\App\Model\Backup;
use Osumi\OsumiFramework\App\Model\InstallationCredential;

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

  /**
   * Updates the editable data of an installation.
   *
   * @param Installation $installation Installation to update.
   * @param string       $name         New installation name.
   *
   * @return Installation Updated installation.
   *
   * @throws RuntimeException When the installation cannot be persisted.
   */
  public function update(
    Installation $installation,
    string $name
  ): Installation {
    $installation->name = $name;

    if (!$installation->save()) {
      throw new RuntimeException('Installation could not be updated.');
    }

    return $installation;
  }

  /**
   * Enables or disables an installation.
   *
   * @param Installation $installation Installation to update.
   * @param bool         $active       New active state.
   *
   * @return Installation Updated installation.
   *
   * @throws RuntimeException When the installation cannot be persisted.
   */
  public function setActive(
    Installation $installation,
    bool $active
  ): Installation {
    if ($installation->active === $active) {
      return $installation;
    }

    $installation->active = $active;

    if (!$installation->save()) {
      throw new RuntimeException('Installation active state could not be updated.');
    }

    return $installation;
  }

  /**
   * Deletes an installation and all its credentials.
   *
   * Installations with registered backups cannot be deleted.
   *
   * @param Installation $installation Installation to delete.
   *
   * @return void
   *
   * @throws DomainException  When the installation has registered backups.
   * @throws RuntimeException When the deletion cannot be completed.
   */
  public function delete(Installation $installation): void {
    if (is_null($installation->id)) {
      throw new RuntimeException('Installation must be persisted before deletion.');
    }

    $db = ODB::getInstance();

    try {
      $db->beginTransaction();

      $backup_count = Backup::count([
        'id_installation' => $installation->id
      ]);

      if ($backup_count > 0) {
        throw new DomainException(
          'Installation cannot be deleted while it has backups.'
        );
      }

      $credentials = InstallationCredential::all([
        'id_installation' => $installation->id
      ]);

      foreach ($credentials as $credential) {
        if (!$credential->delete()) {
          throw new RuntimeException(
            'Installation credential could not be deleted.'
          );
        }
      }

      if (!$installation->delete()) {
        throw new RuntimeException(
          'Installation could not be deleted.'
        );
      }

      $db->commit();
    }
    catch (DomainException $exception) {
      if ($db->inTransaction()) {
        $db->rollBack();
      }

      throw $exception;
    }
    catch (Throwable $exception) {
      if ($db->inTransaction()) {
        $db->rollBack();
      }

      throw new RuntimeException(
        'Installation could not be deleted.',
        0,
        $exception
      );
    }
  }
}
