<?php

namespace Drupal\api\Entity;

use Drupal\api\Interfaces\ExternalBranchInterface;
use Drupal\api\Traits\MatchingTrait;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\api\Interfaces\ExternalDocumentationInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;

/**
 * Defines the external documentation entity class.
 *
 * @ContentEntityType(
 *   id = "external_documentation",
 *   label = @Translation("External Documentation"),
 *   label_collection = @Translation("External Documentation items"),
 *   handlers = {
 *     "access" = "Drupal\api\ApiAccessControlHandler",
 *     "list_builder" = "Drupal\api\ListBuilder\ExternalDocumentationListBuilder",
 *     "views_data" = "Drupal\views\EntityViewsData",
 *     "storage_schema" = "Drupal\api\StorageSchema\ApiContentStorageSchema",
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\AdminHtmlRouteProvider",
 *     }
 *   },
 *   base_table = "api_external_branch_documentation",
 *   admin_permission = "administer API reference",
 *   field_indexes = {
 *     "title",
 *     "object_name",
 *     "object_type",
 *     "member_name",
 *     "namespaced_name"
 *   },
 *   entity_keys = {
 *     "id" = "id",
 *     "uuid" = "uuid"
 *   },
 *   links = {
 *     "collection" = "/admin/config/development/api/external_branch/documentation"
 *   },
 * )
 */
class ExternalDocumentation extends ContentEntityBase implements ExternalDocumentationInterface {

  use EntityChangedTrait;
  use MatchingTrait;

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['title'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Title'))
      ->setRequired(TRUE);

    $fields['object_name'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Object name'))
      ->setRequired(TRUE);

    $fields['object_type'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Object type'))
      ->setRequired(TRUE);

    $fields['member_name'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Member name'))
      ->setDescription(t('For class members, the name without Class::'));

    $fields['namespaced_name'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Namespaced name'))
      ->setDescription(t('Fully-qualified namespaced name, starting with backslash.'));

    $fields['url'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Url'))
      ->setRequired(TRUE);

    $fields['summary'] = BaseFieldDefinition::create('string_long')
      ->setLabel(t('Summary'))
      ->setRequired(TRUE);

    $fields['external_branch'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Branch'))
      ->setDescription(t('The identifier of the External branch this documentation is part of.'))
      ->setSetting('target_type', 'external_branch')
      ->setSetting('handler', 'default')
      ->setRequired(TRUE)
      ->setCardinality(1);

    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function getTitle() {
    return $this->get('title')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setTitle($title) {
    $this->set('title', $title);
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
  public function getMemberName() {
    return $this->get('member_name')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setMemberName($member_name) {
    $this->set('member_name', $member_name);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getNamespacedName() {
    return $this->get('namespaced_name')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setNamespacedName($namespaced_name) {
    $this->set('namespaced_name', $namespaced_name);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getSummary() {
    return $this->get('summary')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setSummary($summary) {
    $this->set('summary', $summary);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getUrl() {
    return $this->get('url')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setUrl($url) {
    $this->set('url', $url);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getExternalBranch() {
    return $this->get('external_branch')->referencedEntities()[0];
  }

  /**
   * {@inheritdoc}
   */
  public function setExternalBranch(ExternalBranchInterface $external_branch) {
    $this->set('external_branch', $external_branch);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getExternalLink() {
    $url = Url::fromUri($this->getUrl(), [
      'attributes' => [
        'target' => '_blank',
        'class' => ['reference-manual'],
      ],
    ]);
    return Link::fromTextAndUrl($this->getTitle(), $url);
  }

  /**
   * {@inheritdoc}
   */
  public static function matches(array $conditions, ExternalBranchInterface $branch = NULL) {
    /** @var \Drupal\Core\Entity\Query\QueryInterface $query */
    $query = \Drupal::entityQuery('external_documentation');
    if ($branch) {
      $query->condition('external_branch', $branch->id());
    }

    self::applyConditions($query, $conditions);

    return $query->execute();
  }

  /**
   * {@inheritdoc}
   */
  public static function findByUrl($url, ExternalBranchInterface $branch) {
    return self::matches(['url' => $url], $branch);
  }

  /**
   * {@inheritdoc}
   */
  public static function findByMemberName($member_name, array $branch_ids, $function_or_not_functions) {
    $operator = ($function_or_not_functions) ? '=' : '<>';
    return self::matches([
      'member_name' => $member_name,
      'external_branch' => [
        'operator' => 'IN',
        'value' => $branch_ids,
      ],
      'object_type' => [
        'operator' => $operator,
        'value' => 'function',
      ],
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public static function findByNamespaceNameAndType($namespace_name, $type, array $branch_ids) {
    return self::matches([
      'object_type' => $type,
      'namespaced_name' => $namespace_name,
      'external_branch' => [
        'operator' => 'IN',
        'value' => $branch_ids,
      ],
    ]);
  }

  /**
   * Get the total number of entities of this custom type.
   *
   * @return int
   *   Total number of entities of this custom type.
   */
  public static function totalCount() {
    return (int) \Drupal::entityQuery('external_documentation')
      ->accessCheck(TRUE)
      ->count()
      ->execute();
  }

  /**
   * Update or insert new External documentation item.
   *
   * @param array $properties
   *   Fields and values to insert/udpate.
   * @param \Drupal\api\Interfaces\ExternalBranchInterface $branch
   *   Branch where this item belongs to.
   *
   * @return \Drupal\api\Interfaces\ExternalDocumentationInterface|null
   *   Created entity or null.
   */
  public static function createOrUpdate(array $properties, ExternalBranchInterface $branch) {
    $create_new = TRUE;
    $existing_ids = self::findByUrl($properties['url'], $branch);
    if (!empty($existing_ids)) {
      if (count($existing_ids) > 1) {
        $existing = self::loadMultiple($existing_ids);
        $storage_external_documentation = \Drupal::entityTypeManager()->getStorage('external_documentation');
        $storage_external_documentation->delete($existing);
      }
      else {
        $id = reset($existing_ids);
        $item = self::load($id);
        foreach ($properties as $field => $value) {
          if ($item->hasField($field)) {
            $item->set($field, $value);
          }
        }
        $item->save();
        $create_new = FALSE;
      }
    }

    if ($create_new) {
      $properties['external_branch'] = $branch->id();
      $item = self::create($properties);
      $item->save();
    }

    return $item;
  }

}
