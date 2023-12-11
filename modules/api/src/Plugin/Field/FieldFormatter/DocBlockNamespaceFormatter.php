<?php

namespace Drupal\api\Plugin\Field\FieldFormatter;

use Drupal\api\Formatter;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Link;
use Drupal\Core\Url;

/**
 * Formatter to render a namespace property as a link to the file.
 *
 * @FieldFormatter(
 *   id = "docblock_namespace",
 *   label = @Translation("DocBlock namespace link"),
 *   field_types = {
 *     "string"
 *   }
 * )
 */
class DocBlockNamespaceFormatter extends FormatterBase {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    /** @var \Drupal\api\Interfaces\DocBlockInterface $entity */
    $entity = $items->getEntity();

    $element = [];
    foreach ($items as $delta => $item) {
      $namespace = $item->value;
      $link = $namespace ?
        Link::fromTextAndUrl($namespace, Url::fromUri(Formatter::namespaceUrl($entity->getBranch(), $namespace)))->toString() :
        $namespace;

      $element[$delta] = ['#markup' => $link];
    }

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public static function isApplicable(FieldDefinitionInterface $field_definition) {
    // Just show this file extension formatter on the namespace field.
    return parent::isApplicable($field_definition) &&
      $field_definition->getName() === 'namespace' &&
      $field_definition->getTargetEntityTypeId() === 'docblock';
  }

}
