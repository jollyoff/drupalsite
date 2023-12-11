<?php

namespace Drupal\Tests\api\Functional;

use Drupal\api\Formatter;
use Drupal\Component\Utility\Html;
use Drupal\Core\Url;

/**
 * Tests the Formatter::linkDocumentation() function.
 */
class LinkDocumentationTest extends TestBase {

  /**
   * Tests the api_link_documentation() function.
   */
  public function testApiLinkDocumentation() {
    $tests = [
      // Items that should be linked.
      [
        'message' => 'Function name linking',
        'data' => 'sample_function(',
        'expected' => '<a href="' . Url::fromUri('internal:/api/test/sample.php/function/sample_function/6')->toString() . '" title="A sample function." class="local">sample_function</a>(',
      ],
      [
        'message' => 'Function name linking with preceding space',
        'data' => ' sample_function(',
        'expected' => ' <a href="' . Url::fromUri('internal:/api/test/sample.php/function/sample_function/6')->toString() . '" title="A sample function." class="local">sample_function</a>(',
      ],
      [
        'message' => 'Function @link',
        'data' => '@link sample_function A sample function. @endlink',
        'expected' => '<a href="' . Url::fromUri('internal:/api/test/sample.php/function/sample_function/6')->toString() . '" title="A sample function." class="local">A sample function.</a>',
      ],
      [
        'message' => 'Multiline function @link',
        'data' => "@link sample_function\nA sample function. @endlink",
        'expected' => '<a href="' . Url::fromUri('internal:/api/test/sample.php/function/sample_function/6')->toString() . '" title="A sample function." class="local">A sample function.</a>',
      ],
      [
        'message' => 'Multiple @link',
        'data' => "@link sample_function A sample function. @endlink and @link samp_GRP-6.x A topic. @endlink",
        'expected' => '<a href="' . Url::fromUri('internal:/api/test/sample.php/function/sample_function/6')->toString() . '" title="A sample function." class="local">A sample function.</a> and <a href="' . Url::fromUri('internal:/api/test/sample.php/group/samp_GRP-6.x/6')->toString() . '" title="A sample group." class="local">A topic.</a>',
      ],
      [
        'message' => 'Method name linking',
        'data' => 'Sample::foo(',
        'expected' => '<a href="' . Url::fromUri('internal:/api/test/classes.php/function/Sample::foo/6')->toString() . '" title="Metasyntatic member function." class="local">Sample::foo</a>(',
      ],
      [
        'message' => 'Method name linking without explicit class -- should not link',
        'data' => 'foo(',
        'expected' => 'foo(',
      ],
      [
        'message' => 'Class constant @link',
        'data' => '@link Sample::CONSTANT A class constant @endlink',
        'expected' => '<a href="' . Url::fromUri('internal:/api/test/classes.php/constant/Sample::CONSTANT/6')->toString() . '" title="A class constant." class="local">A class constant</a>',
      ],
      [
        'message' => 'Property @link',
        'data' => '@link Sample::property A property @endlink',
        'expected' => '<a href="' . Url::fromUri('internal:/api/test/classes.php/property/Sample::property/6')->toString() . '" title="A property." class="local">A property</a>',
      ],
      [
        'message' => 'Class @link',
        'data' => '@link Sample A class @endlink',
        'expected' => '<a href="' . Url::fromUri('internal:/api/test/classes.php/class/Sample/6')->toString() . '" title="Sample class." class="local">A class</a>',
      ],
      [
        'message' => 'Class aggressive link',
        'data' => 'Sample',
        'expected' => '<a href="' . Url::fromUri('internal:/api/test/classes.php/class/Sample/6')->toString() . '" title="Sample class." class="local">Sample</a>',
        'aggressive_classes' => TRUE,
      ],
      [
        'message' => 'Constant link',
        'data' => 'SAMPLE_CONSTANT',
        'expected' => '<a href="' . Url::fromUri('internal:/api/test/sample.php/constant/SAMPLE_CONSTANT/6')->toString() . '" title="A sample constant." class="local">SAMPLE_CONSTANT</a>',
      ],

      // Items that should not be linked.
      [
        'message' => 'Function name linking with preceding non-space character',
        'data' => '.sample_function(',
        'expected' => '.sample_function(',
      ],
      [
        'message' => 'Function name linking with preceding letter',
        'data' => 'asample_function(',
        'expected' => 'asample_function(',
      ],
      [
        'message' => 'Function name linking without parenthesis',
        'data' => 'sample_function',
        'expected' => 'sample_function',
      ],
      [
        'message' => 'Function name linking inside a HTML tag',
        'data' => '<tag attribute="sample_function()">',
        'expected' => '<tag attribute="sample_function()">',
      ],
      [
        'message' => 'Function \\@link',
        'data' => '\\@link sample_function A sample function. @endlink',
        'expected' => '@link sample_function A sample function. @endlink',
      ],
      [
        'message' => 'Class aggressive link with trailing letter',
        'data' => 'Samplea',
        'expected' => 'Samplea',
        'aggressive_classes' => TRUE,
      ],
      [
        'message' => 'Class link',
        'data' => 'Sample',
        'expected' => 'Sample',
      ],
      [
        'message' => 'Constant link with trailing character',
        'data' => 'SAMPLE_CONSTANTA',
        'expected' => 'SAMPLE_CONSTANTA',
      ],

      // Items that should be linked.
      [
        'message' => 'File name linking',
        'data' => 'sample.php',
        'expected' => '<a href="' . Url::fromUri('internal:/api/test/sample.php/6')->toString() . '" title="A sample file." class="local">sample.php</a>',
      ],
      [
        'message' => 'File name linking with preceding space',
        'data' => ' sample.php',
        'expected' => ' <a href="' . Url::fromUri('internal:/api/test/sample.php/6')->toString() . '" title="A sample file." class="local">sample.php</a>',
      ],
      [
        'message' => 'File name linking with following space',
        'data' => 'sample.php ',
        'expected' => '<a href="' . Url::fromUri('internal:/api/test/sample.php/6')->toString() . '" title="A sample file." class="local">sample.php</a> ',
      ],
      [
        'message' => 'File name linking with following punctuation',
        'data' => 'sample.php.',
        'expected' => '<a href="' . Url::fromUri('internal:/api/test/sample.php/6')->toString() . '" title="A sample file." class="local">sample.php</a>.',
      ],
      [
        'message' => 'File @link',
        'data' => '@link sample.php A sample file. @endlink',
        'expected' => '<a href="' . Url::fromUri('internal:/api/test/sample.php/6')->toString() . '" title="A sample file." class="local">A sample file.</a>',
      ],

      // Items that should not be linked.
      [
        'message' => 'File name linking with preceding non-space character',
        'data' => '.sample.php',
        'expected' => '.sample.php',
      ],
      [
        'message' => 'File name linking with preceding letter',
        'data' => 'asample.php',
        'expected' => 'asample.php',
      ],
      [
        'message' => 'File name linking inside a HTML tag',
        'data' => '<tag attribute="sample.php">',
        'expected' => '<tag attribute="sample.php">',
      ],
      [
        'message' => 'File \\@link, does fill in file link',
        'data' => '\\@link sample.php A sample file. @endlink',
        'expected' => '@link <a href="' . Url::fromUri('internal:/api/test/sample.php/6')->toString() . '" title="A sample file." class="local">sample.php</a> A sample file. @endlink',
      ],
      [
        'message' => 'Escaped @tag is not unescaped.',
        'data' => 'Escaped \\@stuff.',
        'expected' => 'Escaped @stuff.',
      ],
      [
        'message' => 'Double-escaped @tag is not unescaped.',
        'data' => 'Double-escaped \\\\@stuff.',
        'expected' => 'Double-escaped \\@stuff.',
      ],
    ];

    foreach ($tests as $test) {
      if (!isset($test['class_id'])) {
        $test['class_id'] = NULL;
      }
      if (!isset($test['file_id'])) {
        $test['file_id'] = NULL;
      }
      if (!isset($test['aggressive_classes'])) {
        $test['aggressive_classes'] = FALSE;
      }
      $result = Formatter::linkDocumentation($test['data'], $this->getBranch(), $test['file_id'], $test['class_id'], $test['aggressive_classes']);
      $this->assertEquals($result, $test['expected'], $test['message'] . ' Got: <code>' . Html::escape($result) . '</code>');
    }
  }

}
