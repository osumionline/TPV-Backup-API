<?php

declare(strict_types=1);

namespace Osumi\OsumiFramework\App\Model;

use Osumi\OsumiFramework\ORM\OModel;
use Osumi\OsumiFramework\ORM\OPK;
use Osumi\OsumiFramework\ORM\OField;
use Osumi\OsumiFramework\ORM\OCreatedAt;
use Osumi\OsumiFramework\ORM\OUpdatedAt;

class InstallationCredential extends OModel {
  #[OPK(
    comment: 'Identificador interno de la credencial'
  )]
  public ?int $id = null;

  #[OField(
    type: OField::NUMBER,
    nullable: false,
    ref: 'installation.id',
    comment: 'Instalación a la que pertenece la credencial'
  )]
  public ?int $id_installation = null;

  #[OField(
    max: 64,
    nullable: false,
    comment: 'Identificador público de la credencial'
  )]
  public ?string $key_id = null;

  #[OField(
    max: 255,
    nullable: false,
    visible: false,
    comment: 'Hash no reversible del secreto de la credencial'
  )]
  public ?string $secret_hash = null;

  #[OField(
    type: OField::DATE,
    nullable: true,
    comment: 'Último uso válido de la credencial'
  )]
  public ?string $last_used_at = null;

  #[OField(
    type: OField::DATE,
    nullable: true,
    comment: 'Fecha de revocación; NULL indica credencial no revocada'
  )]
  public ?string $revoked_at = null;

  #[OCreatedAt(
    comment: 'Fecha de creación del registro'
  )]
  public ?string $created_at = null;

  #[OUpdatedAt(
    comment: 'Fecha de última actualización del registro'
  )]
  public ?string $updated_at = null;
}
