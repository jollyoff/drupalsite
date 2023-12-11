<?php

namespace Drupal\Tests\exchange_rates\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;

/**
 * Tests Javascript and Ajax functionality on exchange_rates module.
 *
 * @group exchange_rates
 */
class ExchangeRatesTest extends WebDriverTestBase {
  /**
   * {@inheritdoc}
   */
  public static $modules = ['block', 'exchange_rates', 'user'];

  /**
   * A test user with administrative privileges.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $adminUser;

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();

    $this->adminUser = $this->drupalCreateUser([
      'access administration pages',
      'administer blocks',
      'administer exchange_rates',
    ]);

    $this->drupalLogin($this->adminUser);
  }

  /**
   * Tests settings form.
   */
  public function testConfigurationForm() {
    $assert_session = $this->assertSession();
    $page = $this->getSession()->getPage();
    $this->drupalGet('admin/config/regional/exchange-rates');

    // Selects all conversion currencies in alphabetical order and verifies that
    // the Add Currency button is able to add a new dropdown field via Ajax.
    $currency = $this->config('exchange_rates.settings')->get('currency');
    $i = 0;
    foreach ($currency as $key => $value) {
      if ($i > 5) {
        $page->findButton('Add Currency')->click();
        $conversion_currency_field = $assert_session->waitForField('fx_fieldset[conversion_currency][' . $i . ']');
      }
      else {
        $conversion_currency_field = $page->findField('fx_fieldset[conversion_currency][' . $i . ']');
      }

      $this->assertNotEmpty($conversion_currency_field);
      $conversion_currency_field->selectOption($key);
      ++$i;
    }

    // Verifies the Add Currency button is not displayed after all conversion
    // currencies are added.
    $add_currency_button = $page->findButton('Add Currency');
    $this->assertEmpty($add_currency_button);

    // Verifies that the configuration save works as expected.
    $this->submitForm([], 'Save configuration', 'exchange-rates-settings-form');
    $assert_session->pageTextContains('The Exchange Rates settings have been saved.');

    // Verifies that all the conversion currencies selected are saved.
    $conversion_currencies = $this->config('exchange_rates.settings')->get('conversion_currency');
    for ($i = 0; $i < count($conversion_currencies); $i++) {
      $conversion_currency_field = $page->findField('fx_fieldset[conversion_currency][' . $i . ']');
      $this->assertEqual($conversion_currencies[$i], $conversion_currency_field->getValue());
    }

    // Removes all conversion currencies except the first and verifies that the
    // Remove Currency button is able to remove a dropdown field via Ajax.
    for ($i = count($conversion_currencies) - 1; $i > 0; $i--) {
      $page->findButton('Remove Currency')->click();
      $assert_session->assertWaitOnAjaxRequest();
      $conversion_currency_field = $page->findField('fx_fieldset[conversion_currency][' . $i . ']');
      $this->assertEmpty($conversion_currency_field);
    }

    // Verifies the Remove Currency button is not displayed after all but 1
    // remaining conversion currency are removed.
    $remove_currency_button = $page->findButton('Remove Currency');
    $this->assertEmpty($remove_currency_button);
  }

  /**
   * Tests Exchange Rates block.
   */
  public function testBlock() {
    $assert_session = $this->assertSession();
    $page = $this->getSession()->getPage();

    // Places block in content region.
    $this->drupalPlaceBlock('exchange_rates_block', ['region' => 'content']);

    // Verifies block is placed on content region of front page.
    $this->drupalGet('<front>');
    $assert_session->elementExists('css', '.exchange-rates-block-form');

    $config = $this->config('exchange_rates.settings');
    $base_currency = $config->get('base_currency');
    $conversion_currencies = $config->get('conversion_currency');

    // Verifies base currency field.
    $base_currency_field = $page->findField('base_currency[' . strtolower($base_currency) . ']');
    $this->assertNotEmpty($base_currency_field);

    // Tests a range of random base currency values from $0.01-$0.10, $0.10-$1,
    // $1-$10, $10-$100, $100-$1000, $1000-$10000, $10000-$100000 and
    // $100000-$1000000.
    for ($i = 0; $i < 8; $i++) {
      $base_value = mt_rand(pow(10, $i), pow(10, $i + 1)) / 100;
      $base_currency_field->setValue($base_value);

      for ($j = 0; $j < count($conversion_currencies); $j++) {
        $assert_session->waitForField('conversion_currency[' . $j . '][' . strtolower($conversion_currencies[$j]) . ']');
        // Checks that conversion currency is correctly calculated.
        $assert_session->fieldValueEquals('conversion_currency[' . $j . '][' . strtolower($conversion_currencies[$j]) . ']', number_format(round(\Drupal::state()->get('exchange_rates.' . $base_currency . '-' . $conversion_currencies[$j]) * $base_value, 4), 4, '.', ''));
      }
    }
  }

}
