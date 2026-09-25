<?php

declare(strict_types=1);

namespace Osumi\OsumiFramework\App\Module\Api\Health;

use Throwable;
use Osumi\OsumiFramework\Core\OComponent;
use Osumi\OsumiFramework\Web\ORequest;

class HealthComponent extends OComponent {
  public string $status = 'ok';
  public string $database = 'ok';

  public function run(ORequest $req): void {
    global $core;

    try {
      if (is_null($core->db_container)) {
        throw new \RuntimeException('Database container is not available.');
      }

      $connection = $core->db_container->getConnection(
        $core->config->getDB('driver') ?? 'mysql',
        $core->config->getDB('host') ?? '',
        $core->config->getDB('user') ?? '',
        $core->config->getDB('pass') ?? '',
        $core->config->getDB('name') ?? '',
        $core->config->getDB('charset') ?? 'utf8mb4'
      );

      $connection['link']->query('SELECT 1');
    }
    catch (Throwable) {
      $this->status = 'error';
      $this->database = 'error';

      $core->setHttpStatus(503);
    }
  }
}
