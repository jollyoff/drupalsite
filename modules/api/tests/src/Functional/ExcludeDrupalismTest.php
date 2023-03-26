<?php

namespace Drupal\Tests\api\Functional;

/**
 * Tests the exclude from Drupalism functionality.
 */
class ExcludeDrupalismTest extends WebPagesBase {

  /**
   * Overrides WebPagesBase::setUp() so we have the excluded directory.
   */
  protected function setUp() : void {
    $this->baseSetUp();

    // Create a "file" branch with the excluded directory but exclude it from
    // drupalisms.
    $this->branchInfo = $this->setUpBranchUi(NULL, TRUE, [
      'excluded' => '',
      'exclude_drupalism_regexp' => '|to_exclude|',
    ]);

    $this->removePhpBranch();

    // Parse the code.
    $this->clearCache();
    $this->cronRun();
    $this->processApiParseQueue();
  }

  /**
   * Tests that the hook does not turn into a link in the excluded file.
   */
  public function testDrupalExclusion() {
    $this->drupalGet('api/' . $this->branchInfo['project'] . '/functions');
    $this->clickLink('excluded_function');
    $this->assertSession()->responseContains('sample_two');
    $this->assertSession()->linkNotExists('sample_two', 'sample_two text is not a link in excluded.php');
    $this->assertSession()->responseContains('sample_function');
    $this->assertSession()->linkExists('sample_function', 0, 'sample_function text is a link in excluded.php');
    $this->clickLink('sample_function');
    $this->assertSession()->linkExists('excluded_function', 0, 'sample_function page has reference link to excluded_function()');

    $this->drupalGet('api/' . $this->branchInfo['project'] . '/sample.php/function/sample_in_code_links');
    $this->assertSession()->responseContains('sample_two');
    $this->assertSession()->linkExists('sample_two', 0, 'sample_two text is a link in sample.php');
    $this->clickLink('sample_two');
    $this->assertSession()->linkExists('sample_in_code_links', 0, 'Theme page has reference link to sample_in_code_links()');
    $this->assertSession()->linkNotExists('excluded_function', 'Theme page has no reference link to excluded_function()');
  }

}
