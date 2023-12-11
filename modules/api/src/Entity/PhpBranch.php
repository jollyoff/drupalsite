<?php

namespace Drupal\api\Entity;

use Drupal\api\Utilities;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\api\Interfaces\PhpBranchInterface;

/**
 * Defines the PHP Branch entity class.
 *
 * @ContentEntityType(
 *   id = "php_branch",
 *   label = @Translation("PHP Branch"),
 *   label_collection = @Translation("PHP Branches"),
 *   handlers = {
 *     "access" = "Drupal\api\ApiAccessControlHandler",
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "list_builder" = "Drupal\api\ListBuilder\PhpBranchListBuilder",
 *     "views_data" = "Drupal\views\EntityViewsData",
 *     "storage_schema" = "Drupal\api\StorageSchema\ApiContentStorageSchema",
 *     "form" = {
 *       "add" = "Drupal\api\Form\PhpBranchForm",
 *       "edit" = "Drupal\api\Form\PhpBranchForm",
 *       "delete" = "Drupal\Core\Entity\ContentEntityDeleteForm"
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\AdminHtmlRouteProvider",
 *     }
 *   },
 *   base_table = "api_php_branch",
 *   admin_permission = "administer API reference",
 *   field_indexes = {
 *   },
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "title",
 *     "uuid" = "uuid"
 *   },
 *   links = {
 *     "add-form" = "/admin/config/development/api/php_branch/add",
 *     "canonical" = "/php_branch/{php_branch}",
 *     "edit-form" = "/admin/config/development/api/php_branch/{php_branch}/edit",
 *     "delete-form" = "/admin/config/development/api/php_branch/{php_branch}/delete",
 *     "collection" = "/admin/config/development/api/php_branch"
 *   },
 * )
 */
class PhpBranch extends ContentEntityBase implements PhpBranchInterface {

  use EntityChangedTrait;

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['title'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Title'))
      ->setDescription(t('The title of the branch entity.'))
      ->setRequired(TRUE)
      ->setSetting('max_length', 255)
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'string',
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['function_list'] = BaseFieldDefinition::create('uri')
      ->setLabel(t('Function list'))
      ->setDescription(t('The URL of the JSON-formatted PHP function summary list.'))
      ->setRequired(TRUE)
      ->setSetting('max_length', 1024)
      ->setDisplayOptions('form', [
        'type' => 'url',
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'string',
      ])
      ->setDisplayConfigurable('view', TRUE)
      ->addConstraint('UniqueField');

    $fields['function_url_pattern'] = BaseFieldDefinition::create('uri')
      ->setLabel(t('Function URL pattern'))
      ->setDescription(t('The URL format used to build the link to PHP functions. Use the variable !function in place of the function name.'))
      ->setRequired(TRUE)
      ->setSetting('max_length', 1024)
      ->setDisplayOptions('form', [
        'type' => 'url',
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'string',
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['update_frequency'] = BaseFieldDefinition::create('list_integer')
      ->setLabel(t('Update frequency'))
      ->setDescription(t('During cron runs, this branch will be checked for updated files. This sets a minimum time to wait before checking.'))
      ->setRequired(TRUE)
      ->setDefaultValue(Utilities::ONE_MONTH)
      ->setSettings([
        'allowed_values' => Utilities::updateFrequencyValues(),
      ])
      ->setDisplayOptions('form', [
        'type' => 'options_select',
      ]);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created'))
      ->setDescription(t('The time that the branch was created.'));

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setDescription(t('The time that the branch was last edited.'));

    $fields['queued'] = BaseFieldDefinition::create('timestamp')
      ->setLabel(t('Queued'))
      ->setDescription(t('The time that the branch was last queued.'));

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
  public function getFunctionList() {
    return $this->get('function_list')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setFunctionList($function_list) {
    $this->set('function_list', $function_list);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getFunctionUrlPattern() {
    return $this->get('function_url_pattern')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setFunctionUrlPattern($function_url_pattern) {
    $this->set('function_url_pattern', $function_url_pattern);
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
  public function getQueued() {
    return $this->get('queued')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setQueued($timestamp) {
    $this->set('queued', $timestamp);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function reParse() {
    $this->set('queued', NULL);
    return $this->save();
  }

  /**
   * {@inheritdoc}
   */
  public function getUpdateFrequency() {
    return $this->get('update_frequency')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setUpdateFrequency($update_frequency) {
    $this->set('update_frequency', $update_frequency);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public static function preDelete(EntityStorageInterface $storage, array $entities) {
    parent::preDelete($storage, $entities);
    foreach ($entities as $entity) {
      /** @var \Drupal\api\Entity\PhpBranch $entity */
      $entity->deleteParsedData();
    }
  }

  /**
   * Deletes all related parsed data to the entity.
   *
   * @return \Drupal\api\Entity\PhpBranch
   *   The called php_branch entity.
   */
  public function deleteParsedData() {
    // Queuing the deletion because otherwise it could time-out.
    $info = [
      'entity_id' => $this->id(),
      'entity_type' => $this->getEntityTypeId(),
      'related' => [
        'php_documentation' => 'php_branch',
      ],
    ];
    \Drupal::service('queue')->get('api_delete_related')->createItem($info);

    return $this;
  }

}
