<?php

namespace Drupal\api\Entity\DocBlock;

use Drupal\api\Interfaces\DocBlock\DocClassMemberInterface;
use Drupal\api\Traits\MatchingTrait;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\api\Interfaces\DocBlockInterface;

/**
 * Defines the docblock class member entity class.
 *
 * @ContentEntityType(
 *   id = "docblock_class_member",
 *   label = @Translation("DocBlock Class Member"),
 *   label_collection = @Translation("DocBlock class members"),
 *   handlers = {
 *     "access" = "Drupal\api\ApiAccessControlHandler",
 *     "views_data" = "Drupal\views\EntityViewsData",
 *     "storage_schema" = "Drupal\api\StorageSchema\ApiContentStorageSchema",
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\AdminHtmlRouteProvider",
 *     }
 *   },
 *   base_table = "api_branch_docblock_class_member",
 *   admin_permission = "administer API reference",
 *   field_indexes = {
 *     "member_alias"
 *   },
 *   entity_keys = {
 *     "id" = "id",
 *     "uuid" = "uuid"
 *   },
 * )
 */
class DocClassMember extends ContentEntityBase implements DocClassMemberInterface {

  use MatchingTrait;

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['member_alias'] = BaseFieldDefinition::create('string')
      ->setDescription(t('Alias of the member, for trait inheritance'))
      ->setLabel(t('Member alias'));

    $fields['docblock'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('DocBlock'))
      ->setDescription(t('DocBlock method or element within the class'))
      ->setSetting('target_type', 'docblock')
      ->setSetting('handler', 'default')
      ->setCardinality(1)
      ->setRequired(TRUE);

    $fields['class_docblock'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Class DocBlock'))
      ->setDescription(t('Containing Class DocBlock'))
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
  public function getClassDocBlock() {
    return $this->get('class_docblock')->referencedEntities()[0];
  }

  /**
   * {@inheritdoc}
   */
  public function setClassDocBlock(DocBlockInterface $docblock) {
    $this->set('class_docblock', $docblock);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getMemberAlias() {
    return $this->get('member_alias')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setMemberAlias($member_alias) {
    $this->set('member_alias', $member_alias);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public static function matches(array $conditions, DocBlockInterface $class = NULL) {
    /** @var \Drupal\Core\Entity\Query\QueryInterface $query */
    $query = \Drupal::entityQuery('docblock_class_member');
    if ($class) {
      $query->condition('class_docblock', $class->id());
    }
    self::applyConditions($query, $conditions);

    return $query->execute();
  }

}
