<?php

namespace Drupal\commerce_avatax\Plugin\Commerce\TaxService;

use Drupal\address\AddressInterface;
use Drupal\commerce_avatax\AvaTaxAddress;
use Drupal\commerce_avatax\AvaTaxLineCollection;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_price\Price;
use Drupal\commerce_tax_service\Exception\TaxServiceException;
use Drupal\commerce_tax_service\Plugin\Commerce\TaxService\RemoteTaxServiceBase;
use Drupal\commerce_tax_service\TaxServiceMode;
use Drupal\Core\Form\FormStateInterface;
use GuzzleHttp\Exception\RequestException;

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

  const TEST_API_URL = 'https://sandbox-rest.avatax.com/api/v2';
  const LIVE_API_URL = 'https://rest.avatax.com/api/v2';

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
        'account' => '',
        'license' => '',
        'company_code' => '',
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
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValue($form['#parents']);

    $this->configuration['account'] = $values['api_information']['account'];
    $this->configuration['license'] = $values['api_information']['license'];
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
      $this->applyAdjustment($order, $this->getTax());
    } catch (\Exception $exception) {
      drupal_set_message('Failed to calculate sales tax.');
    }
  }

  protected function avaTaxRequest($requestPath, $parameters = []) {
    try {
      $response = $this->httpClient->post($this->getApiUrl($requestPath), [
        'auth' => [
          $this->configuration['account'],
          $this->configuration['license']
        ],
        'json' => $parameters,
      ]);

      $data = json_decode($response->getBody(), TRUE);

      return $data;
    }
    catch (RequestException $e) {
      throw new TaxServiceException('Could not calculate the taxes.');
    }
  }

  protected function getApiUrl($requestPath = '') {
    $url = ($this->configuration['mode'] == TaxServiceMode::TEST)
      ? self::TEST_API_URL
      : self::LIVE_API_URL;

    if ($requestPath) {
      $url .= $requestPath;
    }

    return $url;
  }

  protected function getTax() {
    /** @var OrderInterface $order */
    $order = $this->getTargetEntity();

    $billingProfile = $order->getBillingProfile();

    /** @var AddressInterface $billingAddressItem */
    $billingAddressItem = $billingProfile->get('address')->first();

    $originAddress = new AvaTaxAddress($order->getStore()->getAddress());
    $billingAddress = new AvaTaxAddress($billingAddressItem);
    $lines = new AvaTaxLineCollection($order, $this->configuration['include_shipping']);

    $data = $this->avaTaxRequest('/transactions/create', [
      'type' => 'SalesInvoice',
      'companyCode' => $this->configuration['company_code'],
      'code' => $order->id(),
      'date' => date(\DateTime::ATOM, $order->getCreatedTime()),
      'customerCode' => $order->getCustomerId(),
      'currencyCode' => $order->getTotalPrice()->getCurrencyCode(),
      'addresses' => [
        'ShipFrom' => $originAddress->getAddress(),
        'ShipTo' => $billingAddress->getAddress()
      ],
      'lines' => $lines->getLines()
    ]);

    if (empty($data['totalTax'])) {
      throw new TaxServiceException('Could not calculate tax.');
    }

    return new Price("${data['totalTax']}", $order->getTotalPrice()->getCurrencyCode());
  }
}
