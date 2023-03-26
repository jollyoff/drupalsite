<?php

namespace Drupal\Tests\api\Functional;

/**
 * Tests URLs to make sure they are handled correctly.
 *
 * Tests current and legacy URL patterns for the API module, for listing and
 * item pages, but only those that have examples in the base "sample" test
 * area.
 */
class UrlPatternsTest extends TestBase {

  /**
   * Ensures that valid and invalid URL patterns are successful.
   *
   * Loads an example of each valid URL pattern. Verifies that it loads
   * successfully by checking for a 200 response code and expected text.
   * Some 404 patterns are also checked.
   */
  public function testApiUrlPaths() {
    // Check listing pages.
    $project_name = 'test';
    $branch_name = '6';
    $branch_title = 'Testing 6';

    $tests = [
      [
        'url' => 'api/' . $project_name . '/' . $branch_name,
        'aliases' => ['api'],
        'text' => ['A sample group.', 'A sample group of classes'],
        'links' => ['Topics', 'Files'],
      ],
      [
        'url' => 'api/' . $project_name . '/' . $branch_name,
        'aliases' => [],
        'text' => ['A sample group.', 'A sample group of classes'],
        'links' => ['Topics', 'Files'],
      ],
      [
        'url' => 'api/' . $project_name . '/classes',
        'text' => [
          'Classes, traits, and interfaces',
          'Sample class',
          'Sample interface',
        ],
        'links' => ['Sample', 'SampleInterface'],
      ],
      [
        'url' => 'api/' . $project_name . '/classes/' . $branch_name,
        'text' => [
          'Classes, traits, and interfaces',
          'Sample class',
          'Sample interface',
          'subdirectory',
        ],
        'links' => ['Sample', 'SampleInterface', 'classes.php'],
      ],
      [
        'url' => 'api/' . $project_name . '/constants/' . $branch_name,
        'aliases' => ['api/constants'],
        'text' => ['A sample constant'],
        'links' => ['SAMPLE_CONSTANT', 'sample.php'],
      ],
      [
        'url' => 'api/' . $project_name . '/constants/' . $branch_name,
        'aliases' => ['api/constants/' . $branch_name],
        'text' => ['A sample constant'],
        'links' => ['SAMPLE_CONSTANT', 'sample.php'],
      ],
      [
        'url' => 'api/' . $project_name . '/files/' . $branch_name,
        'aliases' => ['api/files'],
        'text' => ['A sample file'],
        'links' => ['sample.php'],
      ],
      [
        'url' => 'api/' . $project_name . '/files/' . $branch_name,
        'aliases' => ['api/files/' . $branch_name],
        'text' => ['A sample file'],
        'links' => ['sample.php'],
      ],
      [
        'url' => 'api/' . $project_name . '/functions/' . $branch_name,
        'aliases' => ['api/functions'],
        'text' => ['A sample function', 'subdirectory'],
        'links' => ['sample_function', 'sample.php', 'sample-subdir.php'],
      ],
      [
        'url' => 'api/' . $project_name . '/functions/' . $branch_name,
        'aliases' => ['api/functions/' . $branch_name],
        'text' => ['A sample function', 'subdirectory'],
        'links' => ['sample_function', 'sample.php', 'sample-subdir.php'],
      ],
      [
        'url' => 'api/' . $project_name . '/globals/' . $branch_name,
        'aliases' => ['api/globals'],
        'text' => ['A sample global', 'subdirectory'],
        'links' => ['$sample_global', 'sample.php', 'sample-subdir.php'],
      ],
      [
        'url' => 'api/' . $project_name . '/globals/' . $branch_name,
        'aliases' => ['api/globals/' . $branch_name],
        'text' => ['A sample global', 'subdirectory'],
        'links' => ['$sample_global', 'sample.php', 'sample-subdir.php'],
      ],
      [
        'url' => 'api/' . $project_name . '/groups/' . $branch_name,
        'aliases' => ['api/groups'],
        'text' => ['A sample group'],
        'links' => ['Samples'],
      ],
      [
        'url' => 'api/' . $project_name . '/groups/' . $branch_name,
        'aliases' => ['api/groups/' . $branch_name],
        'text' => ['A sample group'],
        'links' => ['Samples'],
      ],

      // Check individual documentation items.
      [
        'url' => 'api/' . $project_name . '/subdirectory!classes-subdir.php/class/SampleInSubDir/' . $branch_name,
        'aliases' => [],
        'text' => [
          'implements',
          'A public property for testing',
          'Sample class in a subdirectory',
          'SampleInSubDir',
          'Only implemented in children',
          'A class constant in a subdirectory',
          'subdirectory/',
          'A sample group of classes.',
        ],
        'links' => [
          'SampleInterface',
          'Expanded class hierarchy of SampleInSubDir',
          'SampleInSubDir::$property_in_sub_dir',
          'SampleInSubDir::baz',
          'SampleInSubDir::CONSTANT',
          'classes-subdir.php',
          'Class Samples',
        ],
      ],
      [
        'url' => 'api/' . $project_name . '/subdirectory!classes-subdir.php/class/hierarchy/SampleInSubDir/' . $branch_name,
        'aliases' => [],
        'text' => [
          'implements',
        ],
        'links' => [
          'SampleInSubDir',
          'SampleInterface',
          'SubInSubDirSample',
          'SampleInterfaceTwo',
        ],
      ],
      [
        'url' => 'api/' . $project_name . '/sample.php/constant/SAMPLE_CONSTANT/' . $branch_name,
        'aliases' => [
          'api/constant/SAMPLE_CONSTANT/' . $branch_name,
          'api/constant/SAMPLE_CONSTANT',
        ],
        'text' => [
          'SAMPLE_CONSTANT',
          'A sample constant',
          'A sample group',
        ],
        'links' => ['sample.php', 'Samples'],
      ],
      [
        'url' => 'api/' . $project_name . '/subdirectory!sample-subdir.php/' . $branch_name,
        'aliases' => [],
        'text' => [
          'A sample file in a subdirectory',
          'sample-subdir.php',
          'A sample global in a subdirectory',
        ],
        'links' => ['sample_in_sub_dir_global'],
      ],
      [
        'url' => 'api/' . $project_name . '/sample--doubledash.tpl.php/' . $branch_name,
        'text' => [
          'Displays a sample with a doubledash.',
          'This is for testing that files with',
        ],
      ],
      [
        'url' => 'api/' . $project_name . '/subdirectory!sample-subdir.php/function/sample_insubdir_function/' . $branch_name,
        'aliases' => [
          'api/function/sample_insubdir_function/' . $branch_name,
          'api/function/sample_insubdir_function/',
        ],
        'text' => [
          'Used for sample and testing URLs',
          'Another sample function; in a sub-directory',
        ],
        'links' => ['sample_function', 'Samples', 'sample-subdir.php'],
      ],
      [
        'url' => 'api/' . $project_name . '/subdirectory!sample-subdir.php/global/sample_in_sub_dir_global/' . $branch_name,
        'aliases' => [
          'api/global/sample_in_sub_dir_global/' . $branch_name,
          'api/global/sample_in_sub_dir_global',
        ],
        'text' => [
          '$sample_in_sub_dir_global',
          'A sample global in a subdirectory',
          'subdirectory/',
          'A sample group.',
        ],
        'links' => ['sample-subdir.php', 'Samples'],
      ],
      [
        'url' => 'api/' . $project_name . '/subdirectory--classes-subdir.php/interface/SampleInSubDirInterface/' . $branch_name,
        'aliases' => [],
        'text' => [
          'SampleInSubDirInterface',
          'Sample interface in a subdirectory',
          'Implements this API',
        ],
        'links' => [
          'SampleInSubDirInterface::foo2',
          'classes-subdir.php',
          'Class Samples',
        ],
      ],
      [
        'url' => 'api/' . $project_name . '/subdirectory!classes-subdir.php/property/SampleInSubDir::protected_property_in_sub_dir/' . $branch_name,
        'aliases' => [],
        'text' => [
          'SampleInSubDir::$protected_property_in_sub_dir',
          'A protected property for testing',
        ],
        'links' => ['classes-subdir.php'],
      ],
      [
        'url' => 'api/' . $project_name . '/sample.php/group/samp_GRP-6.x/' . $branch_name,
        'aliases' => [
          'api/group/samp_GRP-6.x/' . $branch_name,
          'api/group/samp_GRP-6.x',
        ],
        'text' => ['A sample group', 'A sample function'],
        'links' => ['sample_function', 'sample.php'],
      ],

      // Check same item URLs as above, but without branch suffixes. File
      // pages do not work without branch suffix.
      [
        'url' => 'api/' . $project_name . '/subdirectory!classes-subdir.php/class/SampleInSubDir/',
        'aliases' => [],
        'text' => [
          'implements',
          'A public property for testing',
          'Sample class in a subdirectory',
          'SampleInSubDir',
          'Only implemented in children',
          'A class constant in a subdirectory',
          'subdirectory/',
          'A sample group of classes.',
        ],
        'links' => [
          'SampleInterface',
          'Expanded class hierarchy of SampleInSubDir',
          'SampleInSubDir::$property_in_sub_dir',
          'SampleInSubDir::baz',
          'SampleInSubDir::CONSTANT',
          'classes-subdir.php',
          'Class Samples',
        ],
      ],
      [
        'url' => 'api/' . $project_name . '/subdirectory!classes-subdir.php/class/hierarchy/SampleInSubDir/',
        'aliases' => [],
        'text' => [
          'implements',
        ],
        'links' => [
          'SampleInSubDir',
          'SampleInterface',
          'SubInSubDirSample',
          'SampleInterfaceTwo',
        ],
      ],
      [
        'url' => 'api/' . $project_name . '/subdirectory!sample-subdir.php/function/sample_insubdir_function/',
        'aliases' => [],
        'text' => [
          'Used for sample and testing URLs',
          'Another sample function; in a sub-directory',
        ],
        'links' => ['sample_function', 'Samples', 'sample-subdir.php'],
      ],
      [
        'url' => 'api/' . $project_name . '/subdirectory!sample-subdir.php/global/sample_in_sub_dir_global/',
        'aliases' => [],
        'text' => [
          '$sample_in_sub_dir_global',
          'A sample global in a subdirectory',
          'subdirectory/',
          'A sample group.',
        ],
        'links' => ['sample-subdir.php', 'Samples'],
      ],
      [
        'url' => 'api/' . $project_name . '/subdirectory!classes-subdir.php/interface/SampleInSubDirInterface/',
        'aliases' => [],
        'text' => [
          'SampleInSubDirInterface',
          'Sample interface in a subdirectory',
          'Implements this API',
        ],
        'links' => [
          'SampleInSubDirInterface::foo2',
          'classes-subdir.php',
          'Class Samples',
        ],
      ],
      [
        'url' => 'api/' . $project_name . '/subdirectory!classes-subdir.php/property/SampleInSubDir::protected_property_in_sub_dir/',
        'aliases' => [],
        'text' => [
          'SampleInSubDir::$protected_property_in_sub_dir',
          'A protected property for testing',
        ],
        'links' => ['classes-subdir.php'],
      ],

      // Check search page.
      [
        'url' => 'api/' . $project_name . '/' . $branch_name . '/search/duplicate',
        'text' => ['For testing duplicate function name linking'],
        'links' => [
          'duplicates.php',
          'duplicate_function',
        ],
        'aliases' => ['api/search/' . $branch_name . '/duplicate'],
      ],
      [
        'url' => 'api/' . $project_name . '/' . $branch_name . '/search/foo',
        'text' => [
          'Implements',
          'Metasyntatic member function',
          'Implements this API',
          'Implements foo2.',
          'A property that matches',
          'Overrides parent function',
        ],
        'links' => [
          'foo_sample_name',
          'sample.php',
          'Sample::foo',
          'SampleInterface::foo',
          'classes.php',
          'SampleInSubDir::$foo',
          'SampleInSubDir::foo',
          'SampleInSubDirInterface::foo2',
          'SubInSubDirSample::foo',
          'Sample2InSubDir::foo2',
          'classes-subdir.php',
        ],
        'aliases' => ['api/search/' . $branch_name . '/foo'],
      ],
      [
        'url' => 'api/' . $project_name . '/' . $branch_name . '/search',
        'text' => [$branch_title],
        'aliases' => ['api/search'],
      ],
      // Check menu callbacks.
      [
        'url' => 'api/opensearch/',
        'text' => ['Drupal API documentation'],
      ],
      [
        'url' => 'api/suggest/duplicate',
        'text' => [
          'duplicate',
          'duplicates.php',
          'duplicate_function',
        ],
      ],
      [
        'url' => 'api/function_dump/' . $branch_name,
        'text' => [
          'sample_function($parameter, $complex_parameter)',
          'A sample function.',
        ],
      ],
      // Check the full list dump, with and without paging and limits.
      [
        'url' => 'api/' . $project_name . '/full_list/' . $branch_name,
        'text' => [
          '"object_name":"sample_function"',
          '"summary":"A sample function."',
          '"object_name":"another_sample","namespaced_name"',
          '"summary":"Returns HTML for another sample."',
        ],
      ],
      [
        'url' => 'api/' . $project_name . '/full_list/' . $branch_name,
        'url_options' => ['query' => ['limit' => 1]],
        'text' => ['"object_name":"another_sample"'],
        'notext' => ['"object_name":"classes.php"'],
      ],
      [
        'url' => 'api/' . $project_name . '/full_list/' . $branch_name,
        'url_options' => ['query' => ['limit' => 1, 'page' => 1]],
        'text' => ['"object_name":"classes-subdir.php"'],
        'notext' => [
          '"object_name":"another_sample"',
          '"object_name":"class_samples"',
        ],
      ],
      [
        'url' => 'api/' . $project_name . '/full_list/' . $branch_name,
        'url_options' => ['query' => ['limit' => 2]],
        'text' => [
          '"object_name":"another_sample"',
          '"object_name":"classes-subdir.php"',
        ],
        'notext' => ['"object_name":"class_samples"'],
      ],

      // Check pages that should not exist.
      [
        'url' => 'api/foobar',
        'response' => '404',
      ],
      [
        'url' => 'api/foobar/functions',
        'response' => '404',
      ],
      [
        'url' => 'api/' . $project_name . '/sample.php/function/not_real_name/' . $branch_name,
        'response' => '404',
      ],
      [
        'url' => 'api/' . $project_name . '/sample.php/class/not_real_name/' . $branch_name,
        'response' => '404',
      ],
      [
        'url' => 'api/' . $project_name . '/sample.php/global/not_real_name/' . $branch_name,
        'response' => '404',
      ],
      [
        'url' => 'api/' . $project_name . '/sample.php/interface/not_real_name/' . $branch_name,
        'response' => '404',
      ],
      [
        'url' => 'api/' . $project_name . '/sample.php/constant/not_real_name/' . $branch_name,
        'response' => '404',
      ],
      [
        'url' => 'api/' . $project_name . '/sample.php/property/not_real_name/' . $branch_name,
        'response' => '404',
      ],
      [
        'url' => 'api/' . $project_name . '/not_real_file_name/' . $branch_name,
        'response' => '404',
      ],
      // This one should be redirected from legacy URL.
      [
        'url' => 'api/function/sample_function/' . $branch_name,
        'text' => ['sample-related purposes'],
      ],
    ];

    foreach ($tests as $test) {
      // Verify the HTTP response is expected.
      $options = $test['url_options'] ?? [];
      $this->drupalGet($test['url'], $options);
      $response = $test['response'] ?? 200;
      $this->assertSession()->statusCodeEquals($response);

      // Verify the expected text is on the page.
      if (isset($test['text'])) {
        foreach ($test['text'] as $text) {
          $this->assertSession()->responseContains($text);
        }
      }

      // Verify the non-expected text is not on the page.
      if (isset($test['notext'])) {
        foreach ($test['notext'] as $text) {
          $this->assertSession()->responseNotContains($text);
        }
      }

      // Verify the expected links are on the page.
      if (isset($test['links'])) {
        foreach ($test['links'] as $text) {
          $this->assertSession()->linkExists($text, 0, "Link for $text found");
        }
      }

      // Verify that aliases from prior versions redirect to here correctly.
      if (isset($test['aliases'])) {
        $url = $this->getUrl();
        foreach ($test['aliases'] as $alias) {
          $this->drupalGet($alias);
          $this->assertEquals($url, $this->getUrl());
        }
      }
    }
  }

}
