<?php

namespace Drupal\Tests\api\Functional;

use Drupal\api\Entity\Branch;
use Drupal\api\Entity\DocBlock;
use Drupal\api\Entity\DocBlock\DocFile;
use Drupal\api\Entity\ExternalBranch;
use Drupal\api\Entity\PhpBranch;
use Drupal\api\Entity\Project;
use Drupal\api\Parser;
use Drupal\comment\Plugin\Field\FieldType\CommentItemInterface;
use Drupal\comment\Tests\CommentTestTrait;
use Drupal\editor\Entity\Editor;
use Drupal\filter\Entity\FilterFormat;
use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\Traits\Core\CronRunTrait;
use Drupal\user\Entity\Role;
use Drupal\user\RoleInterface;

/**
 * Provides a base class for testing the API module.
 */
abstract class TestBase extends BrowserTestBase {

  use CronRunTrait;
  use CommentTestTrait;

  const ADMIN_PERMISSIONS = [
    'administer site configuration',
    'access API reference',
    'administer API reference',
    'access content',
    'access administration pages',
    'administer blocks',
    'access site reports',
    'administer comments',
    'access comments',
    'post comments',
    'skip comment approval',
    'administer filters',
    'administer users',
    'administer permissions',
    'administer search',
  ];

  const COMMENTS_PERMISSIONS = [
    'access comments',
    'post comments',
    'skip comment approval',
    'use text format filtered_html',
  ];

  /**
   * Path to the api module.
   *
   * @var string
   */
  protected $apiModulePath;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Modules to install.
   *
   * @var array
   */
  protected static $modules = [
    'api',
    'dblog',
    'search',
  ];

  /**
   * Default set up: Sets up branch using API calls, removes PHP branch, parses.
   */
  protected function setUp() : void {
    $this->baseSetUp();
    $this->setUpBranchApiCall();
    $this->removePhpBranch();

    $this->clearCache();
    $this->cronRun();

    $this->getParsedCount();
    $this->clearCache();
  }

  /**
   * Cron processes the queue already, just check if there are any left.
   */
  protected function getParsedCount() {
    $count_left = $this->countParseQueue();
    if ($count_left) {
      $count = $this->processApiParseQueue();
      $this->assertEquals($count, $count_left, "$count_left files were parsed ($count)");
    }
    else {
      $files_parsed = count(DocFile::loadMultiple());
      $this->assertEquals($files_parsed, 11, "11 files were parsed ($files_parsed)");
    }
  }

  /**
   * Sets up modules for API tests, and a super-user.
   *
   * @param array $extra_modules
   *   Extra modules to install.
   */
  protected function baseSetUp(array $extra_modules = []) {
    parent::setUp();

    // We'll use this often, so let's have it handy.
    $this->apiModulePath = \Drupal::service('file_system')->realpath(
      $this->moduleHandler()->getModule('api')->getPath()
    );

    $this->moduleInstaller()->install($extra_modules, TRUE);

    // Set the line break tag to nothing for most tests.
    $this->config('api.settings')
      ->set('breaks_tag', '')
      ->save();

    // Set up a super-user and log in with it.
    $this->drupalAdminLogin([], FALSE);

    // For debug purposes, visit the Recent Log Messages report page.
    $this->drupalGet('admin/reports/dblog');
    $this->verifyCounts([
      'api_project' => 0,
      'api_branch' => 0,
      'api_branch_docblock' => 0,
      'api_branch_docblock_file' => 0,
      'api_branch_docblock_function' => 0,
      'api_branch_docblock_reference' => 0,
      'api_branch_docblock_override' => 0,
      'api_branch_docblock_class_member' => 0,
      'api_php_branch' => 1,
      'api_php_branch_documentation' => 0,
    ], 0, 'Immediately after install');
  }

  /**
   * Helper method to show current page content.
   *
   * @param string $url
   *   Visit the URL first.
   */
  protected function dumpContent($url = NULL) {
    if ($url) {
      $this->drupalGet($url);
    }
    dump($this->getSession()->getPage()->getContent());
  }

  /**
   * Returns the module handler service.
   *
   * @return \Drupal\Core\Extension\ModuleHandlerInterface
   *   Module hander service.
   */
  protected function moduleHandler() {
    return \Drupal::moduleHandler();
  }

