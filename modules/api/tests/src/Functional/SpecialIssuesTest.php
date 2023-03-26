<?php

namespace Drupal\Tests\api\Functional;

use Drupal\api\Entity\DocBlock;

/**
 * Tests that files containing special issues can be dealt with.
 */
class SpecialIssuesTest extends WebPagesBase {

  /**
   * {@inheritdoc}
   */
  protected function setUp() : void {
    $this->baseSetUp(['api_test']);

    // Set up a files branch with the special files directory.
    $this->branchInfo = $this->setUpBranchUi(NULL, TRUE, [
      'directory' => $this->apiModulePath . '/tests/files/special_files',
      'excluded' => '',
    ]);

    // We don't need the PHP branch for this test, so for speed, remove it.
    $this->removePhpBranch();

    // Parse the code.
    $this->clearCache();
    $this->cronRun();
    $this->processApiParseQueue();

    // Display the navigation block.
    $this->drupalPlaceBlock('api_navigation_block');
  }

  /**
   * Runs the other tests, to avoid reparsing multiple times.
   */
  public function testAll() {
    $this->verifyFormatting();
    $this->verifyUnicode();
    $this->verifyBadFileHeader();
    $this->verifyExtendsLoops();
    $this->verifyTwig();
    $this->verifyYamlServices();
    $this->verifyParseAlterHook();
    $this->verifyReferences();
    $this->verifySearchCaseSensitivity();
  }

  /**
   * Tests that code formatting matches original code for PHP files.
   */
  protected function verifyFormatting() {
    // ClassWithUnicode.php is omitted, because it has a Unicode error in
    // the file (intentionally), so the formatted output does not work.
    $files = [
      'badheader.php',
      'ClassExtendsLoop.php',
      'class_loop.php',
      'dup_names.php',
      'php54.php',
    ];
    foreach ($files as $file) {
      $object_id = DocBlock::findFileByFileName($file, $this->getBranch());
      $object = DocBlock::load($object_id);
      $this->assertCodeFormatting($object->getCode(), $this->branchInfo['directory'] . '/' . $object->getFileName());
    }
  }

  /**
   * Tests that files with Unicode can be dealt with.
   */
  protected function verifyUnicode() {
    // Verify that both files are present on the Files page.
    $this->drupalGet('api/' . $this->branchInfo['project'] . '/files');
    $this->assertSession()->linkExists('AllUnicodeChars.txt', 0, 'Unicode text file is there');
    $this->assertSession()->linkExists('ClassWithUnicode.php', 0, 'Unicode class file is there');
    $this->assertSession()->responseContains('Tests that Unicode characters can be dealt with.');

    // Verify the text file page.
    $this->drupalGet('api/' . $this->branchInfo['project'] . '/AllUnicodeChars.txt/' . $this->branchInfo['branch_name']);
    $this->assertSession()->responseContains('abcdefghij');
    $this->assertSession()->responseContains('àáâãä');
    $this->assertSession()->responseContains('ਙਚਛਜ');
    $this->assertSession()->responseContains('ゾタダ');
    $this->assertSession()->responseContains('AllUnicodeChars.txt');

    // Verify the class is listed on the classes page.
    $this->drupalGet('api/' . $this->branchInfo['project'] . '/classes');
    $this->assertSession()->linkExists('CacheArray', 0, 'Link to class is there');
    $this->assertSession()->responseContains('Non-displayable characters.',);

    // Click through to the class page and verify the methods are shown.
    $this->clickLink('CacheArray');
    $this->assertSession()->responseContains('Non-displayable characters.');
    $this->assertSession()->pageTextContains('CacheArray implements ArrayAccess');
    $this->assertSession()->linkExists('CacheArray::$cid', 0, 'Property link is shown');
    $this->assertSession()->responseContains('A cid to pass to');
    $this->assertSession()->linkExists('CacheArray::persist', 0, 'Method link is shown');
    $this->assertSession()->responseContains('Flags an offset value');
    $this->assertSession()->responseContains('$this');
    $this->assertSession()->responseContains('written to the persistent');
    $this->assertSession()->responseContains('keysToPersist');
  }

