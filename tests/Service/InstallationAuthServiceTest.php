<?php

declare(strict_types=1);

namespace Osumi\OsumiFramework\App\Tests\Service;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Osumi\OsumiFramework\App\Model\Installation;
use Osumi\OsumiFramework\App\Model\InstallationCredential;
use Osumi\OsumiFramework\App\Model\Subscription;
use Osumi\OsumiFramework\App\Service\InstallationAuthService;

final class InstallationAuthServiceTest extends TestCase {
  /**
   * Verifies authentication for an installation with an active subscription.
   *
   * @return void
   */
  public function testLoginAuthenticatesActiveSubscription(): void {
    $credential = $this->createCredential();
    $installation = $this->createInstallation();
    $subscription = $this->createSubscription();

    $service = $this->createValidLoginService(
      $credential,
      $installation,
      $subscription,
      false
    );

    $result = $service->login(
      '  key-id  ',
      'installation-secret'
    );

    self::assertNotNull(
      $result
    );

    self::assertSame(
      'installation-token',
      $result['token']
    );

    self::assertSame(
      2000000000,
      $result['expires_at']
    );

    self::assertSame(
      $installation,
      $result['installation']
    );

    self::assertSame(
      $subscription,
      $result['subscription']
    );

    self::assertSame(
      $credential,
      $result['credential']
    );

    self::assertSame(
      'active',
      $result['subscription_status']
    );

    self::assertTrue(
      $result['can_upload']
    );
  }

  /**
   * Verifies that expired subscriptions may authenticate but cannot upload.
   *
   * @return void
   */
  public function testLoginAllowsExpiredSubscriptionWithoutUpload(): void {
    $credential = $this->createCredential();
    $installation = $this->createInstallation();
    $subscription = $this->createSubscription(
      '2020-01-01 23:59:59'
    );

    $service = $this->createValidLoginService(
      $credential,
      $installation,
      $subscription,
      true
    );

    $result = $service->login(
      'key-id',
      'installation-secret'
    );

    self::assertNotNull(
      $result
    );

    self::assertSame(
      'expired',
      $result['subscription_status']
    );

    self::assertFalse(
      $result['can_upload']
    );
  }

  /**
   * Verifies that an invalid credential secret is rejected.
   *
   * @return void
   */
  public function testLoginRejectsInvalidSecret(): void {
    $credential = $this->createCredential();

    $service = $this->createServiceMock();

    $service
      ->expects(
        self::once()
      )
      ->method('getCredentialByKeyId')
      ->with(
        'key-id'
      )
      ->willReturn(
        $credential
      );

    $service
      ->expects(
        self::never()
      )
      ->method('getInstallationById');

    $service
      ->expects(
        self::never()
      )
      ->method('getSubscriptionById');

    $service
      ->expects(
        self::never()
      )
      ->method('createToken');

    $service
      ->expects(
        self::never()
      )
      ->method('markSuccessfulAuthentication');

    self::assertNull(
      $service->login(
        'key-id',
        'wrong-secret'
      )
    );
  }

  /**
   * Verifies token authentication and numeric claim normalization.
   *
   * @return void
   */
  public function testAuthenticateTokenReturnsTrustedContext(): void {
    $credential = $this->createCredential();
    $installation = $this->createInstallation();
    $subscription = $this->createSubscription();

    $service = $this->createServiceMock();

    $service
      ->expects(
        self::once()
      )
      ->method('decodeToken')
      ->with(
        'installation-token'
      )
      ->willReturn([
        'type' => 'installation',
        'credential_id' => '7',
        'key_id' => 'key-id',
        'installation_id' => '10',
        'installation_public_id' =>
          'installation-public-id'
      ]);

    $service
      ->expects(
        self::once()
      )
      ->method('getCredentialById')
      ->with(7)
      ->willReturn(
        $credential
      );

    $service
      ->expects(
        self::once()
      )
      ->method('getInstallationById')
      ->with(10)
      ->willReturn(
        $installation
      );

    $service
      ->expects(
        self::once()
      )
      ->method('getSubscriptionById')
      ->with(20)
      ->willReturn(
        $subscription
      );

    $service
      ->expects(
        self::once()
      )
      ->method('isSubscriptionExpired')
      ->with(
        $subscription
      )
      ->willReturn(false);

    self::assertSame(
      [
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
      ],
      $service->authenticateToken(
        'installation-token'
      )
    );
  }

