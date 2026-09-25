<?php

declare(strict_types=1);

namespace Osumi\OsumiFramework\App\Model;

use Osumi\OsumiFramework\ORM\OModel;
use Osumi\OsumiFramework\ORM\OPK;
use Osumi\OsumiFramework\ORM\OField;
use Osumi\OsumiFramework\ORM\OCreatedAt;
use Osumi\OsumiFramework\ORM\OUpdatedAt;

class Installation extends OModel {
  #[OPK(
    comment: 'Identificador interno de la instalación'
  )]
  public ?int $id = null;

  #[OField(
    max: 36,
    nullable: false,
    comment: 'Identificador público UUID v4'
  )]
  public ?string $public_id = null;

  #[OField(
    type: OField::NUMBER,
    nullable: false,
    ref: 'subscription.id',
    comment: 'Suscripción propietaria de la instalación'
  )]
  public ?int $id_subscription = null;

  #[OField(
    max: 150,
    nullable: false,
    comment: 'Nombre descriptivo de la instalación'
  )]
  public ?string $name = null;

  #[OField(
    type: OField::BOOL,
    nullable: false,
    default: true,
    comment: 'Indica si la instalación está habilitada'
  )]
  public ?bool $active = null;

  #[OField(
    type: OField::DATE,
    nullable: true,
    comment: 'Última comunicación válida de la instalación con la API'
  )]
  public ?string $last_seen_at = null;

  #[OCreatedAt(
    comment: 'Fecha de creación del registro'
  )]
  public ?string $created_at = null;

  #[OUpdatedAt(
    comment: 'Fecha de última actualización del registro'
  )]
  public ?string $updated_at = null;
}
