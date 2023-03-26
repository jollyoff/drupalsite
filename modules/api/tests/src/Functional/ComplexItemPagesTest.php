<?php

namespace Drupal\Tests\api\Functional;

/**
 * Tests the API module complex individual item pages (class, topic, etc.).
 */
class ComplexItemPagesTest extends WebPagesBase {

  /**
   * Runs all the tests in this set, to avoid multiple setUp() calls.
   */
  public function testAll() {
    $this->verifyClassPages();
    $this->verifyTopicPages();
    $this->verifyFilePages();
  }

  /**
   * Tests that class pages have the right information.
   */
  protected function verifyClassPages() {
    // Visit a class page.
    $this->drupalGet('api/' . $this->branchInfo['project'] . '/classes.php/class/Sample');

    // Check the text on the page.
    $this->assertTitleContains('Sample', 'Class page title includes class name');
    $this->assertSession()->responseContains('Sample class.');
    $this->assertSession()->linkExists('classes.php', 0, 'Link to file is present on class page');
    $this->assertSession()->linkExists('Class Samples', 0, 'Topic link appears on the class page');
    $this->assertSession()->responseContains('A sample group of classes.');
    $this->assertSession()->linkExists('SampleInterface', 0, 'Link to interface appears on class page');
    $this->assertSession()->linkExists('Sample::$property', 0, 'Link to property appears on class page');
    $this->assertSession()->responseContains('A property.');
    $this->assertSession()->linkExists('Sample::baz', 0, 'Link to method appears on class page');
    $this->assertSession()->responseContains('Only implemented in children.');
    $this->assertSession()->linkExists('Sample::foo', 0, 'Link to second method appears on class page');
    $this->assertSession()->responseContains('Metasyntatic member function.');
    $this->assertSession()->responseContains('public');
    $this->assertSession()->linkExists('Sample::CONSTANT', 0, 'Link to constant appears on class page');
    $this->assertSession()->responseContains('A class constant.');
    $this->assertSession()->responseContains('* Only implemented in children.');
    $this->assertSession()->responseContains('class Sample');
    $this->assertSession()->responseContains('Deprecated');
    $this->assertSession()->linkExists('SubSample::foo', 0, 'Link to inherited member is made');

    // Plugin annotation section.
    $this->assertSession()->responseContains('Plugin annotation');
    $this->assertSession()->pageTextContains('A great plugin title');
    $this->assertSession()->linkExists('foo_sample_name', 0, 'Link to function name in plugin annotation is there');
    $this->assertSession()->linkExists('SubSample', 0, 'Link to class in plugin annotation is there');
    $this->assertSession()->linkExists('SampleInSubDir', 0, 'Link to annotation class is there');
    $this->assertSession()->responseContains('&lt;em&gt; &lt;strong&gt; &lt;blockquote&gt; &lt;anothertag&gt;');

    // Click the links on the page and verify.
    $this->assertLinkUrlSubstring('classes.php', $this->branchInfo['project'] . '/classes.php', 'classes.php link exists', 'classes.php link went to the right place');
    $this->assertLinkUrlSubstring('Class Samples', $this->branchInfo['project'] . '/classes.php/group/class_samples', 'Class Samples link exists', 'Class Samples link went to the right place');

    $this->clickLink('Expanded class hierarchy of Sample');
    $this->assertUrlContains($this->branchInfo['project'] . '/classes.php/class/hierarchy/Sample', 'Hierarchy link went to the right place');
    $this->assertSession()->linkExists('SubSample', 0, 'Link to subclass appears on hierarchy page');
    $this->assertSession()->linkExists('SampleInterfaceTwo', 0, 'Link to subclass interface appears on hierarchy page');
    $this->assertSession()->responseContains('InterfaceNotDefinedHere');

    $this->drupalGet('api/' . $this->branchInfo['project'] . '/classes.php/class/Sample');
    $this->clickLink('SampleInterface');
    $this->assertUrlContains($this->branchInfo['project'] . '/classes.php/interface/SampleInterface', 'Interface link went to the right place');
    // While we're here, check some text...
    $this->assertSession()->pageTextContains('interface SampleInterface');
    // Visit the implements page and verify classes are there.
    $this->clickLink('All classes that implement SampleInterface');
    $this->assertSession()->linkExists('Sample', 0, 'Link to sample class appears on interface page');
    $this->assertSession()->linkExists('Sample2', 0, 'Link to sample2 class appears on interface page');

    // Back on the interface page, click through to method and verify overrides
    // link.
    $this->drupalGet('api/' . $this->branchInfo['project'] . '/classes.php/interface/SampleInterface');
    $this->clickLink('SampleInterface::foo');
    $this->assertSession()->pageTextContains('2 methods override SampleInterface::foo()');
    $this->assertSession()->linkExists('Sample::foo', 0, 'First override is found');
    $this->assertSession()->linkExists('SampleInSubDir::foo', 0, 'Second override is found');

    // Back on the class page, verify the property page.
    $this->drupalGet('api/' . $this->branchInfo['project'] . '/classes.php/class/Sample');
    $this->clickLink('Sample::$property');
    $this->assertUrlContains($this->branchInfo['project'] . '/classes.php/property/Sample', 'Property link went to the right place');
    // While here, check text...
    $this->assertTitleContains('Sample::$property', 'Property page title includes property name');
    $this->assertSession()->linkExists('SampleInterface', 0, 'Link to type appears on property page');
    $this->assertSession()->responseContains('A property');
    $this->assertSession()->responseContains('variable value');
    $this->assertSession()->responseContains('Some text to go after the var line');
    $this->assertSession()->responseContains('Just a bit more text and it is done.');
    $this->assertSession()->linkExists('Sample', 0, 'Link to class is there');
    $this->assertSession()->responseContains('Sample class.');
    // Check deprecated text too.
    $this->assertSession()->responseContains('Deprecated');
    $this->assertSession()->responseContains('This property is deprecated for sample purposes.');

    $this->drupalGet('api/' . $this->branchInfo['project'] . '/classes.php/class/Sample');
    $this->clickLink('Sample::baz');
    $this->assertUrlContains($this->branchInfo['project'] . '/classes.php/function/Sample', 'Method link went to the right place');
    // While here, check text...
    $this->assertTitleContains('Sample::baz', 'Method page title includes method name');
    $this->assertSession()->pageTextContains('public function');
    $this->assertSession()->responseContains('Only implemented in children');
    $this->assertSession()->pageTextContains('2 calls to Sample::baz()');
    $this->assertSession()->linkExists('Sample', 0, 'Link to class is there');
    $this->assertSession()->linkExists('Sample::CONSTANT', 0, 'Link to constant is there');
    $this->assertLinkUrlSubstring('self::foo', 'classes.php/function/Sample', 'Link using self is there', 'Link using self goes to the right place');
    $this->assertSession()->responseContains('Sample class.');
    $this->assertSession()->pageTextContains('public function baz');
    $this->assertSession()->linkExists('property', 0, 'Link to static property is there');

    // And check the calling functions links.
    $this->assertSession()->linkExists('Sample::foo', 0, 'Calling function 1 is there');
    $this->assertSession()->linkExists('SubSample::bar', 0, 'Calling function 2 link is there');

    $this->drupalGet('api/' . $this->branchInfo['project'] . '/classes.php/class/Sample');
    $this->clickLink('Sample::CONSTANT');
    $this->assertUrlContains($this->branchInfo['project'] . '/classes.php/constant/Sample', 'Constant link went to the right place');
    // While here, check text...
    $this->assertTitleContains('Sample::CONSTANT', 'Constant page title includes constant name');
    $this->assertSession()->pageTextContains('constant value');
    $this->assertSession()->responseContains('A class constant');
    $this->assertSession()->linkExists('Sample', 0, 'Link to class is there');
    $this->assertSession()->responseContains('Sample class.');

    // Visit the subclass page and verify the extends info is in the hierarchy.
    $this->drupalGet('api/' . $this->branchInfo['project'] . '/classes.php/class/SubSample');
    $this->assertSession()->responseContains('InterfaceNotDefinedHere');
    $this->assertSession()->linkExists('SampleInterface', 0, 'Class hierarchy shows implements of parent');
    // Also verify the deprecated text.
    $this->assertSession()->responseContains('Deprecated');
    $this->assertSession()->responseContains('This class is deprecated for sample purposes.');
    // And verify the parent link.
    $this->assertLinkUrlSubstring('parent::baz', 'classes.php/function/Sample', 'Link using parent is there', 'Link using parent goes to the right place');

    // Visit a class in a subdirectory and verify.
    $this->drupalGet('api/' . $this->branchInfo['project'] . '/subdirectory!classes-subdir.php/class/SampleInSubDir');
    $this->assertSession()->responseContains('Sample class in a subdirectory.');
    $this->assertSession()->responseContains('SampleInSubDir');
    $this->assertSession()->responseContains('subdirectory/');
    $this->assertSession()->linkExists('classes-subdir.php', 0, 'classes-subdir.php file name is on subdirectory class page');
    $this->assertLinkUrlSubstring('SampleInSubDir::$foo', 'classes-subdir.php/property/SampleInSubDir', 'foo property link is present', 'foo property link goes to right place');
    $this->assertLinkUrlSubstring('SampleInSubDir::foo', 'classes-subdir.php/function/SampleInSubDir', 'foo function link is present', 'foo function link goes to right place');
    // Check on annotation.
    $this->assertSession()->pageTextContains('1 class is annotated with SampleInSubDir');
    $this->assertSession()->linkExists('Sample', 0, 'Link to annotated class is present');

    $this->clickLink('classes-subdir.php');
    $this->assertSession()->responseContains('Classes in a subdirectory test');
    $this->assertSession()->pageTextContains('Another Sample interface in a subdirectory.');

    // Visit the two methods and verify the links.
    $this->drupalGet('api/' . $this->branchInfo['project'] . '/subdirectory!classes-subdir.php/class/SampleInSubDir');
    $this->clickLink('SampleInSubDir::baz');
    $this->assertLinkUrlSubstring('foo', 'classes-subdir.php/function/SampleInSubDir', 'foo function link is present', 'foo function link goes to right place');
    $this->clickLink('foo');
    $this->assertLinkUrlSubstring('foo', 'classes-subdir.php/property/SampleInSubDir', 'foo property link is present', 'foo property link goes to right place');
    $this->assertSession()->pageTextContains('1 call to SampleInSubDir::foo()');
    $this->assertSession()->pageTextContains('1 method overrides SampleInSubDir::foo()');
    $this->assertSession()->linkExists('SubInSubDirSample::foo', 0, 'Overriding function link is there');
    $this->assertSession()->linkExists('classes-subdir.php', 0, 'Overriding function file link is there');
    $this->assertSession()->responseContains('Overrides parent function.');

    // Visit the class that is for overrides testing.
    $this->drupalGet('api/' . $this->branchInfo['project'] . '/subdirectory!classes-subdir.php/class/SubInSubDirSample');
    // Verify override links for properties.
    $this->assertSession()->linkExists('SubInSubDirSample::$property_in_sub_dir', 0, 'Override property link is there');
    $this->assertSession()->responseContains('Overrides parent property');
    $this->assertSession()->linkExists('SampleInSubDir::$property_in_sub_dir', 0, 'Overriden property link is there');
    $this->assertSession()->responseContains('A protected property for testing');
    $this->clickLink('SubInSubDirSample::$property_in_sub_dir');
    $this->assertSession()->linkExists('SampleInSubDir::$property_in_sub_dir', 0, 'Overriden property link is on property page');

    // Verify override links for methods.
    $this->drupalGet('api/' . $this->branchInfo['project'] . '/subdirectory!classes-subdir.php/class/SubInSubDirSample');
    $this->assertSession()->linkExists('SampleInSubDir::baz', 0, 'Non-overridden function link is there');
    $this->assertSession()->linkExists('SubInSubDirSample::bar', 0, 'Overridden function link is there');
    $this->assertSession()->responseContains('A public method');
    $this->assertSession()->responseContains('Overrides parent function');
    $this->assertSession()->linkExists('SubInSubDirSample::foo', 0, 'Link to override method is there');
    $this->assertSession()->linkExists('SampleInSubDir::foo', 0, 'Link to overridden method is there');
    $this->clickLink('SubInSubDirSample::foo');
    $this->assertSession()->linkExists('SampleInSubDir::foo', 0, 'Link to overridden method is on method page');

    // Verify override links for constants.
    $this->drupalGet('api/' . $this->branchInfo['project'] . '/subdirectory!classes-subdir.php/class/SubInSubDirSample');
    $this->assertSession()->linkExists('SubInSubDirSample::CONSTANT', 0, 'Override constant link is there');
    $this->assertSession()->responseContains('Overrides parent constant');
    $this->assertSession()->linkExists('SampleInSubDir::CONSTANT', 0, 'Overridden constant link is there');
    $this->clickLink('SubInSubDirSample::CONSTANT');
    $this->assertSession()->linkExists('SampleInSubDir::CONSTANT', 0, 'Overridden constant link is on constant page');

    // Verify references for render element.
    $this->drupalGet('api/' . $this->branchInfo['project'] . '/subdirectory!classes-subdir.php/class/SubInSubDirSample');
    $this->assertSession()->pageTextContains('1 #type use of SubInSubDirSample');
    $this->assertSession()->linkExists('sample_function', 0, 'Link to element reference is there');
    $this->clickLink('sample_function');
    $this->assertSession()->linkExists('subdir_sample', 0, 'Element machine name is linked');
    $this->clickLink('subdir_sample');
    $this->assertTitleContains('SubInSubDirSample', 'Element link went to the right place');

    // Go back to the classes page with the breaks tag set, and verify.
    $this->config('api.settings')
      ->set('breaks_tag', '<wbr />')
      ->save();
    $this->clearCache();

    $this->drupalGet('api/' . $this->branchInfo['project'] . '/classes.php/class/Sample');
    $this->assertSession()->responseContains('/<wbr />');

    // Set breaks tag back to empty to continue with testing.
    $this->config('api.settings')
      ->set('breaks_tag', '')
      ->save();
    $this->clearCache();
  }

