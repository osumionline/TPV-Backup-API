<?php

declare(strict_types=1);

namespace Osumi\OsumiFramework\App\Component\Model\Backup;

use Osumi\OsumiFramework\App\Model\Backup;
use Osumi\OsumiFramework\App\Model\Installation;
use Osumi\OsumiFramework\App\Model\Subscription;
use Osumi\OsumiFramework\Core\OComponent;

class BackupComponent extends OComponent {
  public ?Backup $backup = null;

  public ?string $installation_public_id = null;
  public ?string $installation_name = null;

  public ?string $subscription_public_id = null;
  public ?string $subscription_name = null;

  /**
   * Loads installation and subscription metadata associated with the backup.
   *
   * @return void
   */
  public function run(): void {
    if (
      is_null($this->backup) ||
      is_null($this->backup->id_installation)
    ) {
      return;
    }

    $installation = Installation::findOne([
      'id' => $this->backup->id_installation
    ]);

    if (is_null($installation)) {
      return;
    }

    $this->installation_public_id = $installation->public_id;
    $this->installation_name = $installation->name;

    if (is_null($installation->id_subscription)) {
      return;
    }

    $subscription = Subscription::findOne([
      'id' => $installation->id_subscription
    ]);

    if (is_null($subscription)) {
      return;
    }

    $this->subscription_public_id = $subscription->public_id;
    $this->subscription_name = $subscription->name;
  }
}
