<?php

declare(strict_types=1);

namespace Osumi\OsumiFramework\App\Filter;

use Throwable;
use Osumi\OsumiFramework\Plugins\OToken;
use Osumi\OsumiFramework\App\Model\AdminUser;

class AdminAuthFilter {
  public static function handle(array $params, array $headers): array {
    global $core;

    $ret = [
      'status' => 'error',
      'id' => null,
      'public_id' => null,
      'name' => null,
      'email' => null
    ];

    $authorization = $headers['Authorization'] ?? $headers['authorization'] ?? null;

    if (!is_string($authorization)) {
      return $ret;
    }

    if (!preg_match('/^Bearer\s+(.+)$/i', trim($authorization), $matches)) {
      return $ret;
    }

    $raw_token = trim($matches[1]);

    // OToken expects exactly the three JWT segments.
    if (substr_count($raw_token, '.') !== 2) {
      return $ret;
    }

    $secret = $core->config->getExtra('admin_token_secret');

    if (!is_string($secret) || $secret === '') {
      return $ret;
    }

    try {
      $token = new OToken($secret);

      if (!$token->checkToken($raw_token)) {
        return $ret;
      }

      if ($token->getParam('type') !== 'admin') {
        return $ret;
      }

      $id = (int) $token->getParam('id');
      $public_id = $token->getParam('public_id');

      if ($id <= 0 || !is_string($public_id) || $public_id === '') {
        return $ret;
      }

      $admin = AdminUser::findOne(['id' => $id]);

      if (
        is_null($admin) ||
        $admin->active !== true ||
        $admin->public_id !== $public_id
      ) {
        return $ret;
      }

      return [
        'status' => 'ok',
        'id' => $admin->id,
        'public_id' => $admin->public_id,
        'name' => $admin->name,
        'email' => $admin->email
      ];
    }
    catch (Throwable) {
      return $ret;
    }
  }
}