  /**
   * Tests that topic pages have the right information.
   */
  protected function verifyTopicPages() {
    // Test the Sample topic page.
    $this->drupalGet('api/' . $this->branchInfo['project'] . '/sample.php/group/samp_GRP-6.x');
    $this->assertSession()->responseContains('A sample group');
    $this->assertTitleContains('Samples', 'Topic page title includes topic name');
    $this->assertSession()->linkExists('sample_function', 0, 'sample_function link is on sample topic page');
    $this->assertSession()->responseContains('A sample function');
    $this->assertSession()->linkExists('sample_class_function', 0, 'sample_class_function link is on sample topic page');
    $this->assertSession()->linkExists('sample.php', 0, 'sample.php file name is on sample topic page');
    $this->assertSession()->linkExists('SAMPLE_CONSTANT', 0, 'SAMPLE_CONSTANT link is on sample topic page');
    $this->assertSession()->responseContains('A sample constant.');
    $this->assertSession()->linkExists('$sample_global', 0, 'sample_global link is on sample topic page');
    $this->assertSession()->responseContains('A sample global.');
    $this->assertSession()->linkExists('sample_insubdir_function', 0, 'sample_insubdir_function link is on sample topic page');
    $this->assertSession()->linkExists('sample--doubledash.tpl.php', 0, 'sample--doubledash.tpl.php link is shown');
    $this->assertSession()->responseContains('Displays a sample with a doubledash');
    $this->assertSession()->responseContains('Deprecated');
    $this->assertSession()->responseContains('&lt;em&gt; &lt;strong&gt; &lt;blockquote&gt; &lt;anothertag&gt;');
    $this->assertSession()->responseContains('"{$entity-&gt;bundle()}"');

    // Verify section, sub-section, and ref links.
    $this->assertSession()->responseContains('<h3 id="sec_one">Section 1</h3>');
    $this->assertSession()->responseContains('<h3 id="sec_two">Section 2</h3>');
    $this->assertSession()->responseContains('<h4 id="sub_a">Sub-section A</h4>');
    $this->assertSession()->responseContains('<a href="#sub_a">Sub-section A</a>');
    $this->assertSession()->responseContains('<a href="#sec_two">Section 2</a>');

    // Verify links.
    $this->assertLinkUrlSubstring('sample_function', $this->branchInfo['project'] . '/sample.php/function/sample_function', 'sample_function link exists', 'sample_function link went to the right place');
    $this->assertLinkUrlSubstring('sample.php', $this->branchInfo['project'] . '/sample.php', 'sample.php link exists', 'sample.php link went to the right place');
    $this->assertLinkUrlSubstring('SAMPLE_CONSTANT', $this->branchInfo['project'] . '/sample.php/constant/SAMPLE_CONSTANT', 'SAMPLE_CONSTANT link exists', 'SAMPLE_CONSTANT link went to the right place');
    $this->assertLinkUrlSubstring('$sample_global', $this->branchInfo['project'] . '/sample.php/global/sample_global', 'sample_global link exists', 'sample_global link went to the right place');

    // Now try the Sample Classes topic and make sure only classes and not
    // members appear there.
    $this->drupalGet('api/' . $this->branchInfo['project'] . '/classes.php/group/class_samples');
    $this->assertSession()->responseContains('A sample group of classes. Should not include members');
    $this->assertTitleContains('Class Samples', 'Topic page title includes topic name');
    $this->assertSession()->responseContains('Sample class.');
    $this->assertSession()->linkExists('Sample2', 0, 'Sample2 class link is on topic page');
    $this->assertSession()->linkExists('SubSample', 0, 'SubSample class link is on topic page');
    $this->assertSession()->responseContains('Sample interface.');
    $this->assertSession()->linkExists('SampleInterfaceTwo', 0, 'SampleInterface2 link is on topic page');
    $this->assertSession()->responseNotContains('baz');

    // Verify link destinations.
    $this->assertLinkUrlSubstring('Sample', $this->branchInfo['project'] . '/classes.php/class/Sample', 'Sample link exists', 'Sample link went to the right place');
    $this->assertLinkUrlSubstring('SampleInterface', $this->branchInfo['project'] . '/classes.php/interface/SampleInterface', 'SampleInterface link exists', 'SampleInterface link went to the right place');
    $this->assertLinkUrlSubstring('classes.php', $this->branchInfo['project'] . '/classes.php', 'classes.php link exists', 'classes.php link went to the right place');
  }

