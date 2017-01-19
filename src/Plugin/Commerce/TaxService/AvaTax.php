<?php

namespace Drupal\commerce_avatax\Plugin\Commerce\TaxService;

use AvaTax\DocumentType;
use AvaTax\GetTaxRequest;
use AvaTax\SeverityLevel;
use AvaTax\TaxServiceSoap;
use Drupal\address\AddressInterface;
use Drupal\commerce_avatax\AvaTaxAddress;
use Drupal\commerce_avatax\AvaTaxConfig;
use Drupal\commerce_avatax\AvaTaxConfigManager;
use Drupal\commerce_avatax\AvaTaxLineCollection;
use Drupal\commerce_avatax\CustomerUsageType;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_price\Price;
use Drupal\commerce_tax_service\Exception\TaxServiceException;
use Drupal\commerce_tax_service\Plugin\Commerce\TaxService\RemoteTaxServiceBase;
use Drupal\Core\Form\FormStateInterface;
use GuzzleHttp\Exception\RequestException;
use SoapFault;

/**
 * Provides an 'AvaTax' tax service.
 *
 * @CommerceTaxService(
 *   id = "commerce_tax_service_avatax",
 *   label = @Translation("AvaTax"),
 *   target_entity_type = "commerce_order",
 * )
 */
class AvaTax extends RemoteTaxServiceBase {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
        'account' => '',
        'license' => '',
        'company_code' => '',
        'trace' => FALSE,
        'include_shipping' => FALSE,
      ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form += parent::buildConfigurationForm($form, $form_state);

    $form['api_information'] = [
      '#type' => 'details',
      '#title' => $this->t('API information'),
      '#description' => $this->t('Your Avalara account information and API connection settings.')
    ];

    $form['api_information']['account'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Account'),
      '#description' => $this->t('Enter your Avalara account number.'),
      '#default_value' => $this->configuration['account'],
      '#required' => TRUE,
    ];

    $form['api_information']['license'] = [
      '#type' => 'textfield',
      '#title' => $this->t('License'),
      '#description' => $this->t('Enter your Avalara license key.'),
      '#default_value' => $this->configuration['license'],
      '#required' => TRUE,
    ];

    $form['api_information']['trace'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable Trace option'),
      '#description' => $this->t('May be useful in development mode.'),
      '#default_value' => $this->configuration['trace'],
    ];

    $form['company_information'] = [
      '#type' => 'details',
      '#title' => $this->t('Company information'),
      '#description' => $this->t('Your Avalara company information.')
    ];

    $form['company_information']['company_code'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Company code'),
      '#description' => $this->t('Enter the Avalara company code to use.'),
      '#default_value' => $this->configuration['company_code'],
      '#required' => TRUE,
    ];

    $form['settings'] = [
      '#type' => 'details',
      '#title' => $this->t('Settings'),
      '#description' => $this->t('General settings about AvaTax.')
    ];

    $form['settings']['include_shipping'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Include shipping'),
      '#description' => $this->t('Include shipping charges in tax calculations.'),
      '#default_value' => $this->configuration['include_shipping'],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::validateConfigurationForm($form, $form_state);

    $values = $form_state->getValue($form['#parents']);

    $apiInfo = $values['target_plugin_configuration']['api_information'];
    $companyInfo = $values['target_plugin_configuration']['company_information'];

    if (empty($apiInfo['account'])) {
      $form_state->setError($form, $this->t('A value for Account must be set.'));
    }

    if (empty($apiInfo['license'])) {
      $form_state->setError($form, $this->t('A value for License must be set.'));
    }

    if (empty($companyInfo['company_code'])) {
      $form_state->setError($form, $this->t('A value for Company code must be set.'));
    }

    if (!$this->isAuthorized()) {
      $form_state->setError($form, $this->t('The specified Avalara account is not authorized to make tax requests. Please check the information and try again.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValue($form['#parents']);

    $this->configuration['account'] = $values['api_information']['account'];
    $this->configuration['license'] = $values['api_information']['license'];
    $this->configuration['trace'] = $values['api_information']['trace'];
    $this->configuration['company_code'] = $values['company_information']['company_code'];
    $this->configuration['include_shipping'] = $values['settings']['include_shipping'];

    parent::submitConfigurationForm($form, $form_state);
  }

  /**
   * Executes the plugin.
   */
  public function execute() {
    /** @var OrderInterface $order */
    $order = $this->getTargetEntity();

    $billingProfile = $order->getBillingProfile();

    if (!$billingProfile || $billingProfile->get('address')->isEmpty()) {
      return;
    }

    try {
      $response = $this->httpClient->post('url', [
        'headers' => [
          'Content-type' => 'application/json',
          'Authorization' => '',
        ],
        'json' => [],
        'timeout' => 0,
      ]);

      $data = json_decode($response->getBody(), TRUE);
    }
    catch (RequestException $e) {
      throw new TaxServiceException('Could not calculate the taxes.');
    }

    // TODO: Replace below.

    $config = $this->registerConfig();
    $taxRequest = $this->getTaxRequest();

    $taxService = new TaxServiceSoap($config->getId());

    try {
      $result = $taxService->getTax($taxRequest);

      if ($result->getResultCode() != SeverityLevel::$Success) {
        throw new TaxServiceException("Could not calculate sales tax", $result->getResultCode());
      }

      $currencyCode = $order->getTotalPrice()->getCurrencyCode();
      $amount = new Price($result->getTotalTax(), $currencyCode);

      $this->applyAdjustment($order, $amount);
    } catch (SoapFault $e) {
      throw new TaxServiceException("Could not calculate sales tax", $e->getCode(), $e);
    }
  }

  protected function isAuthorized() {
    $config = $this->registerConfig();
    $taxService = new TaxServiceSoap($config->getId());

    try {
      $result = $taxService->isAuthorized('GetTax');

      return ($result->getResultCode() == SeverityLevel::$Success);
    } catch (\Exception $e) {
      return false;
    }
  }

  protected function getTaxRequest() {
    /** @var OrderInterface $order */
    $order = $this->getTargetEntity();

    $billingProfile = $order->getBillingProfile();

    /** @var AddressInterface $billingAddressItem */
    $billingAddressItem = $billingProfile->get('address')->first();

    $originAddress = new AvaTaxAddress($order->getStore()->getAddress());
    $billingAddress = new AvaTaxAddress($billingAddressItem);
    $lines = new AvaTaxLineCollection($order, $this->configuration['include_shipping']);

    $taxRequest = new GetTaxRequest();

    $taxRequest
      ->setCompanyCode($this->configuration['company_code'])
      ->setDocType(DocumentType::$SalesInvoice)
      ->setDocCode($order->getOrderNumber())
      ->setCustomerCode($order->getCustomerId())
      ->setCustomerUsageType(CustomerUsageType::DIRECT_MAIL)
      ->setCurrencyCode($order->getTotalPrice()->getCurrencyCode())
      ->setOriginAddress($originAddress->getAddress())
      ->setDestinationAddress($billingAddress->getAddress())
      ->setLines($lines->getLines());
  }

  protected function registerConfig() {
    /** @var AvaTaxConfigManager $avataxConfigManager */
    $avataxConfigManager = \Drupal::service('commerce_avatax.avatax_config_manager');

    $config = new AvaTaxConfig($this->getPluginId());

    $config
      ->setAccount($this->configuration['account'])
      ->setLicense($this->configuration['license'])
      ->setTrace($this->configuration['trace'])
      ->setUrl($this->configuration['mode']);

    $avataxConfigManager->register($config);

    return $config;
  }
}
