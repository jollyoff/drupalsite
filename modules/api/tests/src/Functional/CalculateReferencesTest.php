<?php

namespace Drupal\Tests\api\Functional;

/**
 * Tests references calculations (old shutdown method).
 *
 * @group api
 */
class CalculateReferencesTest extends WebPagesBase {

  /**
   * {@inheritdoc}
   */
  protected function setUp() : void {
    $this->baseSetUp();
  }

  /**
   * Tests module enabling and cron for the API module.
   */
  public function testCalculateReferences() {
    $this->branchInfo = $this->setUpBranchUi();
    $this->clearCache();
    $this->cronRun();
    $this->checkAndClearLog([
      'Created new branch ' . $this->branchInfo['title'],
      'Created new project ' . $this->branchInfo['project_title'],
    ]);

    // Process all entries.
    $this->cronRun();
    $this->processApiParseQueue();
    $count = $this->countParseQueue();
    $this->assertEquals(0, $count, "Parse queue is empty ($count)");

    // Spot check to verify that the shutdown queue job has run and all files
    // are parsed: check a class page and see if methods are there, and check a
    // function page and verify that calls are there.
    $this->drupalGet('api/' . $this->branchInfo['project'] . '/sample.php/function/sample_function');
    $this->assertSession()->pageTextContains('11 calls to sample_function()');
    $this->assertSession()->pageTextContains("1 string reference to 'sample_function'");

    $this->drupalGet('api/' . $this->branchInfo['project'] . '/classes.php/class/SubSample');
    $this->assertSession()->linkExists('Sample::baz', 0, 'Inherited method is shown');
    $this->assertSession()->linkExists('SubSample::bar', 0, 'Direct method is shown');
  }

}
