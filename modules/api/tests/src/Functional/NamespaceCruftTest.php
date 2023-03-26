<?php

namespace Drupal\Tests\api\Functional;

use Drupal\comment\Plugin\Field\FieldType\CommentItemInterface;

/**
 * Tests that namespace-related cruft in API module is removed appropriately.
 */
class NamespaceCruftTest extends WebPagesBase {

  /**
   * {@inheritdoc}
   */
  protected function setUp() : void {
    $this->baseSetUp();

    $this->drupalAdminLogin();

    // Set up comment settings.
    $this->drupalGet('admin/config/development/api/comments');
    $this->submitForm(['status' => CommentItemInterface::OPEN], 'Save configuration');

    // We don't need the PHP branch for this test, so for speed, remove it.
    $this->removePhpBranch();
  }

  /**
   * Tests that cruft is removed appropriately.
   */
  public function testCruftRemoval() {
    $counts = [
      'api_project' => 0,
      'api_branch' => 0,
      'api_php_branch' => 0,
      'api_branch_docblock' => 0,
      'api_php_branch_documentation' => 0,
      'api_branch_docblock_function' => 0,
      'api_branch_docblock_class_member' => 0,
      'api_branch_docblock_override' => 0,
      'api_branch_docblock_file' => 0,
      'api_branch_docblock_namespace' => 0,
    ];
    $this->verifyCounts($counts, 0, 'No branches');

    // Add a branch for the namespace test files.
    $this->branchInfo = $this->setUpBranchUi(NULL, TRUE, [
      'directory' => $this->apiModulePath . '/tests/files/sample_namespace',
      'excluded' => '',
    ]);

    $counts['api_project'] = 1;
    $counts['api_branch'] = 1;
    $this->clearCache();
    $this->verifyCounts($counts, 0, 'Branch added');

    // Parse everything and verify counts.
    $this->checkAndClearLog();
    $this->cronRun();
    $this->processApiParseQueue();
    $counts['api_branch_docblock'] = 39;
    $counts['api_branch_docblock_function'] = 16;
    $counts['api_branch_docblock_file'] = 11;
    $counts['api_branch_docblock_class_member'] = 25;
    $counts['api_branch_docblock_override'] = 17;
    $counts['api_branch_docblock_namespace'] = 17;
    $this->verifyCounts($counts, 0, 'Parse the branch');

    // Delete the branch, and verify counts.
    $branch = $this->getBranch();
    $this->drupalGet('admin/config/development/api/branch/' . $branch->id() . '/delete');
    $this->submitForm([], 'Delete');
    $this->cronRun();
    $this->clearCache();
    $counts['api_branch'] = 0;
    $counts['api_branch_docblock_file'] = 0;
    $counts['api_branch_docblock'] = 0;
    $counts['api_branch_docblock_function'] = 0;
    $counts['api_branch_docblock_class_member'] = 0;
    $counts['api_branch_docblock_override'] = 0;
    $counts['api_branch_docblock_namespace'] = 0;
    $counts['api_branch_docblock_reference'] = 0;
    $counts['api_branch_docblock_reference_count'] = 0;
    $this->verifyCounts($counts, 0, 'Branch deleted');

    // Delete the project and verify counts.
    $this->drupalGet('admin/config/development/api/project/' . $branch->getProject()->id() . '/delete');
    $this->submitForm([], 'Delete');
    $counts['api_project'] = 0;
    $this->cronRun();
    $this->verifyCounts($counts, 0, 'Project deleted');
  }

}
