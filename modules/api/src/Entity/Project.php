<?php

namespace Drupal\api\Entity;

use Drupal\api\Traits\MatchingTrait;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\api\Interfaces\ProjectInterface;

/**
 * Defines the project entity class.
 *
 * @ContentEntityType(
 *   id = "project",
 *   label = @Translation("Project"),
 *   label_collection = @Translation("Projects"),
 *   handlers = {
 *     "access" = "Drupal\api\ApiAccessControlHandler",
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "list_builder" = "Drupal\api\ListBuilder\ProjectListBuilder",
 *     "views_data" = "Drupal\views\EntityViewsData",
 *     "storage_schema" = "Drupal\api\StorageSchema\ApiContentStorageSchema",
 *     "form" = {
 *       "add" = "Drupal\api\Form\ProjectForm",
 *       "edit" = "Drupal\api\Form\ProjectForm",
 *       "delete" = "Drupal\Core\Entity\ContentEntityDeleteForm"
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\AdminHtmlRouteProvider",
 *     }
 *   },
 *   base_table = "api_project",
 *   admin_permission = "administer API reference",
 *   field_indexes = {
 *     "title",
 *     "slug",
 *     "type"
 *   },
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "title",
 *     "uuid" = "uuid"
 *   },
 *   links = {
 *     "add-form" = "/admin/config/development/api/project/add",
 *     "canonical" = "/project/{project}",
 *     "edit-form" = "/admin/config/development/api/project/{project}/edit",
 *     "delete-form" = "/admin/config/development/api/project/{project}/delete",
 *     "collection" = "/admin/config/development/api/project"
 *   },
 * )
 */
class Project extends ContentEntityBase implements ProjectInterface {

  use EntityChangedTrait;
  use MatchingTrait;

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {

    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['title'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Title'))
      ->setDescription(t('The title of the project entity.'))
      ->setRequired(TRUE)
      ->setSetting('max_length', 255)
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => -5,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'string',
        'weight' => -5,
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['slug'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Slug'))
      ->setDescription(t('Used in URLs to identify the project, must be unique.'))
      ->setRequired(TRUE)
      ->setSetting('max_length', 255)
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => -5,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'string',
        'weight' => -5,
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['type'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Type'))
      ->setDescription(t('Type of project (core, module, theme, etc.).'))
      ->setRequired(TRUE)
      ->setSetting('max_length', 255)
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => -5,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'string',
        'weight' => -5,
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created'))
      ->setDescription(t('The time that the project was created.'));

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setDescription(t('The time that the project was last edited.'));

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
  public function getSlug() {
    return $this->get('slug')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setSlug($slug) {
    $this->set('slug', $slug);
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
  public function isCore() {
    return ($this->getType() == 'core');
  }

  /**
   * {@inheritdoc}
   */
  public function setType($type) {
    $this->set('type', $type);
    return $this;
  }

  /**
   * Find matching projects given some conditions.
   *
   * @param array $conditions
   *   Search conditions.
   *
   * @return int[]|null
   *   Matching entries IDs or null.
   */
  public static function matches(array $conditions) {
    /** @var \Drupal\Core\Entity\Query\QueryInterface $query */
    $query = \Drupal::entityQuery('project');
    self::applyConditions($query, $conditions);

    return $query->execute();
  }

  /**
   * Checks if a project exists.
   *
   * @param string $slug
   *   Slug of the project.
   *
   * @return bool
   *   Whether the project exists or not.
   */
  public static function exists($slug) {
    $exists = self::getBySlug($slug);
    return (bool) $exists;
  }

  /**
   * Gets project from the slug.
   *
   * @param string $slug
   *   Slug of the project.
   *
   * @return \Drupal\api\Interfaces\ProjectInterface|null
   *   Project if it exists or null if not.
   */
  public static function getBySlug($slug) {
    if (empty($slug)) {
      return NULL;
    }

    $ids = self::matches([
      'slug' => $slug,
    ]);
    if (empty($ids)) {
      return NULL;
    }

    $id = reset($ids);
    return self::load($id);
  }

  /**
   * Get all core projects in the system.
   *
   * @param bool $full_entity
   *   Return full entities or just ids.
   *
   * @return \Drupal\Core\Entity\EntityInterface[]|int[]|null
   *   List of core projects as objects or IDs or NULL if none found.
   */
  public static function getCoreProjects($full_entity = FALSE) {
    $ids = self::matches([
      'type' => 'core',
    ]);
    if (!$full_entity) {
      return $ids;
    }

    return Project::loadMultiple($ids);
  }

  /**
   * {@inheritdoc}
   */
  public function getBranches($full_entity = FALSE) {
    $ids = Branch::matches([
      'project' => $this->id(),
    ]);
    if (!$full_entity) {
      return $ids;
    }

    $storage_branches = $this->entityTypeManager()->getStorage('branch');
    return $storage_branches->loadMultiple($ids);
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultBranch($fallback = FALSE) {
    $branches = $this->getBranches(TRUE);
    if ($branches) {
      foreach ($branches as $branch) {
        /** @var \Drupal\api\Interfaces\BranchInterface $branch */
        if ($branch->isPreferred()) {
          return $branch;
        }
      }

      if ($fallback) {
        return array_shift($branches);
      }
    }

    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public static function preDelete(EntityStorageInterface $storage, array $entities) {
    parent::preDelete($storage, $entities);

    $storage_branches = \Drupal::entityTypeManager()->getStorage('branch');
    foreach ($entities as $entity) {
      $branches = $entity->getBranches(TRUE);
      $storage_branches->delete($branches);
    }
  }

}
