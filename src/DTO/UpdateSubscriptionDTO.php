<?php

declare(strict_types=1);

namespace Osumi\OsumiFramework\App\DTO;

use Osumi\OsumiFramework\DTO\ODTO;
use Osumi\OsumiFramework\DTO\ODTOField;

class UpdateSubscriptionDTO extends ODTO {
  #[ODTOField(required: true)]
  public ?string $publicId = null;

  #[ODTOField(required: true)]
  public ?string $name = null;

  #[ODTOField]
  public ?string $contactEmail = null;

  #[ODTOField]
  public ?string $expiresAt = null;

  #[ODTOField(required: true)]
  public ?int $maxInstallations = null;

  #[ODTOField(required: true)]
  public ?int $maxBackupsPerInstallation = null;
}
