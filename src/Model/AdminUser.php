<?php

declare(strict_types=1);

namespace Osumi\OsumiFramework\App\Model;

use Osumi\OsumiFramework\ORM\OModel;
use Osumi\OsumiFramework\ORM\OPK;
use Osumi\OsumiFramework\ORM\OField;
use Osumi\OsumiFramework\ORM\OCreatedAt;
use Osumi\OsumiFramework\ORM\OUpdatedAt;

class AdminUser extends OModel {
  #[OPK(
    comment: 'Identificador interno del administrador'
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
    comment: 'Email usado para iniciar sesión'
  )]
  public ?string $email = null;

  #[OField(
    max: 255,
    nullable: false,
    visible: false,
    comment: 'Hash de contraseña'
  )]
  public ?string $password = null;

  #[OField(
    max: 100,
    nullable: false,
    comment: 'Nombre del administrador'
  )]
  public ?string $name = null;

  #[OField(
    type: OField::BOOL,
    nullable: false,
    default: true,
    comment: 'Indica si el usuario puede acceder al panel'
  )]
  public ?bool $active = null;

  #[OField(
    type: OField::DATE,
    nullable: true,
    comment: 'Último inicio de sesión correcto'
  )]
  public ?string $last_login_at = null;

  #[OCreatedAt(
    comment: 'Fecha de creación del registro'
  )]
  public ?string $created_at = null;

  #[OUpdatedAt(
    comment: 'Fecha de última actualización del registro'
  )]
  public ?string $updated_at = null;
}
