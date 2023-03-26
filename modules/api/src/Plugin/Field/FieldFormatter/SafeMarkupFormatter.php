<?php

namespace Drupal\api\Plugin\Field\FieldFormatter;

use Drupal\Component\Utility\Xss;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;

/**
 * Formatter to render safe markup coming from PhpDocs.
 *
 * @FieldFormatter(
 *   id = "api_safe_markup",
 *   label = @Translation("Safe markup coming from PhpDocs"),
 *   field_types = {
 *     "string_long"
 *   }
 * )
 */
class SafeMarkupFormatter extends FormatterBase {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $element = [];
    foreach ($items as $delta => $item) {
      $element[$delta] = ['#markup' => Xss::filterAdmin($item->value)];
    }

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public static function isApplicable(FieldDefinitionInterface $field_definition) {
    return parent::isApplicable($field_definition) &&
      $field_definition->getTargetEntityTypeId() === 'docblock';
  }

}
