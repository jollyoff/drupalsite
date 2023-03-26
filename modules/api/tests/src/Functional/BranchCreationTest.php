<?php

namespace Drupal\Tests\api\Functional;

use Drupal\api\Entity\DocBlock;
use Drupal\api\Formatter;

/**
 * Tests branch and object creation.
 */
class BranchCreationTest extends TestBase {

  /**
   * Tests that branch and API objects are created correctly.
   */
  public function testBranchObjects() {
    // Make sure we have the right number of doc objects.
    $this->assertObjectCount();

    // Check sample.php.
    $branch = $this->getBranch();
    $object_id = DocBlock::findFileByFileName('sample.php', $branch);
    $object = DocBlock::load($object_id);
    $this->assertEquals($object->getSummary(), 'A sample file.', 'sample.php has summary ' . $object->getSummary());
    $this->assertCodeFormatting($object->getCode(), $branch->getDirectories() . '/' . $object->getFileName());

    // Check sample_function().
    $object_id = DocBlock::findByNameAndType('sample_function', 'function', $branch);
    $object = DocBlock::load(array_shift($object_id));
    $function = $object->getDocFunction();
    $this->assertEquals($object->getSummary(), 'A sample function.', 'sample_function() has summary ' . $object->getSummary());

    // Check @see directives.
    $this->assertTrue(strpos($object->getSee(), 'duplicate_function') !== FALSE, 'sample_function() includes duplicate_function in "See also" section.');
    $this->assertTrue(strpos($object->getSee(), 'SAMPLE_CONSTANT') !== FALSE, 'sample_function() includes SAMPLE_CONSTANT in "See also" section.');

    // Check multi-paragraph @param.
    $this->assertTrue(strpos($function->getParameters(), 'A second paragraph about') !== FALSE, 'sample_function() parameters contains second paragraph.');

    // Check multi-paragraph @param with @link.
    $this->assertTrue(strpos($function->getParameters(), 'this is a link') !== FALSE, 'sample_function() parameters contains link.');

    // Check for formatting on parameter.
    $this->assertTrue(strpos($function->getParameters(), '<strong>$parameter</strong>:') !== FALSE, 'sample_function() parameter has strong/colon formatting');

    // Check multi-paragraph @return.
    $this->assertTrue(strpos($function->getReturnValue(), 'second paragraph about the return') !== FALSE, 'sample_function() return contains second paragraph.');

    // Check list formatting.
    // Ignoring coding standards checks here because the strings are easier
    // to read and maintain broken up into lines as they are.
    // @codingStandardsIgnoreStart
    $list1 = "<p>This is a sample list:</p>" .
      "<ul><li>One item." .
      "</li><li>Another item." .
      "<ul><li>A sub-item. This one goes for multiple lines, just to make" .
      "sure that that works. It should. And here&#039;s a colon: just to" .
      "make sure that isn&#039;t wonky." .
      "</li><li>Another sub-item." .
      "</li></ul></li><li>A third item." .
      "</li></ul>";

    $list2 = "<p>This list uses our key format:</p>" .
      "<ul><li><strong>key1</strong>: The usual format, no quotes.</li>" .
      "<li><strong>&#039;key2&#039;</strong>: Sometimes we have quotes.</li>" .
      "<li><strong>&quot;key3 multiple&quot;</strong>: Sometimes double quotes and multiple words.</li>" .
      "<li>The following item should not have strong formatting.</li>" .
      "<li>http://example.com</li>" .
      "</ul>";
    // @codingStandardsIgnoreEnd

    // Test the list HTML with no whitespace, and then spot check some of the
    // list text to make sure the spaces are there. Trying to get all the
    // white space in the list HTML to match exactly is pointless.
    $no_spaces_doc = Formatter::entityDecode(preg_replace('|\s+|', '', $object->getDocumentation()));
    $list1 = Formatter::entityDecode(preg_replace('|\s+|', '', $list1));
    $list2 = Formatter::entityDecode(preg_replace('|\s+|', '', $list2));

    $this->assertTrue(strpos($no_spaces_doc, $list1) !== FALSE, 'Nested list is formatted correctly');
    $this->assertTrue(strpos($no_spaces_doc, $list2) !== FALSE, 'List with keys is formatted correctly');
    $this->assertTrue(strpos($object->getDocumentation(), "A sub-item. This one goes for multiple lines, just to make") !== FALSE, 'List item text is verbatim');
    $this->assertTrue(strpos($object->getDocumentation(), "key3 multiple") !== FALSE, 'List item key is verbatim');

    // Check $sample_global.
    $object_id = DocBlock::findByNameAndType('sample_global', 'global', $branch);
    $object = DocBlock::load(array_shift($object_id));
    $this->assertEquals($object->getSummary(), 'A sample global.', '$sample_global has summary ' . $object->getSummary());

    // Check SAMPLE_CONSTANT.
    $object_id = DocBlock::findByNameAndType('SAMPLE_CONSTANT', 'constant', $branch);
    $object = DocBlock::load(array_shift($object_id));
    $this->assertEquals($object->getSummary(), 'A sample constant.', 'SAMPLE_CONSTANT has summary ' . $object->getSummary());

    // Check group samp_GRP-6.x.
    $object_id = DocBlock::findByNameAndType('samp_GRP-6.x', 'group', $branch);
    $samples_group = DocBlock::load(array_shift($object_id));
    $this->assertEquals($samples_group->getSummary(), 'A sample group.', 'Group samples has summary ' . $samples_group->getSummary());
    $references = $samples_group->getDocReferences('group', FALSE, $samples_group->getObjectName());
    $count = count($references ?? []);
    $this->assertEquals($count, 8, 'Group samples has ' . $count . ' members');

    // Check group class_samples.
    $object_id = DocBlock::findByNameAndType('class_samples', 'group', $branch);
    $class_samples_group = DocBlock::load(array_shift($object_id));
    $this->assertEquals($class_samples_group->getSummary(), 'A sample group of classes. Should not include members.', 'Group class_samples has summary ' . $class_samples_group->getSummary());
    $references = $class_samples_group->getDocReferences('group', FALSE, $class_samples_group->getObjectName());
    $count = count($references ?? []);
    $this->assertEquals($count, 10, 'Group class_samples has ' . $count . ' members.');

    // Check classes.php.
    $object = DocBlock::load(DocBlock::findFileByFileName('classes.php', $branch));
    $this->assertEquals($object->getSummary(), 'Object-oriented tests.', 'classes.php has summary ' . $object->getSummary());
    $this->assertCodeFormatting($object->getCode(), $branch->getDirectories() . '/' . $object->getFileName());

    // Check Sample class.
    $object_id = DocBlock::findByNameAndType('Sample', 'class', $branch);
    $class = DocBlock::load(array_shift($object_id));
    $this->assertEquals($class->getSummary(), 'Sample class.', 'Sample has summary ' . $class->getSummary());

    // Check Sample::CONSTANT constant.
    $object_id = DocBlock::findByNameAndType('Sample::CONSTANT', 'constant', $branch);
    $sample_constant = DocBlock::load(array_shift($object_id));
    $this->assertEquals($sample_constant->getSummary(), 'A class constant.', 'Sample::CONSTANT has summary ' . $sample_constant->getSummary());
    $this->assertNotEmpty($sample_constant->getClass());
    $this->assertEquals($sample_constant->getClass()->id(), $class->id(), 'Constant belongs to parent class.');

    // Check Sample::property property.
    $object_id = DocBlock::findByNameAndType('Sample::property', 'property', $branch);
    $sample_property = DocBlock::load(array_shift($object_id));
    $this->assertEquals($sample_property->getSummary(), 'A property.', 'Sample::property has summary ' . $sample_property->getSummary());
    $this->assertNotEmpty($sample_property->getClass());
    $this->assertEquals($sample_property->getClass()->id(), $class->id(), 'Property belongs to parent class.');
    $this->assertEquals($sample_property->getVar(), 'SampleInterface', 'Property has @var.');

    // Check Sample::foo() method.
    $object_id = DocBlock::findByNameAndType('Sample::foo', 'function', $branch);
    $sample_foo = DocBlock::load(array_shift($object_id));
    $this->assertEquals($sample_foo->getSummary(), 'Metasyntatic member function.', 'Sample::foo() has summary ' . $sample_foo->getSummary());
    $this->assertEquals($sample_foo->getMemberName(), 'foo', 'Sample::foo() has member name ' . $sample_foo->getMemberName());
    $this->assertNotEmpty($sample_foo->getClass());
    $this->assertEquals($sample_foo->getClass()->id(), $class->id(), 'Method belongs to parent class.');
    $this->assertTrue(strpos($sample_foo->getThrows(), 'SampleException when it all goes wrong.') !== FALSE, 'Sample::foo() throws exception.');

    // Check SampleInterface interface.
    $object_id = DocBlock::findByNameAndType('SampleInterface', 'interface', $branch);
    $interface = DocBlock::load(array_shift($object_id));
    $this->assertEquals($interface->getSummary(), 'Sample interface.', 'Sample has summary ' . $interface->getSummary());

    // Check SampleInterface::foo() method.
    $object_id = DocBlock::findByNameAndType('SampleInterface::foo', 'function', $branch);
    $sampleInterface_foo = DocBlock::load(array_shift($object_id));
    $this->assertEquals($sampleInterface_foo->getSummary(), 'Implement this API.', 'SampleInterface::foo() has summary ' . $sampleInterface_foo->getSummary());
    $this->assertEquals($sampleInterface_foo->getMemberName(), 'foo', 'SampleInterface::foo() has member name ' . $sampleInterface_foo->getMemberName());
    $this->assertNotEmpty($sampleInterface_foo->getClass());
    $this->assertEquals($sampleInterface_foo->getClass()->id(), $interface->id(), 'Method belongs to parent interface.');

    // Check inheritance.
    $object_id = DocBlock::findByNameAndType('SubSample', 'class', $branch);
    $subclass = DocBlock::load(array_shift($object_id));
    $references = $subclass->getDocReferences('interface');
    $count = count($references ?? []);
    $this->assertEquals($count, 1, 'Sample implements SampleInterface.');
    $references = $subclass->getDocReferences('class');
    $count = count($references ?? []);
    $this->assertEquals($count, 1, 'SubSample extends Sample.');

    // Check overrides.
    $object_id = DocBlock::findByNameAndType('SubSample::bar', 'function', $branch);
    $subSample_bar = DocBlock::load(array_shift($object_id));
    $object_id = DocBlock::findByNameAndType('SampleInterfaceTwo::bar', 'function', $branch);
    $sampleInterfaceTwo_bar = DocBlock::load(array_shift($object_id));

    $sample_foo_overrides = $sample_foo->getDocOverrides();
    $this->assertNotEmpty($sample_foo_overrides);
    $sample_foo_override = array_shift($sample_foo_overrides);

    $subSample_bar_overrides = $subSample_bar->getDocOverrides();
    $this->assertNotEmpty($subSample_bar_overrides);
    $subSample_bar_override = array_shift($subSample_bar_overrides);

    $this->assertNotEmpty($sample_foo_override->getOverridesDocBlock());
    $this->assertEquals($sample_foo_override->getOverridesDocBlock()->id(), $sampleInterface_foo->id(), 'Sample::foo() overrides SampleInterface::foo()');
    $this->assertNotEmpty($sample_foo_override->getDocumentedInDocBlock());
    $this->assertEquals($sample_foo_override->getDocumentedInDocBlock()->id(), $sample_foo->id(), 'Sample::foo() is documented by itself.');
    $this->assertNotEmpty($subSample_bar_override->getDocumentedInDocBlock());
    $this->assertEquals($subSample_bar_override->getDocumentedInDocBlock()->id(), $sampleInterfaceTwo_bar->id(), 'SubSample::bar() is documented by SampleInterfaceTwo::bar()');

    // Check membership.
    $result = $class->getDocBlockClassMembers();
    $this->assertNotEmpty($result);
    $docBlock_ids = [];
    foreach ($result as $row) {
      $docBlock_ids[$row->id()] = TRUE;
    }
    $this->assertEquals(count($docBlock_ids), 4, 'Found ' . count($docBlock_ids) . ' members of Sample.');
    $this->assertEquals($docBlock_ids[$sample_foo->id()], TRUE, 'Sample::foo is a member of Sample.');
    $this->assertEquals($docBlock_ids[$sample_constant->id()], TRUE, 'Sample::CONSTANT is a member of Sample.');
    $this->assertEquals($docBlock_ids[$sample_property->id()], TRUE, 'Sample::property is a member of Sample.');

    $result = $subclass->getDocBlockClassMembers();
    $this->assertNotEmpty($result);
    $docBlock_ids = [];
    foreach ($result as $row) {
      $docBlock_ids[$row->id()] = TRUE;
    }
    $this->assertEquals(count($docBlock_ids), 5, 'Found ' . count($docBlock_ids) . ' members of SubSample.');
    $this->assertEquals($docBlock_ids[$sample_foo->id()], TRUE, 'Sample::foo is a member of SubSample.');
    $this->assertEquals($docBlock_ids[$sample_constant->id()], TRUE, 'Sample::CONSTANT is a member of SubSample.');
    $this->assertEquals($docBlock_ids[$sample_property->id()], TRUE, 'Sample::property is a member of SubSample.');
    $this->assertEquals($docBlock_ids[$subSample_bar->id()], TRUE, 'SubSample::bar is a member of SubSample.');

    // Check formatting for remaining PHP files in top directory of this branch.
    $files = [
      'duplicates.php',
      'onefunction.php',
      'sample--doubledash.tpl.php',
      'sample-three.tpl.php',
    ];
    foreach ($files as $file) {
      $object = DocBlock::load(DocBlock::findFileByFileName($file, $branch));
      $this->assertCodeFormatting($object->getCode(), $branch->getDirectories() . '/' . $object->getFileName());
    }
  }

}
