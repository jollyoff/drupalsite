<?php

namespace Drupal\Tests\api\Functional;

/**
 * Tests that duplicate classes, interfaces, and groups work.
 *
 * @group api
 */
class DuplicatesTest extends WebPagesBase {

  /**
   * {@inheritdoc}
   */
  protected function setUp() : void {
    $this->baseSetUp();

    // Set up a files branch, but do not exclude the usual directories.
    $this->branchInfo = $this->setUpBranchUi(NULL, TRUE, ['excluded' => '']);

    // We don't need the PHP branch for this test, so for speed, remove it.
    $this->removePhpBranch();

    // Parse the code.
    $this->clearCache();
    $this->cronRun();
    $this->processApiParseQueue();
  }

  /**
   * Tests that duplicate classes, interfaces, and groups work.
   */
  public function testDuplicates() {
    // Verify that both Sample classes and interfaces are listed.
    $this->drupalGet('api/' . $this->branchInfo['project'] . '/classes');
    $this->assertSession()->linkExists('Sample', 0, 'Sample is present once');
    $this->assertSession()->linkExists('Sample', 1, 'Sample is present twice');
    $this->assertSession()->linkExists('SampleInterface', 0, 'SampleInterface is present once');
    $this->assertSession()->linkExists('SampleInterface', 1, 'SampleInterface is present twice');

    // Visit both the Sample pages and verify they show the interface they
    // implement, and that each links to the other one.
    $this->drupalGet('api/' . $this->branchInfo['project'] . '/classes.php/class/Sample');
    $this->assertSession()->responseContains('SampleInterface');
    $this->assertLinkUrlSubstring($this->branchInfo['branch_name'] . ' to_exclude/excluded.php', 'excluded.php/class/Sample', 'Link to other version exists', 'Link to other version goes to right place');
    $this->drupalGet('api/' . $this->branchInfo['project'] . '/to_exclude!excluded.php/class/Sample');
    $this->assertSession()->responseContains('SampleInterface');
    $this->assertLinkUrlSubstring($this->branchInfo['branch_name'] . ' classes.php', 'api/' . $this->branchInfo['project'] . '/classes.php/class/Sample', 'Link to other version exists', 'Link to other version goes to right place');

    // Visit both the SampleInterface pages and verify they link to each other.
    $this->drupalGet('api/' . $this->branchInfo['project'] . '/classes.php/interface/SampleInterface');
    $this->assertLinkUrlSubstring($this->branchInfo['branch_name'] . ' to_exclude/excluded.php', 'excluded.php/interface/SampleInterface', 'Link to other version exists', 'Link to other version goes to right place');
    $this->drupalGet('api/' . $this->branchInfo['project'] . '/to_exclude!excluded.php/interface/SampleInterface');
    $this->assertLinkUrlSubstring($this->branchInfo['branch_name'] . ' classes.php', 'api/' . $this->branchInfo['project'] . '/classes.php/interface/SampleInterface', 'Link to other version exists', 'Link to other version goes to right place');

    // Verify that the group is listed only once, and verify that all the
    // members are shown.
    $this->drupalGet('api/' . $this->branchInfo['project'] . '/groups');
    $this->assertLinkCount('Samples', 1, 'Exactly one link to sample group exists');
    $this->clickLink('Samples');
    $this->assertSession()->linkExists('Sample', 0, 'Sample class is on topic page');
    $this->assertSession()->linkExists('SampleInterface', 0, 'Sample interface is on topic page');
    $this->assertSession()->linkExists('sample_function', 0, 'Sample function is on topic page');
    $this->assertSession()->linkExists('sample_insubdir_function', 0, 'Sample subdir function is on topic page');
    $this->assertSession()->linkExists('SAMPLE_CONSTANT', 0, 'Sample constant is on topic page');
    $this->assertSession()->linkExists('$sample_global', 0, 'Sample global is on topic page');
    $this->assertSession()->linkExists('non_duplicate_name', 0, 'Function from duplicate group is on topic page');

    // Verify that the watchdog message about duplicate group definitions was
    // logged.
    $this->drupalGet('admin/reports/dblog');
    $this->assertSession()->responseContains('Replaced defgroup');

    // Verify that the other group gets created.
    $this->drupalGet('api/' . $this->branchInfo['project'] . '/groups');
    $this->assertLinkCount('Test group', 1, 'Exactly one link to test group exists');
    $this->clickLink('Test group');
    $this->assertSession()->linkExists('some_name', 0, 'Correct function is on topic page');
    $this->assertSession()->responseContains('A group whose machine name');
  }

}
