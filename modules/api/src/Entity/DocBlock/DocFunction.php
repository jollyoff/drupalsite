<?php

namespace Drupal\api\Entity\DocBlock;

use Drupal\api\ExtendedQueries;
use Drupal\api\Interfaces\BranchInterface;
use Drupal\api\Interfaces\DocBlock\DocFunctionInterface;
use Drupal\api\Traits\MatchingTrait;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\api\Interfaces\DocBlockInterface;

/**
 * Defines the docblock function entity class.
 *
 * @ContentEntityType(
 *   id = "docblock_function",
 *   label = @Translation("DocBlock Function"),
 *   label_collection = @Translation("DocBlock functions"),
 *   handlers = {
 *     "access" = "Drupal\api\ApiAccessControlHandler",
 *     "views_data" = "Drupal\views\EntityViewsData",
 *     "storage_schema" = "Drupal\api\StorageSchema\ApiContentStorageSchema",
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\AdminHtmlRouteProvider",
 *     }
 *   },
 *   base_table = "api_branch_docblock_function",
 *   admin_permission = "administer API reference",
 *   field_indexes = {
 *     "signature",
 *     "parameters",
 *     "return_value"
 *   },
 *   entity_keys = {
 *     "id" = "id",
 *     "uuid" = "uuid"
 *   },
 * )
 */
class DocFunction extends ContentEntityBase implements DocFunctionInterface {

  use MatchingTrait;

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['signature'] = BaseFieldDefinition::create('string_long')
      ->setLabel(t('Signature'))
      ->setRequired(TRUE);

    $fields['parameters'] = BaseFieldDefinition::create('string_long')
      ->setLabel(t('Parameters'));

    $fields['return_value'] = BaseFieldDefinition::create('string_long')
      ->setLabel(t('Return value'));

    $fields['docblock'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('DocBlock'))
      ->setDescription(t('DocBlock with information about this function'))
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
  public function getSignature() {
    return $this->get('signature')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setSignature($signature) {
    $this->set('signature', $signature);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getParameters() {
    return $this->get('parameters')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setParameters($parameters) {
    $this->set('parameters', $parameters);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getReturnValue() {
    return $this->get('return_value')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setReturnValue($return_value) {
    $this->set('return_value', $return_value);
    return $this;
  }

  /**
   * Gets an array of all the plain functions belonging to a branch.
   *
   * @param \Drupal\api\Interfaces\BranchInterface $branch
   *   Branch to check data from.
   *
   * @return array
   *   Function dump information.
   */
  public static function getFunctionDumpByBranch(BranchInterface $branch) {
    return ExtendedQueries::getFunctionDumpByBranch($branch);
  }

  /**
   * {@inheritdoc}
   */
  public static function matches(array $conditions, DocBlockInterface $docBlock) {
    /** @var \Drupal\Core\Entity\Query\QueryInterface $query */
    $query = \Drupal::entityQuery('docblock_function');
    $query->condition('docblock', $docBlock->id());
    self::applyConditions($query, $conditions);

    return $query->execute();
  }

}
