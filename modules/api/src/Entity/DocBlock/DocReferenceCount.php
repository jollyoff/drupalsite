<?php

namespace Drupal\api\Entity\DocBlock;

use Drupal\api\ExtendedQueries;
use Drupal\api\Interfaces\BranchInterface;
use Drupal\api\Interfaces\DocBlock\DocReferenceCountInterface;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;

/**
 * Defines the docblock reference count entity class.
 *
 * @ContentEntityType(
 *   id = "docblock_reference_count",
 *   label = @Translation("DocBlock Reference Count"),
 *   label_collection = @Translation("DocBlock reference counts"),
 *   handlers = {
 *     "access" = "Drupal\api\ApiAccessControlHandler",
 *     "views_data" = "Drupal\views\EntityViewsData",
 *     "storage_schema" = "Drupal\api\StorageSchema\ApiContentStorageSchema",
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\AdminHtmlRouteProvider",
 *     }
 *   },
 *   base_table = "api_branch_docblock_reference_count",
 *   admin_permission = "administer API reference",
 *   field_indexes = {
 *     "object_name",
 *     "reference_type"
 *   },
 *   entity_keys = {
 *     "id" = "id",
 *     "uuid" = "uuid"
 *   },
 * )
 */
class DocReferenceCount extends ContentEntityBase implements DocReferenceCountInterface {

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['object_name'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Object name'));

    $fields['reference_type'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Reference type'))
      ->setRequired(TRUE);

    $fields['reference_count'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Reference count'))
      ->setRequired(TRUE);

    $fields['branch'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Branch'))
      ->setDescription(t('Branch where this is documented'))
      ->setSetting('target_type', 'branch')
      ->setSetting('handler', 'default')
      ->setCardinality(1)
      ->setRequired(TRUE);

    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function getBranch() {
    return $this->get('branch')->referencedEntities()[0];
  }

  /**
   * {@inheritdoc}
   */
  public function setBranch(BranchInterface $branch) {
    $this->set('branch', $branch);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getObjectName() {
    return $this->get('object_name')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setObjectName($object_name) {
    $this->set('object_name', $object_name);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getReferenceType() {
    return $this->get('reference_type')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setReferenceType($reference_type) {
    $this->set('reference_type', $reference_type);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getReferenceCount() {
    return $this->get('reference_count')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setReferenceCount($reference_count) {
    $this->set('reference_count', $reference_count);
    return $this;
  }

  /**
   * Calculates all new reference counts for a given branch.
   *
   * @param \Drupal\api\Interfaces\BranchInterface $branch
   *   Branch to check counts.
   */
  public static function calculateReferenceCounts(BranchInterface $branch) {
    return ExtendedQueries::calculateReferenceCounts($branch);
  }

}
