<?php

declare(strict_types=1);

namespace Osumi\OsumiFramework\App\DTO;

use Osumi\OsumiFramework\DTO\ODTO;
use Osumi\OsumiFramework\DTO\ODTOField;

class AdminMeDTO extends ODTO {
  #[ODTOField(
    required: true,
    middleware: 'AdminAuth',
    middlewareProperty: 'public_id'
  )]
  public ?string $publicId = null;

  #[ODTOField(
    required: true,
    middleware: 'AdminAuth',
    middlewareProperty: 'name'
  )]
  public ?string $name = null;

  #[ODTOField(
    required: true,
    middleware: 'AdminAuth',
    middlewareProperty: 'email'
  )]
  public ?string $email = null;
}
