<?php

declare(strict_types=1);

namespace Osumi\OsumiFramework\App\DTO;

use Osumi\OsumiFramework\DTO\ODTO;
use Osumi\OsumiFramework\DTO\ODTOField;

class CreateBackupDTO extends ODTO {
  #[ODTOField(required: true)]
  public ?array $file = null;

  #[ODTOField(
    required: true,
    middleware: 'InstallationAuth',
    middlewareProperty: 'installation_id'
  )]
  public ?int $installationId = null;

  #[ODTOField(
    required: true,
    middleware: 'InstallationAuth',
    middlewareProperty: 'can_upload'
  )]
  public ?bool $canUpload = null;
}
