<?php

declare(strict_types=1);

namespace Osumi\OsumiFramework\App\Middleware;

use Throwable;
use Osumi\OsumiFramework\App\Service\InstallationAuthService;
use Osumi\OsumiFramework\Core\OMiddleware;

/**
 * Validates installation authentication and publishes its trusted context.
 */
final class InstallationAuthMiddleware {
  /**
   * Handles installation authentication during the before phase.
   *
   * @param string               $phase Current middleware phase.
   * @param array<string, mixed> $data  Current middleware pipeline data.
   *
   * @return array<string, mixed> Middleware result.
   */
  public static function handle(
    string $phase,
    array $data
  ): array {
    if (
      $phase !==
      OMiddleware::PHASE_BEFORE
    ) {
      return [];
    }

    $headers = $data['headers']
      ?? [];

    if (!is_array($headers)) {
      return self::unauthorized();
    }

    $authorization =
      $headers['Authorization']
      ?? $headers['authorization']
      ?? null;

    if (
      !is_string($authorization) ||
      !preg_match(
        '/^Bearer\s+(.+)$/i',
        trim($authorization),
        $matches
      )
    ) {
      return self::unauthorized();
    }

    try {
      $auth_service = inject(
        InstallationAuthService::class
      );

      $context = $auth_service->authenticateToken(
        trim(
          $matches[1]
        )
      );

      if (is_null($context)) {
        return self::unauthorized();
      }

      return [
        'context' => $context
      ];
    }
    catch (Throwable) {
      return self::unauthorized();
    }
  }

  /**
   * Builds the middleware stop response used when authentication fails.
   *
   * @return array<string, mixed> Middleware stop result.
   */
  private static function unauthorized(): array {
    return [
      'stop' => true,
      'status_code' => 403,
      'message' => ''
    ];
  }
}
