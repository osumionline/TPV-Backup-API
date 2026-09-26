<?php

declare(strict_types=1);

namespace Osumi\OsumiFramework\App\DTO;

use Osumi\OsumiFramework\DTO\ODTO;
use Osumi\OsumiFramework\DTO\ODTOField;

class CreateSubscriptionDTO extends ODTO {
  #[ODTOField(required: true)]
  public ?string $name = null;

  #[ODTOField]
  public ?string $contactEmail = null;

  #[ODTOField]
  public ?string $expiresAt = null;

  #[ODTOField]
  public ?int $maxInstallations = null;

  #[ODTOField]
  public ?int $maxBackupsPerInstallation = null;
}
