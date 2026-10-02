<?php

declare(strict_types=1);

namespace Osumi\OsumiFramework\App\Module\Api\Admin\Me;

use Osumi\OsumiFramework\App\DTO\AdminMeDTO;
use Osumi\OsumiFramework\Core\OComponent;

class MeComponent extends OComponent {
  public string $status    = 'error';
  public string $public_id = '';
  public string $name      = '';
  public string $email     = '';

  /**
   * Loads the authenticated administrator data from trusted Middleware context.
   *
   * @param AdminMeDTO $dto Authenticated administrator data.
   *
   * @return void
   */
  public function run(AdminMeDTO $dto): void {
    global $core;

    if (!$dto->isValid()) {
      $core->setHttpStatus(500);
      return;
    }

    $this->status = 'ok';
    $this->public_id = $dto->publicId ?? '';
    $this->name = $dto->name ?? '';
    $this->email = $dto->email ?? '';
  }
}
