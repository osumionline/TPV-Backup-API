<?php

declare(strict_types=1);

namespace Osumi\OsumiFramework\App\Tests\Service;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Osumi\OsumiFramework\App\Model\Backup;
use Osumi\OsumiFramework\App\Service\BackupStorageReconciliationService;
use Osumi\OsumiFramework\App\Service\BackupStorageService;

final class BackupStorageReconciliationServiceTest extends TestCase {
  /**
   * Verifies that orphaned, missing and invalid
   * storage relationships are reported.
   *
   * @return void
   */
  public function testInspectReportsStorageInconsistencies(): void {
    $storage_service =
      $this->createStorageServiceMock();

    $storage_service
      ->expects(
        self::once()
      )
      ->method('listObjects')
      ->willReturn([
        [
          'storageKey' =>
            'installations/test/present.otpv',

          'sizeBytes' => 100,
          'modifiedAt' => 1000
        ],
        [
          'storageKey' =>
            'installations/test/orphan.otpv',

          'sizeBytes' => 200,
          'modifiedAt' => 2000
        ]
      ]);

    $service = $this
      ->getMockBuilder(
        BackupStorageReconciliationService::class
      )
      ->disableOriginalConstructor()
      ->onlyMethods([
        'getBackups'
      ])
      ->getMock();

    $storage_property =
      new ReflectionProperty(
        BackupStorageReconciliationService::class,
        'storage_service'
      );

    $storage_property->setValue(
      $service,
      $storage_service
    );

    $service
      ->expects(
        self::once()
      )
      ->method('getBackups')
      ->willReturn([
        $this->createBackup(
          'public-present',
          'backup-present',
          'installations/test/present.otpv'
        ),

        $this->createBackup(
          'public-missing',
          'backup-missing',
          'installations/test/missing.otpv'
        ),

        $this->createBackup(
          'public-invalid',
          'backup-invalid',
          null
        )
      ]);

    $report =
      $service->inspect();

    self::assertSame(
      3,
      $report['metadataCount']
    );

    self::assertSame(
      2,
      $report['storageObjectCount']
    );

    self::assertSame(
      [
        [
          'storageKey' =>
            'installations/test/orphan.otpv',

          'sizeBytes' => 200,
          'modifiedAt' => 2000
        ]
      ],
      $report['orphanedObjects']
    );

    self::assertSame(
      [
        [
          'publicId' =>
            'public-missing',

          'backupId' =>
            'backup-missing',

          'storageKey' =>
            'installations/test/missing.otpv'
        ]
      ],
      $report['missingObjects']
    );

    self::assertSame(
      [
        [
          'publicId' =>
            'public-invalid',

          'backupId' =>
            'backup-invalid'
        ]
      ],
      $report['invalidMetadata']
    );
  }

  /**
   * Creates a persisted-looking backup fixture.
   *
   * @param string      $public_id  Public identifier.
   * @param string      $backup_id  Manifest backup identifier.
   * @param string|null $storage_key Storage key.
   *
   * @return Backup Backup fixture.
   */
  private function createBackup(
    string $public_id,
    string $backup_id,
    ?string $storage_key
  ): Backup {
    $backup =
      new Backup();

    $backup->public_id =
      $public_id;

    $backup->backup_id =
      $backup_id;

    $backup->storage_key =
      $storage_key;

    return $backup;
  }

  /**
   * Creates a controlled storage service.
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
        'listObjects'
      ])
      ->getMock();
  }
}
