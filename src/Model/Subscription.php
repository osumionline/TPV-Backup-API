<?php

declare(strict_types=1);

namespace Osumi\OsumiFramework\App\Model;

use Osumi\OsumiFramework\ORM\OModel;
use Osumi\OsumiFramework\ORM\OPK;
use Osumi\OsumiFramework\ORM\OField;
use Osumi\OsumiFramework\ORM\OCreatedAt;
use Osumi\OsumiFramework\ORM\OUpdatedAt;

class Subscription extends OModel {
  #[OPK(
    comment: 'Identificador interno de la suscripción'
  )]
  public ?int $id = null;

  #[OField(
    max: 36,
    nullable: false,
    comment: 'Identificador público UUID v4'
  )]
  public ?string $public_id = null;

  #[OField(
    max: 150,
    nullable: false,
    comment: 'Nombre descriptivo del cliente o suscripción'
  )]
  public ?string $name = null;

  #[OField(
    max: 150,
    nullable: true,
    comment: 'Email de contacto del cliente'
  )]
  public ?string $contact_email = null;

  #[OField(
    type: OField::BOOL,
    nullable: false,
    default: true,
    comment: 'Indica si la suscripción está habilitada administrativamente'
  )]
  public ?bool $active = null;

  #[OField(
    type: OField::DATE,
    nullable: true,
    comment: 'Fecha de expiración; NULL indica que no caduca'
  )]
  public ?string $expires_at = null;

  #[OField(
    type: OField::NUMBER,
    nullable: false,
    default: 1,
    comment: 'Número máximo de instalaciones permitidas'
  )]
  public ?int $max_installations = null;

  #[OField(
    type: OField::NUMBER,
    nullable: false,
    default: 6,
    comment: 'Número máximo de copias retenidas por instalación'
  )]
  public ?int $max_backups_per_installation = null;

  #[OCreatedAt(
    comment: 'Fecha de creación del registro'
  )]
  public ?string $created_at = null;

  #[OUpdatedAt(
    comment: 'Fecha de última actualización del registro'
  )]
  public ?string $updated_at = null;
}