  /**
   * Verifies that a token stops working when its credential is revoked.
   *
   * @return void
   */
  public function testAuthenticateTokenRejectsRevokedCredential(): void {
    $credential = $this->createCredential(
      '2026-10-04 19:00:00'
    );

    $service = $this->createServiceMock();

    $service
      ->expects(
        self::once()
      )
      ->method('decodeToken')
      ->with(
        'installation-token'
      )
      ->willReturn([
        'type' => 'installation',
        'credential_id' => '7',
        'key_id' => 'key-id',
        'installation_id' => '10',
        'installation_public_id' =>
          'installation-public-id'
      ]);

    $service
      ->expects(
        self::once()
      )
      ->method('getCredentialById')
      ->with(7)
      ->willReturn(
        $credential
      );

    $service
      ->expects(
        self::never()
      )
      ->method('getInstallationById');

    $service
      ->expects(
        self::never()
      )
      ->method('getSubscriptionById');

    self::assertNull(
      $service->authenticateToken(
        'installation-token'
      )
    );
  }

  /**
   * Creates a service mock configured for a valid credential login.
   *
   * @param InstallationCredential $credential   Credential to authenticate.
   * @param Installation           $installation Credential owner.
   * @param Subscription           $subscription Installation subscription.
   * @param bool                   $expired       Whether the subscription is expired.
   *
   * @return InstallationAuthService&MockObject Configured service mock.
   */
  private function createValidLoginService(
    InstallationCredential $credential,
    Installation $installation,
    Subscription $subscription,
    bool $expired
  ): InstallationAuthService&MockObject {
    $service = $this->createServiceMock();

    $service
      ->expects(
        self::once()
      )
      ->method('getCredentialByKeyId')
      ->with(
        'key-id'
      )
      ->willReturn(
        $credential
      );

    $service
      ->expects(
        self::once()
      )
      ->method('getInstallationById')
      ->with(10)
      ->willReturn(
        $installation
      );

    $service
      ->expects(
        self::once()
      )
      ->method('getSubscriptionById')
      ->with(20)
      ->willReturn(
        $subscription
      );

    $service
      ->expects(
        self::once()
      )
      ->method('createToken')
      ->with(
        $installation,
        $credential
      )
      ->willReturn([
        'token' => 'installation-token',
        'expires_at' => 2000000000
      ]);

    $service
      ->expects(
        self::once()
      )
      ->method('markSuccessfulAuthentication')
      ->with(
        $credential,
        $installation,
        'installation-secret'
      );

    $service
      ->expects(
        self::once()
      )
      ->method('isSubscriptionExpired')
      ->with(
        $subscription
      )
      ->willReturn(
        $expired
      );

    return $service;
  }

  /**
   * Creates an InstallationAuthService partial mock.
   *
   * @return InstallationAuthService&MockObject Authentication service mock.
   */
  private function createServiceMock(): InstallationAuthService&MockObject {
    return $this
      ->getMockBuilder(
        InstallationAuthService::class
      )
      ->disableOriginalConstructor()
      ->onlyMethods([
        'getCredentialByKeyId',
        'getCredentialById',
        'getInstallationById',
        'getSubscriptionById',
        'createToken',
        'decodeToken',
        'markSuccessfulAuthentication',
        'isSubscriptionExpired'
      ])
      ->getMock();
  }

  /**
   * Creates an active installation credential for tests.
   *
   * @param string|null $revoked_at Optional revocation date.
   *
   * @return InstallationCredential Test credential.
   */
  private function createCredential(
    ?string $revoked_at = null
  ): InstallationCredential {
    $credential =
      new InstallationCredential();

    $credential->id = 7;
    $credential->id_installation = 10;
    $credential->key_id = 'key-id';
    $credential->secret_hash = password_hash(
      'installation-secret',
      PASSWORD_DEFAULT
    );
    $credential->revoked_at = $revoked_at;

    return $credential;
  }

  /**
   * Creates an active installation for tests.
   *
   * @return Installation Test installation.
   */
  private function createInstallation(): Installation {
    $installation = new Installation();

    $installation->id = 10;
    $installation->public_id =
      'installation-public-id';
    $installation->id_subscription = 20;
    $installation->name = 'TPV principal';
    $installation->active = true;

    return $installation;
  }

  /**
   * Creates an active subscription for tests.
   *
   * @param string|null $expires_at Optional expiration date.
   *
   * @return Subscription Test subscription.
   */
  private function createSubscription(
    ?string $expires_at = null
  ): Subscription {
    $subscription = new Subscription();

    $subscription->id = 20;
    $subscription->public_id =
      'subscription-public-id';
    $subscription->name = 'Cliente';
    $subscription->active = true;
    $subscription->expires_at = $expires_at;

    return $subscription;
  }
}
