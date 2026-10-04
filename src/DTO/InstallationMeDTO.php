<?php

declare(strict_types=1);

namespace Osumi\OsumiFramework\App\DTO;

use Osumi\OsumiFramework\DTO\ODTO;
use Osumi\OsumiFramework\DTO\ODTOField;

class InstallationMeDTO extends ODTO {
  #[ODTOField(
    required: true,
    middleware: 'InstallationAuth',
    middlewareProperty: 'installation_public_id'
  )]
  public ?string $installationPublicId = null;

  #[ODTOField(
    required: true,
    middleware: 'InstallationAuth',
    middlewareProperty: 'installation_name'
  )]
  public ?string $installationName = null;

  #[ODTOField(
    required: true,
    middleware: 'InstallationAuth',
    middlewareProperty: 'subscription_public_id'
  )]
  public ?string $subscriptionPublicId = null;

  #[ODTOField(
    required: true,
    middleware: 'InstallationAuth',
    middlewareProperty: 'subscription_name'
  )]
  public ?string $subscriptionName = null;

  #[ODTOField(
    required: true,
    middleware: 'InstallationAuth',
    middlewareProperty: 'subscription_status'
  )]
  public ?string $subscriptionStatus = null;

  #[ODTOField(
    required: true,
    middleware: 'InstallationAuth',
    middlewareProperty: 'can_upload'
  )]
  public ?bool $canUpload = null;
}
