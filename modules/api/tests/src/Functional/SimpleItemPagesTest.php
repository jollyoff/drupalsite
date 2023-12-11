<?php

namespace Drupal\Tests\api\Functional;

/**
 * Tests the API module simple individual item pages (function, constant, etc.).
 */
class SimpleItemPagesTest extends WebPagesBase {

  /**
   * Overrides WebPagesBase::setUp() so we have the PHP branch.
   */
  protected function setUp() : void {
    $this->baseSetUp();

    // Create a "file" branch with the sample code, from the admin interface.
    $this->branchInfo = $this->setUpBranchUi();

    // Create a "php" branch with the sample PHP function list, from the admin
    // interface.
    $this->createPhpBranchUi();

    // Parse the code.
    $this->clearCache();
    $this->cronRun();
    $this->processApiParseQueue();
  }

  /**
   * Runs all tests in this section, so setUp() doesn't run multiple times.
   */
  public function testAll() {
    $this->verifyConstantPage();
    $this->verifyFunctionPage();
    $this->verifyGlobalPage();
  }

  /**
   * Tests that constant pages have the right information.
   */
  protected function verifyConstantPage() {
    // Visit a constant page.
    $this->drupalGet('api/' . $this->branchInfo['project'] . '/sample.php/constant/DUPLICATE_CONSTANT');

    // Check the text on the page.
    $this->assertTitleContains('DUPLICATE_CONSTANT', 'Constant page title includes constant name');
    $this->assertSession()->pageTextContains('For testing duplicate constant linking.');
    $this->assertSession()->pageTextContains("define('DUPLICATE_CONSTANT'");
    $this->assertSession()->pageTextNotContains('Class');

    // Verify link destinations.
    $this->assertLinkUrlSubstring($this->branchInfo['branch_name'] . ' duplicates.php', $this->branchInfo['project'] . '/duplicates.php/constant/DUPLICATE_CONSTANT', 'Other version link exists', 'Other version link went to the right place');
    $this->assertLinkUrlSubstring('sample.php', $this->branchInfo['project'] . '/sample.php', 'sample.php link exists', 'sample.php link went to the right place');

    // Visit a constant in a subdirectory and verify.
    $this->drupalGet('api/' . $this->branchInfo['project'] . '/subdirectory!sample-subdir.php/constant/SAMPLE_CONSTANT_IN_SUB_DIR');
    $this->assertSession()->pageTextContains('A sample constant');
    $this->assertSession()->pageTextContains('SAMPLE_CONSTANT_IN_SUB_DIR');
    $this->assertSession()->pageTextContains('subdirectory/');
    $this->assertSession()->linkExists('sample-subdir.php', 0, 'sample-subdir.php file name is on subdirectory constant page');
    $this->clickLink('sample-subdir.php');
    $this->assertSession()->pageTextContains('A sample global in a subdirectory');
    $this->assertSession()->pageTextContains('A sample file in a subdirectory');

    // Visit a deprecated constant.
    $this->drupalGet('api/' . $this->branchInfo['project'] . '/sample.php/constant/SAMPLE_CONSTANT');
    $this->assertSession()->pageTextContains('Deprecated');
    $this->assertSession()->pageTextContains('This constant is deprecated for sample purposes.');
    $this->assertSession()->pageTextContains('interesting, to test in-code');
    $this->assertLinkUrlSubstring('sample.php', $this->branchInfo['project'] . '/sample.php', 'sample.php link exists', 'sample.php link went to the right place');
    $this->assertLinkUrlSubstring('sample_in_code_links', $this->branchInfo['project'] . '/sample.php/function/sample_in_code_links', 'sample_in_code_links link exists', 'sample_in_code_links link went to the right place');
  }