  /**
   * Returns the module installer service.
   *
   * @return \Drupal\Core\Extension\ModuleInstallerInterface
   *   Module installer service.
   */
  protected function moduleInstaller() {
    return \Drupal::service('module_installer');
  }

  /**
   * Log in as admin user.
   *
   * @param array $extra_permissions
   *   Additional permissions.
   * @param bool $logout
   *   Logout first.
   *
   * @return \Drupal\user\Entity\User|false
   *   A fully loaded user object or FALSE.
   */
  protected function drupalAdminLogin(array $extra_permissions = [], $logout = TRUE) {
    if ($logout) {
      $this->drupalLogout();
    }

    $permissions = array_merge(self::ADMIN_PERMISSIONS, $extra_permissions);
    $super_user = $this->drupalCreateUser($permissions);
    $this->drupalLogin($super_user);

    return $super_user;
  }

  /**
   * Allows anonymous users to see API pages.
   */
  protected function allowAnonymousUsersToSeeApiPages(): void {
    $anonymous_role = Role::load(RoleInterface::ANONYMOUS_ID);
    $anonymous_role->grantPermission('access API reference')->save();
  }

  /**
   * Sets up a project and a files branch using API function calls.
   *
   * @param string $prefix
   *   Directory prefix to prepend on the data directories.
   * @param bool $default
   *   TRUE to set this as the default branch; FALSE to not set it as default.
   * @param array $info
   *   Array of information to override the defaults (see function code to see
   *   what they are). Note that $prefix is applied after this information is
   *   read, and that only one directory and one excluded are supported in this
   *   function.
   *
   * @return array
   *   Array of information (defaults with overrides) used to create the
   *   branch and project.
   */
  protected function setUpBranchApiCall($prefix = NULL, $default = TRUE, array $info = []) {
    $base_path = $prefix ?? $this->apiModulePath;
    // Set up defaults.
    $info += [
      'project' => 'test',
      'project_title' => 'Project 6',
      'project_type' => 'module',
      'branch_name' => '6',
      'title' => 'Testing 6',
      'core_compatibility' => '7.x',
      'update_frequency' => 1,
      'directory' => $base_path . '/tests/files/sample',
      'excluded' => $base_path . '/tests/files/sample/to_exclude',
      'regexps' => '',
    ];
    $info['preferred'] = $default ? 1 : 0;

    // Create the project.
    $project = Project::create();
    $project
      ->setType($info['project_type'])
      ->setTitle($info['project'])
      ->setSlug($info['project'])
      ->save();
    $this->assertNotEmpty($project, 'Project was not created.');

    // Create the branch.
    $branch = Branch::create();
    $branch
      ->setProject($project)
      ->setTitle($info['title'])
      ->setSlug($info['branch_name'])
      ->setPreferred($info['preferred'])
      ->setCoreCompatibility($info['core_compatibility'])
      ->setUpdateFrequency($info['update_frequency'])
      ->setDirectories($info['directory'])
      ->setExcludedDirectories($info['excluded'])
      ->setExcludeFilesRegexp($info['regexps'])
      ->save();
    $this->assertNotEmpty($branch, 'Branch was not created.');

    if ($default) {
      $api_config = $this->config('api.settings');
      // Make this the default project/branch/compatibility.
      $api_config
        ->set('default_branch_project', $info['branch_name'] . '|' . $branch->id())
        ->save();

      $this->assertEquals(
        $api_config->get('default_branch_project'),
        $info['branch_name'] . '|' . $branch->id(),
        'Variable for default branch is set correctly'
      );
    }

    return $info;
  }

  /**
   * Removes the PHP branch, which most tests do not need.
   */
  protected function removePhpBranch() {
    $branches = PhpBranch::loadMultiple() ?? [];
    foreach ($branches as $branch) {
      $branch->delete();
    }
  }

  /**
   * Removes the External branch, which most tests do not need.
   */
  protected function removeExternalBranch() {
    $branches = ExternalBranch::loadMultiple() ?? [];
    foreach ($branches as $branch) {
      $branch->delete();
    }
  }

  /**
   * Removes all branches.
   */
  protected function removeAllBranchesAndProjects() {
    $projects = Project::loadMultiple() ?? [];
    foreach ($projects as $project) {
      $project->delete();
    }
  }

