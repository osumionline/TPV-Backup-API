<?php

declare(strict_types=1);

namespace Osumi\OsumiFramework\App\Model;

use Osumi\OsumiFramework\ORM\OModel;
use Osumi\OsumiFramework\ORM\OPK;
use Osumi\OsumiFramework\ORM\OField;
use Osumi\OsumiFramework\ORM\OCreatedAt;
use Osumi\OsumiFramework\ORM\OUpdatedAt;

class AuditLog extends OModel {
  #[OPK(
    comment: 'Identificador interno del evento de auditoría'
  )]
  public ?int $id = null;

  #[OField(
    max: 20,
    nullable: false,
    comment: 'Tipo de actor: admin, installation o system'
  )]
  public ?string $actor_type = null;

  #[OField(
    type: OField::NUMBER,
    nullable: true,
    ref: 'admin_user.id',
    comment: 'Administrador causante del evento, si procede'
  )]
  public ?int $id_admin_user = null;

  #[OField(
    type: OField::NUMBER,
    nullable: true,
    ref: 'installation.id',
    comment: 'Instalación causante del evento, si procede'
  )]
  public ?int $id_installation = null;

  #[OField(
    max: 100,
    nullable: false,
    comment: 'Código de la acción auditada'
  )]
  public ?string $action = null;

  #[OField(
    max: 50,
    nullable: false,
    comment: 'Tipo de entidad afectada'
  )]
  public ?string $entity_type = null;

  #[OField(
    max: 50,
    nullable: true,
    comment: 'Identificador público de la entidad afectada'
  )]
  public ?string $entity_public_id = null;

  #[OField(
    type: OField::LONGTEXT,
    nullable: true,
    comment: 'JSON con datos adicionales no sensibles del evento'
  )]
  public ?string $data = null;

  #[OField(
    max: 45,
    nullable: true,
    comment: 'Dirección IPv4 o IPv6 del origen'
  )]
  public ?string $ip = null;

  #[OField(
    max: 255,
    nullable: true,
    comment: 'User-Agent del origen'
  )]
  public ?string $user_agent = null;

  #[OCreatedAt(
    comment: 'Fecha de creación del evento'
  )]
  public ?string $created_at = null;

  #[OUpdatedAt(
    comment: 'Fecha de última actualización del registro'
  )]
  public ?string $updated_at = null;
}
