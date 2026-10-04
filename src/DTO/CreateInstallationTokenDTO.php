<?php

declare(strict_types=1);

namespace Osumi\OsumiFramework\App\DTO;

use Osumi\OsumiFramework\DTO\ODTO;
use Osumi\OsumiFramework\DTO\ODTOField;

class CreateInstallationTokenDTO extends ODTO {
  #[ODTOField(required: true)]
  public ?string $keyId = null;

  #[ODTOField(required: true)]
  public ?string $secret = null;
}
