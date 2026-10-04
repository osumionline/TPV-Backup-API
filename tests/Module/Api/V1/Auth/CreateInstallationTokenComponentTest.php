<?php

declare(strict_types=1);

namespace Osumi\OsumiFramework\App\Tests\Module\Api\V1\Auth;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionProperty;
use RuntimeException;
use Osumi\OsumiFramework\App\DTO\CreateInstallationTokenDTO;
use Osumi\OsumiFramework\App\Model\Installation;
use Osumi\OsumiFramework\App\Model\InstallationCredential;
use Osumi\OsumiFramework\App\Model\Subscription;
use Osumi\OsumiFramework\App\Module\Api\V1\Auth\CreateInstallationToken\CreateInstallationTokenComponent;
use Osumi\OsumiFramework\App\Service\AuditLogService;
use Osumi\OsumiFramework\App\Service\InstallationAuthService;
use Osumi\OsumiFramework\Core\OCore;
use Osumi\OsumiFramework\Core\OMiddleware;
use Osumi\OsumiFramework\Web\ORequest;

final class CreateInstallationTokenComponentTest extends TestCase {
  private bool $core_existed = false;
  private mixed $previous_core = null;

  /**
   * Creates isolated framework state for each test.
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
   * Verifies that missing credentials return a 400 response.
   *
   * @return void
   */
  public function testRunRejectsMissingCredentials(): void {
    $auth_service =
      $this->createAuthServiceMock();

    $audit_service =
      $this->createAuditServiceMock();

    $auth_service
      ->expects(
        self::never()
      )
      ->method('login');

    $audit_service
      ->expects(
        self::never()
      )
      ->method('recordInstallationAction');

    $component = $this->createComponent(
      $auth_service,
      $audit_service
    );

    $component->run(
      $this->createDto()
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
  }

  /**
   * Verifies that invalid installation credentials return a 401 response.
   *
   * @return void
   */
  public function testRunRejectsInvalidCredentials(): void {
    $auth_service =
      $this->createAuthServiceMock();

    $audit_service =
      $this->createAuditServiceMock();

    $auth_service
      ->expects(
        self::once()
      )
      ->method('login')
      ->with(
        'key-id',
        'installation-secret'
      )
      ->willReturn(null);

    $audit_service
      ->expects(
        self::never()
      )
      ->method('recordInstallationAction');

    $component = $this->createComponent(
      $auth_service,
      $audit_service
    );

    $component->run(
      $this->createDto(
        'key-id',
        'installation-secret'
      )
    );

    self::assertSame(
      'Invalid installation credentials.',
      $component->message
    );

    self::assertSame(
      401,
      OMiddleware::getStatusCode()
    );
  }

  /**
   * Verifies that authentication infrastructure failures return 500.
   *
   * @return void
   */
  public function testRunReturnsServerErrorWhenAuthenticationFails(): void {
    $auth_service =
      $this->createAuthServiceMock();

    $audit_service =
      $this->createAuditServiceMock();

    $auth_service
      ->expects(
        self::once()
      )
      ->method('login')
      ->willThrowException(
        new RuntimeException(
          'Token configuration unavailable.'
        )
      );

    $audit_service
      ->expects(
        self::never()
      )
      ->method('recordInstallationAction');

    $component = $this->createComponent(
      $auth_service,
      $audit_service
    );

    $component->run(
      $this->createDto(
        'key-id',
        'installation-secret'
      )
    );

    self::assertSame(
      'Installation authentication could not be completed.',
      $component->message
    );

    self::assertSame(
      500,
      OMiddleware::getStatusCode()
    );
  }

  /**
   * Verifies a successful installation authentication response.
   *
   * @return void
   */
  public function testRunReturnsInstallationToken(): void {
    $installation = new Installation();

    $installation->id = 10;
    $installation->public_id =
      'installation-public-id';
    $installation->name = 'TPV principal';

    $subscription = new Subscription();

    $subscription->id = 20;
    $subscription->public_id =
      'subscription-public-id';
    $subscription->name = 'Cliente';

    $credential =
      new InstallationCredential();

    $credential->id = 7;
    $credential->key_id = 'key-id';

    $auth_service =
      $this->createAuthServiceMock();

    $audit_service =
      $this->createAuditServiceMock();

    $auth_service
      ->expects(
        self::once()
      )
      ->method('login')
      ->with(
        'key-id',
        'installation-secret'
      )
      ->willReturn([
        'token' => 'installation-token',
        'expires_at' => 2000000000,
        'installation' => $installation,
        'subscription' => $subscription,
        'credential' => $credential,
        'subscription_status' => 'active',
        'can_upload' => true
      ]);

    $audit_service
      ->expects(
        self::once()
      )
      ->method('recordInstallationAction')
      ->with(
        $installation,
        AuditLogService::ACTION_INSTALLATION_AUTHENTICATE,
        AuditLogService::ENTITY_INSTALLATION,
        'installation-public-id',
        [
          'keyId' => 'key-id'
        ]
      )
      ->willReturn(true);

    $component = $this->createComponent(
      $auth_service,
      $audit_service
    );

    $component->run(
      $this->createDto(
        'key-id',
        'installation-secret'
      )
    );

    self::assertSame(
      'ok',
      $component->status
    );

    self::assertSame(
      'installation-token',
      $component->token
    );

    self::assertSame(
      2000000000,
      $component->expires_at
    );

    self::assertSame(
      'installation-public-id',
      $component->installation_public_id
    );

    self::assertSame(
      'TPV principal',
      $component->installation_name
    );

    self::assertSame(
      'subscription-public-id',
      $component->subscription_public_id
    );

    self::assertSame(
      'Cliente',
      $component->subscription_name
    );

    self::assertSame(
      'active',
      $component->subscription_status
    );

    self::assertTrue(
      $component->can_upload
    );

    self::assertSame(
      200,
      OMiddleware::getStatusCode()
    );
  }

  /**
   * Creates an installation authentication DTO.
   *
   * @param string|null $key_id Credential key identifier.
   * @param string|null $secret Credential secret.
   *
   * @return CreateInstallationTokenDTO Created DTO.
   */
  private function createDto(
    ?string $key_id = null,
    ?string $secret = null
  ): CreateInstallationTokenDTO {
    $params = [];

    if (!is_null($key_id)) {
      $params['keyId'] = $key_id;
    }

    if (!is_null($secret)) {
      $params['secret'] = $secret;
    }

    return new CreateInstallationTokenDTO(
      new ORequest([
        'method' => 'POST',
        'headers' => [],
        'params' => $params
      ])
    );
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
   * Creates an AuditLogService mock.
   *
   * @return AuditLogService&MockObject Audit service mock.
   */
  private function createAuditServiceMock(): AuditLogService&MockObject {
    return $this->createMock(
      AuditLogService::class
    );
  }

  /**
   * Creates a token component with controlled dependencies.
   *
   * @param InstallationAuthService $auth_service  Authentication service.
   * @param AuditLogService         $audit_service Audit service.
   *
   * @return CreateInstallationTokenComponent Prepared component.
   */
  private function createComponent(
    InstallationAuthService $auth_service,
    AuditLogService $audit_service
  ): CreateInstallationTokenComponent {
    $reflection = new ReflectionClass(
      CreateInstallationTokenComponent::class
    );

    $component =
      $reflection->newInstanceWithoutConstructor();

    self::assertInstanceOf(
      CreateInstallationTokenComponent::class,
      $component
    );

    $auth_property = new ReflectionProperty(
      CreateInstallationTokenComponent::class,
      'auth_service'
    );

    $auth_property->setValue(
      $component,
      $auth_service
    );

    $audit_property = new ReflectionProperty(
      CreateInstallationTokenComponent::class,
      'audit_log_service'
    );

    $audit_property->setValue(
      $component,
      $audit_service
    );

    return $component;
  }
}
