<?php

declare(strict_types=1);

namespace Osumi\OsumiFramework\App\DTO;

use Osumi\OsumiFramework\DTO\ODTO;
use Osumi\OsumiFramework\DTO\ODTOField;

class InstallationBackupDTO extends ODTO {
  #[ODTOField(required: true)]
  public ?string $publicId = null;

  #[ODTOField(
    required: true,
    middleware: 'InstallationAuth',
    middlewareProperty: 'installation_id'
  )]
  public ?int $installationId = null;
}
