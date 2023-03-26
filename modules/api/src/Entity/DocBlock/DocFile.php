<?php

namespace Drupal\api\Entity\DocBlock;

use Drupal\api\Interfaces\DocBlock\DocFileInterface;
use Drupal\api\Traits\MatchingTrait;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\api\Interfaces\DocBlockInterface;

/**
 * Defines the docblock file entity class.
 *
 * @ContentEntityType(
 *   id = "docblock_file",
 *   label = @Translation("DocBlock File"),
 *   label_collection = @Translation("DocBlock files"),
 *   handlers = {
 *     "access" = "Drupal\api\ApiAccessControlHandler",
 *     "views_data" = "Drupal\views\EntityViewsData",
 *     "storage_schema" = "Drupal\api\StorageSchema\ApiContentStorageSchema",
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\AdminHtmlRouteProvider",
 *     }
 *   },
 *   base_table = "api_branch_docblock_file",
 *   admin_permission = "administer API reference",
 *   field_indexes = {
 *     "basename"
 *   },
 *   entity_keys = {
 *     "id" = "id",
 *     "uuid" = "uuid"
 *   },
 * )
 */
class DocFile extends ContentEntityBase implements DocFileInterface {

  use MatchingTrait;

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['basename'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Basename'))
      ->setRequired(TRUE);

    $fields['docblock'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('DocBlock'))
      ->setDescription(t('DocBlock with information about this file'))
      ->setSetting('target_type', 'docblock')
      ->setSetting('handler', 'default')
      ->setCardinality(1)
      ->setRequired(TRUE);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created'))
      ->setDescription(t('The time that the file entry was created.'));

    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function getBasename() {
    return $this->get('basename')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setBasename($basename) {
    $this->set('basename', $basename);
    return $this;
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
  public function getCreatedTime() {
    return $this->get('created')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setCreatedTime($timestamp) {
    $this->set('created', $timestamp);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public static function matches(array $conditions, DocBlockInterface $docBlock = NULL) {
    /** @var \Drupal\Core\Entity\Query\QueryInterface $query */
    $query = \Drupal::entityQuery('docblock_file');
    if (!is_null($docBlock)) {
      $query->condition('docblock', $docBlock->id());
    }
    self::applyConditions($query, $conditions);

    return $query->execute();
  }

  /**
   * {@inheritdoc}
   */
  public static function findCreatedBefore($timestamp) {
    return self::matches([
      'created' => [
        'operator' => '<',
        'value' => $timestamp,
      ],
    ]);
  }

}
