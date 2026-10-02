<?php

declare(strict_types=1);

namespace Osumi\OsumiFramework\Routes;

use Osumi\OsumiFramework\Routing\ORoute;
use Osumi\OsumiFramework\Core\OMiddleware;
use Osumi\OsumiFramework\App\Middleware\AdminAuthMiddleware;
use Osumi\OsumiFramework\App\Module\Api\Health\HealthComponent;
use Osumi\OsumiFramework\App\Module\Api\Admin\Login\LoginComponent;
use Osumi\OsumiFramework\App\Module\Api\Admin\Me\MeComponent;
use Osumi\OsumiFramework\App\Module\Api\Admin\Subscriptions\GetSubscriptions\GetSubscriptionsComponent;
use Osumi\OsumiFramework\App\Module\Api\Admin\Subscriptions\CreateSubscription\CreateSubscriptionComponent;
use Osumi\OsumiFramework\App\Module\Api\Admin\Subscriptions\UpdateSubscription\UpdateSubscriptionComponent;
use Osumi\OsumiFramework\App\Module\Api\Admin\Subscriptions\SetSubscriptionActive\SetSubscriptionActiveComponent;
use Osumi\OsumiFramework\App\Module\Api\Admin\Subscriptions\DeleteSubscription\DeleteSubscriptionComponent;
use Osumi\OsumiFramework\App\Module\Api\Admin\Installations\GetInstallations\GetInstallationsComponent;
use Osumi\OsumiFramework\App\Module\Api\Admin\Installations\CreateInstallation\CreateInstallationComponent;
use Osumi\OsumiFramework\App\Module\Api\Admin\Installations\UpdateInstallation\UpdateInstallationComponent;
use Osumi\OsumiFramework\App\Module\Api\Admin\Installations\SetInstallationActive\SetInstallationActiveComponent;

ORoute::prefix('/api', function(): void {
  ORoute::get('/health', HealthComponent::class);

  ORoute::prefix('/admin', function(): void {
    ORoute::post('/login', LoginComponent::class);

    ORoute::get(
      '/me',
      MeComponent::class,
      [
        OMiddleware::PHASE_BEFORE => [
          AdminAuthMiddleware::class
        ]
      ]
    );

    ORoute::prefix(
      '/subscriptions',
      function(): void {
        ORoute::get('', GetSubscriptionsComponent::class);
        ORoute::post('/create', CreateSubscriptionComponent::class);
        ORoute::post('/update', UpdateSubscriptionComponent::class);
        ORoute::post('/set-active', SetSubscriptionActiveComponent::class);
        ORoute::post('/delete', DeleteSubscriptionComponent::class);
      },
      [
        OMiddleware::PHASE_BEFORE => [
          AdminAuthMiddleware::class
        ]
      ]
    );

    ORoute::prefix(
      '/installations',
      function(): void {
        ORoute::get('', GetInstallationsComponent::class);
        ORoute::post('/create', CreateInstallationComponent::class);
        ORoute::post('/update', UpdateInstallationComponent::class);
        ORoute::post('/set-active', SetInstallationActiveComponent::class);
      },
      [
        OMiddleware::PHASE_BEFORE => [
          AdminAuthMiddleware::class
        ]
      ]
    );
  });
});
