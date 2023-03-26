<?php

namespace Drupal\Tests\api\Functional;

use Drupal\api\Entity\Branch;
use Drupal\api\Entity\ExternalBranch;
use Drupal\api\Entity\Project;
use Drupal\Core\Url;

/**
 * Provides a base class for testing web pages (user/admin) for the API module.
 */
abstract class WebPagesBase extends TestBase {

  /**
   * Array of branch information for the sample functions branch.
   *
   * @var array
   */
  protected $branchInfo;

  /**
   * Array of branch information for the sample PHP functions branch.
   *
   * @var array
   */
  protected $phpBranchInfo;

  /**
   * Overrides TestBase::setUp().
   *
   * Sets up the sample branch, using the administrative interface, removes the
   * default PHP branch, adds our fake PHP branch, and updates everything.
   */
  protected function setUp() : void {
    $this->baseSetUp();

    // Create a "file" branch with the sample code, from the admin interface.
    $this->branchInfo = $this->setUpBranchUi();

    // Remove the default PHP branch, which most tests do not need.
    $this->removePhpBranch();

    // Create a "php" branch with the sample PHP function list, from the admin
    // interface.
    $this->phpBranchInfo = $this->createPhpBranchUi();

    // Parse the code.
    $this->clearCache();
    $this->cronRun();
    $this->processApiParseQueue();
  }

  /**
   * Sets up a project and branch using the user interface.
   *
   * @param string $prefix
   *   Directory prefix to prepend on the data directories.
   * @param bool $default
   *   TRUE to set this as the default branch; FALSE to not set it as default.
   * @param array $info
   *   Array of information to override the defaults (see function code to see
   *   what they are). Note that $prefix is applied after this information is
   *   read, and that only one directory and one excluded are supported in this
   *   function.
   *
   * @return array
   *   Array of information (defaults with overrides) used to create the
   *   branch and project.
   */
  protected function setUpBranchUi($prefix = NULL, $default = TRUE, array $info = []) {
    $base_path = $prefix ?? $this->apiModulePath;

    // Set up defaults.
    $info += [
      'project' => 'test',
      'project_title' => 'Project 6',
      'project_type' => 'module',
      'branch_name' => '6',
      'title' => 'Testing 6',
      'core_compatibility' => '7.x',
      'update_frequency' => 1,
      'directory' => $base_path . '/tests/files/sample',
      'excluded' => $base_path . '/tests/files/sample/to_exclude',
      'exclude_drupalism_regexp' => '',
      'regexps' => '',
    ];
    $info['preferred'] = $default;

    // Create the project.
    $project_info = [
      'slug' => $info['project'],
      'type' => $info['project_type'],
      'title' => $info['project_title'],
    ];
    $project = Project::getBySlug($info['project']);
    if ($project) {
      $this->drupalGet('admin/config/development/api/project/' . $project->id() . '/edit');
    }
    else {
      $this->drupalGet('admin/config/development/api/project/add');
    }
    $this->submitForm(
      $project_info,
      'Save'
    );
    $project = Project::getBySlug($info['project']);
    $this->assertNotEmpty($project, 'Project was not created.');

    // Create the branch.
    $branch_info = [
      'project' => $project->id(),
      'slug' => $info['branch_name'],
      'title' => $info['title'],
      'preferred' => $info['preferred'],
      'core_compatibility' => $info['core_compatibility'],
      'update_frequency' => $info['update_frequency'],
      'directories' => $info['directory'],
      'exclude_files_regexp' => $info['regexps'],
      'exclude_drupalism_regexp' => $info['exclude_drupalism_regexp'],
    ];
    if ($info['excluded'] != 'none') {
      $branch_info['excluded_directories'] = $info['excluded'];
    }
    $this->drupalGet('admin/config/development/api/branch/add');
    $this->submitForm(
      $branch_info,
      'Save'
    );
    $branch = Branch::getBySlug($info['branch_name'], $project);
    $this->assertNotEmpty($branch, 'Branch was not created.');

    if ($default) {
      // Make this the default project/core compat. This has to be done after
      // the setup for the branch, because the API module will override the
      // setting if the branch doesn't exist yet, in an attempt to make sure
      // a branch exists.
      $this->drupalGet('admin/config/development/api');
      $this->submitForm([
        'default_branch_project' => $branch->getSlug() . '|' . $branch->id(),
      ], 'Save configuration');
      // Get the settings fresh from Drupal, $this->config... will get a cached
      // version and we might have false negatives.
      $config = \Drupal::config('api.settings');

      $this->assertEquals(
        $branch->getSlug() . '|' . $branch->id(),
        $config->get('default_branch_project') ?? '',
        'Variable for default branch is set correctly'
      );
    }

    return $info;
  }

