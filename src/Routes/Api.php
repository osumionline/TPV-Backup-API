<?php

declare(strict_types=1);

namespace Osumi\OsumiFramework\Routes;

use Osumi\OsumiFramework\Routing\ORoute;
use Osumi\OsumiFramework\App\Filter\AdminAuthFilter;
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

ORoute::prefix('/api', function(): void {
  ORoute::get('/health', HealthComponent::class);

  ORoute::prefix('/admin', function(): void {
    ORoute::post('/login', LoginComponent::class);
    ORoute::get('/me', MeComponent::class, [AdminAuthFilter::class]);

    ORoute::prefix('/subscriptions', function(): void {
      ORoute::get('', GetSubscriptionsComponent::class, [AdminAuthFilter::class]);
      ORoute::post('/create', CreateSubscriptionComponent::class, [AdminAuthFilter::class]);
      ORoute::post('/update', UpdateSubscriptionComponent::class, [AdminAuthFilter::class]);
      ORoute::post('/set-active', SetSubscriptionActiveComponent::class, [AdminAuthFilter::class]);
      ORoute::post('/delete', DeleteSubscriptionComponent::class, [AdminAuthFilter::class]);
    });

    ORoute::prefix('/installations', function(): void {
      ORoute::get('', GetInstallationsComponent::class, [AdminAuthFilter::class]);
      ORoute::post('/create', CreateInstallationComponent::class, [AdminAuthFilter::class]);
    });
  });
});
