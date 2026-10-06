<?php

declare(strict_types=1);

namespace Osumi\OsumiFramework\App\Tests\Service;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use RuntimeException;
use Osumi\OsumiFramework\App\Model\Backup;
use Osumi\OsumiFramework\App\Model\Installation;
use Osumi\OsumiFramework\App\Service\BackupService;
use Osumi\OsumiFramework\App\Service\BackupStorageService;
use Osumi\OsumiFramework\App\Service\OtpvV3InspectorService;

final class BackupServiceIntegrityTest extends TestCase {
  private const BACKUP_ID =
    '123e4567-e89b-42d3-a456-426614174000';

  private const INSTALLATION_PUBLIC_ID =
    '8a8758ea-5052-4c71-bd77-679509addead';

  private const SOURCE_SHA256 =
    'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

  private const CORRUPT_SHA256 =
    'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';

  /**
   * Verifies that a newly stored object is rejected
   * when its persisted SHA-256 differs from the upload.
   *
   * @return void
   */
  public function testCreateRejectsStoredSha256Mismatch(): void {
    $inspection =
      $this->createInspection();

    $inspector_service =
      $this->createMock(
        OtpvV3InspectorService::class
      );

    $inspector_service
      ->expects(
        self::once()
      )
      ->method('inspect')
      ->with(
        '/tmp/backup.otpv'
      )
      ->willReturn(
        $inspection
      );

    $storage_service =
      $this->createStorageServiceMock();

    $storage_service
      ->expects(
        self::once()
      )
      ->method('storeFile');

    $storage_service
      ->expects(
        self::once()
      )
      ->method('getSize')
      ->willReturn(
        $inspection['sizeBytes']
      );

    $storage_service
      ->expects(
        self::once()
      )
      ->method('getSha256')
      ->willReturn(
        self::CORRUPT_SHA256
      );

    $storage_service
      ->expects(
        self::once()
      )
      ->method('delete');

    $service =
      $this->createBackupService(
        $inspector_service,
        $storage_service
      );

    $service
      ->expects(
        self::exactly(2)
      )
      ->method('getByBackupId')
      ->with(
        self::BACKUP_ID
      )
      ->willReturn(null);

    $this->expectException(
      RuntimeException::class
    );

    $this->expectExceptionMessage(
      'Backup could not be persisted.'
    );

    $service->createFromFile(
      $this->createInstallation(),
      '/tmp/backup.otpv',
      'backup.otpv'
    );
  }

  /**
   * Verifies that an idempotent retry is rejected
   * when the existing stored blob is corrupt.
   *
   * @return void
   */
  public function testIdempotentRetryRejectsCorruptStoredObject(): void {
    $inspection =
      $this->createInspection();

    $inspector_service =
      $this->createMock(
        OtpvV3InspectorService::class
      );

    $inspector_service
      ->expects(
        self::once()
      )
      ->method('inspect')
      ->willReturn(
        $inspection
      );

    $storage_service =
      $this->createStorageServiceMock();

    $storage_service
      ->expects(
        self::once()
      )
      ->method('exists')
      ->willReturn(true);

    $storage_service
      ->expects(
        self::once()
      )
      ->method('getSize')
      ->willReturn(
        $inspection['sizeBytes']
      );

    $storage_service
      ->expects(
        self::once()
      )
      ->method('getSha256')
      ->willReturn(
        self::CORRUPT_SHA256
      );

    $storage_service
      ->expects(
        self::never()
      )
      ->method('storeFile');

    $storage_service
      ->expects(
        self::never()
      )
      ->method('delete');

    $existing_backup = new Backup();

    $existing_backup->id = 100;
    $existing_backup->id_installation = 10;
    $existing_backup->backup_id =
      self::BACKUP_ID;

    $existing_backup->storage_key =
      'installations/'
      . self::INSTALLATION_PUBLIC_ID
      . '/existing.otpv';

    $existing_backup->size_bytes =
      $inspection['sizeBytes'];

    $existing_backup->sha256 =
      self::SOURCE_SHA256;

    $service =
      $this->createBackupService(
        $inspector_service,
        $storage_service
      );

    $service
      ->expects(
        self::once()
      )
      ->method('getByBackupId')
      ->with(
        self::BACKUP_ID
      )
      ->willReturn(
        $existing_backup
      );

    $this->expectException(
      RuntimeException::class
    );

    $this->expectExceptionMessage(
      'Backup metadata and stored file SHA-256 are inconsistent.'
    );

    $service->createFromFile(
      $this->createInstallation(),
      '/tmp/backup.otpv',
      'backup.otpv'
    );
  }

  /**
   * Creates a BackupService with controlled
   * inspector and storage dependencies.
   *
   * @param OtpvV3InspectorService $inspector_service Inspector dependency.
   * @param BackupStorageService   $storage_service   Storage dependency.
   *
   * @return BackupService&MockObject Prepared service.
   */
  private function createBackupService(
    OtpvV3InspectorService $inspector_service,
    BackupStorageService $storage_service
  ): BackupService&MockObject {
    $service = $this
      ->getMockBuilder(
        BackupService::class
      )
      ->disableOriginalConstructor()
      ->onlyMethods([
        'getByBackupId'
      ])
      ->getMock();

    $inspector_property =
      new ReflectionProperty(
        BackupService::class,
        'inspector_service'
      );

    $inspector_property->setValue(
      $service,
      $inspector_service
    );

    $storage_property =
      new ReflectionProperty(
        BackupService::class,
        'storage_service'
      );

    $storage_property->setValue(
      $service,
      $storage_service
    );

    return $service;
  }

  /**
   * Creates a storage service mock exposing
   * the integrity operations used by BackupService.
   *
   * @return BackupStorageService&MockObject Storage mock.
   */
  private function createStorageServiceMock(): BackupStorageService&MockObject {
    return $this
      ->getMockBuilder(
        BackupStorageService::class
      )
      ->disableOriginalConstructor()
      ->onlyMethods([
        'storeFile',
        'exists',
        'getSize',
        'getSha256',
        'delete'
      ])
      ->getMock();
  }

  /**
   * Creates a persisted-looking installation.
   *
   * @return Installation Test installation.
   */
  private function createInstallation(): Installation {
    $installation =
      new Installation();

    $installation->id = 10;

    $installation->public_id =
      self::INSTALLATION_PUBLIC_ID;

    return $installation;
  }

  /**
   * Creates the public metadata obtained
   * from inspection of an incoming package.
   *
   * @return array{
   *   formatVersion: int,
   *   application: string,
   *   applicationVersion: string,
   *   databaseSchemaVersion: int,
   *   backupId: string,
   *   createdAt: string,
   *   cryptoSuite: string,
   *   sizeBytes: int,
   *   sha256: string
   * } Inspection fixture.
   */
  private function createInspection(): array {
    return [
      'formatVersion' => 3,
      'application' =>
        'Osumi TPV Client',

      'applicationVersion' =>
        '1.0.0',

      'databaseSchemaVersion' => 1,

      'backupId' =>
        self::BACKUP_ID,

      'createdAt' =>
        '2026-10-06T12:00:00Z',

      'cryptoSuite' =>
        'otpv3-scrypt-aes-256-gcm',

      'sizeBytes' => 4096,

      'sha256' =>
        self::SOURCE_SHA256
    ];
  }
}