  /**
   * Uninstall the api module, so we can also test installation.
   */
  protected function uninstallApiModule() {
    $this->removeAllBranchesAndProjects();
    $this->removePhpBranch();
    $this->removeExternalBranch();
    // Dependencies of projects and branches are queued for deletion and
    // processed on cron.
    $this->cronRun();
    $this->cronRun();

    // Everything should be deleted now, we can uninstall.
    $result = $this->moduleInstaller()->uninstall(['api']);
    $this->assertTrue($result, 'Module api was uninstalled.');
    $this->assertFalse($this->moduleHandler()->moduleExists('api'), 'API module is not enabled');
  }

  /**
   * Returns the first branch in the branches list.
   */
  protected function getBranch() {
    $branches = Branch::loadMultiple();
    return array_shift($branches);
  }

  /**
   * Asserts the right number of documentation objects are in the given branch.
   *
   * @param object|null $branch
   *   Branch object to look in. Omit to use the default branch.
   * @param int $num
   *   Number of objects to assert. Omit to use the current number that should
   *   be present for the default branch.
   */
  protected function assertObjectCount($branch = NULL, $num = 68) {
    if (is_null($branch)) {
      $branch = $this->getBranch();
    }

    $count = count(DocBlock::matches([], $branch));
    $this->assertEquals($count, $num, 'Found ' . $count . ' documentation objects (should be ' . $num . ')');
  }

  /**
   * Clear Drupal caches.
   */
  protected function clearCache() {
    drupal_flush_all_caches();
  }

  /**
   * Creates a node and comments field.
   *
   * @return \Drupal\node\NodeInterface
   *   Created node.
   */
  protected function setUpNodeAndComments() {
    $this->moduleInstaller()->install(['node']);
    $this->drupalCreateContentType(['type' => 'article', 'name' => 'Article']);
    $this->addDefaultCommentField('node', 'article');
    return $this->drupalCreateNode([
      'comment' => CommentItemInterface::OPEN,
      'type' => 'article',
    ]);
  }

  /**
   * Set up the link documentation filter.
   */
  protected function setUpFilterComments() {
    $this->moduleInstaller()->install([
      'ckeditor5',
      'editor',
      'filter',
    ]);
    // Set up the Filtered HTML format to have the API format as part of it.
    $filtered_html_format = FilterFormat::create([
      'format' => 'filtered_html',
      'name' => 'Filtered HTML',
      'weight' => 0,
      'filters' => [
        'filter_link_documentation' => ['status' => TRUE],
      ],
      'roles' => [
        RoleInterface::AUTHENTICATED_ID,
        RoleInterface::ANONYMOUS_ID,
      ],
    ]);
    $filtered_html_format->save();
    $editor = Editor::create([
      'format' => 'filtered_html',
      'editor' => 'ckeditor5',
    ]);
    $editor->save();
  }

  /**
   * Processes the API parse queue.
   *
   * @param bool $verbose
   *   TRUE to print verbose output; FALSE (default) to omit.
   *
   * @return int
   *   Number of files parsed.
   */
  protected function processApiParseQueue($verbose = FALSE) {
    /** @var \Drupal\Core\Queue\QueueWorkerInterface $queue_worker */
    $queue_worker = \Drupal::service('plugin.manager.queue_worker')->createInstance(Parser::QUEUE_PARSE);
    $queue = \Drupal::queue(Parser::QUEUE_PARSE);
    if ($verbose) {
      dump('Queue count: ' . $queue->numberOfItems());
    }
    $count = 0;
    while ($item = $queue->claimItem()) {
      if ($verbose) {
        dump('Processing queue ' . Parser::QUEUE_PARSE . ' - file ' . $item->data['path']);
      }
      $queue_worker->processItem($item->data);
      $queue->deleteItem($item);
      $count++;
    }

    $this->clearCache();

    return $count;
  }

  /**
   * Returns the approximate number of items in the API parse queue.
   */
  protected function countParseQueue() {
    $queue = \Drupal::queue(Parser::QUEUE_PARSE);
    return $queue->numberOfItems();
  }

