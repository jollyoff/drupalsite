<?php

namespace Drupal\Tests\api\Functional;

/**
 * Tests search integration for the API module.
 */
class SearchIntegrationTest extends WebPagesBase {

  /**
   * User with permission to search but not see API stuff.
   *
   * @var object
   */
  protected $restrictedUser;

  /**
   * {@inheritdoc}
   */
  protected function setUp() : void {
    $this->baseSetUp(['search', 'api_search_core']);

    // Set up a new super-user.
    $this->super_user = $this->drupalAdminLogin(['search content']);

    // Set up a restricted user.
    $this->restrictedUser = $this->drupalCreateUser([
      'access content',
      'search content',
    ]);

    // Set up a regular files branch.
    $this->branchInfo = $this->setUpBranchUi();
    $this->removePhpBranch();

    $this->cronRun();
    $this->processApiParseQueue();
  }

  /**
   * Tests that API documentation can be searched.
   */
  public function testSearch() {
    $terms = [
      // Direct name of the function.
      'sample_function',
      // Some text in the documentation body.
      'sample-related',
      // Text in parameter.
      'generic parameter',
      // Text in the return value.
      'about the return value',
    ];

    $this->clearCache();
    foreach ($terms as $term) {
      $this->drupalGet('search/api');
      $this->submitForm(['keys' => $term], 'Search');
      $this->assertLinkUrlSubstring('sample_function', 'sample.php/function/sample_function', 'sample_function page title is in search results for ' . $term, 'sample_function link is in search results for ' . $term);
    }

    // Now log in as the restricted user and verify that the searches do
    // not give the API results.
    $this->drupalLogin($this->restrictedUser);
    foreach ($terms as $term) {
      $this->drupalGet('search/api');
      $this->assertSession()->responseContains('You are not authorized to access this page');
    }
  }

}
