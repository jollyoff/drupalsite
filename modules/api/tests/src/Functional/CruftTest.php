<?php

namespace Drupal\Tests\api\Functional;

use Drupal\api\Entity\Branch;
use Drupal\api\Entity\DocBlock;
use Drupal\api\Entity\ExternalBranch;
use Drupal\api\Entity\PhpBranch;
use Drupal\api\Parser;
use Drupal\comment\Plugin\Field\FieldType\CommentItemInterface;
use Symfony\Component\Finder\SplFileInfo;

/**
 * Tests that "cruft" in the API module is removed appropriately.
 */
class CruftTest extends WebPagesBase {

  /**
   * The directory where the source files to parse are located.
   *
   * @var string
   */
  protected $sourceFileDirectory;

  /**
   * {@inheritdoc}
   */
  protected function setUp() : void {
    $this->baseSetUp();

    // Set up a temporary copy of the files to parse.
    $source = $this->apiModulePath . '/tests/files/sample';
    $this->sourceFileDirectory = realpath($this->publicFilesDirectory) . '/sample_files';
    self::recursiveCopyFiles($source, $this->sourceFileDirectory);
    $this->assertDirectoryExists($this->sourceFileDirectory);

    // Set up a new super-user.
    $this->super_user = $this->drupalAdminLogin();

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
      'api_external_branch' => 0,
      'api_branch_docblock' => 0,
      'api_php_branch_documentation' => 0,
      'api_external_branch_documentation' => 0,
      'api_branch_docblock_function' => 0,
      'api_branch_docblock_class_member' => 0,
      'api_branch_docblock_override' => 0,
      'api_branch_docblock_file' => 0,
      'api_branch_docblock_namespace' => 0,
    ];
    $this->verifyCounts($counts, 0, 'No branches');

    // Add a branch without the usual exclude directory excluded.
    $this->setUpBranchUi(NULL, TRUE, [
      'directory' => $this->sourceFileDirectory,
      'excluded' => '',
    ]);
    $counts['api_project'] = 1;
    $counts['api_branch'] = 1;
    $this->clearCache();
    $this->verifyCounts($counts, 0, 'Branch added');

    // Run the branch update function and verify counts. There should be
    // records for each file.
    $this->cronRun();
    $counts['api_branch_docblock'] = 75;
    $counts['api_branch_docblock_file'] = 12;
    $counts['api_branch_docblock_function'] = 33;
    $counts['api_branch_docblock_class_member'] = 25;
    $counts['api_branch_docblock_override'] = 22;
    $this->verifyCounts($counts, 0, 'Add and update branch with no exclusion');

    // Update the branch to exclude the exclude directory.
    $default_branch = $this->getBranch();
    $this->drupalGet('admin/config/development/api/branch/' . $default_branch->id() . '/edit');
    $this->submitForm([
      'excluded_directories' => $this->sourceFileDirectory . '/to_exclude',
    ], 'Save');
    $this->drupalGet('admin/config/development/api/branch/' . $default_branch->id() . '/edit');
    $this->clearCache();
    $default_branch = Branch::load($default_branch->id());

    // Orphan files (after excluded) are only deleted after a number of days.
    // Set that in the configuration and also fake the date of the files, to
    // see how they go away.
    $this->drupalGet('admin/config/development/api');
    $this->submitForm([
      'remove_orphan_files' => 5,
    ], 'Save configuration');
    $this->clearCache();
    $docBlock = DocBlock::findFileByFileName('to_exclude/excluded.php', $default_branch);
    $this->assertNotEmpty($docBlock);
    $docBlock = DocBlock::load($docBlock);
    $docBlock->getDocFile()->setCreatedTime(strtotime('-10 days'))->save();
    // This should get rid of the file and related entities.
    $this->cronRun();
    $this->checkAndClearLog(['DocBlock excluded.php deleted.']);
    $this->clearCache();
    $docBlock = DocBlock::findFileByFileName('to_exclude/excluded.php', $default_branch);
    $this->assertEmpty($docBlock);

    // Parse everything and re-verify counts.
    $default_branch->reParse();
    $this->cronRun();
    $this->processApiParseQueue();
    $this->clearCache();
    // Make sure the file was not recreated.
    $docBlock = DocBlock::findFileByFileName('to_exclude/excluded.php', $default_branch);
    $this->assertEmpty($docBlock);
    $counts['api_branch_docblock'] = 74;
    $counts['api_branch_docblock_file'] = 11;
    $this->verifyCounts($counts, 0, 'Parse the branch');

