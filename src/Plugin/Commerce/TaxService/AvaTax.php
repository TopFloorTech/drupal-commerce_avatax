<?php

namespace Drupal\commerce_avatax\Plugin\Commerce\TaxService;

use Drupal\commerce_tax_service\Plugin\Commerce\TaxService\RemoteTaxServiceBase;
use Drupal\Core\Form\FormStateInterface;

class AvaTax extends RemoteTaxServiceBase {

  const TEST_API_URL = 'https://development.avalara.net';
  const LIVE_API_URL = 'https://avatax.avalara.net';

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
        'account' => '',
        'license' => '',
        'trace' => FALSE,
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

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::validateConfigurationForm($form, $form_state);

    $values = $form_state->getValue($form['#parents']);

    if (empty($values['target_plugin_configuration']['api_information']['account'])) {
      $form_state->setError($form, $this->t('A value for Account must be set.'));
    }

    if (empty($values['target_plugin_configuration']['api_information']['license'])) {
      $form_state->setError($form, $this->t('A value for License must be set.'));
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

    parent::submitConfigurationForm($form, $form_state);
  }

  /**
   * Executes the plugin.
   */
  public function execute() {
    // TODO: Implement execute() method.
  }
}