  /**
   * Tests that a file with defgroup/mainpage in the file header can be parsed.
   */
  protected function verifyBadFileHeader() {
    // This file is in the special_files directory. Check that it triggered
    // three log messages when it was parsed.
    $this->drupalGet('admin/reports/dblog');
    $this->assertSession()->responseContains('@file docblock containing @defgroup');
    $this->assertSession()->responseContains('Item docblock containing');
    $this->assertSession()->responseContains('Duplicate item found in file');

    // Verify that the file is listed and the file's page can be visited.
    $this->drupalGet('api/' . $this->branchInfo['project'] . '/files');
    $this->assertLinkUrlSubstring('badheader.php', $this->branchInfo['project'] . '/badheader.php', 'File link exists', 'File link goes to right place');
    $this->clickLink('badheader.php');
    $this->assertSession()->linkExists('badheader_fun', 0, 'Function link is present on file page');
    $this->assertTitleContains('badheader.php', 'Page title has file name');

    // Verify that the class is listed on this page, and not the method.
    $this->assertSession()->linkExists('ClassWithDefgroupDocBlock', 0, 'Class with defgroup docblock appears on file page');
    $this->assertSession()->linkNotExists('ClassWithDefgroupDocBlock::foo', 'Method in class with defgroup docblock does not appear on file page');

    // Verify that the duplicate function is listed, and it's the right
    // version.
    $this->clickLink('twice_in_one_file');
    $this->assertSession()->responseContains('First duplicate function in same file');
    $this->assertSession()->responseNotContains('Second duplicate function in same file');
  }

  /**
   * Tests that circular class dependencies do not screw us up.
   */
  protected function verifyExtendsLoops() {
    $this->drupalGet('api/' . $this->branchInfo['project'] . '/ClassExtendsLoop.php/class/FirstClass');
    $this->assertSession()->responseContains('First class');
    $this->clickLink('Expanded class hierarchy of FirstClass');
    $this->assertSession()->responseContains('SecondClass');
    $this->assertSession()->responseContains('ThirdClass');
  }

  /**
   * Tests Twig files.
   */
  protected function verifyTwig() {
    $this->drupalGet('api/' . $this->branchInfo['project'] . '/files');
    $this->assertSession()->responseContains('Sample Twig template file, taken from');
    $this->assertSession()->responseNotContains('Available variables');

    // Visit the Twig template file and verify the theme call link is there.
    $this->clickLink('sample.html.twig');
    $this->assertSession()->responseContains('Sample Twig template file');
    $this->assertSession()->linkExists('badheader_fun', 0, 'Link to theme calls is present');

    // Click through to the badheader_fun() function and check the theme link.
    $this->clickLink('badheader_fun');
    $this->assertLinkUrlSubstring('sample', '/sample.html.twig', 'Theme call turns into link', 'Link goes to right place');
  }

  /**
   * Tests searching case sensitivity.
   */
  protected function verifySearchCaseSensitivity() {
    // Search for lower-case function name should get to the function page.
    $this->drupalGet('api/' . $this->branchInfo['project'] . '/' . $this->branchInfo['branch_name'] . '/search');
    $this->submitForm(['search' => 'sample_function'], 'Search');
    $this->assertUrlContains('dup_names.php/function/sample_function', 'Got to function page with lower-case search');

    // Search for upper-case function name should get to the constant page.
    $this->drupalGet('api/' . $this->branchInfo['project'] . '/' . $this->branchInfo['branch_name'] . '/search');
    $this->submitForm(['search' => 'SAMPLE_FUNCTION'], 'Search');
    $this->assertUrlContains('dup_names.php/constant/SAMPLE_FUNCTION', 'Got to constant page with upper-case search');
  }

  /**
   * Tests services YAML file parsing and display.
   */
  protected function verifyYamlServices() {
    // Test the Services listing page.
    $this->drupalGet('api/' . $this->branchInfo['project'] . '/services/');
    $this->assertSession()->linkExists('cache_context.url', 0, 'Service is linked');
    $this->assertSession()->linkExists('test.services.yml', 0, 'Services file is linked');
    $this->assertSession()->responseContains('Alias of config.storage.active');
    $this->assertSession()->responseContains('Abstract');
    $this->assertSession()->responseContains('CacheArray');

    // Test individual services pages.
    $this->clickLink('route_enhancer.param_conversion');
    $this->assertSession()->responseContains('Tags');
    $this->assertSession()->responseContains('event_subscriber');
    $this->assertSession()->responseContains('ParamConversionEnhancer');

    $this->drupalGet('api/' . $this->branchInfo['project'] . '/test.services.yml/service/container.trait');
    $this->assertSession()->responseContains('Abstract');

    $this->drupalGet('api/' . $this->branchInfo['project'] . '/test.services.yml/service/config.storage');
    $this->assertSession()->responseContains('Alias of');

    $this->drupalGet('api/' . $this->branchInfo['project'] . '/test.services.yml/service/cache_context.url');
    $this->assertSession()->linkExists('CacheArray', 0, 'Class is linked');
    $this->assertSession()->linkExists('test.services.yml', 0, 'File is linked');
    $this->assertSession()->responseContains('@request');

    // Test that the class page links back to the service.
    $this->clickLink('CacheArray');
    $this->assertSession()->pageTextContains('1 service uses CacheArray');
    $this->assertSession()->linkExists('cache_context.url', 0, 'Service is linked');

    // Test that in the method page, there is a link to the service it
    // references.
    $this->clickLink('CacheArray::persist');
    $this->assertLinkUrlSubstring('container.trait', 'test.services.yml/service/container.trait', 'Link to service name exists', 'Link goes to right place');

    // Test that the service page shows references.
    $this->clickLink('container.trait');
    $this->assertSession()->pageTextContains('1 string reference to container.trait');
    $this->assertSession()->linkExists('CacheArray::persist', 0, 'Link to referencing method is there');
  }