    // Mark branch for update and reparsing.
    $this->drupalGet('admin/config/development/api/branch/' . $default_branch->id() . '/parse');
    // Check counts and log; counts should stay the same.
    $this->clearCache();
    $this->cronRun();
    $this->processApiParseQueue();
    $this->verifyCounts($counts, 0, 'Reparse the branch');

    // Add one comment in the main directory and one in the subdirectory, and
    // verify counts.
    $this->drupalGet('api/' . $default_branch->getProject()->getSlug() . '/classes.php/class/Sample');
    $this->submitForm([
      'subject' => 'Subject 1',
      'field_api_comment_body' => 'Comment 1 body',
    ], 'Save');
    $this->assertSession()->responseContains('Your comment has been posted');

    $this->drupalGet('api/' . $default_branch->getProject()->getSlug() . '/subdirectory!classes-subdir.php/class/SampleInSubDir');
    $this->submitForm([
      'subject' => 'Subject 2',
      'field_api_comment_body' => 'Comment 2 body',
    ], 'Save');
    $this->assertSession()->responseContains('Your comment has been posted');

    $counts['comment'] = 2;
    $this->verifyCounts($counts, 0, 'Add two comments');

    $this->clearCache();
    $this->drupalGet('api/' . $default_branch->getProject()->getSlug() . '/classes.php/class/Sample');
    $this->assertSession()->linkExists('Subject 1', 0, 'Comment subject appears');
    $this->assertSession()->responseContains('Comment 1 body');

    // Delete the branch, and verify counts.
    $this->drupalGet('admin/config/development/api/branch/' . $default_branch->id() . '/delete');
    $this->submitForm([], 'Delete');
    $this->cronRun();
    $this->clearCache();
    $counts['api_branch'] = 0;
    $counts['comment'] = 0;
    $counts['api_branch_docblock_file'] = 0;
    $counts['api_branch_docblock'] = 0;
    $counts['api_branch_docblock_function'] = 0;
    $counts['api_branch_docblock_class_member'] = 0;
    $counts['api_branch_docblock_override'] = 0;
    // At this point, make sure the reference storage table is cleared out
    // too. We haven't been tracking it.
    $counts['api_branch_docblock_reference'] = 0;
    $counts['api_branch_docblock_reference_count'] = 0;
    $this->verifyCounts($counts, 0, 'Branch deleted');
    // Go back to not tracking reference storage.
    unset($counts['api_branch_docblock_reference']);
    unset($counts['api_branch_docblock_reference_count']);

    // Add a job using the deleted branch to the queue, and attempt to parse it.
    // Verify that the log message is generated.
    $this->clearCache();
    $parser = \Drupal::service('api.parser');
    $queue = \Drupal::service('queue')->get(Parser::QUEUE_PARSE);
    $file = new SplFileInfo($this->sourceFileDirectory . '/sample.php', '', '');
    $docblock_info = [
      'branch_id' => $default_branch->id(),
      'branch_type' => $default_branch->getEntityTypeId(),
      'action' => 'parse',
      'data' => $parser->parsePhp($parser->parseFile($file)) ?? [],
    ];
    $queue->createItem($docblock_info);
    $this->verifyCounts($counts, 1, 'Bad parse queue job added');
    $this->checkAndClearLog();
    $this->processApiParseQueue();
    $this->verifyCounts($counts, 0, 'Bad parse queue job processed');
    $this->checkAndClearLog();

    // Add the fake PHP branch.
    $this->createPhpBranchUi();
    $this->clearCache();
    $counts['api_php_branch'] = 1;
    $this->verifyCounts($counts, 0, 'Create PHP branch');

    // Parse and verify counts.
    $this->cronRun();
    $counts['api_php_branch_documentation'] = 2;
    $this->verifyCounts($counts, 0, 'Parse PHP branch');

    // Delete the branch and verify counts.
    $branches = PhpBranch::loadMultiple();
    foreach ($branches as $branch) {
      $this->drupalGet('admin/config/development/api/php_branch/' . $branch->id() . '/delete');
      $this->submitForm([], 'Delete');
      break;
    }
    $this->cronRun();
    $this->clearCache();
    $counts['api_php_branch'] = 0;
    $counts['api_php_branch_documentation'] = 0;
    $this->verifyCounts($counts, 0, 'Delete PHP branch');

