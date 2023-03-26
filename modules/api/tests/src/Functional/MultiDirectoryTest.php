<?php

namespace Drupal\Tests\api\Functional;

use Drupal\api\Entity\DocBlock;

/**
 * Tests that a multi-directory setup works.
 */
class MultiDirectoryTest extends TestBase {

  /**
   * {@inheritdoc}
   */
  protected function setUp() : void {
    $this->baseSetUp();

    $this->setUpBranchApiCall('', TRUE, [
      'directory' => $this->apiModulePath . '/tests/files/sample' . "\n" . $this->apiModulePath . '/tests/files/sample2',
      'excluded' => $this->apiModulePath . '/tests/files/sample/to_exclude' . "\n" . $this->apiModulePath . '/tests/files/sample2/maybe_exclude',
    ]);
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
    $branch = $this->getBranch();
    $this->assertObjectCount($branch, 93);

    // Verify that the top-level directory was parsed.
    $file_id = DocBlock::findFileByFileName('sample.php', $branch);
    $this->assertNotEmpty($file_id, 'sample.php was found');
    $object = DocBlock::load($file_id);
    $this->assertEquals('A sample file.', $object->getSummary(), 'sample.php has summary ' . $object->getSummary());

    $file_id = DocBlock::findFileByFileName('sample2.php', $branch);
    $this->assertNotEmpty($file_id, 'sample2.php was found');
    $object = DocBlock::load($file_id);
    $this->assertEquals('A sample file to make as a new project.', $object->getSummary(), 'sample2.php has summary ' . $object->getSummary());

    $object_id = DocBlock::findByNameAndType('second_sample_function', 'function', $branch);
    $this->assertNotEmpty($object_id, 'second_sample_function was found');
    $object_id = array_shift($object_id);
    $object = DocBlock::load($object_id);
    $this->assertEquals('sample2.php', $object->getFileName(), 'second_sample_function was in file ' . $object->getFileName());

    $object_id = DocBlock::findByNameAndType('sample_class_function', 'function', $branch);
    $this->assertNotEmpty($object_id, 'sample_class_function was found');
    $object_id = array_shift($object_id);
    $object = DocBlock::load($object_id);
    $this->assertEquals('sample.php', $object->getFileName(), 'sample_class_function was in file ' . $object->getFileName());

    $object_id = DocBlock::findByNameAndType('DifferentClassName', 'class', $branch);
    $this->assertEmpty($object_id, 'Class in excluded file was loaded');
  }

}
