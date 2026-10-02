<?php

declare(strict_types=1);

namespace Osumi\OsumiFramework\App\Middleware;

use Osumi\OsumiFramework\App\Model\AdminUser;
use Osumi\OsumiFramework\Core\OMiddleware;
use Osumi\OsumiFramework\Plugins\OToken;
use Throwable;

/**
 * Validates administrator authentication and publishes its context.
 */
final class AdminAuthMiddleware {
  /**
   * Handles administrator authentication during the before phase.
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
    if ($phase !== OMiddleware::PHASE_BEFORE) {
      return [];
    }

    global $core;

    $headers = $data['headers'] ?? [];

    if (!is_array($headers)) {
      return self::unauthorized();
    }

    $authorization = $headers['Authorization']
      ?? $headers['authorization']
      ?? null;

    if (!is_string($authorization)) {
      return self::unauthorized();
    }

    if (!preg_match('/^Bearer\s+(.+)$/i', trim($authorization), $matches)) {
      return self::unauthorized();
    }

    $raw_token = trim($matches[1]);

    if (substr_count($raw_token, '.') !== 2) {
      return self::unauthorized();
    }

    $secret = $core->config->getExtra('admin_token_secret');

    if (!is_string($secret) || $secret === '') {
      return self::unauthorized();
    }

    try {
      $token = new OToken($secret);

      if (!$token->checkToken($raw_token)) {
        return self::unauthorized();
      }

      if ($token->getParam('type') !== 'admin') {
        return self::unauthorized();
      }

      $id = (int) $token->getParam('id');
      $public_id = $token->getParam('public_id');

      if (
        $id <= 0 ||
        !is_string($public_id) ||
        $public_id === ''
      ) {
        return self::unauthorized();
      }

      $admin = AdminUser::findOne([
        'id' => $id
      ]);

      if (
        is_null($admin) ||
        $admin->active !== true ||
        $admin->public_id !== $public_id
      ) {
        return self::unauthorized();
      }

      return [
        'context' => [
          'status' => 'ok',
          'id' => $admin->id,
          'public_id' => $admin->public_id,
          'name' => $admin->name,
          'email' => $admin->email
        ]
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