  /**
   * Tests that function pages have the right information.
   */
  protected function verifyFunctionPage() {
    // Visit a function page.
    $this->drupalGet('api/' . $this->branchInfo['project'] . '/sample.php/function/duplicate_function');

    // Check the text on the page.
    $this->assertTitleContains('duplicate_function', 'Function page title includes function name');
    $this->assertSession()->pageTextContains('For testing duplicate function name linking.');
    $this->assertSession()->pageTextContains("function duplicate_function()");
    $this->assertSession()->pageTextContains('$foo = sample_function()');
    $this->assertSession()->linkExists('sample.php', 0, 'Link to file is present on function page');
    $this->assertSession()->linkExists($this->branchInfo['branch_name'] . ' duplicates.php', 0, 'Link to other version is present on function page');
    $this->assertSession()->linkExists('duplicate_function', 0, 'Second link to other version is present on function page');
    $this->assertSession()->pageTextContains('1 call to duplicate_function()');
    $this->assertSession()->pageTextContains('function duplicate_function');

    // Test the links made with @link on this function.
    $this->assertLinkUrlSubstring('Subscribers', $this->branchInfo['project'] . '/sample.php/group/samp_GRP-6.x', 'Subscribers link exists', 'Subscribers link went to the right place');
    $this->assertLinkUrlSubstring('subscription', $this->branchInfo['project'] . '/sample.php/group/samp_GRP-6.x', 'Subscribers link exists', 'Subscribers link went to the right place');
    $this->assertLinkUrlSubstring('newsletter issues', $this->branchInfo['project'] . '/classes.php/group/class_samples', 'Newsletter link exists', 'Newsletter link went to the right place');
    $this->assertLinkUrlSubstring('newsletters (categories)', $this->branchInfo['project'] . '/classes.php/group/class_samples', 'Newsletter categories link exists', 'Newsletter categories link went to the right place');

    // Click the automatically-generated links on the page and verify.
    $this->assertLinkUrlSubstring($this->branchInfo['branch_name'] . ' duplicates.php', $this->branchInfo['project'] . '/duplicates.php/function/duplicate_function', 'Other version link exists', 'Other version link went to the right place');
    $this->assertLinkUrlSubstring('sample.php', $this->branchInfo['project'] . '/sample.php', 'sample.php link exists', 'sample.php link went to the right place');

    // Verify the calling functions links.
    $this->assertSession()->linkExists('sample_in_code_links', 0, 'Calling function link is present');
    $this->assertSession()->pageTextContains('Does something interesting, to test');

    // Verify the calling functions links for the lots of calls case.
    $this->drupalGet('api/' . $this->branchInfo['project'] . '/sample.php/function/sample_function');
    $this->assertSession()->pageTextContains('11 calls to');
    $this->assertSession()->linkExists('another_sample', 0, 'Calling function link is there');
    $this->assertSession()->linkExists('duplicate_function', 0, 'Calling function link is there');
    $this->assertSession()->linkExists('foo_sample_name', 0, 'Calling function link is there');
    $this->assertSession()->linkExists('hook_another_sample_alter', 0, 'Calling function link is there');
    $this->assertSession()->linkExists('sample_in_code_links', 0, 'Calling function link is there');
    $this->assertSession()->linkExists('... See full list', 0, 'Full list link is present');
    $this->clickLink('... See full list');
    $this->assertTitleContains('11 calls to sample_function()', 'Title is correct');
    $this->assertUrlContains('api/' . $this->branchInfo['project'] . '/sample.php/function/calls/sample_function', 'URL is correct for calling functions');
    $this->assertSession()->linkExists('another_sample', 0, 'Calling function link is there');
    $this->assertSession()->linkExists('duplicate_function', 0, 'Calling function link is there');
    $this->assertSession()->linkExists('foo_sample_name', 0, 'Calling function link is there');
    $this->assertSession()->linkExists('hook_another_sample_alter', 0, 'Calling function link is there');
    $this->assertSession()->linkExists('sample_in_code_links', 0, 'Calling function link is there');
    $this->assertSession()->linkExists('sample_name', 0, 'Calling function link is there');
    $this->assertSession()->linkExists('sample_one', 0, 'Calling function link is there');
    $this->assertSession()->linkExists('theme_sample_four', 0, 'Calling function link is there');
    $this->assertSession()->linkExists('theme_sample_one', 0, 'Calling function link is there');
    $this->assertSession()->linkExists('theme_sample_two', 0, 'Calling function link is there');

    // Verify the referenced functions link and page.
    $this->drupalGet('api/' . $this->branchInfo['project'] . '/sample.php/function/sample_function');
    $this->assertSession()->pageTextContains('1 string reference to');
    $this->assertSession()->linkExists('sample_in_code_links', 0, 'Link to referencing function is present');
    $this->assertSession()->pageTextContains('Does something interesting');

    // Test the function implementations link and page.
    $this->drupalGet('api/' . $this->branchInfo['project'] . '/sample.php/function/hook_sample_name');
    $this->assertSession()->pageTextContains('1 function implements');
    $this->assertSession()->linkExists('foo_sample_name', 0, 'Link to implementing function page is present');
    $this->assertSession()->pageTextContains('Implements hook_sample_name');

    // Verify that the wrong-case text did not turn into links.
    $this->assertSession()->linkNotExists('SAMPLE_FUNCTION', 'Wrong-case function did not turn into a link');
    $this->assertSession()->linkNotExists('sample_constant', 'Wrong-case constant did not turn into a link');

    // Test the hook invocations link and page.
    $this->drupalGet('api/' . $this->branchInfo['project'] . '/sample.php/function/sample_in_code_links');
    $this->clickLink('sample_name');
    $this->assertUrlContains('api/' . $this->branchInfo['project'] . '/sample.php/function/hook_sample_name', 'URL is correct for hook link');
    $this->assertSession()->pageTextContains('2 invocations of');
    $this->assertSession()->linkExists('sample_in_code_links', 0, 'Link to invoking function page is present');
    $this->assertSession()->pageTextContains('Does something interesting');
    $this->assertSession()->linkExists('sample_name', 0, 'Link to second invoking function page is present');

    // Test hook invocation links for alter hook.
    $this->drupalGet('api/' . $this->branchInfo['project'] . '/sample.php/function/sample_in_code_links');
    $this->clickLink('another_sample');
    $this->assertUrlContains('api/' . $this->branchInfo['project'] . '/sample.php/function/hook_another_sample_alter', 'URL is correct for hook link');
    $this->assertSession()->pageTextContains('2 invocations of');

    // Test the theme invokes page.
    $this->drupalGet('api/' . $this->branchInfo['project'] . '/sample.php/function/sample_in_code_links');
    $this->clickLink('sample_one');
    $this->assertSession()->pageTextContains('3 theme calls to');
    $this->assertSession()->linkExists('sample_in_code_links', 0, 'Link to theme calling function is present');
    $this->assertSession()->linkExists('sample_one', 0, 'Link to second theme calling function is present');
    $this->assertSession()->linkExists('sample-two.tpl.php', 0, 'Link to theme calling template file is present');

    // Test the theme invokes page from a template.
    $this->drupalGet('api/' . $this->branchInfo['project'] . '/sample.php/function/sample_in_code_links');
    $this->clickLink('sample_two');
    $this->assertSession()->pageTextContains('2 theme calls to');
    $this->assertSession()->linkExists('sample_in_code_links', 0, 'Link to theme calling function is present');
    $this->assertSession()->linkExists('sample_function', 0, 'Link to theme string reference function is present');

    // Also test the theme invokes page from the same-hook function.
    $this->drupalGet('api/' . $this->branchInfo['project'] . '/sample.php/function/theme_sample_two');
    $this->assertSession()->pageTextContains('2 theme calls to');
    $this->assertSession()->linkExists('sample_in_code_links', 0, 'Link to theme calling function is present');
    $this->assertSession()->linkExists('sample_function', 0, 'Link to theme string reference function is present');

    // Try the other duplicate_function() page, and verify the calling
    // functions are there too.
    $this->drupalGet('api/' . $this->branchInfo['project'] . '/duplicates.php/function/duplicate_function');
    $this->assertSession()->pageTextContains('1 call to');
    $this->assertSession()->linkExists('sample_in_code_links', 0, 'Calling function link is present');
    $this->assertSession()->pageTextContains('Does something interesting, to test');

    // Also check the links in duplicate_function() to the two PHP branches.
    $this->drupalGet('api/' . $this->branchInfo['project'] . '/duplicates.php/function/duplicate_function');
    $this->assertLinkUrl('foo_function', 'http://example.com/function/foo_function', 'foo_function is linked', 'foo_function link goes to right place');
    $this->assertLinkUrl('bar_function', 'http://example.com/function/bar_function', 'bar_function is linked', 'bar_function link goes to right place');
    $this->assertSession()->linkNotExists('not_a_function', 'not_a_function() is not linked');
    $this->assertLinkUrl('substr', 'http://php.net/substr', 'substr function is linked', 'substr function link goes to the right place');

    // Now try the function page with more complicated documentation.
    $this->drupalGet('api/' . $this->branchInfo['project'] . '/sample.php/function/sample_function');

    // Note that the unit tests verify a lot of other stuff, such as the
    // presence of text when loading a function, so we'll just look for some of
    // the text on the page.
    $this->assertSession()->pageTextContains('Use for sample-related purposes');
    $this->assertSession()->pageTextContains('A generic parameter');
    $this->assertSession()->pageTextContains('Something about the return value');
    $this->assertSession()->linkExists('this is a link for the parameter', 0, 'Link appears on the function page');
    $this->assertSession()->linkExists('duplicate_function', 0, 'See also link appears on the function page');
    $this->assertSession()->linkExists('Samples', 0, 'Topic link appears on the function page');
    $this->assertSession()->pageTextContains('A sample group.');
    $this->assertSession()->linkExists('htmlfile.html', 0, 'Link to HTML file is there');
    $this->assertSession()->linkExists('textfile.txt', 0, 'Link to text file is there');
    $this->assertSession()->linkExists('classes.php', 0, 'Link to php file is there');
    $this->assertSession()->linkExists('HTML link text', 0, 'Link to HTML file using @link is there');
    $this->assertSession()->linkExists('Text link text', 0, 'Link to text file using @link is there');
    $this->assertSession()->linkExists('PHP link text', 0, 'Link to PHP file using @link is there');

    // Verify list and parameter formatting -- again the unit tests take care of
    // some of this.
    $strong_lists = $this->xpath('//li/strong');
    $this->assertEquals(3, count($strong_lists), 'key1, key2 and key3 are highlighted, and not http');

    $strong_pars = $this->xpath('//p/strong');
    $this->assertGreaterThanOrEqual(1, count($strong_pars), 'parameter is highlighted on function page');

    // Now try the function page with class parameters -- test the links.
    $this->drupalGet('api/' . $this->branchInfo['project'] . '/sample.php/function/sample_class_function');
    $this->assertSession()->linkExists('SubSample', 0, 'Parameter type is linked');
    $this->assertSession()->linkExists('SampleInterface', 0, 'Return value type is linked');
    $this->assertLinkUrlSubstring('SubSample', $this->branchInfo['project'] . '/classes.php/class/SubSample', 'SubSample link exists', 'SubSample link went to the right place');
    $this->assertLinkUrlSubstring('SampleInterface', $this->branchInfo['project'] . '/classes.php/interface/SampleInterface', 'SampleInterface link exists', 'SampleInterface link went to the right place');
    $this->assertSession()->linkExists('sample_function', 0, 'Function in code sample is linked');
    $this->assertSession()->linkExists('sample_one', 0, 'Theme invoke in code sample is linked');
    $this->assertSession()->linkExists('sample_name', 0, 'Hook in code sample is linked');
    $this->assertSession()->linkExists('another_sample', 0, 'Alter hook in code sample is linked');
    $this->assertSession()->linkExists('SAMPLE_CONSTANT', 0, 'Constant in code sample is linked');
    // Check the deprecated text.
    $this->assertSession()->pageTextContains('Deprecated');
    $this->assertSession()->pageTextContains('This function is deprecated for sample purposes.');
    $this->assertSession()->linkExists('sample_in_code_links', 0, 'Link in deprecated section appears');

    // Verify the "functions that call this" text exists and references the
    // classes.php file, which calls this function from the global scope.
    $this->assertSession()->pageTextContains('1 call to');
    $this->assertSession()->linkExists('classes.php', 0, 'Link to classes.php is there');
    $this->assertSession()->pageTextContains('Object-oriented tests.');

    // Visit a function in a subdirectory and verify.
    $this->drupalGet('api/' . $this->branchInfo['project'] . '/subdirectory!sample-subdir.php/function/sample_insubdir_function');
    $this->assertSession()->pageTextContains('Another sample function; in a sub-directory.');
    $this->assertSession()->pageTextContains('sample_insubdir_function');
    $this->assertSession()->pageTextContains('subdirectory/');
    $this->assertSession()->linkExists('sample-subdir.php', 0, 'sample-subdir.php file name is on subdirectory function page');
    $this->clickLink('sample-subdir.php');
    $this->assertSession()->pageTextContains('A sample global in a subdirectory');
    $this->assertSession()->pageTextContains('A sample file in a subdirectory');
  }

