<?php

namespace Drupal\api\Entity\DocBlock;

use Drupal\api\Interfaces\DocBlock\DocOverrideInterface;
use Drupal\api\Traits\MatchingTrait;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\api\Interfaces\DocBlockInterface;

/**
 * Defines the docblock override entity class.
 *
 * @ContentEntityType(
 *   id = "docblock_override",
 *   label = @Translation("DocBlock Override"),
 *   label_collection = @Translation("DocBlock overrides"),
 *   handlers = {
 *     "access" = "Drupal\api\ApiAccessControlHandler",
 *     "views_data" = "Drupal\views\EntityViewsData",
 *     "storage_schema" = "Drupal\api\StorageSchema\ApiContentStorageSchema",
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\AdminHtmlRouteProvider",
 *     }
 *   },
 *   base_table = "api_branch_docblock_override",
 *   admin_permission = "administer API reference",
 *   field_indexes = {
 *   },
 *   entity_keys = {
 *     "id" = "id",
 *     "uuid" = "uuid"
 *   },
 * )
 */
class DocOverride extends ContentEntityBase implements DocOverrideInterface {

  use MatchingTrait;

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['docblock'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('DocBlock'))
      ->setDescription(t('DocBlock element'))
      ->setSetting('target_type', 'docblock')
      ->setSetting('handler', 'default')
      ->setCardinality(1)
      ->setRequired(TRUE);

    $fields['overrides_docblock'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Overrides DocBlock'))
      ->setDescription(t('DocBlock that is being overridden'))
      ->setSetting('target_type', 'docblock')
      ->setSetting('handler', 'default')
      ->setCardinality(1);

    $fields['documented_in_docblock'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Documented in DocBlock'))
      ->setDescription(t('DocBlock where this is documented'))
      ->setSetting('target_type', 'docblock')
      ->setSetting('handler', 'default')
      ->setCardinality(1)
      ->setRequired(TRUE);

    return $fields;
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
  public function getOverridesDocBlock() {
    $docblock = $this->get('overrides_docblock')->referencedEntities();
    return !empty($docblock[0]) ? $docblock[0] : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function setOverridesDocBlock(DocBlockInterface $docblock) {
    $this->set('overrides_docblock', $docblock);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getDocumentedInDocBlock() {
    return $this->get('documented_in_docblock')->referencedEntities()[0];
  }

  /**
   * {@inheritdoc}
   */
  public function setDocumentedInDocBlock(DocBlockInterface $docblock) {
    $this->set('documented_in_docblock', $docblock);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public static function matches(array $conditions, DocBlockInterface $docBlock = NULL) {
    /** @var \Drupal\Core\Entity\Query\QueryInterface $query */
    $query = \Drupal::entityQuery('docblock_override');
    if ($docBlock) {
      $query->condition('docblock', $docBlock->id());
    }

    self::applyConditions($query, $conditions);

    return $query->execute();
  }

}
