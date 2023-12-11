<?php

namespace Drupal\Tests\api\Functional;

use Drupal\api\Entity\DocBlock;

/**
 * Tests that files in .. directories are scanned.
 *
 * @group api
 */
class DotDotTest extends TestBase {

  /**
   * {@inheritdoc}
   */
  protected function setUp() : void {
    $this->baseSetUp();

    // Make a branch for sample code, using a .. in the path.
    $prefix = __DIR__ . '/../../..';

    $this->setUpBranchApiCall($prefix);
    $this->removePhpBranch();
    $this->clearCache();
    $this->cronRun();
    $this->processApiParseQueue();
  }

  /**
   * Tests that all the files were parsed.
   */
  public function testParsing() {
    // Make sure we have the right number of doc objects.
    $this->assertObjectCount();

    // Verify that the top-level directory was parsed.
    $branch = $this->getBranch();
    $object = DocBlock::findFileByFileName('sample.php', $branch);
    $this->assertTrue(isset($object) && $object, 'sample.php was found (top level)');

    $object = DocBlock::findByNameAndType('sample_function', 'function', $branch);
    $this->assertTrue(isset($object) && $object, 'sample_function was found (top level)');

    // Verify that the subdirectory was parsed.
    $object = DocBlock::findFileByFileName('subdirectory/sample-subdir.php', $branch);
    $this->assertTrue(isset($object) && $object, 'sample_insubdir.php was found (sub-directory)');

    $object = DocBlock::findByNameAndType('sample_insubdir_function', 'function', $branch);
    $this->assertTrue(isset($object) && $object, 'sample_insubdir_function was found (sub-directory)');
  }

}
