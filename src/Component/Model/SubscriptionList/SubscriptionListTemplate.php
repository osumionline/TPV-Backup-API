<?php

use Osumi\OsumiFramework\App\Component\Model\Subscription\SubscriptionComponent;

foreach ($list as $i => $subscription) {
  $component = new SubscriptionComponent([
    'subscription' => $subscription
  ]);

  echo strval($component);

  if ($i < count($list) - 1) {
    echo ',';
  }
}
