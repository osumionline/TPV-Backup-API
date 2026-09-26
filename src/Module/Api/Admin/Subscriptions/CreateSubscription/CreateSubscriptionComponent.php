<?php

declare(strict_types=1);

namespace Osumi\OsumiFramework\App\Module\Api\Admin\Subscriptions\CreateSubscription;

use DateTimeImmutable;
use Osumi\OsumiFramework\Core\OComponent;
use Osumi\OsumiFramework\App\DTO\CreateSubscriptionDTO;
use Osumi\OsumiFramework\App\Service\SubscriptionService;

class CreateSubscriptionComponent extends OComponent {
  private ?SubscriptionService $subscription_service = null;

  public string $status = 'error';
  public ?string $public_id = null;
  public string $message = '';

  public function __construct() {
    parent::__construct();

    $this->subscription_service = inject(SubscriptionService::class);
  }

  public function run(CreateSubscriptionDTO $dto): void {
    global $core;

    if (!$dto->isValid() || is_null($dto->name)) {
      $this->message = 'Missing required fields.';
      $core->setHttpStatus(400);
      return;
    }

    $name = trim($dto->name);

    if ($name === '') {
      $this->message = 'Name cannot be empty.';
      $core->setHttpStatus(400);
      return;
    }

    $contact_email = is_null($dto->contactEmail)
      ? null
      : trim($dto->contactEmail);

    if ($contact_email === '') {
      $contact_email = null;
    }

    if (
      !is_null($contact_email) &&
      filter_var($contact_email, FILTER_VALIDATE_EMAIL) === false
    ) {
      $this->message = 'Contact email is not valid.';
      $core->setHttpStatus(400);
      return;
    }

    $expires_at = null;

    if (!is_null($dto->expiresAt) && trim($dto->expiresAt) !== '') {
      $expires_at_value = trim($dto->expiresAt);
      $date = DateTimeImmutable::createFromFormat('!Y-m-d', $expires_at_value);

      if (
        $date === false ||
        $date->format('Y-m-d') !== $expires_at_value
      ) {
        $this->message = 'Expiration date is not valid.';
        $core->setHttpStatus(400);
        return;
      }

      $expires_at = $date->format('Y-m-d') . ' 23:59:59';
    }

    $max_installations = $dto->maxInstallations ?? 1;
    $max_backups_per_installation = $dto->maxBackupsPerInstallation ?? 6;

    if ($max_installations < 1) {
      $this->message = 'Maximum installations must be greater than zero.';
      $core->setHttpStatus(400);
      return;
    }

    if ($max_backups_per_installation < 1) {
      $this->message = 'Maximum backups per installation must be greater than zero.';
      $core->setHttpStatus(400);
      return;
    }

    $subscription = $this->subscription_service->create(
      $name,
      $contact_email,
      $expires_at,
      $max_installations,
      $max_backups_per_installation
    );

    $this->status = 'ok';
    $this->public_id = $subscription->public_id;
    $core->setHttpStatus(201);
  }
}
