<?php

namespace Drupal\Tests\api\Functional;

use Drupal\api\Entity\Project;
use Drupal\Core\Url;

/**
 * Tests the API module administrative pages.
 */
class AdminPagesTest extends WebPagesBase {

  /**
   * Tests that admin pages work correctly.
   *
   * Note that some admin pages are tested during WebPagesBase::setUp().
   */
  public function testAdminPages() {
    // Verify the PHP branches overview page.
    $this->drupalGet('admin/config/development/api/php_branch');
    $this->assertSession()->responseContains('php');
    $this->assertSession()->responseContains($this->phpBranchInfo['title']);

    // Create an API branch and verify it shows up.
    $this->drupalGet('admin/config/development/api/external_branch/add');
    $info = [
      'title' => 'sample_api_branch',
      'function_list' => Url::fromRoute('<front>', [], ['absolute' => TRUE])->toString() . '/modules/contrib/api/tests/files/php_sample/sample_drupal_listing.json',
      'search_url' => Url::fromRoute('<front>', [], ['absolute' => TRUE])->toString() . '/api/test_api_project/test_api_branch/search/',
      'core_compatibility' => '7.x',
      'type' => 'core',
      'update_frequency' => 1,
    ];
    $this->submitForm($info, 'Save');
    $this->assertSession()->responseContains($info['title']);

    // Delete it and verify it is gone.
    $this->clickLink('Delete');
    $this->submitForm([], 'Delete');
    $this->drupalGet('admin/config/development/api/external_branch');
    $this->assertSession()->responseNotContains($info['title']);

    // Verify the branches overview page.
    $this->drupalGet('admin/config/development/api/branch');

    $this->assertSession()->responseContains($this->branchInfo['project']);
    $this->assertSession()->responseContains($this->branchInfo['title']);

    // Verify the branch edit page.
    $this->clickLink('Edit');
    $this->assertUrlContains('admin/config/development/api/branch/1', 'Edit link went to the right place');
    $this->assertSession()->responseContains($this->branchInfo['project']);
    $this->assertSession()->responseContains($this->branchInfo['branch_name']);
    $this->assertSession()->responseContains($this->branchInfo['title']);
    $this->assertSession()->responseContains($this->branchInfo['directory']);

    $tmp_branch_info = [
      'slug' => 'foo',
      'title' => 'Testing 6 baz',
      'preferred' => TRUE,
    ];

    $this->submitForm($tmp_branch_info, 'Save');

    // This should take us back to the admin listing. Verify the new info is
    // there.
    $this->assertSession()->responseContains($tmp_branch_info['slug']);
    $this->assertSession()->responseContains($tmp_branch_info['title']);

    // Now edit again, verify saved info is there, and set back.
    $this->clickLink('Edit');
    $this->assertSession()->responseContains($tmp_branch_info['slug']);
    $this->assertSession()->responseContains($tmp_branch_info['title']);
    $this->assertSession()->checkboxChecked('edit-preferred-value');

    $this->submitForm([
      'slug' => $this->branchInfo['branch_name'],
      'title' => $this->branchInfo['title'],
    ], 'Save');

    // Now we should be back on the listing page. Test the reparse link.
    $count = $this->countParseQueue();
    $this->assertEquals($count, 0, "No files ($count) are marked to parse before clicking link");
    $this->clickLink('Re-Parse');
    $this->getParsedCount();

    // Add a second branch and set it to Preferred. Verify the old one is not
    // Preferred any more.
    $this->setUpBranchUi(NULL, TRUE, [
      'project' => $this->branchInfo['project'],
      'branch_name' => 'hello',
      'title' => 'hello',
    ]);
    $this->drupalGet('admin/config/development/api/branch/1/edit');
    $this->assertSession()->checkboxNotChecked('edit-preferred-value');
    $this->drupalGet('admin/config/development/api/branch/2/edit');
    $this->assertSession()->checkboxChecked('edit-preferred-value');

    // Test the Projects listing page.
    $this->drupalGet('admin/config/development/api/project');
    $this->assertSession()->responseContains($this->branchInfo['project']);
    $this->assertSession()->responseContains($this->branchInfo['project_type']);
    $this->assertSession()->responseContains($this->branchInfo['project_title']);

    // Create a new project.
    $new_project_word = 'pizza';
    $new_branch_word = 'crust';
    $this->drupalGet('admin/config/development/api/project/add');
    $this->submitForm([
      'title' => $new_project_word,
      'slug' => $new_project_word,
      'type' => $new_project_word,
    ], 'Save');
    $this->assertUrlContains('admin/config/development/api/project', 'Saving project goes back to project page');
    $this->assertSession()->responseContains($new_project_word);

    // Now try creating and then deleting a branch. Set it to Preferred.
    $this->setUpBranchUi(NULL, TRUE, [
      'project' => $new_project_word,
      'branch_name' => $new_branch_word,
      'title' => $new_branch_word,
    ]);
    $this->drupalGet('admin/config/development/api/branch');
    $this->assertSession()->responseContains($new_branch_word);
    $this->drupalGet('admin/config/development/api/branch/3/delete');
    $this->submitForm([], 'Delete');
    $this->drupalGet('admin/config/development/api/branch');
    $this->assertSession()->responseNotContains($new_branch_word);

    // Verify the Preferred status of the branch in the other project was not
    // changed.
    $this->drupalGet('admin/config/development/api/branch/2/edit');
    $this->assertSession()->checkboxChecked('edit-preferred-value');

    // Delete the project and verify the project and branch words are gone.
    $project = Project::getBySlug($new_project_word);
    $this->assertNotEmpty($project);
    $this->drupalGet('admin/config/development/api/project/' . $project->id() . '/delete');
    $this->submitForm([], 'Delete');
    $this->drupalGet('admin/config/development/api/branch');
    $this->assertSession()->responseNotContains($new_project_word);
    $this->drupalGet('admin/config/development/api/project');
    $this->assertSession()->responseNotContains($new_project_word);
  }

}