  /**
   * Verifies the count of items in database tables and parse queue.
   *
   * @param array $counts
   *   Associative array whose keys are names of database tables, and whose
   *   values are the number of records expected to be in those database
   *   tables.
   * @param int $queue
   *   Number of items expected to be in the parse queue.
   * @param string $message
   *   String to append to assertion messages.
   * @param bool $verbose
   *   Print output and don't fail on the equals test.
   */
  protected function verifyCounts(array $counts, $queue, $message, $verbose = FALSE) {
    if ($verbose) {
      dump('COUNTS: ' . $message);
    }

    foreach ($counts as $table => $expected) {
      $query = \Drupal::database()->select($table, 'x');
      $query->addExpression('COUNT(*)');
      $actual = $query
        ->execute()
        ->fetchField();
      if ($verbose && ($actual != $expected)) {
        dump($table . ' // In DB: ' . $actual . ' Expected: ' . $expected);
      }
      else {
        $this->assertEquals($actual, $expected, "Table $table has $expected records ($actual) - $message");
      }
    }

    $actual = $this->countParseQueue();
    $this->assertEquals($actual, $queue, "Parse queue has $queue records ($actual) - $message");
  }

  /**
   * Checks the log for messages, and then clears the log.
   *
   * @param array $messages
   *   Array of messages to assert are in the log.
   * @param array $notmessages
   *   Array of messages to assert are not in the log.
   * @param bool $dump
   *   Show dump of the output.
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   */
  protected function checkAndClearLog(array $messages = [], array $notmessages = [], $dump = FALSE) {
    $this->drupalGet('admin/reports/dblog');

    if ($dump) {
      $this->dumpContent();
    }

    foreach ($messages as $message) {
      $this->assertSession()->responseContains($message);
    }
    foreach ($notmessages as $message) {
      $this->assertSession()->responseNotContains($message);
    }

    $this->drupalGet('admin/reports/dblog/confirm');
    $this->submitForm([], 'Confirm');
  }

  /**
   * Asserts that code formatting did not change the code.
   *
   * @param string $formatted
   *   Formatted code to check.
   * @param string $file
   *   File name to read code from, to check against.
   */
  protected function assertCodeFormatting($formatted, $file) {
    $original = file_get_contents($file);

    // In formatted output, strip out the formatting tags, and then decode
    // HTML entities, which should get us back to the original HTML that was
    // in the file. Hopefully. That is what we're testing in this assert.
    $formatted = html_entity_decode(strip_tags($formatted));

    // Remove vertical whitespace. We used to remove spaces at ends of lines,
    // but now we test that there are none.
    $patterns = [
      '|\n+|' => "\n",
    ];
    foreach ($patterns as $pattern => $replace) {
      $original = preg_replace($pattern, $replace, $original);
      $formatted = preg_replace($pattern, $replace, $formatted);
    }

    // Trim and compare.
    $original = trim($original);
    $formatted = trim($formatted);

    // White spaces and the pretty printing might actually change a bit the code
    // and then assertEquals will be false.
    $percent = 0;
    similar_text($original, $formatted, $percent);
    $this->assertGreaterThanOrEqual(90, $percent, "Formatted code matches code in $file");
  }

  /**
   * {@inheritdoc}
   *
   * Just check the keys of the inputs and see if we can find the right ones.
   */
  protected function submitForm(array $edit, $submit, $form_html_id = NULL) {
    $assert_session = $this->assertSession();
    $form = isset($form_html_id) ?
      $assert_session->elementExists('xpath', "//form[@id='$form_html_id']") :
      $assert_session->elementExists('xpath', './ancestor::form', $assert_session->buttonExists($submit));

    // Many inputs just have the '[0][value]' appended to the name, so let's
    // check it and add it if needed. That way we don't need to always add that
    // suffix to all our inputs, making them tests more readable.
    $edit_tmp = [];
    foreach ($edit as $name => $value) {
      if ($form->findField($name . '[0][value]')) {
        $edit_tmp[$name . '[0][value]'] = $value;
      }
      elseif ($form->findField($name . '[value]')) {
        $edit_tmp[$name . '[value]'] = $value;
      }
      else {
        $edit_tmp[$name] = $value;
      }
    }
    $edit = $edit_tmp;

    parent::submitForm($edit, $submit, $form_html_id);
  }

}
