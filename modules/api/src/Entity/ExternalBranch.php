<?php

namespace Drupal\api\Entity;

use Drupal\api\Utilities;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\api\Interfaces\ExternalBranchInterface;

/**
 * Defines the External Branch entity class.
 *
 * @ContentEntityType(
 *   id = "external_branch",
 *   label = @Translation("External Branch"),
 *   label_collection = @Translation("External Branches"),
 *   handlers = {
 *     "access" = "Drupal\api\ApiAccessControlHandler",
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "list_builder" = "Drupal\api\ListBuilder\ExternalBranchListBuilder",
 *     "views_data" = "Drupal\views\EntityViewsData",
 *     "storage_schema" = "Drupal\api\StorageSchema\ApiContentStorageSchema",
 *     "form" = {
 *       "add" = "Drupal\api\Form\ExternalBranchForm",
 *       "edit" = "Drupal\api\Form\ExternalBranchForm",
 *       "delete" = "Drupal\Core\Entity\ContentEntityDeleteForm"
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\AdminHtmlRouteProvider",
 *     }
 *   },
 *   base_table = "api_external_branch",
 *   admin_permission = "administer API reference",
 *   field_indexes = {
 *   },
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "title",
 *     "uuid" = "uuid"
 *   },
 *   links = {
 *     "add-form" = "/admin/config/development/api/external_branch/add",
 *     "canonical" = "/external_branch/{external_branch}",
 *     "edit-form" = "/admin/config/development/api/external_branch/{external_branch}/edit",
 *     "delete-form" = "/admin/config/development/api/external_branch/{external_branch}/delete",
 *     "collection" = "/admin/config/development/api/external_branch"
 *   },
 * )
 */
class ExternalBranch extends ContentEntityBase implements ExternalBranchInterface {

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
      ->setLabel(t('Branch full_list URL'))
      ->setDescription(t('The URL of the API documentation dump, such as https://api.drupal.org/api/drupal/full_list/7'))
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

    $fields['search_url'] = BaseFieldDefinition::create('uri')
      ->setLabel(t('Branch search URL'))
      ->setDescription(t('The URL to use for searching by appending the search term, such as https://api.drupal.org/api/drupal/7/search/'))
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

    $fields['core_compatibility'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Core compatibility'))
      ->setDescription(t('Which core version this external branch is compatible with (for search/link grouping).'))
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

    $fields['type'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Type'))
      ->setDescription(t('Type of project (core, module, theme, etc.).'))
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

    $fields['items_per_page'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Items per page'))
      ->setDescription(t('Updates are paged using this many items per page. 2000 is suggested, and is the default if not set.'))
      ->setDefaultValue(2000)
      ->setSettings([
        'min' => 100,
        'max' => 3000,
      ])
      ->setDisplayOptions('form', [
        'type' => 'number',
      ]);

    $fields['timeout'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Timeout'))
      ->setDescription(t('Downloads for each page of updates time out after this many seconds. If you are having trouble with timeouts, decrease the items per page setting, or increase this timeout value. Defaults to 30 seconds if not set.'))
      ->setDefaultValue(30)
      ->setSettings([
        'min' => 1,
        'max' => 300,
      ])
      ->setDisplayOptions('form', [
        'type' => 'number',
      ]);

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
  public function getCoreCompatibility() {
    return $this->get('core_compatibility')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setCoreCompatibility($core_compatibility) {
    $this->set('core_compatibility', $core_compatibility);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getType() {
    return $this->get('type')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setType($type) {
    $this->set('type', $type);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getTimeout() {
    return $this->get('timeout')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setTimeout($timeout) {
    $this->set('timeout', $timeout);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getItemsPerPage() {
    return $this->get('items_per_page')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setItemsPerPage($items_per_page) {
    $this->set('items_per_page', $items_per_page);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getSearchUrl() {
    return $this->get('search_url')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setSearchUrl($search_url) {
    $this->set('search_url', $search_url);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public static function preDelete(EntityStorageInterface $storage, array $entities) {
    parent::preDelete($storage, $entities);
    foreach ($entities as $entity) {
      /** @var \Drupal\api\Entity\ExternalBranch $entity */
      $entity->deleteParsedData();
    }
  }

  /**
   * Deletes all related parsed data to the entity.
   *
   * @return \Drupal\api\Entity\ExternalBranch
   *   The called php_branch entity.
   */
  public function deleteParsedData() {
    // Queuing the deletion because otherwise it could time-out.
    $info = [
      'entity_id' => $this->id(),
      'entity_type' => $this->getEntityTypeId(),
      'related' => [
        'external_documentation' => 'external_branch',
      ],
    ];
    \Drupal::service('queue')->get('api_delete_related')->createItem($info);

    return $this;
  }

}
