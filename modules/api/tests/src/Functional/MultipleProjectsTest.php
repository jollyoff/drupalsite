<?php

namespace Drupal\Tests\api\Functional;

/**
 * Tests multiple-branch functionality with multiple projects.
 */
class MultipleProjectsTest extends WebPagesBase {

  /**
   * Array of information about the second sample branch.
   *
   * @var array
   */
  protected $branchInfo2;

  /**
   * Array of information about the sample core branch.
   *
   * @var array
   */
  protected $branchInfoCore;

  /**
   * {@inheritdoc}
   */
  protected function setUp() : void {
    $this->baseSetUp();

    // Create file branches with the two samples, each in its own project.
    $this->branchInfo = $this->setUpBranchUi(NULL, TRUE, [
      'project' => 'sample',
      'project_title' => 'Sample',
      'branch_name' => '7.x-1.x',
      'title' => 'Sample 7.x-1.x',
      'core_compatibility' => '7.x',
    ]);

    $this->branchInfo2 = $this->setUpBranchUi(NULL, TRUE, [
      'project' => 'test',
      'project_title' => 'Test',
      'branch_name' => '7.x-2.x',
      'title' => 'Test 7.x-2.x',
      'core_compatibility' => '7.x',
      'directory' => $this->apiModulePath . '/tests/files/sample2',
      'excluded' => 'none',
    ]);

    // Create a file branch to act as core.
    $this->branchInfoCore = $this->setUpBranchUi(NULL, TRUE, [
      'project' => 'drupal',
      'project_title' => 'Drupal',
      'project_type' => 'core',
      'branch_name' => '7.x',
      'title' => 'Drupal 7.x',
      'core_compatibility' => '7.x',
      'directory' => $this->apiModulePath . '/tests/files/sample_drupal',
      'excluded' => '',
    ]);

    // Remove PHP branch.
    $this->removePhpBranch();

    // Parse the code.
    $this->clearCache();
    $this->cronRun();
    $this->processApiParseQueue();

    // Turn on the navigation block.
    $this->drupalPlaceBlock('api_navigation_block');
    $this->clearCache();
  }

  /**
   * Runs all tests in this section, to avoid multiple calls to setUp().
   */
  public function testAll() {
    $this->verifyPages();
    $this->verifyBranchesCallingFunctions();
    $this->verifyBranchLinks();
    $this->verifyApiS();
  }

  /**
   * Tests that various pages are working correctly.
   */
  protected function verifyPages() {
    // Test the Projects page.
    $this->drupalGet('api/projects');
    $this->assertSession()->linkExists('Drupal', 0, 'Drupal project link exists');
    $this->assertSession()->linkExists('Sample', 0, 'Sample project link exists');
    $this->assertSession()->linkExists('Test', 0, 'Test project link exists');

    // Verify a link on the Project page.
    $this->clickLink('Test');
    $this->assertSession()->responseContains($this->branchInfo2['title']);
    $this->assertUrlContains($this->branchInfo2['project'], 'Project is in URL');
    $this->assertTitleContains($this->branchInfo2['project_title'], 'Project title is in page title');

    // Verify the default project is found at path 'api'.
    $this->drupalGet('api');
    $this->assertSession()->responseContains($this->branchInfoCore['project_title']);

    // Verify function pages without branch suffixes work.
    $this->drupalGet('api/' . $this->branchInfoCore['project'] . '/drupal.php/function/theme');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->responseContains('Generates themed output.');

    // Go to a listing page on the non-default project's default branch and
    // verify that the page is correct and navigation block is correct.
    $this->clearCache();
    $this->drupalGet('api/' . $this->branchInfo2['project'] . '/classes');
    $this->assertSession()->linkExists('BaseInterface', 0, 'Class is found on non-default project class listing page');
    $this->assertSession()->responseContains('API Navigation');
    $this->clickLink('Functions');
    $this->assertUrlContains('api/' . $this->branchInfo2['project'] . '/functions', 'Navigation link went to correct project');
    $this->assertSession()->linkExists('sample_function', 0, 'Sample function is found');
    $this->assertSession()->responseContains('This project/branch');
  }

  /**
   * Tests that the list of functions calling this function is branch-specific.
   */
  protected function verifyBranchesCallingFunctions() {
    // Visit the sample.php function sample_function().
    // Note that you need to have the branch name in URLs unless it matches the
    // default project's default branch name!
    $this->drupalGet('api/' . $this->branchInfo['project'] . '/sample.php/function/sample_function/' . $this->branchInfo['branch_name']);

    $this->assertSession()->pageTextContains('11 calls to sample_function()');
    $this->assertSession()->linkExists('sample_in_code_links', 0, 'Calling function link is present');
    $this->assertSession()->linkNotExists('second_sample_function', 'Calling function from other branch link is not present');

    // Visit the Functions listing page and verify the count there is the same.
    $this->drupalGet('api/' . $this->branchInfo['project'] . '/functions');
    $this->assertSession()->responseContains('11');

    // Visit the other branch's function sample_function().
    $this->drupalGet('api/' . $this->branchInfo2['project'] . '/sample2.php/function/sample_function/' . $this->branchInfo2['branch_name']);
    $this->assertSession()->pageTextContains('1 call to sample_function()');
    $this->assertSession()->linkExists('second_sample_function', 0, 'Calling function link is present');
    $this->assertSession()->linkNotExists('sample_in_code_links', 'Calling function from other branch link is not present');

    // Visit the Functions listing page and verify the count there is the same.
    $this->drupalGet('api/' . $this->branchInfo2['project'] . '/functions');
    $this->assertSession()->responseContains('1');
  }

