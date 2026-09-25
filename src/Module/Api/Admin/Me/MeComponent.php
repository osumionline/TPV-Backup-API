<?php

declare(strict_types=1);

namespace Osumi\OsumiFramework\App\Module\Api\Admin\Me;

use Osumi\OsumiFramework\Core\OComponent;
use Osumi\OsumiFramework\Web\ORequest;

class MeComponent extends OComponent {
  public string $status = 'ok';
  public string $public_id = '';
  public string $name = '';
  public string $email = '';

  public function run(ORequest $req): void {
    $auth = $req->getFilter('AdminAuth');

    $this->public_id = $auth['public_id'] ?? '';
    $this->name = $auth['name'] ?? '';
    $this->email = $auth['email'] ?? '';
  }
}
