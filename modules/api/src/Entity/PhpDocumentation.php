<?php

namespace Drupal\api\Entity;

use Drupal\api\Interfaces\PhpBranchInterface;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\api\Interfaces\PhpDocumentationInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;

/**
 * Defines the php documentation entity class.
 *
 * @ContentEntityType(
 *   id = "php_documentation",
 *   label = @Translation("PHP Documentation"),
 *   label_collection = @Translation("PHP Documentation functions"),
 *   handlers = {
 *     "access" = "Drupal\api\ApiAccessControlHandler",
 *     "list_builder" = "Drupal\api\ListBuilder\PhpDocumentationListBuilder",
 *     "views_data" = "Drupal\views\EntityViewsData",
 *     "storage_schema" = "Drupal\api\StorageSchema\ApiContentStorageSchema",
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\AdminHtmlRouteProvider",
 *     }
 *   },
 *   base_table = "api_php_branch_documentation",
 *   admin_permission = "administer API reference",
 *   field_indexes = {
 *     "object_name",
 *     "object_type",
 *     "member_name"
 *   },
 *   entity_keys = {
 *     "id" = "id",
 *     "uuid" = "uuid"
 *   },
 *   links = {
 *     "collection" = "/admin/config/development/api/php_branch/documentation"
 *   },
 * )
 */
class PhpDocumentation extends ContentEntityBase implements PhpDocumentationInterface {

  use EntityChangedTrait;

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['object_name'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Object name'))
      ->setRequired(TRUE);

    $fields['object_type'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Object type'))
      ->setRequired(TRUE);

    $fields['member_name'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Member name'))
      ->setDescription(t('For class members, the name without Class::'));

    $fields['documentation'] = BaseFieldDefinition::create('string_long')
      ->setLabel(t('Documentation'))
      ->setRequired(TRUE);

    $fields['php_branch'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Branch'))
      ->setDescription(t('The identifier of the PHP branch this documentation is part of.'))
      ->setSetting('target_type', 'php_branch')
      ->setSetting('handler', 'default')
      ->setRequired(TRUE)
      ->setCardinality(1);

    return $fields;
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
  public function getDocumentation() {
    return $this->get('documentation')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setDocumentation($documentation) {
    $this->set('documentation', $documentation);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getPhpBranch() {
    return $this->get('php_branch')->referencedEntities()[0];
  }

  /**
   * {@inheritdoc}
   */
  public function setPhpBranch(PhpBranchInterface $php_branch) {
    $this->set('php_branch', $php_branch);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getExternalLink() {
    // php.net replaces some characters to build the URLs.
    $slug = str_replace(
      ['::', '\\', '__'],
      ['.', '-', ''],
      $this->getObjectName()
    );
    $function_url = str_replace('!function', $slug, $this->getPhpBranch()->getFunctionUrlPattern());
    $url = Url::fromUri($function_url, [
      'attributes' => [
        'target' => '_blank',
        'class' => ['php-manual'],
      ],
    ]);
    return Link::fromTextAndUrl($this->getObjectName(), $url);
  }

  /**
   * Checks if a php documentation object exists and return matches.
   *
   * @param string $object_name
   *   Name of the function to look for.
   * @param \Drupal\api\Interfaces\PhpBranchInterface $branch
   *   Branch the function belongs to.
   *
   * @return int[]|null
   *   Matching entries IDs or null.
   */
  public static function findByName($object_name, PhpBranchInterface $branch = NULL) {
    $query = \Drupal::entityQuery('php_documentation')
      ->accessCheck(TRUE)
      ->condition('object_name', $object_name);
    if (!is_null($branch)) {
      $query->condition('php_branch', $branch->id());
    }
    return $query->execute();
  }

  /**
   * Get the total number of entities of this custom type.
   *
   * @return int
   *   Total number of entities of this custom type.
   */
  public static function totalCount() {
    return (int) \Drupal::entityQuery('php_documentation')
      ->accessCheck(TRUE)
      ->count()
      ->execute();
  }

  /**
   * Update or insert new PHP documentation item.
   *
   * @param array $properties
   *   Fields and values to insert/udpate.
   * @param \Drupal\api\Interfaces\PhpBranchInterface $branch
   *   Branch where this item belongs to.
   *
   * @return \Drupal\api\Interfaces\PhpDocumentationInterface|null
   *   Created entity or null.
   */
  public static function createOrUpdate(array $properties, PhpBranchInterface $branch) {
    $create_new = TRUE;
    $existing_ids = self::findByName($properties['object_name'], $branch);
    if (!empty($existing_ids)) {
      if (count($existing_ids) > 1) {
        $existing = self::loadMultiple($existing_ids);
        $storage_php_documentation = \Drupal::entityTypeManager()->getStorage('php_documentation');
        $storage_php_documentation->delete($existing);
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
      $properties['php_branch'] = $branch->id();
      $item = self::create($properties);
      $item->save();
    }

    return $item;
  }

}
