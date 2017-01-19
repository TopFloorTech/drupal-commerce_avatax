<?php

namespace Drupal\commerce_avatax;

use AvaTax\ATConfig;

class AvaTaxConfigManager {
  protected $configs = [];

  public function exists($id) {
    return isset($this->configs[$id]);
  }

  public function register(AvaTaxConfig $config) {
    $id = $config->getId();

    $this->configs[$id] = $config;

    new ATConfig($id, $config->getConfiguration());

    return $config;
  }

  public function get($id) {
    if (!$this->exists($id)) {
      throw new \InvalidArgumentException("Provided config ID $id is not registered.");
    }

    return $this->configs[$id];
  }
}
