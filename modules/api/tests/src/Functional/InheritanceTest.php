<?php

namespace Drupal\Tests\api\Functional;

use Drupal\api\Entity\DocBlock;

/**
 * Tests that inheritance for classes and interfaces is working.
 */
class InheritanceTest extends WebPagesBase {

  /**
   * {@inheritdoc}
   */
  protected function setUp() : void {
    $this->baseSetUp();

    // Make a branch for sample 2 code.
    $this->branchInfo = $this->setUpBranchUi(NULL, TRUE, [
      'project' => 'test2',
      'project_title' => 'Project 2',
      'title' => 'Project 2 6.x',
      'directory' => $this->apiModulePath . '/tests/files/sample2',
      'excluded' => $this->apiModulePath . '/tests/files/sample2/maybe_exclude',
    ]);

    $this->removePhpBranch();
    $this->clearCache();
    $this->cronRun();
    $this->processApiParseQueue();
  }

  /**
   * Tests that inheritance is working.
   */
  public function testInheritance() {
    // Visit the base interface page, verify implementing class is shown on
    // the implementing page.
    $this->drupalGet('api/' . $this->branchInfo['project'] . '/inheritance.php/interface/BaseInterface');
    $this->clickLink('All classes that implement BaseInterface');
    $this->assertSession()->linkExists('ExcitingClass', 0, 'Implementing class is shown.');

    // Visit the class hierarchy page and verify everything is there.
    $this->drupalGet('api/' . $this->branchInfo['project'] . '/inheritance.php/interface/BaseInterface');
    $this->clickLink('Expanded class hierarchy of BaseInterface');
    $this->assertSession()->linkExists('SecondInterface', 0, 'Second interface is linked');
    $this->assertSession()->linkExists('ThirdInterface', 0, 'Third interface is linked');
    $this->assertSession()->linkExists('FourthInterface', 0, 'Fourth interface is linked');

    // Visit the FourthInterface page and verify that both interfaces it
    // extends have links.
    $this->clickLink('FourthInterface');
    $this->assertSession()->linkExists('BaseInterface', 0, 'First base interface is linked');
    $this->assertSession()->linkExists('AnotherBaseInterface', 0, 'Second base interface is linked');
    // Verify all the methods are shown.
    $this->assertSession()->linkExists('BaseInterface::base_function', 0, 'First base method is shown');
    $this->assertSession()->linkExists('AnotherBaseInterface::another_base_function', 0, 'Second base method is shown');
    $this->assertSession()->linkExists('FourthInterface::fourth_function', 0, 'Self method is shown');

    // Visit the TwoExternal page and verify both external interfaces it extends
    // are shown in the hierarchy line.
    $this->drupalGet('api/' . $this->branchInfo['project'] . '/inheritance.php/interface/TwoExternal');
    $this->assertSession()->responseMatches('|.*extends.*Foo.*|');
    $this->assertSession()->responseMatches('|.*extends.*Bar.*|');

    // Visit the ThirdInterface page and verify that the "Implemented by"
    // text is missing.
    $this->drupalGet('api/' . $this->branchInfo['project'] . '/inheritance.php/interface/ThirdInterface');
    $this->assertSession()->responseNotContains('Implemented by');

    // Visit the first class page and verify the method overrides are shown.
    $this->drupalGet('api/' . $this->branchInfo['project'] . '/inheritance.php/class/ExcitingClass');
    $this->assertSession()->linkExists('BaseInterface::base_function', 0, 'First override is shown');
    $this->assertSession()->linkExists('SecondInterface::second_function', 0, 'Second override is shown');

    // Visit the third class page and verify all methods and overrides are
    // shown.
    $this->drupalGet('api/' . $this->branchInfo['project'] . '/inheritance.php/class/YetAnotherExcitingClass');
    $this->assertSession()->linkExists('AnotherExcitingClass::another_function', 0, 'New function is shown');
    $this->assertSession()->linkExists('ExcitingClass::base_function', 0, 'Indirectly inherited function is shown');
    $this->assertSession()->linkExists('BaseInterface::base_function', 0, 'Indirect function override is shown');
    $this->assertSession()->linkExists('YetAnotherExcitingClass::second_function', 0, 'Directly inherited function is shown');
    $this->assertSession()->linkExists('ExcitingClass::second_function', 0, 'Directly inherited function override is shown');

    // Check formatting for PHP files in top directory of this branch.
    $files = ['inheritance.php', 'sample2.php'];
    foreach ($files as $file) {
      $object_id = DocBlock::findFileByFileName($file, $this->getBranch());
      $object = DocBlock::load($object_id);
      $this->assertCodeFormatting($object->getCode(), $this->branchInfo['directory'] . '/' . $object->getFileName());
    }
  }

}
