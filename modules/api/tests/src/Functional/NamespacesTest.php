<?php

namespace Drupal\Tests\api\Functional;

use Drupal\api\Entity\DocBlock;

/**
 * Tests that namespace and YAML functionality works.
 */
class NamespacesTest extends WebPagesBase {

  /**
   * {@inheritdoc}
   */
  protected function setUp() : void {
    $this->baseSetUp();

    // Set up a files branch for the namespaces files.
    $this->branchInfo = $this->setUpBranchUi(NULL, TRUE, [
      'directory' => $this->apiModulePath . '/tests/files/sample_namespace',
      'excluded' => '',
    ]);

    // Set up a files branch for the second namespace branch files.
    $this->branchInfo2 = $this->setUpBranchUi(NULL, FALSE, [
      'directory' => $this->apiModulePath . '/tests/files/sample_namespace2',
      'branch_name' => 'second_b',
      'title' => 'Second branch',
      'excluded' => '',
    ]);

    // We don't need the PHP branch for this test, so for speed, remove it.
    $this->removePhpBranch();

    // Parse the code.
    $this->clearCache();
    $this->cronRun();
    $this->processApiParseQueue();
  }

  /**
   * Runs all the tests, so that setUp() isn't done multiple times.
   */
  public function testAll() {
    $this->verifyNamespacePages();
    $this->verifyYaml();
  }

