<?php

declare(strict_types=1);

namespace Osumi\OsumiFramework\App\DTO;

use Osumi\OsumiFramework\DTO\ODTO;
use Osumi\OsumiFramework\DTO\ODTOField;

class SetInstallationActiveDTO extends ODTO {
  #[ODTOField(required: true)]
  public ?string $publicId = null;

  #[ODTOField(required: true)]
  public ?bool $active = null;
}