  /**
   * Sets up a PHP external branch using the sample code, in the admin UI.
   *
   * @return array
   *   Information array used to create the branch.
   */
  protected function createPhpBranchUi() {
    $info = [
      'title' => 'php2',
      'function_list' => Url::fromRoute('<front>', [], ['absolute' => TRUE])->toString() . '/modules/contrib/api/tests/files/php_sample/funcsummary.json',
      'function_url_pattern' => 'http://example.com/function/!function',
      'update_frequency' => 1,
    ];

    $this->drupalGet('admin/config/development/api/php_branch/add');
    $this->submitForm(
      $info,
      'Save'
    );

    return $info;
  }

  /**
   * Sets up an API external branch using the sample code, in the admin UI.
   *
   * @param array $info
   *   Array of information to override the defaults (see function code to see
   *   what they are).
   *
   * @return array
   *   Information array used to create the branch.
   */
  protected function createApiBranchUi(array $info = []) {
    $info += [
      'title' => 'sample_api_branch',
      'function_list' => Url::fromRoute('<front>', [], ['absolute' => TRUE])->toString() . '/modules/contrib/api/tests/files/php_sample/sample_drupal_listing.json',
      'search_url' => Url::fromRoute('<front>', [], ['absolute' => TRUE])->toString() . '/api/test_api_project/test_api_branch/search/',
      'core_compatibility' => '7.x',
      'type' => 'core',
      'items_per_page' => 2000,
      'timeout' => 30,
      'update_frequency' => 1,
    ];

    $this->drupalGet('admin/config/development/api/external_branch/add');
    $this->submitForm(
      $info,
      'Save'
    );

    $external_branches = ExternalBranch::loadMultiple();
    $this->assertGreaterThanOrEqual(1, count($external_branches));

    return $info;
  }

  /**
   * Asserts that a link exists, with the given URL.
   *
   * @param string $label
   *   Label of the link to find.
   * @param string $url
   *   URL to match.
   * @param string $message_link
   *   Message to use in link exist assertion.
   * @param string $message_url
   *   Message to use for URL matching assertion.
   */
  protected function assertLinkUrl($label, $url, $message_link, $message_url) {
    // Code follows DrupalWebTestCase::clickLink() and assertLink().
    $links = $this->xpath('//a[text()="' . $label . '"]');
    $this->assertTrue(isset($links[0]), $message_link);
    if (isset($links[0])) {
      $url_target = $this->getAbsoluteUrl($links[0]->getAttribute('href'));
      $this->assertEquals($url_target, $url, $message_url);
    }
  }

  /**
   * Asserts that a link exists, with substring matching on the URL.
   *
   * @param string $label
   *   Label of the link to find.
   * @param string $url
   *   URL to match. The test passes if $url is a substring of the link's URL.
   * @param string $message_link
   *   Message to use in link exist assertion.
   * @param string $message_url
   *   Message to use for URL matching assertion.
   * @param int $index
   *   (optional) Index of the link on the page, like assertLink(). Default: 0.
   */
  protected function assertLinkUrlSubstring($label, $url, $message_link, $message_url, $index = 0) {
    // Code follows DrupalWebTestCase::clickLink() and assertLink().
    $links = $this->xpath('//a[text()="' . $label . '"]');
    $this->assertTrue(isset($links[$index]), $message_link);
    if (isset($links[$index])) {
      $url_target = $this->getAbsoluteUrl($links[$index]->getAttribute('href'));
      $this->assertTrue(strpos($url_target, $url) !== FALSE, $message_url);
    }
  }

  /**
   * Asserts that the current page's title contains a string.
   *
   * @param string $string
   *   String to match in the title.
   * @param string $message
   *   Message to print.
   */
  protected function assertTitleContains($string, $message) {
    $title = current($this->xpath('//title'));
    $this->assertTrue(strpos($title->getText(), $string) !== FALSE, $message);
  }

  /**
   * Asserts that the current page's URL contains a string.
   *
   * @param string $string
   *   String to match in the URL.
   * @param string $message
   *   Message to print.
   */
  protected function assertUrlContains($string, $message) {
    $message .= ' // Full URL: ' . $this->getUrl();
    $this->assertTrue(strpos($this->getUrl(), $string) !== FALSE, $message);
  }

  /**
   * Asserts that the count of links with the given label is correct.
   *
   * @param string $label
   *   Label to search for.
   * @param int $count
   *   Count to assert.
   * @param string $message
   *   Message to display.
   */
  protected function assertLinkCount($label, $count, $message) {
    $links = $this->xpath('//a[normalize-space(text())=:label]', [':label' => $label]);
    $this->assertEquals(count($links), $count, $message);
  }

}
