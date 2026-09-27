<?php

declare(strict_types=1);

namespace Osumi\OsumiFramework\App\DTO;

use Osumi\OsumiFramework\DTO\ODTO;
use Osumi\OsumiFramework\DTO\ODTOField;

class DeleteSubscriptionDTO extends ODTO {
  #[ODTOField(required: true)]
  public ?string $publicId = null;
}
