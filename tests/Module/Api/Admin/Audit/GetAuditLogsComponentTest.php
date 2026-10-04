<?php

declare(strict_types=1);

namespace Osumi\OsumiFramework\App\Tests\Module\Api\Admin\Audit;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionProperty;
use RuntimeException;
use Osumi\OsumiFramework\App\Component\Model\AuditLogList\AuditLogListComponent;
use Osumi\OsumiFramework\App\DTO\GetAuditLogsDTO;
use Osumi\OsumiFramework\App\Model\AuditLog;
use Osumi\OsumiFramework\App\Module\Api\Admin\Audit\GetAuditLogs\GetAuditLogsComponent;
use Osumi\OsumiFramework\App\Service\AuditLogService;
use Osumi\OsumiFramework\Core\OCore;
use Osumi\OsumiFramework\Core\OMiddleware;
use Osumi\OsumiFramework\Web\ORequest;

final class GetAuditLogsComponentTest extends TestCase {
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
   * Verifies that a valid request returns the requested audit page.
   *
   * @return void
   */
  public function testRunReturnsPaginatedAuditEvents(): void {
    $audit_log = new AuditLog();
    $audit_log->id = 10;

    $service = $this->createAuditLogServiceMock();

    $service
      ->expects(
        self::once()
      )
      ->method('countAll')
      ->willReturn(26);

    $service
      ->expects(
        self::once()
      )
      ->method('getPage')
      ->with(
        2,
        25
      )
      ->willReturn([
        $audit_log
      ]);

    $component = $this->createComponent(
      $service
    );

    $component->run(
      $this->createDto(
        2,
        25
      )
    );

    self::assertSame(
      'ok',
      $component->status
    );

    self::assertSame(
      2,
      $component->page
    );

    self::assertSame(
      25,
      $component->page_size
    );

    self::assertSame(
      26,
      $component->total
    );

    self::assertSame(
      2,
      $component->total_pages
    );

    self::assertSame(
      [$audit_log],
      $component->list?->list
    );

    self::assertSame(
      200,
      OMiddleware::getStatusCode()
    );
  }

  /**
   * Verifies that oversized pages are rejected.
   *
   * @return void
   */
  public function testRunRejectsInvalidPageSize(): void {
    $service = $this->createAuditLogServiceMock();

    $service
      ->expects(
        self::never()
      )
      ->method('countAll');

    $service
      ->expects(
        self::never()
      )
      ->method('getPage');

    $component = $this->createComponent(
      $service
    );

    $component->run(
      $this->createDto(
        1,
        101
      )
    );

    self::assertSame(
      'error',
      $component->status
    );

    self::assertSame(
      'Invalid pagination values.',
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
   * Creates an audit pagination DTO.
   *
   * @param int $page      Page number.
   * @param int $page_size Page size.
   *
   * @return GetAuditLogsDTO Created DTO.
   */
  private function createDto(
    int $page,
    int $page_size
  ): GetAuditLogsDTO {
    return new GetAuditLogsDTO(
      new ORequest([
        'method' => 'GET',
        'headers' => [],
        'params' => [
          'page' => $page,
          'pageSize' => $page_size
        ]
      ])
    );
  }

  /**
   * Creates an AuditLogService mock.
   *
   * @return AuditLogService&MockObject Audit service mock.
   */
  private function createAuditLogServiceMock(): AuditLogService&MockObject {
    return $this->createMock(
      AuditLogService::class
    );
  }

  /**
   * Creates a component with a controlled service dependency.
   *
   * @param AuditLogService $audit_log_service Audit service dependency.
   *
   * @return GetAuditLogsComponent Prepared component.
   */
  private function createComponent(
    AuditLogService $audit_log_service
  ): GetAuditLogsComponent {
    $reflection = new ReflectionClass(
      GetAuditLogsComponent::class
    );

    $component = $reflection->newInstanceWithoutConstructor();

    self::assertInstanceOf(
      GetAuditLogsComponent::class,
      $component
    );

    $service_property = new ReflectionProperty(
      GetAuditLogsComponent::class,
      'audit_log_service'
    );

    $service_property->setValue(
      $component,
      $audit_log_service
    );

    $component->list = $this->createAuditLogListComponent();

    return $component;
  }

  /**
   * Creates an audit log list component without framework initialization.
   *
   * The constructor is skipped because the test only needs the public list
   * property and does not render the nested component template.
   *
   * @return AuditLogListComponent Prepared audit log list component.
   */
  private function createAuditLogListComponent(): AuditLogListComponent {
    $reflection = new ReflectionClass(
      AuditLogListComponent::class
    );

    $component = $reflection->newInstanceWithoutConstructor();

    self::assertInstanceOf(
      AuditLogListComponent::class,
      $component
    );

    return $component;
  }

  /**
   * Gets the test framework core.
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
