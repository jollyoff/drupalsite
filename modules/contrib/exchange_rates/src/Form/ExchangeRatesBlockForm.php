<?php

namespace Drupal\exchange_rates\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\State\StateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form that displays Exchange Rates block.
 */
class ExchangeRatesBlockForm extends FormBase {
  /**
   * Stores the state storage service.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * Constructor.
   *
   * @param \Drupal\Core\State\StateInterface $state
   *   The state key value store.
   */
  public function __construct(StateInterface $state) {
    $this->state = $state;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('state')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'exchange_rates_block_form';
  }

  /**
   * Build form for display block.
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('exchange_rates.settings');
    $base_currency = $config->get('base_currency');
    $conversion_currencies = $config->get('conversion_currency');

    $form['#tree'] = TRUE;

    if (!empty($base_currency)) {
      $form['base_currency'] = [
        '#type' => 'container',
        '#attributes' => [
          'class' => [
            'container-inline',
          ],
        ],
        strtolower($base_currency) => [
          '#type' => 'number',
          '#title' => $base_currency,
          '#min' => 0,
          '#step' => '1.0000',
          '#default_value' => '1.0000',
          '#size' => 10,
          '#attributes' => [
            'class' => ['exchange-rates-base-currency'],
            'oninput' => 'for(var c=document.getElementsByClassName("exchange-rates-conversion-currency"),i=0;i<c.length;i++)c[i].value=(this.value*c[i].getAttribute("data-rate")).toFixed(4);',
          ],
          '#prefix' => '<div class="currency-flag currency-flag-' . strtolower($base_currency) . '"></div>',
        ],
      ];

      if (!empty($conversion_currencies)) {
        for ($i = 0; $i < count($conversion_currencies); $i++) {
          $form['conversion_currency'][$i] = [
            '#type' => 'container',
            '#attributes' => [
              'class' => [
                'container-inline',
              ],
            ],
            strtolower($conversion_currencies[$i]) => [
              '#type' => 'textfield',
              '#title' => strtoupper($conversion_currencies[$i]),
              '#default_value' => round($this->state->get('exchange_rates.' . $base_currency . '-' . $conversion_currencies[$i]), 4),
              '#size' => 10,
              '#attributes' => [
                'class' => ['exchange-rates-conversion-currency'],
                'data-rate' => $this->state->get('exchange_rates.' . $base_currency . '-' . $conversion_currencies[$i]),
                'disabled' => 'disabled',
              ],
              '#prefix' => '<div class="currency-flag currency-flag-' . strtolower($conversion_currencies[$i]) . '"></div>',
            ],
          ];
        }
      }
    }

    $form['#attached']['library'][] = 'exchange_rates/exchange-rates';

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
  }

}
