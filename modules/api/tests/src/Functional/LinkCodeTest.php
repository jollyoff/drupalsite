<?php

namespace Drupal\Tests\api\Functional;

use Drupal\api\ExtendedQueries;
use Drupal\api\Formatter;
use Drupal\Core\Url;

/**
 * Tests the Formatter::linkCode() function.
 */
class LinkCodeTest extends TestBase {

  /**
   * Overrides TestBase::setUp() so we do have the PHP branch.
   */
  protected function setUp() : void {
    $this->baseSetUp();
    $this->setUpBranchApiCall();
    $this->clearCache();
    $this->cronRun();
    $this->processApiParseQueue();
  }

  /**
   * Tests the api_link_code() function.
   */
  public function testApiLinkCode() {
    $branch = $this->getBranch();
    $class = ExtendedQueries::loadExtendedWithOverrides('Sample', $branch, 'class', 'classes.php');
    $subclass = ExtendedQueries::loadExtendedWithOverrides('SubSample', $branch, 'class', 'classes.php');

    $tests = [
      // Items that should be linked.
      [
        'message' => 'Marked-up function name linking',
        'data' => '<span class="php-function-or-constant">sample_function</span>',
        'expected' => '<span class="php-function-or-constant"><a href="' . Url::fromUri('internal:/api/' . $branch->getProject()->getSlug() . '/sample.php/function/sample_function/' . $branch->getSlug())->toString() . '" title="A sample function." class="local">sample_function</a></span>',
      ],
      [
        'message' => 'Linking to method defined in multiple classes from self',
        'data' => '<span class="php-function-or-constant function member-of-self">foo</span>',
        'expected' => '<span class="php-function-or-constant"><a href="' . Url::fromUri('internal:/api/' . $branch->getProject()->getSlug() . '/classes.php/function/Sample::foo/' . $branch->getSlug())->toString() . '" title="Metasyntatic member function." class="local">foo</a></span>',
        'class_id' => $class->id,
      ],
      [
        'message' => 'Linking to method defined in multiple classes as parent',
        'data' => '<span class="php-function-or-constant function member-of-parent">foo</span>',
        'expected' => '<span class="php-function-or-constant"><a href="' . Url::fromUri('internal:/api/' . $branch->getProject()->getSlug() . '/classes.php/function/Sample::foo/' . $branch->getSlug())->toString() . '" title="Metasyntatic member function." class="local">foo</a></span>',
        'class_id' => $subclass->id,
      ],
      [
        'message' => 'Linking to method defined in multiple subclasses',
        'data' => '<span class="php-function-or-constant function member-of-variable">baz</span>',
        'expected' => '<span class="php-function-or-constant"><a href="' . Url::fromUri('internal:/api/' . $branch->getProject()->getSlug() . '/' . $branch->getSlug() . '/search/baz')->toString() . '" title="Multiple implementations exist." class="local">baz</a></span>',
      ],
      [
        'message' => 'Marked-up class name linking',
        'data' => '<span class="php-function-or-constant">Sample</span>',
        'expected' => '<span class="php-function-or-constant"><a href="' . Url::fromUri('internal:/api/' . $branch->getProject()->getSlug() . '/classes.php/class/Sample/' . $branch->getSlug())->toString() . '" title="Sample class." class="local">Sample</a></span>',
      ],
      [
        'message' => 'Duplicate function name linking',
        'data' => '<span class="php-function-or-constant">duplicate_function</span>',
        'expected' => '<span class="php-function-or-constant"><a href="' . Url::fromUri('internal:/api/' . $branch->getProject()->getSlug() . '/' . $branch->getSlug() . '/search/duplicate_function')->toString() . '" title="Multiple implementations exist." class="local">duplicate_function</a></span>',
      ],
      [
        'message' => 'Linking to a PHP function',
        'data' => '<span class="php-function-or-constant">strpos</span>',
        'expected' => '<span class="php-function-or-constant"><a href="http://php.net/strpos" title="false strpos(string $haystack, string $needle [, int $offset = &#039;&#039;]) Find the position of the first occurrence of a substring in a string" class="php-manual">strpos</a></span>',
      ],
      [
        'message' => 'Marked-up global linking',
        'data' => '<span class="php-keyword">global</span> <span class="php-variable">$sample_global</span>',
        'expected' => '<span class="php-keyword">global</span> <span class="php-variable">$<a href="' . Url::fromUri('internal:/api/' . $branch->getProject()->getSlug() . '/sample.php/global/sample_global/' . $branch->getSlug())->toString() . '" title="A sample global." class="local">sample_global</a></span>',
      ],
      [
        'message' => 'String that is function name linking',
        'data' => '<span class="php-string">\'sample_function\'</span>',
        'expected' => '<span class="php-function-or-constant">\'<a href="' . Url::fromUri('internal:/api/' . $branch->getProject()->getSlug() . '/sample.php/function/sample_function/' . $branch->getSlug())->toString() . '" title="A sample function." class="local">sample_function</a>\'</span>',
      ],
      [
        'message' => 'String that is theme hook linking to function',
        'data' => '<span class="php-function-or-constant">theme</span>(<span class="php-string potential-theme">\'sample_one\'</span>',
        'expected' => '<span class="php-function-or-constant">theme</span>(<span class="php-function-or-constant">\'<a href="' . Url::fromUri('internal:/api/' . $branch->getProject()->getSlug() . '/sample.php/function/theme_sample_one/' . $branch->getSlug())->toString() . '" title="Returns HTML for a sample." class="local">sample_one</a>\'</span>',
      ],
      [
        'message' => 'String that is theme hook linking to template',
        'data' => '<span class="php-function-or-constant">theme</span>(<span class="php-string potential-theme">\'sample_three\'</span>',
        'expected' => '<span class="php-function-or-constant">theme</span>(<span class="php-function-or-constant">\'<a href="' . Url::fromUri('internal:/api/' . $branch->getProject()->getSlug() . '/sample-three.tpl.php/' . $branch->getSlug())->toString() . '" title="Displays yet another sample." class="local">sample_three</a>\'</span>',
      ],
      [
        'message' => 'String that is theme hook linking to template when function also exists',
        'data' => '<span class="php-function-or-constant">theme</span>(<span class="php-string potential-theme">\'sample_two\'</span>',
        'expected' => '<span class="php-function-or-constant">theme</span>(<span class="php-function-or-constant">\'<a href="' . Url::fromUri('internal:/api/' . $branch->getProject()->getSlug() . '/sample.php/function/theme_sample_two/' . $branch->getSlug())->toString() . '" title="Returns HTML for another sample." class="local">sample_two</a>\'</span>',
      ],
      [
        'message' => 'String that is theme hook with base linking to function',
        'data' => '<span class="php-function-or-constant">theme</span>(<span class="php-string potential-theme">\'sample_four__option\'</span>',
        'expected' => '<span class="php-function-or-constant">theme</span>(<span class="php-function-or-constant">\'<a href="' . Url::fromUri('internal:/api/' . $branch->getProject()->getSlug() . '/sample.php/function/theme_sample_four/' . $branch->getSlug())->toString() . '" title="Returns HTML for yet another sample." class="local">sample_four__option</a>\'</span>',
      ],
      [
        'message' => 'String that is hook linking to function in module_invoke_all()',
        'data' => '<span class="php-function-or-constant">module_invoke_all</span>(<span class="php-string potential-hook">\'sample_name\'</span>',
        'expected' => '<span class="php-function-or-constant">module_invoke_all</span>(<span class="php-function-or-constant">\'<a href="' . Url::fromUri('internal:/api/' . $branch->getProject()->getSlug() . '/sample.php/function/hook_sample_name/' . $branch->getSlug())->toString() . '" title="Respond to sample updates." class="local">sample_name</a>\'</span>',
      ],
      [
        'message' => 'String that is hook linking to function in module_implements()',
        'data' => '<span class="php-function-or-constant">module_implements</span>(<span class="php-string potential-hook">\'sample_name\'</span>',
        'expected' => '<span class="php-function-or-constant">module_implements</span>(<span class="php-function-or-constant">\'<a href="' . Url::fromUri('internal:/api/' . $branch->getProject()->getSlug() . '/sample.php/function/hook_sample_name/' . $branch->getSlug())->toString() . '" title="Respond to sample updates." class="local">sample_name</a>\'</span>',
      ],
      [
        'message' => 'String that is hook linking to function in module_invoke()',
        'data' => '<span class="php-function-or-constant">module_invoke</span>(<span class="php-variable">$module</span>, <span class="php-string potential-hook">\'sample_name\'</span>',
        'expected' => '<span class="php-function-or-constant">module_invoke</span>(<span class="php-variable">$module</span>, <span class="php-function-or-constant">\'<a href="' . Url::fromUri('internal:/api/' . $branch->getProject()->getSlug() . '/sample.php/function/hook_sample_name/' . $branch->getSlug())->toString() . '" title="Respond to sample updates." class="local">sample_name</a>\'</span>',
      ],
      [
        'message' => 'String that is alter hook linking to function in drupal_alter()',
        'data' => '<span class="php-function-or-constant">drupal_alter</span>(<span class="php-string potential-alter">\'another_sample\'</span>',
        'expected' => '<span class="php-function-or-constant">drupal_alter</span>(<span class="php-function-or-constant">\'<a href="' . Url::fromUri('internal:/api/' . $branch->getProject()->getSlug() . '/sample.php/function/hook_another_sample_alter/' . $branch->getSlug())->toString() . '" title="Alter samples." class="local">another_sample</a>\'</span>',
      ],
      // Items that should not be linked.
      [
        'message' => 'Function name linking',
        'data' => 'sample_function',
        'expected' => 'sample_function',
      ],
      [
        'message' => 'Function name linking with (',
        'data' => 'sample_function(',
        'expected' => 'sample_function(',
      ],
      [
        'message' => 'String that is not a function name',
        'data' => '<span class="php-string">\'not_an_actual_function\'</span>',
        'expected' => '<span class="php-string">\'not_an_actual_function\'</span>',
      ],
      [
        'message' => 'String that is nonexistent global',
        'data' => '<span class="php-keyword">global</span> <span class="php-variable">$not_a_global_name</span>',
        'expected' => '<span class="php-keyword">global</span> <span class="php-variable">$not_a_global_name</span>',
      ],
      [
        'message' => 'String that is nonexistent theme hook',
        'data' => '<span class="php-function-or-constant">theme</span>(<span class="php-string potential-theme">\'not_a_theme_hook_name\'</span>',
        'expected' => '<span class="php-function-or-constant">theme</span>(<span class="php-string">\'not_a_theme_hook_name\'</span>',
      ],
      [
        'message' => 'String that is nonexistent hook in module_invoke_all()',
        'data' => '<span class="php-function-or-constant">module_invoke_all</span>(<span class="php-string potential-hook">\'not_a_sample_hook_name\'</span>',
        'expected' => '<span class="php-function-or-constant">module_invoke_all</span>(<span class="php-string">\'not_a_sample_hook_name\'</span>',
      ],
      [
        'message' => 'String that is nonexistent hook in module_implements()',
        'data' => '<span class="php-function-or-constant">module_implements</span>(<span class="php-string potential-hook">\'not_a_sample_hook_name\'</span>',
        'expected' => '<span class="php-function-or-constant">module_implements</span>(<span class="php-string">\'not_a_sample_hook_name\'</span>',
      ],
      [
        'message' => 'String that is nonexistent hook in module_invoke()',
        'data' => '<span class="php-function-or-constant">module_invoke</span>(<span class="php-variable">$module</span>, <span class="php-string potential-hook">\'not_a_hook_name\'</span>',
        'expected' => '<span class="php-function-or-constant">module_invoke</span>(<span class="php-variable">$module</span>, <span class="php-string">\'not_a_hook_name\'</span>',
      ],
      [
        'message' => 'String that is nonexistent alter hook in drupal_alter()',
        'data' => '<span class="php-function-or-constant">drupal_alter</span>(<span class="php-string potential-alter">\'not_a_hook_name\'</span>',
        'expected' => '<span class="php-function-or-constant">drupal_alter</span>(<span class="php-string">\'not_a_hook_name\'</span>',
      ],
    ];

    foreach ($tests as $test) {
      if (!isset($test['class_id'])) {
        $test['class_id'] = NULL;
      }
      if (!isset($test['file_id'])) {
        $test['file_id'] = NULL;
      }
      $result = Formatter::linkCode($test['data'], $this->getBranch(), $test['file_id'], $test['class_id']);

      // Compare result and expected with slightly liberal matching -- all
      // whitespace is considered equal for HTML.
      $result = preg_replace('|\s+|', ' ', $result);
      $expected = preg_replace('|\s+|', ' ', $test['expected']);
      $this->assertEquals($expected, $result, $test['message']);
    }
  }

}
