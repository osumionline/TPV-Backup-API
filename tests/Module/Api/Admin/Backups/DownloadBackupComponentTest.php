<?php

declare(strict_types=1);

namespace Osumi\OsumiFramework\App\Tests\Module\Api\Admin\Backups;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionProperty;
use RuntimeException;
use Osumi\OsumiFramework\App\DTO\DownloadBackupDTO;
use Osumi\OsumiFramework\App\Model\Backup;
use Osumi\OsumiFramework\App\Module\Api\Admin\Backups\DownloadBackup\DownloadBackupComponent;
use Osumi\OsumiFramework\App\Service\BackupService;
use Osumi\OsumiFramework\Core\OCore;
use Osumi\OsumiFramework\Core\OMiddleware;
use Osumi\OsumiFramework\Web\ORequest;
use Osumi\OsumiFramework\Web\OStreamResponse;

final class DownloadBackupComponentTest extends TestCase {
  private bool $core_existed = false;
  private mixed $previous_core = null;

  /**
   * Creates an isolated framework response state for each test.
   *
   * @return void
   */
  protected function setUp(): void {
    parent::setUp();

    $this->core_existed = array_key_exists(
      'core',
      $GLOBALS
    );

    if ($this->core_existed) {
      $this->previous_core = $GLOBALS['core'];
    }

    $GLOBALS['core'] = new OCore();

    OMiddleware::reset();
  }

  /**
   * Restores global framework state after each test.
   *
   * @return void
   */
  protected function tearDown(): void {
    OMiddleware::reset();

    if ($this->core_existed) {
      $GLOBALS['core'] = $this->previous_core;
    }
    else {
      unset(
        $GLOBALS['core']
      );
    }

    parent::tearDown();
  }

  /**
   * Verifies that a missing public identifier returns a 400 response.
   *
   * @return void
   */
  public function testRunRejectsMissingPublicId(): void {
    $backup_service = $this->createBackupServiceMock();

    $backup_service
      ->expects(
        self::never()
      )
      ->method('getByPublicId');

    $backup_service
      ->expects(
        self::never()
      )
      ->method('openReadStream');

    $component = $this->createComponent(
      $backup_service
    );

    $result = $component->run(
      $this->createDto()
    );

    self::assertNull(
      $result
    );

    self::assertSame(
      'error',
      $component->status
    );

    self::assertSame(
      'Missing required fields.',
      $component->message
    );

    self::assertSame(
      400,
      OMiddleware::getStatusCode()
    );

    self::assertSame(
      '400 Bad Request',
      $this->getCore()->getHttpStatus()
    );
  }

  /**
   * Verifies that an unknown backup returns a 404 response.
   *
   * @return void
   */
  public function testRunReturnsNotFoundForUnknownBackup(): void {
    $backup_service = $this->createBackupServiceMock();

    $backup_service
      ->expects(
        self::once()
      )
      ->method('getByPublicId')
      ->with(
        'test-public-id'
      )
      ->willReturn(null);

    $backup_service
      ->expects(
        self::never()
      )
      ->method('openReadStream');

    $component = $this->createComponent(
      $backup_service
    );

    $result = $component->run(
      $this->createDto(
        'test-public-id'
      )
    );

    self::assertNull(
      $result
    );

    self::assertSame(
      'Backup not found.',
      $component->message
    );

    self::assertSame(
      404,
      OMiddleware::getStatusCode()
    );

    self::assertSame(
      '404 Not Found',
      $this->getCore()->getHttpStatus()
    );
  }

  /**
   * Verifies that storage inconsistencies return a 500 response.
   *
   * @return void
   */
  public function testRunReturnsServerErrorWhenBackupCannotBeOpened(): void {
    $backup = $this->createBackup();

    $backup_service = $this->createBackupServiceMock();

    $backup_service
      ->expects(
        self::once()
      )
      ->method('getByPublicId')
      ->with(
        'test-public-id'
      )
      ->willReturn(
        $backup
      );

    $backup_service
      ->expects(
        self::once()
      )
      ->method('openReadStream')
      ->with(
        $backup
      )
      ->willThrowException(
        new RuntimeException(
          'Stored file does not exist.'
        )
      );

    $component = $this->createComponent(
      $backup_service
    );

    $result = $component->run(
      $this->createDto(
        'test-public-id'
      )
    );

    self::assertNull(
      $result
    );

    self::assertSame(
      'Backup file could not be opened.',
      $component->message
    );

    self::assertSame(
      500,
      OMiddleware::getStatusCode()
    );

    self::assertSame(
      '500 Internal Server Error',
      $this->getCore()->getHttpStatus()
    );
  }