  /**
   * Tests that file pages have the right information.
   */
  protected function verifyFilePages() {
    $this->drupalGet('api/' . $this->branchInfo['project'] . '/sample.php/' . $this->branchInfo['branch_name']);
    $this->assertSession()->responseContains('A sample file');
    $this->assertTitleContains('sample.php', 'File page title includes file name');
    $this->assertSession()->linkExists('sample_function', 0, 'sample_function link is on sample file page');
    $this->assertSession()->responseContains('A sample function');
    $this->assertSession()->linkExists('sample_class_function', 0, 'sample_class_function link is on sample file page');
    $this->assertSession()->responseContains('sample.php');
    $this->assertSession()->linkExists('SAMPLE_CONSTANT', 0, 'SAMPLE_CONSTANT link is on sample file page');
    $this->assertSession()->responseContains('A sample constant.');
    $this->assertSession()->linkExists('sample_global', 0, 'sample_global link is on sample file page');
    $this->assertSession()->responseContains('A sample global.');
    $this->assertSession()->responseContains('* Use for sample-related purposes.');
    $this->assertSession()->responseContains('Deprecated');

    // Verify links.
    $this->assertLinkUrlSubstring('sample_function', $this->branchInfo['project'] . '/sample.php/function/sample_function', 'sample_function link exists', 'sample_function link went to the right place');
    $this->assertLinkUrlSubstring('SAMPLE_CONSTANT', $this->branchInfo['project'] . '/sample.php/constant/SAMPLE_CONSTANT', 'SAMPLE_CONSTANT link exists', 'SAMPLE_CONSTANT link went to the right place');
    $this->assertLinkUrlSubstring('sample_global', $this->branchInfo['project'] . '/sample.php/global/sample_global', 'sample_global link exists', 'sample_global link went to the right place');

    // Now try the classes file.
    $this->drupalGet('api/' . $this->branchInfo['project'] . '/classes.php/' . $this->branchInfo['branch_name']);
    $this->assertSession()->responseContains('Object-oriented tests');
    $this->assertTitleContains('classes.php', 'File page title includes file name');
    $this->assertSession()->linkExists('Sample', 0, 'Sample class link is on file page');
    $this->assertSession()->responseContains('Sample class.');
    $this->assertSession()->linkExists('Sample2', 0, 'Sample2 class link is on file page');
    $this->assertSession()->linkExists('SubSample', 0, 'SubSample class link is on file page');
    $this->assertSession()->linkExists('SampleInterface', 0, 'SampleInterface link is on file page');
    $this->assertSession()->responseContains('Sample interface.');
    $this->assertSession()->linkExists('SampleInterfaceTwo', 0, 'SampleInterface2 link is on file page');
    $this->assertSession()->responseContains('* Only implemented in children.');

    // Verify link destinations.
    $this->assertLinkUrlSubstring('Sample', $this->branchInfo['project'] . '/classes.php/class/Sample', 'Sample link exists', 'Sample link went to the right place');
    $this->assertLinkUrlSubstring('SampleInterface', $this->branchInfo['project'] . '/classes.php/interface/SampleInterface', 'SampleInterface link exists', 'SampleInterface link went to the right place');

    // Now try a file in a subdirectory.
    $this->drupalGet('api/' . $this->branchInfo['project'] . '/subdirectory!sample-subdir.php/' . $this->branchInfo['branch_name']);
    $this->assertSession()->responseContains('A sample file in a subdirectory.');
    $this->assertSession()->responseContains('sample-subdir.');
    $this->assertSession()->linkExists('sample_insubdir_function', 0, 'Function link appears on subdirectory file page');
    $this->clickLink('sample_insubdir_function');
    $this->assertSession()->responseContains('Used for sample and testing URLs');

    // Now try a file with -- in the name.
    $this->drupalGet('api/' . $this->branchInfo['project'] . '/sample--doubledash.tpl.php/' . $this->branchInfo['branch_name']);
    $this->assertSession()->responseContains('Displays a sample with a doubledash.');

    // Now try the HTML sample file.
    $this->drupalGet('api/' . $this->branchInfo['project'] . '/htmlfile.html/' . $this->branchInfo['branch_name']);
    $this->assertTitleContains("Sample HTML file's title", 'HTML file page has right title');
    $this->assertSession()->responseContains("Sample HTML file's body");
    $this->assertSession()->responseContains("Sample HTML file's title");
    $this->assertSession()->responseContains('head');

    // Now try the text sample file.
    $this->drupalGet('api/' . $this->branchInfo['project'] . '/subdirectory!textfile.txt/' . $this->branchInfo['branch_name']);
    $this->assertTitleContains('textfile.txt', 'Text file page has right title');
    $this->assertSession()->responseContains('This is a sample text file for testing.');

    // Now try the one-function file.
    $this->drupalGet('api/' . $this->branchInfo['project'] . '/onefunction.php/' . $this->branchInfo['branch_name']);
    $this->assertSession()->linkExists('only_function_in_file', 0, 'Function link is present from one-function file.');

    // Now the deprecated file.
    $this->drupalGet('api/' . $this->branchInfo['project'] . '/sample-three.tpl.php/' . $this->branchInfo['branch_name']);
    $this->assertSession()->responseContains('Deprecated');
    $this->assertSession()->responseContains('This file is deprecated for sample purposes.');
  }

}
