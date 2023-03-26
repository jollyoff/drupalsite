<?php

namespace Drupal\Tests\api\Functional;

use Drupal\comment\Plugin\Field\FieldType\CommentItemInterface;

/**
 * Tests the API text format in comments.
 */
class TextFormatTest extends WebPagesBase {

  /**
   * {@inheritdoc}
   */
  protected function setUp() : void {
    $this->baseSetUp();

    // Set up a new super-user.
    $this->super_user = $this->drupalAdminLogin();

    // Set up comment settings so comments are allowed.
    $this->drupalGet('admin/config/development/api/comments');
    $this->submitForm([
      'status' => CommentItemInterface::OPEN,
    ], 'Save configuration');

    // Remove the PHP branch.
    $this->removePhpBranch();

    // Create a "php" branch with the sample PHP function list, from the admin
    // interface.
    $this->createPhpBranchUi();

    // Set up a branch for the Drupal core files.
    $this->setUpBranchUi(NULL, TRUE, [
      'project' => 'drupal',
      'project_title' => 'Drupal',
      'project_type' => 'core',
      'branch_name' => '7.x',
      'title' => 'Drupal 7.x',
      'core_compatibility' => '7.x',
      'directory' => $this->apiModulePath . '/tests/files/sample_drupal',
      'excluded' => '',
    ]);

    // Set up a files branch for the namespaces files.
    $this->branchInfo = $this->setUpBranchUi(NULL, TRUE, [
      'directory' => $this->apiModulePath . '/tests/files/sample_namespace',
      'excluded' => '',
    ]);

    $this->setUpFilterComments();

    // Parse the code.
    $this->clearCache();
    $this->cronRun();
    $this->processApiParseQueue();
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
      'comment_body' => 'This is a test of links to things like MyClassB::bMethod(), another_function(), InterfaceD::dMethod(), foo_function(), and drupal_alter().',
    ], 'Save');

    $this->assertLinkUrlSubstring('another_function', 'no_namespace.php/function/another_function', 'Link to function is created', 'Link to function goes to right place');
    $this->assertLinkUrlSubstring('drupal_alter', 'drupal.php/function/drupal_alter', 'Link to core function is created', 'Link to core function goes to right place');
  }

}