  /**
   * Verifies that a valid backup produces a streamed download response.
   *
   * @return void
   */
  public function testRunReturnsStreamResponseForValidBackup(): void {
    $contents = 'otpv-download-test';

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

    $backup = $this->createBackup(
      strlen($contents),
      'Copia TPV ñ 2026.otpv'
    );

    $backup_service = $this->createBackupServiceMock();

    $backup_service
      ->expects(
        self::once()
      )
      ->method('getByPublicId')
      ->with(
        'test-public-id'
      )
      ->willReturn(
        $backup
      );

    $backup_service
      ->expects(
        self::once()
      )
      ->method('openReadStream')
      ->with(
        $backup
      )
      ->willReturn(
        $stream
      );

    $component = $this->createComponent(
      $backup_service
    );

    $result = $component->run(
      $this->createDto(
        'test-public-id'
      )
    );

    self::assertInstanceOf(
      OStreamResponse::class,
      $result
    );

    self::assertSame(
      200,
      $result->getStatusCode()
    );

    self::assertSame(
      [
        'Content-Type' => 'application/octet-stream',
        'Content-Length' => strval(
          strlen($contents)
        ),
        'Content-Disposition' =>
          'attachment; filename="backup.otpv"; filename*=UTF-8\'\''
          . 'Copia%20TPV%20%C3%B1%202026.otpv'
      ],
      $result->getHeaders()
    );

    self::assertSame(
      $contents,
      stream_get_contents(
        $result->getStream()
      )
    );

    self::assertSame(
      200,
      OMiddleware::getStatusCode()
    );

    $result->close();

    self::assertFalse(
      $result->isOpen()
    );
  }

  /**
   * Creates a DownloadBackupDTO from a simulated route request.
   *
   * @param string|null $public_id Public backup identifier.
   *
   * @return DownloadBackupDTO Created DTO.
   */
  private function createDto(
    ?string $public_id = null
  ): DownloadBackupDTO {
    $params = [];

    if (!is_null($public_id)) {
      $params['publicId'] = $public_id;
    }

    return new DownloadBackupDTO(
      new ORequest([
        'method' => 'GET',
        'headers' => [],
        'params' => $params
      ])
    );
  }

  /**
   * Creates a BackupService mock without initializing its real dependencies.
   *
   * @return BackupService&MockObject Backup service mock.
   */
  private function createBackupServiceMock(): BackupService&MockObject {
    return $this->createMock(
      BackupService::class
    );
  }

  /**
   * Creates a DownloadBackupComponent with a controlled service dependency.
   *
   * The constructor is skipped because this test exercises run() directly and
   * does not require component template initialization or the service container.
   *
   * @param BackupService $backup_service Backup service dependency.
   *
   * @return DownloadBackupComponent Prepared component.
   */
  private function createComponent(
    BackupService $backup_service
  ): DownloadBackupComponent {
    $reflection = new ReflectionClass(
      DownloadBackupComponent::class
    );

    $component = $reflection->newInstanceWithoutConstructor();

    self::assertInstanceOf(
      DownloadBackupComponent::class,
      $component
    );

    $property = new ReflectionProperty(
      DownloadBackupComponent::class,
      'backup_service'
    );

    $property->setValue(
      $component,
      $backup_service
    );

    return $component;
  }

  /**
   * Creates backup metadata suitable for download tests.
   *
   * @param int    $size_bytes        Stored backup size.
   * @param string $original_filename Original backup filename.
   *
   * @return Backup Backup metadata.
   */
  private function createBackup(
    int $size_bytes = 1024,
    string $original_filename = 'backup.otpv'
  ): Backup {
    $backup = new Backup();

    $backup->public_id = 'test-public-id';
    $backup->storage_key = 'installations/test/backup.otpv';
    $backup->size_bytes = $size_bytes;
    $backup->original_filename = $original_filename;

    return $backup;
  }

  /**
   * Gets the test OCore instance.
   *
   * @return OCore Test core.
   *
   * @throws RuntimeException When the test core is unavailable.
   */
  private function getCore(): OCore {
    $core = $GLOBALS['core']
      ?? null;

    if (!$core instanceof OCore) {
      throw new RuntimeException(
        'Test core is not available.'
      );
    }

    return $core;
  }
}
