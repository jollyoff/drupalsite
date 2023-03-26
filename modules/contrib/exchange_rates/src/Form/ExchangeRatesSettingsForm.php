<?php

namespace Drupal\exchange_rates\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Config form to set base and conversion currencies.
 */
class ExchangeRatesSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'exchange_rates.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'exchange_rates_settings_form';
  }

  /**
   * Builds Exchange Rates settings form.
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('exchange_rates.settings');
    $total_conversion_currency = $form_state->get('total_conversion_currency');

    if ($total_conversion_currency === NULL) {
      $total_conversion_currency = count($config->get('conversion_currency'));
      $form_state->set('total_conversion_currency', $total_conversion_currency);
    }

    $form['#tree'] = TRUE;
    $form['fx_fieldset'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Exchange Rates Settings'),
      '#prefix' => '<div id="exchange-rates-fieldset-wrapper">',
      '#suffix' => '</div>',
    ];

    $form['fx_fieldset']['base_currency'] = [
      '#type' => 'select',
      '#title' => $this->t('Base Currency:'),
      '#options' => $config->get('currency'),
      '#default_value' => $config->get('base_currency'),
    ];

    $form['fx_fieldset']['conversion_currency'][0] = [
      '#type' => 'select',
      '#title' => $this->t('Conversion Currencies:'),
      '#options' => $config->get('currency'),
      '#default_value' => $config->get('conversion_currency')[0],
    ];

    if ($total_conversion_currency > 1) {
      for ($i = 1; $i < $total_conversion_currency; $i++) {
        $form['fx_fieldset']['conversion_currency'][$i] = [
          '#type' => 'select',
          '#options' => $config->get('currency'),
          '#default_value' => $config->get('conversion_currency')[$i],
        ];
      }
    }

    $form['fx_fieldset']['actions'] = [
      '#type' => 'actions',
    ];

    if ($total_conversion_currency < count($config->get('currency'))) {
      $form['fx_fieldset']['actions']['add'] = [
        '#type' => 'submit',
        '#value' => $this->t('Add Currency'),
        '#submit' => ['::addConversionCurrency'],
        '#ajax' => [
          'callback' => '::conversionCurrencyCallback',
          'wrapper' => 'exchange-rates-fieldset-wrapper',
        ],
      ];
    }

    if ($total_conversion_currency > 1) {
      $form['fx_fieldset']['actions']['remove'] = [
        '#type' => 'submit',
        '#value' => $this->t('Remove Currency'),
        '#submit' => ['::removeConversionCurrency'],
        '#ajax' => [
          'callback' => '::conversionCurrencyCallback',
          'wrapper' => 'exchange-rates-fieldset-wrapper',
        ],
      ];
    }

    return parent::buildForm($form, $form_state);
  }

  /**
   * Ajax callback.
   */
  public function conversionCurrencyCallback(array &$form, FormStateInterface $form_state) {
    return $form['fx_fieldset'];
  }

  /**
   * Add new conversion currency to list.
   */
  public function addConversionCurrency(array &$form, FormStateInterface $form_state) {
    $form_state->set('total_conversion_currency', $form_state->get('total_conversion_currency') + 1);
    $form_state->setRebuild();
  }

  /**
   * Remove last conversion currency from list.
   */
  public function removeConversionCurrency(array &$form, FormStateInterface $form_state) {
    $total_conversion_currency = $form_state->get('total_conversion_currency');

    if ($total_conversion_currency > 1) {
      $form_state->set('total_conversion_currency', $total_conversion_currency - 1);
    }

    $form_state->setRebuild();
  }

  /**
   * Saves base currency and conversion currencies selected on form.
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->configFactory()->getEditable('exchange_rates.settings');
    $fx_fieldset = $form_state->getValue('fx_fieldset');

    $config->set('base_currency', $fx_fieldset['base_currency']);
    $config->set('conversion_currency', $fx_fieldset['conversion_currency']);
    $config->save();

    // Confirmation on form submission.
    $this->messenger()->addStatus($this->t('The Exchange Rates settings have been saved.'));
  }

}
