<?php

namespace Drupal\Tests\api\Functional;

/**
 * Tests the queue functionality of the API module.
 */
class QueuesTest extends TestBase {

  /**
   * {@inheritdoc}
   */
  protected function setUp() : void {
    // This test takes longer than most to run, and times out on some machines.
    $this->timeLimit *= 2;

    $this->baseSetUp();
    $this->branchInfo = $this->setUpBranchApiCall();
    $this->removePhpBranch();
    $this->clearCache();
  }

  /**
   * Tests queue functionality.
   */
  public function testQueues() {
    // Verify there is nothing in the queue to start with.
    $this->assertEquals(0, $this->countParseQueue(), 'Parse queue is empty');
    $this->cronRun();
    $this->getParsedCount();

    // Each time we update the queue after this point, wait for a few seconds
    // to make sure that the branch will think it is time to check for updated
    // files.
    sleep(2);
    $this->cronRun();
    $this->getParsedCount();
    $this->assertEquals(0, $this->countParseQueue(), 'Parse queue fully processed');

    // Mark this branch for reparsing, update, and verify the queue is full.
    sleep(2);
    $branch = $this->getBranch();
    $branch->reParse();
    $this->assertEquals(0, $this->countParseQueue(), 'Parse queue is still empty after marking for parsing');

    sleep(2);
    $this->cronRun();
    $this->getParsedCount();

    // Parse and verify empty again.
    $this->processApiParseQueue();
    $this->assertEquals(0, $this->countParseQueue(), 'Parse queue has been emptied');

    // Test the update functionality.
    // First mark the branch so that it doesn't update each cron run.
    $this->drupalGet('admin/config/development/api/branch/' . $branch->id() . '/edit');
    $this->submitForm(['update_frequency' => 604800], 'Save');
    $this->checkAndClearLog(['Updated new branch ' . $branch->getTitle()]);
    // Run the update once, because after saving a branch it might update once.
    $this->cronRun();

    // Click the update link on the branches page, update, and verify it does
    // get updated this time.
    sleep(2);
    $this->drupalGet('admin/config/development/api/branch');
    $this->clickLink('Re-Parse');
    $this->assertSession()->responseContains('Branch ' . $branch->getTitle() . ' was set for re-parsing.');
    $this->cronRun();
    $this->getParsedCount();
  }

}
