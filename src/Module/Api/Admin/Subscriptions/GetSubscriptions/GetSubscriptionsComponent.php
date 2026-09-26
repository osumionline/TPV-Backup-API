<?php

declare(strict_types=1);

namespace Osumi\OsumiFramework\App\Module\Api\Admin\Subscriptions\GetSubscriptions;

use Osumi\OsumiFramework\Core\OComponent;
use Osumi\OsumiFramework\App\Service\SubscriptionService;
use Osumi\OsumiFramework\App\Component\Model\SubscriptionList\SubscriptionListComponent;

class GetSubscriptionsComponent extends OComponent {
  private ?SubscriptionService $subscription_service = null;

  public string $status = 'ok';
  public ?SubscriptionListComponent $list = null;

  public function __construct() {
    parent::__construct();

    $this->subscription_service = inject(SubscriptionService::class);
    $this->list = new SubscriptionListComponent();
  }

  public function run(): void {
    $this->list->list = $this->subscription_service->getAll();
  }
}
