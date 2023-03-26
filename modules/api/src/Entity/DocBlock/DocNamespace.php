<?php

namespace Drupal\api\Entity\DocBlock;

use Drupal\api\Interfaces\DocBlock\DocNamespaceInterface;
use Drupal\api\Traits\MatchingTrait;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\api\Interfaces\DocBlockInterface;

/**
 * Defines the docblock namespace entity class.
 *
 * @ContentEntityType(
 *   id = "docblock_namespace",
 *   label = @Translation("DocBlock Namespace"),
 *   label_collection = @Translation("DocBlock namespaces"),
 *   handlers = {
 *     "access" = "Drupal\api\ApiAccessControlHandler",
 *     "views_data" = "Drupal\views\EntityViewsData",
 *     "storage_schema" = "Drupal\api\StorageSchema\ApiContentStorageSchema",
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\AdminHtmlRouteProvider",
 *     }
 *   },
 *   base_table = "api_branch_docblock_namespace",
 *   admin_permission = "administer API reference",
 *   field_indexes = {
 *     "object_type",
 *     "class_alias",
 *     "class_name"
 *   },
 *   entity_keys = {
 *     "id" = "id",
 *     "uuid" = "uuid"
 *   },
 * )
 */
class DocNamespace extends ContentEntityBase implements DocNamespaceInterface {

  use MatchingTrait;

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['object_type'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Object type'))
      ->setRequired(TRUE);

    $fields['class_alias'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Class alias'))
      ->setRequired(TRUE);

    $fields['class_name'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Class name'))
      ->setRequired(TRUE);

    $fields['docblock'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('DocBlock'))
      ->setDescription(t('DocBlock with information about this namespace'))
      ->setSetting('target_type', 'docblock')
      ->setSetting('handler', 'default')
      ->setCardinality(1)
      ->setRequired(TRUE);

    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public static function matches(array $conditions, DocBlockInterface $docBlock = NULL) {
    /** @var \Drupal\Core\Entity\Query\QueryInterface $query */
    $query = \Drupal::entityQuery('docblock_namespace');
    if ($docBlock) {
      $query->condition('docblock', $docBlock->id());
    }

    self::applyConditions($query, $conditions);

    return $query->execute();
  }

  /**
   * {@inheritdoc}
   */
  public static function getByClassName($class_name, $type = 'namespace') {
    return self::matches([
      'class_name' => $class_name,
      'object_type' => $type,
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public static function getByDocBlock(DocBlockInterface $docBlock) {
    return self::matches([
      'class_name' => [
        'operator' => '<>',
        'value' => '',
      ],
    ], $docBlock);
  }

  /**
   * {@inheritdoc}
   */
  public function getDocBlock() {
    return $this->get('docblock')->referencedEntities()[0];
  }

  /**
   * {@inheritdoc}
   */
  public function setDocBlock(DocBlockInterface $docblock) {
    $this->set('docblock', $docblock);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getObjectType() {
    return $this->get('object_type')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setObjectType($object_type) {
    $this->set('object_type', $object_type);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getClassAlias() {
    return $this->get('class_alias')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setClassAlias($class_alias) {
    $this->set('class_alias', $class_alias);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getClassName() {
    return $this->get('class_name')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setClassName($class_name) {
    $this->set('class_name', $class_name);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public static function deleteRelatedByType(array $types, DocBlockInterface $entity) {
    // https://www.drupal.org/node/3051072
    $storage_handler = \Drupal::entityTypeManager()->getStorage('docblock_namespace');
    $ids = self::matches([
      'object_type' => [
        'operator' => 'IN',
        'value' => $types,
      ],
    ], $entity);
    foreach (array_chunk($ids, 50) as $chunk) {
      $entities = $storage_handler->loadMultiple($chunk);
      $storage_handler->delete($entities);
    }
  }

}
