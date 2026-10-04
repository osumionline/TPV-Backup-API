<?php

declare(strict_types=1);

namespace Osumi\OsumiFramework\App\Tests\Middleware;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use RuntimeException;
use Osumi\OsumiFramework\App\Middleware\InstallationAuthMiddleware;
use Osumi\OsumiFramework\App\Service\InstallationAuthService;
use Osumi\OsumiFramework\Core\OCore;
use Osumi\OsumiFramework\Core\OMiddleware;

final class InstallationAuthMiddlewareTest extends TestCase {
  private bool $core_existed = false;
  private mixed $previous_core = null;

  /**
   * Creates isolated middleware state for each test.
   *
   * @return void
   */
  protected function setUp(): void {
    parent::setUp();

    $this->loadFrameworkFunctions();

    $this->core_existed = array_key_exists(
      'core',
      $GLOBALS
    );

    if ($this->core_existed) {
      $this->previous_core =
        $GLOBALS['core'];
    }

    $GLOBALS['core'] = new OCore();

    OMiddleware::reset();
  }

  /**
   * Restores framework state after each test.
   *
   * @return void
   */
  protected function tearDown(): void {
    OMiddleware::reset();

    if ($this->core_existed) {
      $GLOBALS['core'] =
        $this->previous_core;
    }
    else {
      unset(
        $GLOBALS['core']
      );
    }

    parent::tearDown();
  }

  /**
   * Verifies that the middleware ignores phases other than before.
   *
   * @return void
   */
  public function testHandleIgnoresOtherPhases(): void {
    self::assertSame(
      [],
      InstallationAuthMiddleware::handle(
        OMiddleware::PHASE_AFTER_RENDER,
        []
      )
    );
  }

  /**
   * Verifies that a missing Authorization header is rejected.
   *
   * @return void
   */
  public function testHandleRejectsMissingAuthorizationHeader(): void {
    self::assertSame(
      [
        'stop' => true,
        'status_code' => 403,
        'message' => ''
      ],
      InstallationAuthMiddleware::handle(
        OMiddleware::PHASE_BEFORE,
        [
          'headers' => []
        ]
      )
    );
  }

  /**
   * Verifies that a valid Bearer token publishes authentication context.
   *
   * @return void
   */
  public function testHandlePublishesInstallationContext(): void {
    $context = [
      'status' => 'ok',
      'installation_id' => 10,
      'installation_public_id' =>
        'installation-public-id',
      'installation_name' => 'TPV principal',
      'credential_id' => 7,
      'key_id' => 'key-id',
      'subscription_id' => 20,
      'subscription_public_id' =>
        'subscription-public-id',
      'subscription_name' => 'Cliente',
      'subscription_status' => 'active',
      'can_upload' => true
    ];

    $service =
      $this->createAuthServiceMock();

    $service
      ->expects(
        self::once()
      )
      ->method('authenticateToken')
      ->with(
        'installation-token'
      )
      ->willReturn(
        $context
      );

    $this->registerAuthService(
      $service
    );

    self::assertSame(
      [
        'context' => $context
      ],
      InstallationAuthMiddleware::handle(
        OMiddleware::PHASE_BEFORE,
        [
          'headers' => [
            'Authorization' =>
              'Bearer installation-token'
          ]
        ]
      )
    );
  }

  /**
   * Verifies that an invalid Bearer token is rejected.
   *
   * @return void
   */
  public function testHandleRejectsInvalidToken(): void {
    $service =
      $this->createAuthServiceMock();

    $service
      ->expects(
        self::once()
      )
      ->method('authenticateToken')
      ->with(
        'invalid-token'
      )
      ->willReturn(null);

    $this->registerAuthService(
      $service
    );

    self::assertSame(
      [
        'stop' => true,
        'status_code' => 403,
        'message' => ''
      ],
      InstallationAuthMiddleware::handle(
        OMiddleware::PHASE_BEFORE,
        [
          'headers' => [
            'authorization' =>
              'Bearer invalid-token'
          ]
        ]
      )
    );
  }

  /**
   * Registers a controlled authentication service for inject().
   *
   * @param InstallationAuthService $service Authentication service.
   *
   * @return void
   */
  private function registerAuthService(
    InstallationAuthService $service
  ): void {
    $this->getCore()->services[
      'InstallationAuth'
    ] = $service;
  }

  /**
   * Creates an InstallationAuthService mock.
   *
   * @return InstallationAuthService&MockObject Authentication service mock.
   */
  private function createAuthServiceMock(): InstallationAuthService&MockObject {
    return $this->createMock(
      InstallationAuthService::class
    );
  }

  /**
   * Loads the framework global helper functions required by inject().
   *
   * @return void
   *
   * @throws RuntimeException When the framework source path cannot be resolved.
   */
  private function loadFrameworkFunctions(): void {
    if (function_exists('inject')) {
      return;
    }

    $reflection = new ReflectionClass(
      OCore::class
    );

    $core_file =
      $reflection->getFileName();

    if ($core_file === false) {
      throw new RuntimeException(
        'Framework core source path could not be resolved.'
      );
    }

    require_once dirname(
      $core_file,
      2
    ) . '/Tools/functions.php';
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
