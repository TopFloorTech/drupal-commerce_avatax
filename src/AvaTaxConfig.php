<?php

namespace Drupal\commerce_avatax;

use Drupal\commerce_tax_service\TaxServiceMode;

class AvaTaxConfig {

  const TEST_API_URL = 'https://development.avalara.net';
  const LIVE_API_URL = 'https://avatax.avalara.net';

  protected $id;

  protected $configuration;

  public function __construct($id, array $configuration = []) {
    $this->id = $id;
    $this->configuration = $configuration + $this->defaultConfiguration();
  }

  public function defaultConfiguration() {
    return [
      'url' => self::TEST_API_URL,
      'addressService' => '/Address/AddressSvc.asmx',
      'taxService' => '/Tax/TaxSvc.asmx',
      'batchService' => '/Batch/BatchSvc.asmx',
      'account' => '',
      'license' => '',
      'adapter' => 'avatax4php,15.5.1.0',
      'client' => 'AvalaraPHPInterface,1.0',
      'name' => '15.5.1.0',
      'trace' => TRUE,
    ];
  }

  public function getId() {
    return $this->id;
  }

  public function setId($id) {
    $this->id = $id;

    return $this;
  }

  public function getConfiguration() {
    return $this->configuration;
  }

  public function setConfiguration(array $configuration = []) {
    $this->configuration = $configuration + $this->defaultConfiguration();

    return $this;
  }

  public function getUrl() {
    return $this->configuration['url'];
  }

  public function setUrl($mode) {
    $this->configuration['url'] = ($mode == TaxServiceMode::TEST)
      ? self::TEST_API_URL
      : self::LIVE_API_URL;

    return $this;
  }

  public function getAccount() {
    return $this->configuration['account'];
  }

  public function setAccount($account) {
    $this->configuration['account'] = $account;

    return $this;
  }

  public function getLicense() {
    return $this->configuration['license'];
  }

  public function setLicense($license) {
    $this->configuration['license'] = $license;

    return $this;
  }

  public function getTrace() {
    return (bool) $this->configuration['trace'];
  }

  public function setTrace(bool $trace = TRUE) {
    $this->configuration['trace'] = $trace;

    return $this;
  }

  public function getAdapter() {
    return $this->configuration['adapter'];
  }

  public function setAdapter($adapter) {
    $this->configuration['adapter'] = $adapter;

    return $this;
  }

  public function getClient() {
    return $this->configuration['client'];
  }

  public function setClient($client) {
    $this->configuration['client'] = $client;

    return $this;
  }

  public function getName() {
    return $this->configuration['name'];
  }

  public function setName($name) {
    $this->configuration['name'] = $name;

    return $this;
  }

  public function getAddressService() {
    return $this->configuration['addressService'];
  }

  public function setAddressService($addressService) {
    $this->configuration['addressService'] = $addressService;

    return $this;
  }

  public function getTaxService() {
    return $this->configuration['taxService'];
  }

  public function setTaxService($taxService) {
    $this->configuration['taxService'] = $taxService;

    return $this;
  }

  public function getBatchService() {
    return $this->configuration['batchService'];
  }

  public function setBatchService($batchService) {
    $this->configuration['batchService'] = $batchService;
  }
}
