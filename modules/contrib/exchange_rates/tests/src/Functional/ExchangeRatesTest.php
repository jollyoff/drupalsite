<?php

namespace Drupal\Tests\exchange_rates\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests exchange_rates module functionality.
 *
 * @group exchange_rates
 */
class ExchangeRatesTest extends BrowserTestBase {
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
  }

  /**
   * Tests settings form.
   */
  public function testConfigurationForm() {
    $assert_session = $this->assertSession();

    // Verifies that the page is accessible only to users with the adequate
    // permissions.
    $this->drupalGet('admin/config/regional/exchange-rates');
    $assert_session->statusCodeEquals(403);

    // Verifies that the config page is accessible for users with the adequate
    // permissions and base/conversion currency select fields are present.
    $this->drupalLogin($this->adminUser);
    $this->drupalGet('admin/config/regional/exchange-rates');
    $assert_session->statusCodeEquals(200);
    $assert_session->pageTextContains('Exchange Rates Settings');
    $assert_session->selectExists('fx_fieldset[base_currency]');
    $assert_session->selectExists('fx_fieldset[conversion_currency][0]');

    // Verifies all currencies are listed in the form field options.
    $config = $this->config('exchange_rates.settings');
    foreach ($config->get('currency') as $currency => $value) {
      $assert_session->optionExists('fx_fieldset[base_currency]', $currency);
    }

    // Verifies that the configuration save works as expected.
    $this->submitForm([], 'Save configuration', 'exchange-rates-settings-form');
    $assert_session->pageTextContains('The Exchange Rates settings have been saved.');
  }

  /**
   * Tests Exchange Rates block.
   */
  public function testBlock() {
    $assert_session = $this->assertSession();
    $this->drupalLogin($this->adminUser);

    // Places block in content region.
    $this->drupalPlaceBlock('exchange_rates_block', ['region' => 'content']);

    // Verifies block is placed on content region of front page.
    $this->drupalGet('<front>');
    $assert_session->elementExists('css', '.exchange-rates-block-form');

    // Verifies base and conversion currencies selected in settings form are
    // correctly listed on block with corresponding currency exchange rates.
    $config = $this->config('exchange_rates.settings');
    $base_currency = $config->get('base_currency');
    $conversion_currencies = $config->get('conversion_currency');
    $assert_session->fieldExists('base_currency[' . strtolower($base_currency) . ']');
    $assert_session->fieldValueEquals('base_currency[' . strtolower($base_currency) . ']', '1.0000');

    for ($i = 0; $i < count($conversion_currencies); $i++) {
      $assert_session->fieldExists('conversion_currency[' . $i . '][' . strtolower($conversion_currencies[$i]) . ']');
      $assert_session->fieldValueEquals('conversion_currency[' . $i . '][' . strtolower($conversion_currencies[$i]) . ']', round(\Drupal::state()->get('exchange_rates.' . $base_currency . '-' . $conversion_currencies[$i]), 4));
    }
  }

}
