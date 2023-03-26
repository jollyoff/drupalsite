<?php

namespace Drupal\Tests\api\Functional;

use Drupal\comment\Plugin\Field\FieldType\CommentItemInterface;

/**
 * Tests module enable and cron functionality.
 *
 * @group api
 */
class EnableTest extends WebPagesBase {

  /**
   * Log message when the PHP branch is updated.
   */
  const PHP_MESSAGE = 'Updated new PHP branch PHP functions';

  /**
   * Log message when cron is complete.
   */
  const CRON_MESSAGE = 'Cron run completed';

  /**
   * {@inheritdoc}
   */
  protected function setUp() : void {
    $this->baseSetUp();
    $this->cronRun();
    $this->checkAndClearLog([self::CRON_MESSAGE]);
  }

  /**
   * Tests module enabling and cron for the API module.
   */
  public function testEnable() {
    $this->assertTrue($this->moduleHandler()->moduleExists('api'), 'API module is enabled');
    $this->uninstallApiModule();
    $this->assertFalse($this->moduleHandler()->moduleExists('api'), 'API module is not enabled');
    $modules = \Drupal::service('extension.list.module')->reset()->getList();
    $this->assertTrue(isset($modules['api']), 'System knows about API module');

    // Enable the API module. Run cron, and verify that it does not do the API
    // cron function (yet).
    $installed = $this->moduleInstaller()->install(['api']);
    $this->drupalAdminLogin();
    $this->assertTrue($installed, 'Module api was installed');
    $this->assertTrue($this->moduleHandler()->moduleExists('api'), 'API module is enabled');
    $this->cronRun();
    $this->checkAndClearLog([self::CRON_MESSAGE], [self::PHP_MESSAGE]);

    // Visit the API module admin page, to turn on API cron. Run cron and
    // verify that it does the API cron function this time.
    $this->drupalGet('admin/config/development/api');
    $this->cronRun();
    $this->checkAndClearLog([self::CRON_MESSAGE]);

    // Set the cron time for the PHP branch to weekly, so it doesn't get parsed
    // again. Set up a new branch. Run cron and verify that the PHP branch is
    // not parsed again, but the new branch is parsed (at least partially).
    $this->drupalGet('admin/config/development/api/php_branch');
    $this->clickLink('Edit');
    $this->submitForm(['update_frequency' => 604800], 'Save');

    $this->branchInfo = $this->setUpBranchUi();
    $this->clearCache();
    $this->cronRun();
    $this->checkAndClearLog([
      self::CRON_MESSAGE,
      'Created new branch ' . $this->branchInfo['title'],
      'Created new project ' . $this->branchInfo['project_title'],
    ]);

    // Process all entries.
    $this->cronRun();
    $this->processApiParseQueue();
    $count = $this->countParseQueue();
    $this->assertEquals(0, $count, "Parse queue is empty ($count)");

    // Turn on the comment module. Change settings so that comments are allowed.
    // Verify that a comment can be added. Note that the Comments test covers
    // a bunch of other settings and tweaks; this test is so that we can verify
    // that if the API module is set up first, we can then enable comment and it
    // will work.
    $this->assertTrue($this->moduleHandler()->moduleExists('comment'), 'Comment module is enabled');
    // Update user for comment module functionality.
    $this->super_user = $this->drupalAdminLogin();

    $this->drupalGet('admin/config/development/api/comments');
    $this->submitForm(['status' => CommentItemInterface::OPEN], 'Save and apply to all');
    $this->clearCache();

    $this->drupalGet('api/' . $this->branchInfo['project'] . '/classes.php/class/Sample');
    $this->submitForm(
      [
        'subject' => 'Subject 1',
        'field_api_comment_body' => 'Comment 1 body',
      ], 'Save');
    $this->assertSession()->responseContains('Your comment has been posted');
    $this->clearCache();
    $this->drupalGet('api/' . $this->branchInfo['project'] . '/classes.php/class/Sample');
    $this->assertSession()->linkExists('Subject 1', 0, 'Comment subject appears');
    $this->assertSession()->responseContains('Comment 1 body');
    $this->assertSession()->linkExists('Reply', 0, 'Reply link appears');

    // Set the PHP branch to update every cron run, and verify it gets updated
    // at next cron run.
    $this->drupalGet('admin/config/development/api/php_branch');
    $this->clickLink('Edit');
    $this->submitForm(['update_frequency' => 1], 'Save');
    $this->clearCache();
    $this->cronRun();
    $this->checkAndClearLog([
      self::CRON_MESSAGE,
      self::PHP_MESSAGE,
    ]);
  }

}