  /**
   * Tests that the in-code links between projects are working correctly.
   */
  protected function verifyBranchLinks() {
    // Note that you need to have the branch name in URLs unless it matches the
    // default project's default branch name!
    $this->drupalGet('api/' . $this->branchInfo['project'] . '/sample.php/function/sample_in_code_links/' . $this->branchInfo['branch_name']);

    // Make sure the sample_function link stays in this branch.
    $this->assertLinkUrlSubstring('sample_function', $this->branchInfo['project'] . '/sample.php/function/sample_function', 'sample_function link exists', 'sample_function link went to the right place');

    // Make sure the link to the other non-core branch is made.
    $this->assertLinkUrlSubstring('second_sample_function', $this->branchInfo2['project'] . '/sample2.php/function/second_sample_function', 'second_sample_function link exists', 'second_sample_function link went to the right place');

    // Make sure the core links are made.
    $this->assertLinkUrlSubstring('theme', $this->branchInfoCore['project'] . '/drupal.php/function/theme', 'theme link exists', 'theme link went to the right place');
    $this->assertLinkUrlSubstring('module_invoke', $this->branchInfoCore['project'] . '/drupal.php/function/module_invoke', 'module_invoke link exists', 'module_invoke link went to the right place');

    $this->drupalGet('api/' . $this->branchInfo2['project'] . '/sample2.php/function/second_sample_function/' . $this->branchInfo2['branch_name']);

    // Make sure the sample_function link stays in this branch.
    $this->assertLinkUrlSubstring('sample_function', $this->branchInfo2['project'] . '/sample2.php/function/sample_function', 'sample_function link exists', 'sample_function link went to the right place');

    // Make sure the other links go to the other project/branch.
    $this->assertLinkUrlSubstring('sample_class_function', $this->branchInfo['project'] . '/sample.php/function/sample_class_function', 'sample_class_function link exists', 'sample_class_function link went to the right place');
    $this->assertLinkUrlSubstring('sample_global', $this->branchInfo['project'] . '/sample.php/global/sample_global', 'sample_global link exists', 'sample_global link went to the right place');
    $this->assertLinkUrlSubstring('SAMPLE_CONSTANT', $this->branchInfo['project'] . '/sample.php/constant/SAMPLE_CONSTANT', 'SAMPLE_CONSTANT link exists', 'SAMPLE_CONSTANT link went to the right place');

    $this->drupalGet('api/' . $this->branchInfoCore['project'] . '/drupal.php/function/module_invoke_all/' . $this->branchInfoCore['branch_name']);

    // Check on links that should and should not be made.
    $this->assertLinkUrlSubstring('theme', $this->branchInfoCore['project'] . '/drupal.php/function/theme', 'theme link exists', 'theme link went to the right place');
    $this->assertSession()->responseContains('sample_function');
    $this->assertSession()->linkNotExists('sample_function', 'sample_function is not a link');

    // Check on the class inheritance across branches.
    // @todo MultipleProjectsTest.php
    $this->drupalGet('api/' . $this->branchInfo2['project'] . '/inheritance.php/class/OtherBranchExtension/' . $this->branchInfo2['branch_name']);
    $this->assertSession()->linkExists('Sample2::baz', 0, 'Link to inherited function is present');
    $this->assertSession()->linkExists('OtherBranchExtension::foo', 0, 'Link to overridden function exists');
  }

  /**
   * Tests that the apis path works.
   */
  protected function verifyApiS() {
    // Search for something that is in both projects.
    $this->drupalGet('apis/sample_function');
    $this->assertUrlContains('function/sample_function', 'First sample_function is found by apis');

    // Search for something that is a partial match.
    $this->drupalGet('apis/samp');
    $this->assertUrlContains('search/samp', 'Partial-match search works in apis');

    // Search for something that is only in the earlier project.
    $this->drupalGet('apis/SAMPLE_CONSTANT');
    $this->assertUrlContains('constant/SAMPLE_CONSTANT', 'SAMPLE_CONSTANT is found by apis');

    // This should go to the drupal_alter in the core project.
    $this->drupalGet('apis/module_invoke_all');
    $this->assertUrlContains('drupal.php/function/module_invoke_all', 'module_invoke_all is found by apis');

    // This should not find anything.
    $this->drupalGet('apis/pizza');
    $this->assertSession()->responseContains('cannot be found');
    $this->assertSession()->responseContains('pizza');
  }

}
