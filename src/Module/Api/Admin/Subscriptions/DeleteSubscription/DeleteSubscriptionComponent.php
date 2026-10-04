<?php

declare(strict_types=1);

namespace Osumi\OsumiFramework\App\Module\Api\Admin\Subscriptions\DeleteSubscription;

use Osumi\OsumiFramework\Core\OComponent;
use Osumi\OsumiFramework\App\DTO\DeleteSubscriptionDTO;
use Osumi\OsumiFramework\App\Model\Installation;
use Osumi\OsumiFramework\App\Service\SubscriptionService;
use Osumi\OsumiFramework\App\Service\AuditLogService;

class DeleteSubscriptionComponent extends OComponent {
  private ?SubscriptionService $subscription_service = null;
  private ?AuditLogService $audit_log_service = null;

  public string $status = 'error';
  public ?string $public_id = null;
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
   * Deletes a subscription when it has no registered installations.
   *
   * @param DeleteSubscriptionDTO $dto Request data.
   *
   * @return void
   */
  public function run(DeleteSubscriptionDTO $dto): void {
    global $core;

    if (
      !$dto->isValid() ||
      is_null($dto->publicId)
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

    $installation_count = Installation::count([
      'id_subscription' => $subscription->id
    ]);

    if ($installation_count > 0) {
      $this->message = 'Subscription cannot be deleted while it has installations.';
      $core->setHttpStatus(409);
      return;
    }

    $public_id = $subscription->public_id;

    if (!$this->subscription_service->delete($subscription)) {
      $this->message = 'Subscription could not be deleted.';
      $core->setHttpStatus(500);
      return;
    }

    $this->audit_log_service?->recordAdminAction(
      AuditLogService::ACTION_SUBSCRIPTION_DELETE,
      AuditLogService::ENTITY_SUBSCRIPTION,
      $public_id
    );

    $this->status = 'ok';
    $this->public_id = $public_id;
  }
}