    // Add the fake API branch.
    $this->createApiBranchUi();
    $this->clearCache();
    $counts['api_external_branch'] = 1;
    $this->verifyCounts($counts, 0, 'Create API branch');

    // Parse and verify counts.
    $this->cronRun();
    $this->processApiParseQueue();
    $counts['api_external_branch_documentation'] = 8;
    $this->verifyCounts($counts, 0, 'Parse API branch');

    // Delete the branch and verify counts.
    $branches = ExternalBranch::loadMultiple();
    foreach ($branches as $branch) {
      $this->drupalGet('admin/config/development/api/external_branch/' . $branch->id() . '/delete');
      $this->submitForm([], 'Delete');
      break;
    }
    $this->cronRun();
    $this->clearCache();
    $counts['api_external_branch'] = 0;
    $counts['api_external_branch_documentation'] = 0;
    $this->verifyCounts($counts, 0, 'Delete API branch');

    // Add and update the sample branch again, without excluding the
    // directory.
    $this->setUpBranchUi(NULL, TRUE, [
      'directory' => $this->sourceFileDirectory,
      'excluded' => '',
    ]);
    $this->clearCache();
    $this->cronRun();
    $counts['api_project'] = 1;
    $counts['api_branch'] = 1;
    $counts['api_branch_docblock'] = 75;
    $counts['api_branch_docblock_function'] = 33;
    $counts['api_branch_docblock_class_member'] = 25;
    $counts['api_branch_docblock_override'] = 22;
    $counts['api_branch_docblock_file'] = 12;
    $this->verifyCounts($counts, 0, 'Add and update branch with no exclusion, take 2');

    // Delete the project and verify counts.
    $default_branch = $this->getBranch();
    $this->drupalGet('admin/config/development/api/project/' . $default_branch->getProject()->id() . '/delete');
    $this->submitForm([], 'Delete');
    $this->cronRun();
    $counts['api_branch'] = 0;
    $counts['api_project'] = 0;
    $counts['api_branch_docblock_file'] = 0;
    $counts['api_branch_docblock'] = 0;
    $counts['api_branch_docblock_function'] = 0;
    $counts['api_branch_docblock_class_member'] = 0;
    $counts['api_branch_docblock_override'] = 0;
    // At this point, make sure the reference storage table is cleared out
    // too. We haven't been tracking it.
    $counts['api_branch_docblock_reference'] = 0;
    $counts['api_branch_docblock_reference_count'] = 0;
    $this->verifyCounts($counts, 0, 'Project deleted');

    // Add both sample and fake PHP/API branches again.
    // Update the branches, but don't parse.
    $this->createPhpBranchUi();
    $this->createApiBranchUi();
    $this->setUpBranchUi(NULL, TRUE, [
      'directory' => $this->sourceFileDirectory,
    ]);
    $this->clearCache();
    $this->cronRun();

    // Disable and uninstall the API module. Verify counts.
    $this->uninstallApiModule();
    // At this point, the API tables should be gone, so only test node
    // and comment tables.
    $counts = [];
    $this->verifyCounts($counts, 0, 'Uninstalled');

    // Verify that there are no variables with 'api' in the name.
    $config = $this->config('api.settings');
    $this->assertEmpty($config->get());

    // Verify that there are no auto-complete files left over.
    $this->assertFalse(is_dir('public://api'), 'API files directory is empty');
  }

  /**
   * Recursively copies files from a source to a destination directory.
   *
   * Slightly modified (variable names and coding standards) from a comment
   * on http://php.net/manual/en/function.copy.php .
   *
   * @param string $source
   *   Source directory to copy.
   * @param string $destination
   *   Desintation to copy to.
   */
  protected static function recursiveCopyFiles($source, $destination) {
    $dir = opendir($source);
    @mkdir($destination);

    while (FALSE !== ($file = readdir($dir))) {
      if (($file != '.') && ($file != '..')) {
        if (is_dir($source . '/' . $file)) {
          self::recursiveCopyFiles($source . '/' . $file, $destination . '/' . $file);
        }
        else {
          copy($source . '/' . $file, $destination . '/' . $file);
        }
      }
    }
    closedir($dir);
  }

}
