<?php

namespace Drupal\api\Plugin\Field\FieldFormatter;

use Drupal\api\Entity\DocBlock;
use Drupal\api\Formatter;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;

/**
 * Formatter to render a file name property as a link to the file.
 *
 * @FieldFormatter(
 *   id = "docblock_filename",
 *   label = @Translation("DocBlock file name link"),
 *   field_types = {
 *     "string"
 *   }
 * )
 */
class DocBlockFileNameFormatter extends FormatterBase {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    /** @var \Drupal\api\Interfaces\DocBlockInterface $entity */
    $entity = $items->getEntity();

    $element = [];
    foreach ($items as $delta => $item) {
      $file_docblock_id = DocBlock::findFileByFileName($item->value, $entity->getBranch());
      $link_to_file = $item->value;
      if ($file_docblock_id) {
        /** @var \Drupal\api\Interfaces\DocBlockInterface $file_entity */
        $file_entity = DocBlock::load($file_docblock_id);
        $link_to_file = Formatter::linkFile($file_entity);
      }

      $element[$delta] = ['#markup' => $link_to_file];
    }

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public static function isApplicable(FieldDefinitionInterface $field_definition) {
    // Just show this file extension formatter on the filename field.
    return parent::isApplicable($field_definition) &&
      $field_definition->getName() === 'file_name' &&
      $field_definition->getTargetEntityTypeId() === 'docblock';
  }

}
