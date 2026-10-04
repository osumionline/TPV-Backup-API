<?php

declare(strict_types=1);

namespace Osumi\OsumiFramework\App\Module\Api\Admin\Subscriptions\SetSubscriptionActive;

use Osumi\OsumiFramework\App\DTO\SetSubscriptionActiveDTO;
use Osumi\OsumiFramework\App\Service\SubscriptionService;
use Osumi\OsumiFramework\Core\OComponent;
use Osumi\OsumiFramework\App\Service\AuditLogService;

class SetSubscriptionActiveComponent extends OComponent {
  private ?SubscriptionService $subscription_service = null;
  private ?AuditLogService $audit_log_service = null;

  public string $status = 'error';
  public ?string $public_id = null;
  public ?bool $active = null;
  public string $message = '';

  /**
   * Initializes component dependencies.
   */
  public function __construct() {
    parent::__construct();

    $this->subscription_service = inject(SubscriptionService::class);
    $this->audit_log_service = inject(AuditLogService::class);
  }

  /**
   * Enables or disables a subscription.
   *
   * @param SetSubscriptionActiveDTO $dto Request data.
   *
   * @return void
   */
  public function run(SetSubscriptionActiveDTO $dto): void {
    global $core;

    if (
      !$dto->isValid() ||
      is_null($dto->publicId) ||
      is_null($dto->active)
    ) {
      $this->message = 'Missing required fields.';
      $core->setHttpStatus(400);
      return;
    }

    $subscription = $this->subscription_service->getByPublicId(
      trim($dto->publicId)
    );

    if (is_null($subscription)) {
      $this->message = 'Subscription not found.';
      $core->setHttpStatus(404);
      return;
    }

    $subscription = $this->subscription_service->setActive(
      $subscription,
      $dto->active
    );

    $this->audit_log_service?->recordAdminAction(
      AuditLogService::ACTION_SUBSCRIPTION_SET_ACTIVE,
      AuditLogService::ENTITY_SUBSCRIPTION,
      $subscription->public_id,
      [
        'active' => $subscription->active
      ]
    );

    $this->status = 'ok';
    $this->public_id = $subscription->public_id;
    $this->active = $subscription->active;
  }
}
