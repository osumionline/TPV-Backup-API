<?php

declare(strict_types=1);

namespace Osumi\OsumiFramework\App\Model;

use Osumi\OsumiFramework\ORM\OModel;
use Osumi\OsumiFramework\ORM\OPK;
use Osumi\OsumiFramework\ORM\OField;
use Osumi\OsumiFramework\ORM\OCreatedAt;
use Osumi\OsumiFramework\ORM\OUpdatedAt;

class Backup extends OModel {
  #[OPK(
    comment: 'Identificador interno de la copia'
  )]
  public ?int $id = null;

  #[OField(
    max: 36,
    nullable: false,
    comment: 'Identificador público UUID v4 del registro remoto'
  )]
  public ?string $public_id = null;

  #[OField(
    type: OField::NUMBER,
    nullable: false,
    ref: 'installation.id',
    comment: 'Instalación propietaria de la copia'
  )]
  public ?int $id_installation = null;

  #[OField(
    max: 36,
    nullable: false,
    comment: 'UUID backupId incluido en manifest.json'
  )]
  public ?string $backup_id = null;

  #[OField(
    type: OField::DATE,
    nullable: false,
    comment: 'Fecha de creación declarada por Osumi TPV Client'
  )]
  public ?string $created_at_client = null;

  #[OField(
    type: OField::NUMBER,
    nullable: false,
    comment: 'Versión del formato OTPV'
  )]
  public ?int $format_version = null;

  #[OField(
    max: 100,
    nullable: false,
    comment: 'Aplicación declarada en manifest.json'
  )]
  public ?string $application = null;

  #[OField(
    max: 50,
    nullable: false,
    comment: 'Versión de Osumi TPV Client que creó la copia'
  )]
  public ?string $application_version = null;

  #[OField(
    type: OField::NUMBER,
    nullable: false,
    comment: 'Versión del esquema SQLite de la copia'
  )]
  public ?int $database_schema_version = null;

  #[OField(
    max: 255,
    nullable: false,
    comment: 'Nombre original del archivo recibido'
  )]
  public ?string $original_filename = null;

  #[OField(
    max: 255,
    nullable: false,
    comment: 'Clave lógica usada por BackupStorage para localizar el blob'
  )]
  public ?string $storage_key = null;

  #[OField(
    type: OField::NUMBER,
    nullable: false,
    comment: 'Tamaño completo del archivo OTPV en bytes; BIGINT UNSIGNED en el SQL canónico'
  )]
  public ?int $size_bytes = null;

  #[OField(
    max: 64,
    nullable: false,
    comment: 'SHA-256 hexadecimal del archivo OTPV completo'
  )]
  public ?string $sha256 = null;

  #[OCreatedAt(
    comment: 'Fecha de creación del registro'
  )]
  public ?string $created_at = null;

  #[OUpdatedAt(
    comment: 'Fecha de última actualización del registro'
  )]
  public ?string $updated_at = null;
}
