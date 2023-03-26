<?php

namespace Drupal\Tests\api\Functional;

/**
 * Tests that links to empty listing pages are not created.
 */
class EmptyPageLinksTest extends WebPagesBase {

  /**
   * Array of information for the second sample branch.
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
    $this->branchInfo = $this->setUpBranchUi();

    // Create a second "file" branch in a different project, but with the same
    // branch name. Exclude the "maybe_exclude" directory so that this branch
    // only has functions and not constants and classes.
    $this->branchInfo2 = $this->setUpBranchUi(NULL, FALSE, [
      'project' => 'test2',
      'project_title' => 'Project 2',
      'title' => 'Project 2 6.x',
      'directory' => $this->apiModulePath . '/tests/files/sample2',
      'excluded' => $this->apiModulePath . '/tests/files/sample2/maybe_exclude',
    ]);

    // Remove built-in PHP branch.
    $this->removePhpBranch();

    // Parse the code.
    $this->clearCache();
    $this->cronRun();
    $this->processApiParseQueue();
  }

  /**
   * Tests that links to empty listing pages are not present.
   */
  public function testEmptyPageLinks() {
    // Visit the branch home page for the first project.
    $this->drupalGet('api/' . $this->branchInfo['project']);
    // Verify that all the listing page links are there.
    $this->assertSession()->linkExists('Files', 0, 'Files link is present');
    $this->assertSession()->linkExists('Functions', 0, 'Functions link is present');
    $this->assertSession()->linkExists('Classes and Interfaces', 0, 'Classes link is present');
    $this->assertSession()->linkExists('Constants', 0, 'Constants link is present');
    $this->assertSession()->linkExists('Globals', 0, 'Globals link is present');
    $this->assertSession()->linkExists('Topics', 0, 'Topics link is present');
    // Verify link to the project page is there.
    $this->assertSession()->linkExists('Other projects', 0, 'Link to projects page is present');

    // Follow the projects link and verify both projects are listed.
    $this->clickLink('Other projects');
    $this->assertSession()->responseContains('Projects');
    $this->assertTitleContains('Projects', 'Page title is correct');
    $this->assertSession()->linkExists($this->branchInfo['project_title'], 0, 'First project link is present');
    $this->assertSession()->linkExists($this->branchInfo2['project_title'], 0, 'Second project link is present');

    // Visit the branch home page for the second project.
    $this->drupalGet('api/' . $this->branchInfo2['project']);
    // Verify that the correct listing page links are there, and the topic.
    $this->assertSession()->linkExists('A great topic');
    $this->assertSession()->linkExists('Files', 0, 'Files link is present');
    $this->assertSession()->linkExists('Functions', 0, 'Functions link is present');
    $this->assertSession()->linkExists('Classes and Interfaces', 0, 'Classes link is present');
    $this->assertSession()->linkExists('Topics', 0, 'Topics link is present');

    // Verify that the listing pages that would be empty are not present.
    $this->assertSession()->linkNotExists('Constants', 'Constants link is not present');
    $this->assertSession()->linkNotExists('Globals', 'Globals link is not present');
    // Verify link to the project page is there.
    $this->assertSession()->linkExists('Other projects', 0, 'Link to projects page is present');

    // Visit the Functions listing pages and verify they link to the other
    // projects.
    $this->drupalGet('api/' . $this->branchInfo['project'] . '/functions');
    $this->assertSession()->linkExists('Other projects', 0, 'Link to projects page is present');

    // Test default branch not set up message.
    $this->drupalGet('api/' . $this->branchInfo2['project'] . '/functions');
    $this->assertSession()->pageTextContains('Default branch is not set for');

    // Turn on the API navigation block. Visit a function page in both
    // projects, and verify the right links are showing in the API
    // navigation block.
    $this->drupalPlaceBlock('api_navigation_block', ['id' => 'apinavigationblock']);

    // Project/branch with all possible types of items.
    $this->drupalGet('api/' . $this->branchInfo['project'] . '/sample.php/function/sample_function');
    $this->assertSession()->responseContains('API Navigation');
    $this->assertSession()->linkExists($this->branchInfo['project_title'] . ' ' . $this->branchInfo['branch_name'], 0, 'Branch link is present');
    $this->assertSession()->linkExists('Files', 0, 'Files link is present');
    $this->assertSession()->linkExists('Functions', 0, 'Functions link is present');
    $this->assertSession()->linkExists('Classes', 0, 'Classes link is present');
    $this->assertSession()->linkExists('Constants', 0, 'Constants link is present');
    $this->assertSession()->linkExists('Globals', 0, 'Globals link is present');
    $this->assertSession()->linkExists('Topics', 0, 'Topics link is present');
    $this->assertSession()->linkExists('Elements', 0, 'Elements link is present');
    $this->assertSession()->linkExists('Deprecated', 0, 'Deprecated link is present');

    // Project/branch with only some types of items.
    $this->drupalGet('api/' . $this->branchInfo2['project'] . '/sample2.php/function/second_sample_function');
    $this->assertSession()->responseContains('API Navigation');
    $this->assertSession()->linkExists($this->branchInfo2['project_title'] . ' ' . $this->branchInfo2['branch_name'], 0, 'Branch link is present');
    $this->assertSession()->linkExists('Topics');
    $this->assertSession()->linkExists('Files');
    $this->assertSession()->linkExists('Functions');
    $this->assertSession()->linkExists('Classes');
    $this->assertSession()->linkNotExists('Constants');
    $this->assertSession()->linkNotExists('Globals');
    $this->assertSession()->linkNotExists('Namespaces');
    $this->assertSession()->linkNotExists('Deprecated');
    $this->assertSession()->linkNotExists('Services');
    $this->assertSession()->linkNotExists('Elements');

    // Test the settings for which pages the block should be displayed on.
    $pages = [
      'branch' => ['api/' . $this->branchInfo['project']],
      'listing' => [
        'api/' . $this->branchInfo['project'] . '/functions',
        'api/' . $this->branchInfo['project'] . '/classes',
        'api/' . $this->branchInfo['project'] . '/groups',
        // Whilst the below is a file item, it's also a hub of information, so
        // the route is marked as listing.
        'api/' . $this->branchInfo['project'] . '/sample.php/' . $this->branchInfo['branch_name'],
      ],
      'item' => [
        'api/' . $this->branchInfo['project'] . '/sample.php/function/sample_function',
        'api/' . $this->branchInfo['project'] . '/sample.php/constant/SAMPLE_CONSTANT',
        'api/' . $this->branchInfo['project'] . '/sample.php/global/sample_global',
        'api/' . $this->branchInfo['project'] . '/classes.php/class/Sample',
        'api/' . $this->branchInfo['project'] . '/sample.php/group/samp_GRP-6.x',
      ],
      'references' => [
        'api/' . $this->branchInfo['project'] . '/sample.php/function/calls/sample_function',
        'api/' . $this->branchInfo['project'] . '/classes.php/class/hierarchy/sample',
        'api/' . $this->branchInfo['project'] . '/classes.php/interface/implements/SampleInterface',
      ],
      'search' => [
        'api/' . $this->branchInfo['project'] . '/' . $this->branchInfo['branch_name'] . '/search',
        'api/' . $this->branchInfo['project'] . '/' . $this->branchInfo['branch_name'] . '/search/function',
      ],
      'special' => ['api/projects'],
    ];

    $types = array_keys($pages);
    $all_off = [];
    foreach ($types as $type) {
      $all_off['settings[navigation_block_display][' . $type . ']'] = FALSE;
    }

    foreach ($types as $type) {
      // Configure the block to only be visible on this type of page.
      $edit = $all_off;
      $edit['settings[navigation_block_display][' . $type . ']'] = TRUE;
      $this->drupalGet('admin/structure/block/manage/apinavigationblock');
      $this->submitForm($edit, 'Save block');
      $this->refreshVariables();

      // Verify all the URLs either have or don't have the block.
      foreach ($pages as $test_type => $urls) {
        foreach ($urls as $url) {
          $this->clearCache();
          $this->drupalGet($url);
          if ($test_type == $type) {
            $this->assertSession()->responseContains('API Navigation');
          }
          else {
            $this->assertSession()->responseNotContains('API Navigation');
          }
        }
      }
    }
  }

}
