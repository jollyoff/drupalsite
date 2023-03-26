<?php

namespace Drupal\exchange_rates\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\State\StateInterface;
use GuzzleHttp\ClientInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a 'ExchangeRatesBlock' block.
 *
 * @Block(
 *  id = "exchange_rates_block",
 *  admin_label = @Translation("Exchange Rates"),
 * )
 */
class ExchangeRatesBlock extends BlockBase implements ContainerFactoryPluginInterface {
  /**
   * Stores the config factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Stores the form builder service.
   *
   * @var \Drupal\Core\Form\FormBuilderInterface
   */
  protected $formBuilder;

  /**
   * A http client.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected $httpClient;

  /**
   * Stores the state storage service.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * Constructor.
   *
   * @param array $configuration
   *   Configuration array.
   * @param string $plugin_id
   *   Plugin ID.
   * @param mixed $plugin_definition
   *   Plugin definition.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   Config factory.
   * @param \Drupal\Core\Form\FormBuilderInterface $form_builder
   *   Form builder.
   * @param \GuzzleHttp\ClientInterface $http_client
   *   A http client.
   * @param \Drupal\Core\State\StateInterface $state
   *   State key value store.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, ConfigFactoryInterface $config_factory, FormBuilderInterface $form_builder, ClientInterface $http_client, StateInterface $state) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->configFactory = $config_factory;
    $this->formBuilder = $form_builder;
    $this->httpClient = $http_client;
    $this->state = $state;
  }

  /**
   * Create method.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   Container.
   * @param array $configuration
   *   Configuration array.
   * @param string $plugin_id
   *   Plugin ID.
   * @param mixed $plugin_definition
   *   Plugin definition.
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('config.factory'),
      $container->get('form_builder'),
      $container->get('http_client'),
      $container->get('state')
    );
  }

  /**
   * Build block.
   */
  public function build() {
    $config = $this->configFactory->get('exchange_rates.settings');
    $base_currency = $config->get('base_currency');
    $conversion_currencies = $config->get('conversion_currency');

    // exchangeratesapi.io API call to fetch exchange rates for base currency.
    try {
      $response = json_decode($this->httpClient->get('https://api.exchangeratesapi.io/latest?base=' . $base_currency)->getBody());
    }
    catch (RequestException $e) {
      watchdog_exception('exchange_rates', $e->getMessage());
    }

    $rates = (!empty($response) && !empty($response->rates)) ? $response->rates : [];

    // Store conversion currency rates in state.
    foreach ($rates as $conversion_currency => $rate) {
      if (in_array($conversion_currency, $conversion_currencies)) {
        $this->state->set('exchange_rates.' . $base_currency . '-' . $conversion_currency, $rate);
      }
    }

    return $this->formBuilder->getForm('Drupal\exchange_rates\Form\ExchangeRatesBlockForm');
  }

}
