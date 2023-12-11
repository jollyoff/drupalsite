<?php

namespace Drupal\Tests\api\Functional;

/**
 * Tests multiple-branch functionality within a single project.
 */
class MultipleBranchesTest extends WebPagesBase {

  /**
   * Array of information about the second sample branch.
   *
   * @var array
   */
  protected $branchInfo2;

  /**
   * {@inheritdoc}
   */
  protected function setUp() : void {
    $this->baseSetUp();

    // Create a "file" branch with the sample code, from the admin interface.
    $this->branchInfo = $this->setUpBranchUi(NULL, TRUE, [
      'branch_name' => 'long_6',
      'project_title' => 'Project Test',
    ]);

    // Create a second "file" branch in a different branch on the same project.
    // Use the sample2 code.
    $this->branchInfo2 = $this->setUpBranchUi(NULL, FALSE, [
      'branch_name' => 'long_7',
      'project_title' => 'Project Test',
      'title' => 'Testing 7',
      'directory' => $this->apiModulePath . '/tests/files/sample2',
      'excluded' => 'none',
    ]);

    // Remove PHP branch.
    $this->removePhpBranch();

    // Parse the code.
    $this->clearCache();
    $this->cronRun();
    $this->processApiParseQueue();
  }

  /**
   * Runs all tests in this section, to avoid multiple calls to setUp().
   */
  public function testAll() {
    $this->verifyBranchesCallingFunctions();
    $this->verifyBranchLinks();
  }

  /**
   * Tests that the list of functions calling this function is branch-specific.
   */
  protected function verifyBranchesCallingFunctions() {
    // Visit the sample.php function sample_function().
    $this->drupalGet('api/' . $this->branchInfo['project'] . '/sample.php/function/sample_function');

    // Verify that there is only one function calling this function.
    $this->assertSession()->pageTextContains('11 calls to sample_function()');
    // Verify links to the calling functions.
    $this->assertSession()->linkExists('sample_in_code_links', 0, 'Calling function link is present');
    $this->assertSession()->linkNotExists('second_sample_function', 'Calling function from other branch link is not present');

    // Visit the Functions listing page and verify the count there is the same.
    $this->drupalGet('api/' . $this->branchInfo['project'] . '/functions');
    $this->assertSession()->responseContains('11');

    // Visit the other branch's function sample_function().
    $this->drupalGet('api/' . $this->branchInfo2['project'] . '/sample2.php/function/sample_function/' . $this->branchInfo2['branch_name']);

    // Verify that there is only one function calling this function.
    $this->assertSession()->pageTextContains('1 call to sample_function()');
    // Verify links to the calling functions.
    $this->assertSession()->linkExists('second_sample_function', 0, 'Calling function link is present');
    $this->assertSession()->linkNotExists('sample_in_code_links', 'Calling function from other branch link is not present');

    // Visit the Functions listing page and verify the count there is the same.
    $this->drupalGet('api/' . $this->branchInfo2['project'] . '/functions/' . $this->branchInfo2['branch_name']);
    $this->assertSession()->responseContains('1');
  }

  /**
   * Tests that the links between branches on pages are working.
   */
  protected function verifyBranchLinks() {
    // Verify function-style linking.
    $this->drupalGet('api/' . $this->branchInfo2['project'] . '/sample2.php/function/sample_function/' . $this->branchInfo2['branch_name']);
    $this->assertSession()->responseContains($this->branchInfo['branch_name'] . ' sample.php');

    $this->drupalGet('api/' . $this->branchInfo['project'] . '/sample.php/function/sample_function');
    $this->assertSession()->responseContains($this->branchInfo2['branch_name'] . ' sample2.php');
    $this->assertSession()->linkExists('sample_function', 0, 'Other sample link is there');

    $this->drupalGet('api/' . $this->branchInfo['project'] . '/sample.php/function/sample_function/lo');
    $this->assertSession()->statusCodeEquals(404);

    // Test constant-style linking.
    $this->drupalGet('api/' . $this->branchInfo['project'] . '/sample.php/constant/DUPLICATE_CONSTANT');
    $this->assertSession()->responseContains($this->branchInfo['branch_name'] . ' duplicates.php');
    $this->assertSession()->responseContains($this->branchInfo2['branch_name'] . ' maybe_exclude/extras.php');

    // Test class and method linking.
    $this->drupalGet('api/' . $this->branchInfo['project'] . '/classes.php/class/Sample');
    $this->assertSession()->responseContains($this->branchInfo2['branch_name'] . ' maybe_exclude/extras.php');

    // Now visit the method page.
    $this->clickLink('Sample::foo');
    $this->assertSession()->responseContains($this->branchInfo2['branch_name'] . ' maybe_exclude/extras.php');

    // Make sure the links go to the right place.
    $this->assertLinkUrlSubstring($this->branchInfo2['branch_name'] . ' maybe_exclude/extras.php', 'extras.php/function/Sample', 'foo link exists', 'foo link went to the right place');
    // This link is in the breadcrumb.
    $this->assertLinkUrlSubstring('Sample', 'classes.php/class/Sample', 'First Sample link exists', 'First Sample link went to the right place');
    // Verify the calling functions are linked.
    $this->assertSession()->linkExists('Sample::baz', 0, 'Link to first calling function is there');

    // Now visit the class page in the other branch. Click through to the
    // method and verify that it doesn't show the DifferentClassName::foo()
    // method on that page at all, and vice versa.
    $this->drupalGet('api/' . $this->branchInfo2['project'] . '/maybe_exclude!extras.php/class/Sample/' . $this->branchInfo2['branch_name']);
    $this->clickLink('Sample::foo');
    $this->assertSession()->responseContains($this->branchInfo['branch_name'] . ' classes.php');
    $this->assertSession()->responseNotContains('DifferentClassName');

    $this->drupalGet('api/' . $this->branchInfo2['project'] . '/maybe_exclude!extras.php/class/DifferentClassName/' . $this->branchInfo2['branch_name']);
    $this->assertSession()->responseNotContains('classes.php');
    $this->clickLink('DifferentClassName::foo');
    $this->assertSession()->responseNotContains('Sample::foo');

    // Test on a search page.
    $this->drupalGet('api/' . $this->branchInfo['project'] . '/' . $this->branchInfo['branch_name'] . '/search/samp');
    $this->assertSession()->linkExists('Sample', 0, 'Sample class is found');
    $this->assertSession()->linkExists('sample.php', 0, 'Sample file is found');
    $this->assertSession()->linkExists('sample_function', 0, 'Sample function is found');
    $this->assertSession()->linkNotExists('another_sample_function', 'Another sample function is not found');
    $this->assertSession()->linkNotExists('Other projects', 'Link to other projects is not present since there are no others');

    // Go to the other branch via link.
    $this->clickLink('Search ' . $this->branchInfo2['title'] . ' for samp');
    $this->assertSession()->linkNotExists('another_function', 'Another function is not found');
    $this->assertSession()->linkExists('second_sample_function', 0, 'Second sample function is found');
    $this->assertSession()->linkExists('Search ' . $this->branchInfo['title'] . ' for samp', 0, 'Link to other search is found');
    $this->assertSession()->linkNotExists('Other projects', 'Link to other projects is not present since there are no others');
  }

}
