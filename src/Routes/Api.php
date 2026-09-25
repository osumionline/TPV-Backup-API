<?php

declare(strict_types=1);

namespace Osumi\OsumiFramework\Routes;

use Osumi\OsumiFramework\Routing\ORoute;
use Osumi\OsumiFramework\App\Filter\AdminAuthFilter;
use Osumi\OsumiFramework\App\Module\Api\Health\HealthComponent;
use Osumi\OsumiFramework\App\Module\Api\Admin\Login\LoginComponent;
use Osumi\OsumiFramework\App\Module\Api\Admin\Me\MeComponent;

ORoute::prefix('/api', function(): void {
  ORoute::get('/health', HealthComponent::class);
  ORoute::post('/admin/login', LoginComponent::class);
  ORoute::get('/admin/me', MeComponent::class, [AdminAuthFilter::class]);
});
