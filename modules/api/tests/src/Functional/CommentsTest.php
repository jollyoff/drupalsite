<?php

namespace Drupal\Tests\api\Functional;

use Drupal\comment\Plugin\Field\FieldType\CommentItemInterface;

/**
 * Tests comment functionality.
 */
class CommentsTest extends WebPagesBase {

  /**
   * {@inheritdoc}
   */
  protected function setUp() : void {
    $this->baseSetUp();

    // Set up a new super-user.
    $this->super_user = $this->drupalAdminLogin();

    // Set up comment settings initially as hidden.
    $this->drupalGet('admin/config/development/api/comments');
    $this->submitForm(['status' => CommentItemInterface::HIDDEN], 'Save configuration');
    $this->clearCache();

    // Set up a regular files branch.
    $this->branchInfo = $this->setUpBranchUi();

    // We don't need the PHP branch for this test, so for speed, remove it.
    $this->removePhpBranch();

    // Parse the code.
    $this->clearCache();
    $this->cronRun();
    $this->processApiParseQueue();
  }

  /**
   * Tests that comments and comment settings are working.
   */
  public function testComments() {
    // Verify that with comments turned off, the comment form does not appear.
    $this->clearCache();
    $this->drupalGet('api/' . $this->branchInfo['project'] . '/classes.php/class/Sample');
    $this->assertSession()->pageTextNotContains('Add new comment');
    $this->assertSession()->pageTextNotContains('Comment');
    $this->assertSession()->pageTextNotContains('Subject');
    $this->assertSession()->pageTextNotContains('Save');

    // Turn comments on.
    $this->drupalGet('admin/config/development/api/comments');
    $this->submitForm(['status' => CommentItemInterface::OPEN], 'Save and apply to all');
    $this->assertSession()->pageTextContains('The configuration options have been saved');
    $this->clearCache();
    $this->drupalGet('api/' . $this->branchInfo['project'] . '/classes.php/class/Sample');
    $this->assertSession()->pageTextContains('Add new comment');
    $this->assertSession()->pageTextContains('Comment');
    $this->assertSession()->pageTextContains('Subject');
    $this->submitForm([
      'subject' => 'Subject 1',
      'field_api_comment_body' => 'Comment 1 body',
    ], 'Save');
    $this->assertSession()->pageTextContains('Your comment has been posted');
    // Comment appear in its own URL.
    $this->drupalGet('comment/1');
    $this->assertSession()->linkExists('Subject 1', 0, 'Comment subject appears');
    $this->assertSession()->pageTextContains('Comment 1 body');
    $this->assertSession()->linkExists('Reply', 0, 'Reply link appears');
    // Comment appear in the right page too.
    $this->clearCache();
    $this->drupalGet('api/' . $this->branchInfo['project'] . '/classes.php/class/Sample');
    $this->assertSession()->linkExists('Subject 1', 0, 'Comment subject appears');
    $this->assertSession()->pageTextContains('Comment 1 body');
    $this->assertSession()->linkExists('Reply', 0, 'Reply link appears');

    // Reparse the branch, and verify the comment is still there.
    $this->drupalGet('admin/config/development/api/branch');
    $this->clickLink('Re-Parse');
    $this->assertSession()->pageTextContains('was set for re-parsing');
    $this->cronRun();
    $this->processApiParseQueue();
    $this->drupalGet('api/' . $this->branchInfo['project'] . '/classes.php/class/Sample');
    $this->assertSession()->linkExists('Subject 1', 0, 'Comment subject appears');
    $this->assertSession()->pageTextContains('Comment 1 body');
    $this->assertSession()->linkExists('Reply', 0, 'Reply link appears');

    // Set to closed and verify the comment form disapppears.
    $this->drupalGet('admin/config/development/api/comments');
    $this->submitForm(['status' => CommentItemInterface::CLOSED], 'Save and apply to all');
    $this->assertSession()->pageTextContains('The configuration options have been saved.');
    $this->clearCache();
    $this->drupalGet('api/' . $this->branchInfo['project'] . '/classes.php/class/Sample');
    $this->assertSession()->linkExists('Subject 1', 0, 'Comment subject appears');
    $this->assertSession()->pageTextContains('Comment 1 body');
    $this->assertSession()->linkNotExists('Reply', 'Reply link disappears');
    $this->assertSession()->pageTextNotContains('Add new comment');

    // Set to hidden and verify comment disappears.
    $this->drupalGet('admin/config/development/api/comments');
    $this->submitForm(['status' => CommentItemInterface::HIDDEN], 'Save and apply to all');
    $this->assertSession()->pageTextContains('The configuration options have been saved.');
    $this->clearCache();
    $this->drupalGet('api/' . $this->branchInfo['project'] . '/classes.php/class/Sample');
    $this->assertSession()->linkNotExists('Subject 1', 'Comment subject disappears');
    $this->assertSession()->pageTextNotContains('Comment 1 body');
    $this->assertSession()->linkNotExists('Reply', 'Reply link disappears');
    $this->assertSession()->pageTextNotContains('Add new comment');
    $this->assertSession()->pageTextNotContains('Comment');
    $this->assertSession()->pageTextNotContains('Subject');
    $this->assertSession()->pageTextNotContains('Save');
  }

}