  /**
   * Tests hook_api_parse_functions_alter().
   *
   * The test module implements this to make sure files with extension 'foo'
   * can be parsed. Verify that the function in this file shows up on the
   * site.
   */
  protected function verifyParseAlterHook() {
    $this->drupalGet('api/' . $this->branchInfo['project'] . '/functions/');
    $this->assertSession()->responseContains('only_function_in_file');
  }

  /**
   * Verifies hook invokes and calls are recognized and linked correctly.
   */
  protected function verifyReferences() {
    // Info array is keyed by hook name. Value is list of invokes that should
    // be detected.
    $info = [
      'hook_foo' => [
        'regular_invoke_bootstrap',
        'regular_invoke_get_implementations',
        'regular_invoke_implements_hook',
        'regular_invoke_invoke',
        'regular_invoke_invoke_all',
        'regular_invoke_module_hook',
        'regular_invoke_module_implements',
        'regular_invoke_module_invoke',
        'regular_invoke_module_invoke_all',
        'regular_invoke_node_invoke',
      ],
      'hook_field_boo' => ['field_invoke_one', 'field_invoke_two'],
      'hook_field_foo' => ['field_invoke_one', 'field_invoke_two'],
      'hook_entity_coo' => ['entity_invoke'],
      'hook_entity_foo' => ['entity_invoke'],
      'hook_node_doo' => ['entity_invoke'],
      'hook_user_moo' => ['user_invoke'],
      'hook_user_foo' => ['user_invoke'],
      'hook_noo_alter' => ['alter_invoke_one', 'alter_invoke_two'],
      'hook_foo_alter' => ['alter_invoke_one'],
    ];

    foreach ($info as $hook => $invokes) {
      $hookname = substr($hook, -3);
      if ($hookname == 'ter') {
        // It was an alter hook.
        $hookname = substr($hook, -9, 3);
      }
      $count = count($invokes);

      // Visit invokes page; verify count and links.
      $this->drupalGet('api/' . $this->branchInfo['project'] . '/hooks.php/function/invokes/' . $hook);
      if ($count == 1) {
        $this->assertSession()->responseContains('1 invocation');
      }
      else {
        $this->assertSession()->responseContains($count . ' invocations');
      }
      foreach ($invokes as $invoke) {
        $this->assertSession()->linkExists($invoke);
      }

      // Verify that the hook "function" is called once from the control
      // structures function.
      $this->drupalGet('api/' . $this->branchInfo['project'] . '/hooks.php/function/calls/' . $hook);
      $this->assertSession()->responseContains('1 call to');
      $this->assertSession()->linkExists('control_structures');

      // Visit each invoking function page; verify links go to the correct
      // hooks, and verify each function is called from the control structures
      // function.
      foreach ($invokes as $invoke) {
        $this->drupalGet('api/' . $this->branchInfo['project'] . '/hooks.php/function/' . $invoke);
        $this->assertLinkUrlSubstring($hookname, 'hooks.php/function/' . $hook, 'Link to hook ' . $hook . ' exists on ' . $invoke, 'Link to hook ' . $hook . ' goes to right hook on ' . $invoke);
        $this->drupalGet('api/' . $this->branchInfo['project'] . '/hooks.php/function/calls/' . $invoke);
        $this->assertSession()->responseContains('1 call to');
        $this->assertSession()->linkExists('control_structures');
      }
    }
  }

}