  /**
   * Tests that global pages have the right information.
   */
  protected function verifyGlobalPage() {
    // Visit a global page.
    $this->drupalGet('api/' . $this->branchInfo['project'] . '/sample.php/global/sample_global');

    // Check the text on the page.
    $this->assertTitleContains('$sample_global', 'Global page title includes global name');
    $this->assertSession()->pageTextContains('A sample global.');
    $this->assertSession()->linkExists('sample.php', 0, 'Link to file is present on global page');
    $this->assertSession()->linkExists('Samples', 0, 'Topic link appears on the global page');
    $this->assertSession()->pageTextContains('A sample group.');
    $this->assertSession()->pageTextContains('global $sample_global');

    // Verify deprecated text.
    $this->assertSession()->pageTextContains('Deprecated');
    $this->assertSession()->pageTextContains('This global is deprecated for sample purposes.');

    // Verify that the wrong-case text did not turn into links.
    $this->assertSession()->linkNotExists('SAMPLE_FUNCTION', 'Wrong-case function did not turn into a link');
    $this->assertSession()->linkNotExists('sample_constant', 'Wrong-case constant did not turn into a link');

    // Verify that the correct-case text embedded in other stuff didn't link.
    $this->assertSession()->linkNotExists('sample_function', 'Function name embedded in other text did not turn into a link');
    $this->assertSession()->linkNotExists('SAMPLE_CONSTANT', 'Constant name embedded in other text did not turn into a link');
    $this->assertSession()->pageTextContains('http://example.com/samp_GRP-6.x');

    // Click the links on the page and verify.
    $this->assertLinkUrlSubstring('sample.php', $this->branchInfo['project'] . '/sample.php', 'sample.php link exists', 'sample.php link went to the right place');
    $this->assertLinkUrlSubstring('Samples', $this->branchInfo['project'] . '/sample.php/group/samp_GRP-6.x', 'Samples link exists', 'Samples link went to the right place');

    // Visit a global in a subdirectory and verify.
    $this->drupalGet('api/' . $this->branchInfo['project'] . '/subdirectory!sample-subdir.php/global/sample_in_sub_dir_global');
    $this->assertSession()->pageTextContains('A sample global in a subdirectory');
    $this->assertSession()->pageTextContains('$sample_in_sub_dir_global');
    $this->assertSession()->pageTextContains('subdirectory/');
    $this->assertSession()->linkExists('sample-subdir.php', 0, 'sample-subdir.php file name is on subdirectory global page');
    $this->clickLink('sample-subdir.php');
    $this->assertSession()->pageTextContains('A sample global in a subdirectory');
    $this->assertSession()->pageTextContains('A sample file in a subdirectory');
  }

}