  /**
   * Tests that the namespace pages and namespaced objects work fine.
   */
  protected function verifyNamespacePages() {
    // Test namespaces page.
    $this->drupalGet('api/' . $this->branchInfo['project'] . '/namespaces');
    $this->assertSession()->linkExists('api\\test1', 0, 'First namespace link is there');
    $this->assertSession()->linkExists('api\\test2', 0, 'Second namespace link is there');

    // Test contents of namespace page.
    $this->clickLink('api\\test1');
    $this->assertSession()->linkExists('ClassA', 0, 'ClassA is on first namespace page');
    $this->assertSession()->linkExists('InterfaceD', 0, 'InterfaceD is on first namespace page');
    $this->assertSession()->responseContains('InterfaceD.php');
    $this->assertSession()->linkNotExists('ClassB', 'ClassB is not on first namespace page');
    $this->assertSession()->responseContains('Sample class in a namespace.');
    $this->assertSession()->linkExists('TraitF', 0, 'TraitF is on first namespace page');
    $this->assertSession()->responseContains('A sample trait.');
    // Verify that the link to the other branch is there.
    $this->assertSession()->responseContains('Same name in other branches');
    $this->assertSession()->linkExists($this->branchInfo2['branch_name'] . ' ' . 'api\\test1');

    // Test that namespace link is on class page.
    $this->clickLink('ClassA');
    $this->assertSession()->responseContains('Namespace');
    $this->assertSession()->linkExists('api\\test1', 0, 'Namespace link is on class page');

    // Test linking in docs to some functions etc.
    $this->assertSession()->linkExists('ClassB::bMethod', 0, 'Link to aliased class method is made');
    $this->assertSession()->linkExists('another_function', 0, 'Link to non namespaced function is made');
    $this->assertSession()->linkExists('InterfaceD::dMethod', 0, 'Link to interface method in same namespace is made');
    $this->clickLink('api\\test1');
    $this->assertSession()->linkExists('InterfaceD', 0, 'Link went back to namespace page');

    // Verify that on method/function pages, param types make links, and that
    // incorrect interface overrides are not present.
    $this->clickLink('InterfaceD');
    $this->assertSession()->responseNotContains('InterfaceC::dMethod');
    $this->clickLink('InterfaceD::dMethod');
    $this->assertSession()->linkExists('\\api\\test2\\InterfaceC', 0, 'Link to param type is there');

    // Verify that namespace page URLs work without branch name.
    $this->drupalGet('api/' . $this->branchInfo['project'] . '/namespace/api!test2');
    $this->assertSession()->linkExists('ClassB');
    $this->drupalGet('api/' . $this->branchInfo['project'] . '/namespace/api!test2/' . $this->branchInfo['branch_name']);
    $this->assertSession()->linkExists('ClassB');

    // Test contents of second namespace page.
    $this->drupalGet('api/' . $this->branchInfo['project'] . '/namespaces');
    $this->clickLink('api\\test2');
    $this->assertSession()->linkExists('ClassB', 0, 'ClassB is on second namespace page');
    $this->assertSession()->linkExists('ClassE', 0, 'ClassE is on second namespace page');
    $this->assertSession()->linkExists('InterfaceC', 0, 'InterfaceC is on second namespace page');
    $this->assertSession()->linkExists('InterfaceD', 0, 'InterfaceD is on second namespace page');
    $this->assertSession()->responseContains('InterfaceD2.php');
    $this->assertSession()->linkNotExists('ClassA', 'ClassA is not on second namespace page');

    // Verify that on class variable pages, types make links.
    $this->clickLink('ClassE');
    $this->clickLink('ClassE::$foobar');
    $this->assertSession()->linkExists('\\api\\test1\\InterfaceD', 0, 'Link to type of member variable is there');

    // Verify that incorrect interface overrides are not present.
    $this->drupalGet('api/' . $this->branchInfo['project'] . '/namespaces');
    $this->clickLink('api\\test2');
    $this->clickLink('InterfaceC');
    $this->assertSession()->responseNotContains('InterfaceD::cMethod');

    // Verify namespace links on file pages.
    $this->drupalGet('api/' . $this->branchInfo['project'] . '/ClassA.php/' . $this->branchInfo['branch_name']);
    $this->assertSession()->responseContains('Namespace', 'Namespace header is on file page');
    $this->assertSession()->linkExists('api\\test1', 0, 'Namespace link is on file page');
    $this->clickLink('api\\test1');
    $this->assertSession()->linkExists('InterfaceD', 0, 'Link went back to namespace page');

    // Verify lack of namespace links on no-namespace files.
    $this->drupalGet('api/' . $this->branchInfo['project'] . '/no_namespace.php/' . $this->branchInfo['branch_name']);
    $this->assertSession()->linkNotExists('api\\test1', 'Namespace link is not on non-namespaced file page');
    $this->assertSession()->linkNotExists('api\\test2', 'Namespace link is not on non-namespaced file page');
    $this->assertSession()->linkExists('ClassQ', 0, 'ClassQ is on the file page');
    $this->drupalGet('api/' . $this->branchInfo['project'] . '/no_namespace.php/class/ClassQ/' . $this->branchInfo['branch_name']);
    $this->assertSession()->linkNotExists('api\\test1', 'Namespace link is not on non-namespaced class page');
    $this->assertSession()->linkNotExists('api\\test2', 'Namespace link is not on non-namespaced class page');

    // Test use references.
    $this->drupalGet('api/' . $this->branchInfo['project'] . '/ClassB.php/class/ClassB');

    $this->assertSession()->pageTextContains('1 file declares its use of ClassB');
    $this->assertSession()->linkExists('ClassA.php', 0, 'Link to file using class is present');

    // Verify traits are listed on the Classes page.
    $this->drupalGet('api/' . $this->branchInfo['project'] . '/classes');
    $this->assertSession()->linkExists('TraitF', 0, 'Trait appears on classes page');

    // Verify trait, class, interface turn into links on Files page
    // (from @file tag first lines).
    $this->drupalGet('api/' . $this->branchInfo['project'] . '/files');
    $this->assertSession()->pageTextContains('\api\test1\TraitF');
    $this->assertSession()->pageTextContains('\api\test1\ClassA');
    $this->assertSession()->pageTextContains('\api\test2\InterfaceC');

    // Verify trait page.
    $this->drupalGet('api/' . $this->branchInfo['project'] . '/TraitF.php/trait/TraitF');
    $this->assertSession()->responseContains('A sample trait.');
    $this->assertSession()->responseContains('Longer description of the trait');
    $this->assertSession()->linkExists('api\test1', 0, 'Namespace link is present on trait page');
    $this->assertSession()->linkExists('TraitF::$fvar', 0, 'Trait property link is present on trait page');
    $this->assertSession()->linkExists('TraitF::xyz', 0, 'Trait method link is present on trait page');
    $this->assertSession()->linkExists('ClassE.php', 0, 'Link to file using trait is present on trait page');

    // Verify page for class using trait.
    $this->drupalGet('api/' . $this->branchInfo['project'] . '/ClassE.php/class/ClassE');
    $this->assertSession()->linkExists('TraitF::$fvar', 0, 'Trait property link is present on using class page');
    $this->assertSession()->linkExists('TraitF::xyz', 0, 'Trait method link is present on using class page');
    $this->assertSession()->linkExists('TraitG::abc', 0, 'Trait method link is present on using class page');
    $this->assertSession()->linkExists('TraitF', 0, 'Link to used trait is present on class page');
    $this->assertSession()->linkExists('TraitG', 0, 'Link to used trait is present on class page');
    // Verify insteadof.
    $this->assertSession()->linkExists('TraitG::def', 0, 'Trait method insteadof link is present on using class page');
    $this->assertSession()->linkNotExists('TraitF::def', 'Trait method omitted with insteadof is not present');

    // Verify links in code for class using trait.
    $this->clickLink('ClassE::eMethod');
    $this->assertSession()->linkExists('fvar', 0, 'Property from trait turns into link');
    $this->assertSession()->linkExists('xyz', 0, 'Method from trait turns into link');
    $this->clickLink('xyz');
    $this->assertSession()->responseContains('Method to inherit');

    // Verify link and reference for non-namespaced class.
    $this->drupalGet('api/' . $this->branchInfo['project'] . '/ClassE.php/class/ClassE');
    $this->clickLink('ClassE::staticMethod');
    $this->assertSession()->linkExists('bMethod', 0, 'Link to non-namespaced method works');
    $this->assertSession()->pageTextContains('1 call to ClassE::staticMethod()');
    $this->clickLink('bMethod');
    $this->assertSession()->pageTextContains("1 call to ClassQ::bMethod()");

    // Verify other reference calls.
    $this->drupalGet('api/' . $this->branchInfo['project'] . '/ClassA.php/class/ClassA');
    $this->clickLink('ClassA::sMethod');
    $this->assertSession()->pageTextContains('1 call to ClassA::sMethod()');
    $this->drupalGet('api/' . $this->branchInfo['project'] . '/ClassA.php/class/ClassA');
    $this->clickLink('ClassB::bMethod');
    $this->assertSession()->pageTextContains('1 call to ClassB::bMethod()');
    $this->drupalGet('api/' . $this->branchInfo['project'] . '/ClassA.php/class/ClassA');
    $this->clickLink('TraitF::xyz');
    $this->assertSession()->pageTextContains('2 calls to TraitF::xyz()');

    // Test traits with aliases.
    $this->drupalGet('api/' . $this->branchInfo['project'] . '/ClassA.php/class/ClassA');
    $this->assertSession()->linkExists('TraitF::$fvar', 0, 'Trait property link is present on using class page');
    $this->assertSession()->linkExists('TraitF::xyz', 0, 'Trait method link is present on using class page');
    $this->assertSession()->responseContains('Aliased as: pdq');

    // Click through to method that uses aliased method and check.
    $this->clickLink('ClassA::cMethod');
    $this->assertLinkUrlSubstring('pdq', $this->branchInfo['project'] . '/TraitF.php/function/TraitF', 'Aliased trait method link exists', 'Aliased trait method link went to the right place');
    $this->clickLink('pdq');
    $this->assertSession()->responseContains('Method to inherit');

    // Turn on the navigation block. This needs to be near the end of the test,
    // because there are tests verifying the word Namespace is not on pages.
    $this->drupalPlaceBlock('api_navigation_block');
    $this->clearCache();

    // Verify the Namespaces link is present in the navigation block.
    $this->drupalGet('api');
    $this->assertSession()->linkExists('Namespaces', 0, 'Namespaces navigation link is present');

    // Set the breaks tag variable and test Classes and Namespaces pages.
    $this->config('api.settings')
      ->set('breaks_tag', '<wbr />')
      ->save();
    $this->clearCache();

    $this->drupalGet('api/' . $this->branchInfo['project'] . '/classes');
    $this->assertSession()->responseContains('<wbr />');

    // Set breaks tag back to empty to continue with testing.
    $this->config('api.settings')
      ->set('breaks_tag', '')
      ->save();
    $this->clearCache();

    // Check formatting for PHP files in top directory of this branch.
    $files = [
      'ClassA.php',
      'ClassB.php',
      'ClassE.php',
      'InterfaceC.php',
      'InterfaceD.php',
      'InterfaceD2.php',
      'InterfaceH.php',
      'no_namespace.php',
      'TraitF.php',
    ];
    foreach ($files as $file) {
      $object_id = DocBlock::findFileByFileName($file, $this->getBranch());
      $object = DocBlock::load($object_id);
      $this->assertCodeFormatting($object->getCode(), $this->branchInfo['directory'] . '/' . $object->getFileName());
    }
  }

