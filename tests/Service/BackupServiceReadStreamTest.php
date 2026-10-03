<?php

declare(strict_types=1);

namespace Osumi\OsumiFramework\App\Tests\Service;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionProperty;
use RuntimeException;
use Osumi\OsumiFramework\App\Model\Backup;
use Osumi\OsumiFramework\App\Service\BackupService;
use Osumi\OsumiFramework\App\Service\BackupStorageService;

final class BackupServiceReadStreamTest extends TestCase {
  /**
   * Verifies that a valid stored backup returns its readable stream.
   *
   * @return void
   */
  public function testOpenReadStreamReturnsStoredBackupStream(): void {
    $contents = 'otpv-test-contents';

    $stream = fopen(
      'php://temp',
      'w+b'
    );

    self::assertIsResource(
      $stream
    );

    fwrite(
      $stream,
      $contents
    );

    rewind(
      $stream
    );

    $storage_service = $this->createStorageServiceMock();

    $storage_service
      ->expects(
        self::once()
      )
      ->method('exists')
      ->with(
        'installations/test/backup.otpv'
      )
      ->willReturn(true);

    $storage_service
      ->expects(
        self::once()
      )
      ->method('getSize')
      ->with(
        'installations/test/backup.otpv'
      )
      ->willReturn(
        strlen($contents)
      );

    $storage_service
      ->expects(
        self::once()
      )
      ->method('openReadStream')
      ->with(
        'installations/test/backup.otpv'
      )
      ->willReturn(
        $stream
      );

    $backup = new Backup();
    $backup->storage_key = 'installations/test/backup.otpv';
    $backup->size_bytes = strlen($contents);

    $service = $this->createBackupService(
      $storage_service
    );

    $result = $service->openReadStream(
      $backup
    );

    self::assertSame(
      $stream,
      $result
    );

    self::assertSame(
      $contents,
      stream_get_contents(
        $result
      )
    );

    fclose(
      $result
    );
  }

  /**
   * Verifies that incomplete storage metadata is rejected.
   *
   * @return void
   */
  public function testOpenReadStreamRejectsIncompleteMetadata(): void {
    $storage_service = $this->createStorageServiceMock();

    $storage_service
      ->expects(
        self::never()
      )
      ->method('exists');

    $storage_service
      ->expects(
        self::never()
      )
      ->method('getSize');

    $storage_service
      ->expects(
        self::never()
      )
      ->method('openReadStream');

    $backup = new Backup();
    $backup->storage_key = null;
    $backup->size_bytes = null;

    $service = $this->createBackupService(
      $storage_service
    );

    $this->expectException(
      RuntimeException::class
    );

    $this->expectExceptionMessage(
      'Backup storage metadata is incomplete.'
    );

    $service->openReadStream(
      $backup
    );
  }

  /**
   * Verifies that missing stored backup objects are rejected.
   *
   * @return void
   */
  public function testOpenReadStreamRejectsMissingStoredObject(): void {
    $storage_service = $this->createStorageServiceMock();

    $storage_service
      ->expects(
        self::once()
      )
      ->method('exists')
      ->with(
        'installations/test/missing.otpv'
      )
      ->willReturn(false);

    $storage_service
      ->expects(
        self::never()
      )
      ->method('getSize');

    $storage_service
      ->expects(
        self::never()
      )
      ->method('openReadStream');

    $backup = new Backup();
    $backup->storage_key = 'installations/test/missing.otpv';
    $backup->size_bytes = 1024;

    $service = $this->createBackupService(
      $storage_service
    );

    $this->expectException(
      RuntimeException::class
    );

    $this->expectExceptionMessage(
      'Backup stored file does not exist.'
    );

    $service->openReadStream(
      $backup
    );
  }

  /**
   * Verifies that a size mismatch between metadata and storage is rejected.
   *
   * @return void
   */
  public function testOpenReadStreamRejectsStoredSizeMismatch(): void {
    $storage_service = $this->createStorageServiceMock();

    $storage_service
      ->expects(
        self::once()
      )
      ->method('exists')
      ->with(
        'installations/test/mismatch.otpv'
      )
      ->willReturn(true);

    $storage_service
      ->expects(
        self::once()
      )
      ->method('getSize')
      ->with(
        'installations/test/mismatch.otpv'
      )
      ->willReturn(1023);

    $storage_service
      ->expects(
        self::never()
      )
      ->method('openReadStream');

    $backup = new Backup();
    $backup->storage_key = 'installations/test/mismatch.otpv';
    $backup->size_bytes = 1024;

    $service = $this->createBackupService(
      $storage_service
    );

    $this->expectException(
      RuntimeException::class
    );

    $this->expectExceptionMessage(
      'Backup metadata and stored file size are inconsistent.'
    );

    $service->openReadStream(
      $backup
    );
  }

  /**
   * Creates a BackupStorageService mock without initializing real storage.
   *
   * @return BackupStorageService&MockObject Storage service mock.
   */
  private function createStorageServiceMock(): BackupStorageService&MockObject {
    return $this
      ->getMockBuilder(
        BackupStorageService::class
      )
      ->disableOriginalConstructor()
      ->onlyMethods([
        'exists',
        'getSize',
        'openReadStream'
      ])
      ->getMock();
  }

  /**
   * Creates a BackupService with the supplied storage dependency.
   *
   * The production constructor is intentionally skipped because this test is
   * focused exclusively on the read-stream domain logic.
   *
   * @param BackupStorageService $storage_service Storage dependency.
   *
   * @return BackupService Service prepared for the test.
   */
  private function createBackupService(
    BackupStorageService $storage_service
  ): BackupService {
    $reflection = new ReflectionClass(
      BackupService::class
    );

    $service = $reflection->newInstanceWithoutConstructor();

    self::assertInstanceOf(
      BackupService::class,
      $service
    );

    $storage_property = new ReflectionProperty(
      BackupService::class,
      'storage_service'
    );

    $storage_property->setValue(
      $service,
      $storage_service
    );

    return $service;
  }
}
