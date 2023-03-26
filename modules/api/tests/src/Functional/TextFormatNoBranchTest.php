<?php

namespace Drupal\Tests\api\Functional;

use Drupal\Core\Url;

/**
 * Tests the API text format in comments without a regular branch.
 */
class TextFormatNoBranchTest extends WebPagesBase {

  /**
   * Node for commenting.
   *
   * @var object
   */
  public $node;

  /**
   * {@inheritdoc}
   */
  protected function setUp() : void {
    $this->baseSetUp();
    $this->allowAnonymousUsersToSeeApiPages();

    // Set up a new super-user.
    $this->super_user = $this->drupalAdminLogin();

    // Remove the PHP branch.
    $this->removePhpBranch();

    // Create a "php" branch with the sample PHP function list, from the admin
    // interface.
    $this->createPhpBranchUi();

    // Temporarily set up a regular files branch and parse it.
    $temp_branch_info = $this->setUpBranchUi();
    $this->clearCache();
    $this->cronRun();
    $this->processApiParseQueue();

    // Create an "api" branch that points to the temporary branch, and parse
    // it. For this, anonymous users must have permission to view API pages.
    $url = Url::fromUri('internal:/api/' . $temp_branch_info['project'] . '/full_list', ['absolute' => TRUE])->toString();
    $this->createApiBranchUi(['function_list' => $url, 'items_per_page' => 100]);

    $this->clearCache();
    $this->cronRun();
    $this->processApiParseQueue();
    $this->checkAndClearLog();

    // Remove the files branch.
    $this->drupalGet('admin/config/development/api/branch/1/delete');
    $this->submitForm([], 'Delete');
    $this->clearCache();
    $this->cronRun();

    // Verify things are set up right.
    $this->verifyCounts([
      'api_branch' => 0,
      'api_branch_docblock' => 0,
      'api_php_branch' => 1,
      'api_external_branch' => 1,
      'api_external_branch_documentation' => 68,
      'api_php_branch_documentation' => 2,
    ], 0, 'Branches set up');

    $this->setUpFilterComments();
  }

  /**
   * Tests the API text format in comments.
   */
  public function testApiTextFormat() {
    $node = $this->setUpNodeAndComments();
    $comments_user = $this->drupalCreateUser(TestBase::COMMENTS_PERMISSIONS);
    $this->drupalLogin($comments_user);

    // Go to the node page and make a comment. Verify that links are
    // created and go to the right places.
    $this->drupalGet('node/' . $node->id());
    $this->submitForm([
      'subject' => 'test',
      'comment_body' => 'This is a test of links to things like foo_function() and sample_function() and SubSample and SubSample::bar().',
    ], 'Save');

    $this->assertLinkUrlSubstring('foo_function', 'example.com/function/foo_function', 'Link to fake PHP function is created', 'Link to fake PHP function goes to right place');
    $this->assertLinkUrlSubstring('sample_function', 'sample.php/function/sample_function', 'Link to fake API reference function is created', 'Link to API reference function goes to right place');
    $this->assertLinkUrlSubstring('SubSample', 'classes.php/class/SubSample', 'Link to fake API reference class is created', 'Link to API reference class goes to right place');
  }

}
