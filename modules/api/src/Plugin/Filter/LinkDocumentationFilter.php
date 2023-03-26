<?php

namespace Drupal\api\Plugin\Filter;

use Drupal\api\Formatter;
use Drupal\filter\FilterProcessResult;
use Drupal\filter\Plugin\FilterBase;

/**
 * Filter to process text and try to link it to elements within the code.
 *
 * @Filter(
 *   id = "filter_link_documentation",
 *   title = @Translation("Link API documentation"),
 *   description = @Translation("Links text to elements present in the API documentation."),
 *   type = Drupal\filter\Plugin\FilterInterface::TYPE_MARKUP_LANGUAGE,
 * )
 */
class LinkDocumentationFilter extends FilterBase {

  /**
   * Process the text given and try to link it to documentation elements.
   *
   * @param string $text
   *   Text to process.
   * @param string $langcode
   *   Language of the text.
   *
   * @return \Drupal\filter\FilterProcessResult
   *   Processed result.
   */
  public function process($text, $langcode) {
    /** @var \Drupal\api\Utilities $utilities */
    $utilities = \Drupal::service('api.utilities');

    // Try to get the loaded elements.
    [
      'docblock' => $docblock,
      'file' => $file,
      'branch' => $branch,
    ] = $utilities->getElementsFromRoute(TRUE);

    // If we are on an API module page, load the current menu router item and
    // see if we can extract an API class and file ID from it. If we are not on
    // an API module page, there is no point trying, and besides calling
    // menu_get_item() could lead to recursion if it is trying to load a text
    // field formatted with the API text filter (on a node for instance).
    $class_id = $docblock ? $docblock->id() : NULL;
    $file_id = $file ? $file->id() : NULL;

    // Process text and return it.
    $new_text = Formatter::linkDocumentation($text, $branch, $file_id, $class_id, TRUE);

    return new FilterProcessResult($new_text);
  }

}