  /**
   * Tests that YAML works properly for linking and references.
   */
  protected function verifyYaml() {
    $this->drupalGet('api/' . $this->branchInfo['project'] . '/sample.routing.yml/' . $this->branchInfo['branch_name']);

    // Verify that the page name is correct.
    $this->assertTitleContains('sample.routing.yml', 'Page title is correct');

    // Verify the links to referenced functions, methods, and classes.
    $this->assertLinkUrlSubstring('\api\test1\ClassA::cMethod', $this->branchInfo['project'] . '/ClassA.php/function/ClassA', 'cMethod link exists', 'cMethod link went to the right place');
    $this->assertLinkUrlSubstring('\api\test1\ClassA::dMethod', $this->branchInfo['project'] . '/ClassA.php/function/ClassA', 'dMethod link exists', 'dMethod link went to the right place');
    $this->assertLinkUrlSubstring('another_function', $this->branchInfo['project'] . '/no_namespace.php/function/another_function', 'another_function link exists', 'another_function link went to the right place');
    $this->assertLinkUrlSubstring('api\test1\ClassA', $this->branchInfo['project'] . '/ClassA.php/class/ClassA', 'ClassA link exists', 'ClassA link went to the right place');

    // Test the references section on the YML file.
    $this->assertSession()->pageTextContains("1 string reference to 'sample.routing'");
    $this->assertSession()->linkExists('ClassQ::bMethod', 0, 'Link to referencing method is there');
    $this->assertSession()->pageTextContains("1 string reference to YAML keys in sample.routing.yml");
    $this->assertSession()->linkExists('another_function', 0, 'Link to YML referencing function is there');

    // Verify the references on the linked functions.
    $this->clickLink('another_function');
    $this->assertSession()->pageTextContains("1 string reference to 'another_function'");
    $this->assertSession()->linkExists('sample.routing.yml', 0, 'Referenced file is linked');

    $this->drupalGet('api/' . $this->branchInfo['project'] . '/sample.routing.yml/' . $this->branchInfo['branch_name']);
    $this->clickLink('\api\test1\ClassA::cMethod');
    $this->assertSession()->pageTextContains("1 string reference to 'ClassA::cMethod'");
    $this->assertSession()->linkExists('sample.routing.yml', 0, 'Referenced file is linked');

    $this->drupalGet('api/' . $this->branchInfo['project'] . '/sample.routing.yml/' . $this->branchInfo['branch_name']);
    $this->clickLink('\api\test1\ClassA::dMethod');
    $this->assertSession()->pageTextContains("1 string reference to 'ClassA::dMethod'");
    $this->assertSession()->linkExists('sample.routing.yml', 0, 'Referenced file is linked');

    $this->drupalGet('api/' . $this->branchInfo['project'] . '/sample.routing.yml/' . $this->branchInfo['branch_name']);
    $this->clickLink('api\test1\ClassA');
    $this->assertSession()->pageTextContains("1 string reference to 'ClassA'");
    $this->assertSession()->linkExists('sample.routing.yml', 0, 'Referenced file is linked');

    // Verify the links to the YML file.
    $this->drupalGet('api/' . $this->branchInfo['project'] . '/no_namespace.php/function/another_function');
    $this->assertLinkUrlSubstring('user_register', $this->branchInfo['project'] . '/sample.routing.yml', 'user_register link exists', 'user_register link went to the right place');

    $this->drupalGet('api/' . $this->branchInfo['project'] . '/no_namespace.php/class/ClassQ');
    $this->clickLink('ClassQ::bMethod');
    $this->assertLinkUrlSubstring('sample.routing', $this->branchInfo['project'] . '/sample.routing.yml', 'sample.routing link exists', 'sample.routing link went to the right place');
  }

}
