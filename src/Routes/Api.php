<?php

declare(strict_types=1);

namespace Osumi\OsumiFramework\Routes;

use Osumi\OsumiFramework\Routing\ORoute;
use Osumi\OsumiFramework\App\Module\Api\Health\HealthComponent;

ORoute::prefix('/api', function(): void {
  ORoute::get('/health', HealthComponent::class);
});
