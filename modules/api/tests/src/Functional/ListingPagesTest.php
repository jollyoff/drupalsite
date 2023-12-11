<?php

namespace Drupal\Tests\api\Functional;

/**
 * Tests the API module listing pages (functions, classes, etc.).
 */
class ListingPagesTest extends WebPagesBase {

  /**
   * Tests that listing pages have the right information.
   */
  public function testListingPages() {
    // Test the Functions page.
    $this->drupalGet('api/' . $this->branchInfo['project'] . '/functions');
    $this->assertSession()->linkExists('duplicate_function', 0, 'duplicate_function is on functions list page');
    $this->assertSession()->linkExists('sample_class_function', 0, 'sample_class_function is on functions list page');
    $this->assertSession()->responseContains('For testing duplicate');
    $this->assertSession()->responseContains('A sample function');
    $this->assertSession()->linkExists('duplicates.php', 0, 'duplicates.php file name is on functions list page');
    $this->assertSession()->linkExists('sample_insubdir_function', 0, 'subdir function is on functions list page');
    $this->assertSession()->linkExists('sample-subdir.php', 0, 'sample-subdir.php file name is on functions list page');
    $this->assertSession()->responseNotContains('excluded_function');
    $this->assertSession()->responseNotContains('hidden_function');
    $this->assertSession()->responseNotContains('hidden_function_two');
    $this->assertSession()->linkNotExists('Other projects', 'Link to other projects is not present since there are no others');
    $this->assertSession()->responseContains('Deprecated');
    $this->assertSession()->responseContains('11');
    $this->assertSession()->responseContains('Direct uses');
    $this->assertSession()->responseContains('Strings');

    // Verify file/function links.
    $this->assertLinkUrlSubstring('sample_function', $this->branchInfo['project'] . '/sample.php/function/sample_function', 'sample_function link exists', 'sample_function link went to the right place');
    $this->assertLinkUrlSubstring('sample.php', $this->branchInfo['project'] . '/sample.php', 'sample.php link exists', 'sample.php link went to the right place');
    $this->clickLink('sample_insubdir_function');
    $this->assertSession()->responseContains('Used for sample and testing URLs');
    $this->drupalGet('api/' . $this->branchInfo['project'] . '/functions');
    $this->clickLink('sample-subdir.php');
    $this->assertSession()->responseContains('A sample global in a subdirectory');
    $this->assertSession()->responseContains('A sample file in a subdirectory');

    // Test the Constants page.
    $this->drupalGet('api/' . $this->branchInfo['project'] . '/constants');
    $this->assertSession()->linkExists('DUPLICATE_CONSTANT', 0, 'DUPLICATE_CONSTANT is on constants list page');
    $this->assertSession()->responseContains('For testing duplicate constant linking');
    $this->assertSession()->responseContains('A sample constant');
    $this->assertSession()->linkExists('duplicates.php', 0, 'duplicates.php file name is on constants list page');
    $this->assertSession()->linkExists('SAMPLE_CONSTANT_IN_SUB_DIR', 0, 'subdir constant is on constants list page');
    $this->assertSession()->linkExists('sample-subdir.php', 0, 'sample-subdir.php file name is on constants list page');
    $this->assertSession()->linkNotExists('Other projects', 'Link to other projects is not present since there are no others');
    $this->assertSession()->responseContains('Deprecated');
    $this->assertSession()->responseContains('2');
    $this->assertSession()->responseContains('Direct uses');
    $this->assertSession()->responseContains('Strings');

    // Verify constant/function links.
    $this->assertLinkUrlSubstring('SAMPLE_CONSTANT', $this->branchInfo['project'] . '/sample.php/constant/SAMPLE_CONSTANT', 'SAMPLE_CONSTANT link exists', 'SAMPLE_CONSTANT link went to the right place');
    $this->assertLinkUrlSubstring('sample.php', $this->branchInfo['project'] . '/sample.php', 'sample.php link exists', 'sample.php link went to the right place');
    $this->clickLink('SAMPLE_CONSTANT_IN_SUB_DIR');
    $this->assertSession()->responseContains('A sample constant');
    $this->drupalGet('api/' . $this->branchInfo['project'] . '/constants');
    $this->clickLink('sample-subdir.php');
    $this->assertSession()->responseContains('A sample global in a subdirectory');
    $this->assertSession()->responseContains('A sample file in a subdirectory');

    // Test the Classes page.
    $this->drupalGet('api/' . $this->branchInfo['project'] . '/classes');
    $this->assertSession()->responseContains('Say something interesting about classes here');
    $this->assertSession()->linkExists('sample_function', 0, 'See also link is shown in header');
    $this->assertSession()->linkExists('Sample2', 0, 'Sample2 is on classes list page');
    $this->assertSession()->linkExists('SubSample', 0, 'SubSample is on classes list page');
    $this->assertSession()->linkExists('SampleInterfaceTwo', 0, 'SampleInterfaceTwo is on classes list page');
    $this->assertSession()->linkExists('SampleInSubDir', 0, 'Subdir sample is on classes list page');
    $this->assertSession()->responseContains('Sample class.');
    $this->assertSession()->responseContains('Sample interface.');
    $this->assertSession()->linkExists('classes-subdir.php', 0, 'classes-subdir.php file name is on classes list page');
    $this->assertSession()->responseNotContains('baz');
    $this->assertSession()->linkNotExists('Other projects', 'Link to other projects is not present since there are no others');
    $this->assertSession()->responseContains('Deprecated');
    $this->assertSession()->responseContains('3');
    $this->assertSession()->responseContains('Direct uses');
    $this->assertSession()->responseContains('Use statements');
    $this->assertSession()->responseContains('Strings');

    // Verify file/class links.
    $this->assertLinkUrlSubstring('Sample', $this->branchInfo['project'] . '/classes.php/class/Sample', 'Sample link exists', 'Sample link went to the right place');
    $this->assertLinkUrlSubstring('classes.php', $this->branchInfo['project'] . '/classes.php', 'classes.php link exists', 'classes.php link went to the right place');
    $this->assertLinkUrlSubstring('SampleInterface', $this->branchInfo['project'] . '/classes.php/interface/SampleInterface', 'SampleInterface link exists', 'SampleInterface link went to the right place');
    $this->clickLink('Sample2InSubDir');
    $this->assertSession()->responseContains('Implements foo2.');
    $this->assertSession()->pageTextContains('Sample2InSubDir implements');
    $this->drupalGet('api/' . $this->branchInfo['project'] . '/classes');
    $this->clickLink('classes-subdir.php');
    $this->assertSession()->responseContains('Classes in a subdirectory test');
    $this->assertSession()->responseContains('Another Sample interface in a subdirectory');

    // Test the Files page.
    $this->drupalGet('api/' . $this->branchInfo['project'] . '/files');
    $this->assertSession()->linkExists('sample.php', 0, 'sample.php file name is on files list page');
    $this->assertSession()->linkExists('duplicates.php', 0, 'duplicates.php file name is on files list page');
    $this->assertSession()->linkExists('classes-subdir.php', 0, 'sub-directory file name is on files list page');
    $this->assertSession()->responseContains('A sample file');
    $this->assertSession()->responseContains('Object-oriented tests');
    $this->assertSession()->responseNotContains('excluded.php');
    $this->assertSession()->responseNotContains('hidden.php');
    $this->assertSession()->responseNotContains('hidden2.php');
    $this->assertSession()->linkNotExists('Other projects', 'Link to other projects is not present since there are no others');
    $this->assertSession()->responseContains("Sample HTML file's title");
    $this->assertSession()->linkExists('htmlfile.html', 0, 'HTML file link is present');
    $this->assertSession()->responseContains('This is a sample text file for testing.');
    $this->assertSession()->linkExists('textfile.txt', 0, 'Text file link is present');
    $this->assertSession()->responseContains('Deprecated');

    // Verify file links.
    $this->assertLinkUrlSubstring('classes.php', $this->branchInfo['project'] . '/classes.php', 'classes.php link exists', 'classes.php link went to the right place');
    $this->assertLinkUrlSubstring('sample--doubledash.tpl.php', $this->branchInfo['project'] . '/sample--doubledash.tpl.php', 'doubledash link exists', 'doubledash link went to the right place');
    $this->clickLink('classes-subdir.php');
    $this->assertSession()->responseContains('Classes in a subdirectory test');
    $this->assertSession()->responseContains('Another Sample interface in a subdirectory');

    // Test the Globals page.
    $this->drupalGet('api/' . $this->branchInfo['project'] . '/globals');
    $this->assertSession()->linkExists('$sample_in_sub_dir_global', 0, '$sample_in_sub_dir_global is on the globals list page');
    $this->assertSession()->responseContains('A sample global.');
    $this->assertSession()->linkExists('sample-subdir.php', 0, 'sample-subdir.php file name is on globals list page');
    $this->assertSession()->linkNotExists('Other projects', 'Link to other projects is not present since there are no others');
    $this->assertSession()->responseContains('Deprecated');

    // Verify global/file links.
    $this->assertLinkUrlSubstring('$sample_global', $this->branchInfo['project'] . '/sample.php/global/sample_global', 'sample_global link exists', 'sample_global link went to the right place');
    $this->assertLinkUrlSubstring('sample.php', $this->branchInfo['project'] . '/sample.php', 'sample.php link exists', 'sample.php link went to the right place');
    $this->clickLink('$sample_in_sub_dir_global');
    $this->assertSession()->responseContains('A sample global in a subdirectory');
    $this->drupalGet('api/' . $this->branchInfo['project'] . '/globals');
    $this->clickLink('sample-subdir.php');
    $this->assertSession()->responseContains('A sample global in a subdirectory');
    $this->assertSession()->responseContains('A sample file in a subdirectory');

    // Test the Topics page.
    $this->drupalGet('api/' . $this->branchInfo['project'] . '/groups');
    $this->assertSession()->linkExists('Class Samples', 0, 'Class Samples topic is on the topics page');
    $this->assertSession()->responseContains('A sample group of classes. Should not include members');
    $this->assertSession()->responseContains('A sample group');
    $this->assertSession()->linkNotExists('Other projects', 'Link to other projects is not present since there are no others');

    // Verify topic link.
    $this->assertLinkUrlSubstring('Samples', $this->branchInfo['project'] . '/sample.php/group/samp_GRP-6.x', 'Samples link exists', 'Samples link went to the right place');

    // Test the Deprecated page.
    $this->drupalGet('api/' . $this->branchInfo['project'] . '/deprecated');
    $this->assertSession()->linkExists('$sample_global', 0, 'Global is on deprecated page');
    $this->assertSession()->linkExists('sample-three.tpl.php', 0, 'File is on deprecated page');
    $this->assertSession()->linkExists('Sample::$property', 0, 'Property is on deprecated page');
    $this->assertSession()->linkExists('sample_class_function', 0, 'Function is on deprecated page');
    $this->assertSession()->linkExists('SAMPLE_CONSTANT', 0, 'Constant is on deprecated page');
    $this->assertSession()->linkExists('SubSample', 0, 'Class is on deprecated page');
    $this->assertSession()->linkExists('sample.php', 0, 'File containing deprecated items is linked on deprecated page');
    $this->assertSession()->linkExists('classes.php', 0, 'Other file containing deprecated items is linked on deprecated page');
    $this->assertSession()->linkNotExists('sample_function', 'Non-deprecated function is not on deprecated page');
    $this->assertSession()->responseContains('1');
    $this->assertSession()->responseContains('Direct uses');
    $this->assertSession()->responseContains('Namespaced uses');
    $this->assertSession()->responseContains('Overrides');
    $this->assertSession()->responseContains('Use statements');

    // Test the Elements page.
    $this->drupalGet('api/' . $this->branchInfo['project'] . '/elements');
    $this->assertSession()->responseContains('sub_sample');
    $this->assertSession()->responseContains('subdir_sample');
    $this->assertSession()->responseContains('FormElement');
    $this->assertSession()->responseContains('RenderElement');
    $this->assertSession()->linkExists('SubSample', 0);
    $this->assertSession()->linkExists('SubInSubDirSample', 0);
  }

}
