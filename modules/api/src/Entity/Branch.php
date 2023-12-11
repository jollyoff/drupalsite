<?php

namespace Drupal\api\Entity;

use Drupal\api\Interfaces\ProjectInterface;
use Drupal\api\Traits\MatchingTrait;
use Drupal\api\Utilities;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\api\Interfaces\BranchInterface;

/**
 * Defines the branch entity class.
 *
 * @ContentEntityType(
 *   id = "branch",
 *   label = @Translation("Branch"),
 *   label_collection = @Translation("Branches"),
 *   handlers = {
 *     "access" = "Drupal\api\ApiAccessControlHandler",
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "list_builder" = "Drupal\api\ListBuilder\BranchListBuilder",
 *     "views_data" = "Drupal\views\EntityViewsData",
 *     "storage_schema" = "Drupal\api\StorageSchema\ApiContentStorageSchema",
 *     "form" = {
 *       "add" = "Drupal\api\Form\BranchForm",
 *       "edit" = "Drupal\api\Form\BranchForm",
 *       "delete" = "Drupal\Core\Entity\ContentEntityDeleteForm"
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\AdminHtmlRouteProvider",
 *     }
 *   },
 *   base_table = "api_branch",
 *   admin_permission = "administer API reference",
 *   field_indexes = {
 *     "title",
 *     "slug",
 *     "core_compatibility",
 *     "preferred",
 *     "weight"
 *   },
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "title",
 *     "uuid" = "uuid"
 *   },
 *   links = {
 *     "add-form" = "/admin/config/development/api/branch/add",
 *     "canonical" = "/branch/{branch}",
 *     "edit-form" = "/admin/config/development/api/branch/{branch}/edit",
 *     "delete-form" = "/admin/config/development/api/branch/{branch}/delete",
 *     "collection" = "/admin/config/development/api/branch"
 *   },
 * )
 */
class Branch extends ContentEntityBase implements BranchInterface {

  use EntityChangedTrait;
  use MatchingTrait;

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

    $fields['slug'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Slug / Branch name'))
      ->setDescription(t('Unique identifier for this branch within this project. Used as the URL suffix for documentation pages for this branch.'))
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

    $fields['core_compatibility'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Core compatibility'))
      ->setDescription(t('Which core version this branch is compatible with (for search/link grouping).'))
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

    $fields['project'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Project'))
      ->setDescription(t('The identifier of the project this branch is part of. If your project is not here, you need to <a href=":project_add">create</a> it first.', [
        ':project_add' => '/admin/config/development/api/project/add',
      ]))
      ->setSetting('target_type', 'project')
      ->setSetting('handler', 'default')
      ->setDisplayOptions('view', [
        'label' => 'hidden',
      ])
      ->setDisplayOptions('form', [
        'type' => 'options_select',
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE)
      ->setRequired(TRUE)
      ->setCardinality(1);

    $fields['preferred'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Preferred'))
      ->setDescription(t('Whether this is the preferred branch for this project. Checking this will uncheck the current preferred branch for the project, if any.'))
      ->setDefaultValue(FALSE)
      ->setDisplayOptions('form', [
        'type' => 'boolean_checkbox',
        'settings' => [
          'display_label' => TRUE,
        ],
      ])
      ->setDisplayConfigurable('form', TRUE);

    $fields['directories'] = BaseFieldDefinition::create('string_long')
      ->setLabel(t('Directories'))
      ->setDescription(t('Absolute paths to index, one per line.'))
      ->setDefaultValue('')
      ->setRequired(TRUE)
      ->setDisplayOptions('form', [
        'type' => 'string_textarea',
        'settings' => [
          'rows' => 4,
        ],
      ])
      ->addPropertyConstraints('value', ['ApiValidFilePaths' => []]);

    $fields['excluded_directories'] = BaseFieldDefinition::create('string_long')
      ->setLabel(t('Excluded directories'))
      ->setDescription(t('Absolute paths to exclude from the index, one per line. If multiple paths were given above, this setting might be temperamental as the relative path will be extracted and matched against all directories given.'))
      ->setDefaultValue('')
      ->setDisplayOptions('form', [
        'type' => 'string_textarea',
        'settings' => [
          'rows' => 4,
        ],
      ])
      ->addPropertyConstraints('value', ['ApiValidFilePaths' => []]);

    $fields['exclude_files_regexp'] = BaseFieldDefinition::create('string_long')
      ->setLabel(t('Exclude files by regular expressions'))
      ->setDescription(t('Regular expressions: all matching files and directories will be excluded, one per line. Include delimiters.'))
      ->setDefaultValue('')
      ->setDisplayOptions('form', [
        'type' => 'string_textarea',
        'settings' => [
          'rows' => 4,
        ],
      ])
      ->addPropertyConstraints('value', ['ApiValidRegex' => []]);

    $fields['exclude_drupalism_regexp'] = BaseFieldDefinition::create('string_long')
      ->setLabel(t('Exclude files from Drupal-specific parsing by regular expressions'))
      ->setDescription(t('Regular expressions: all matching files will be excluded from Drupal-specific parsing, one per line. Include delimeters. Note that if you change this setting, you will need to re-parse affected files.'))
      ->setDefaultValue('')
      ->setDisplayOptions('form', [
        'type' => 'string_textarea',
        'settings' => [
          'rows' => 4,
        ],
      ])
      ->addPropertyConstraints('value', ['ApiValidRegex' => []]);

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

    $fields['weight'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Weight'))
      ->setDescription(t('Ordering weight for some listings in the front end.'))
      ->setDefaultValue(0)
      ->setSettings([
        'min' => 0,
        'max' => 100,
      ])
      ->setDisplayOptions('form', [
        'type' => 'number',
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
  public function getWeight() {
    return $this->get('weight')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setWeight($weight) {
    $this->set('weight', $weight);
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
  public function getProject() {
    return $this->get('project')->referencedEntities()[0];
  }

  /**
   * {@inheritdoc}
   */
  public function setProject(ProjectInterface $project) {
    $this->set('project', $project);
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
  public function getPreferred() {
    return $this->get('preferred')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function isPreferred() {
    return (bool) $this->getPreferred();
  }

  /**
   * {@inheritdoc}
   */
  public function setPreferred($preferred) {
    $this->set('preferred', $preferred);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getDirectories($as_array = FALSE) {
    $value = $this->get('directories')->value;
    if ($as_array) {
      $paths = array_filter(explode(PHP_EOL, $value));
      foreach ($paths as &$path) {
        $path = trim($path);
        $path = rtrim($path, '/\\');
      }

      return $paths;
    }
    return $value;
  }

  /**
   * {@inheritdoc}
   */
  public function setDirectories($directories) {
    $this->set('directories', $directories);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getExcludedDirectories($as_array = FALSE) {
    $value = $this->get('excluded_directories')->value;
    if ($as_array) {
      $paths = array_filter(explode(PHP_EOL, $value));
      foreach ($paths as &$path) {
        $path = trim($path);
        $path = rtrim($path, '/\\');
      }

      return $paths;
    }
    return $value;
  }

  /**
   * {@inheritdoc}
   */
  public function setExcludedDirectories($excluded_directories) {
    $this->set('excluded_directories', $excluded_directories);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getExcludeFilesRegexp($as_array = FALSE) {
    $value = $this->get('exclude_files_regexp')->value;
    if ($as_array) {
      return array_filter(explode(PHP_EOL, $value));
    }
    return $value;
  }

  /**
   * {@inheritdoc}
   */
  public function setExcludeFilesRegexp($exclude_files_regexp) {
    $this->set('exclude_files_regexp', $exclude_files_regexp);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getExcludeDrupalismRegexp($as_array = FALSE) {
    $value = $this->get('exclude_drupalism_regexp')->value;
    if ($as_array) {
      return array_filter(explode(PHP_EOL, $value));
    }
    return $value;
  }

  /**
   * {@inheritdoc}
   */
  public function setExcludeDrupalismRegexp($exclude_drupalism_regexp) {
    $this->set('exclude_drupalism_regexp', $exclude_drupalism_regexp);
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
  public function getDocBlocks($full_entities = FALSE) {
    $ids = DocBlock::matches([
      'branch' => $this->id(),
    ]);
    if (!$full_entities) {
      return $ids;
    }

    $storage_docblock = $this->entityTypeManager()->getStorage('docblock');
    return $storage_docblock->loadMultiple($ids);
  }

  /**
   * Find matching branches given some conditions.
   *
   * @param array $conditions
   *   Search conditions.
   *
   * @return int[]|null
   *   Matching entries IDs or null.
   */
  public static function matches(array $conditions) {
    /** @var \Drupal\Core\Entity\Query\QueryInterface $query */
    $query = \Drupal::entityQuery('branch');
    self::applyConditions($query, $conditions);

    return $query->execute();
  }

  /**
   * Find branches by slug and project (if given).
   *
   * @param string $slug
   *   Slug of the branch.
   * @param \Drupal\api\Interfaces\ProjectInterface|null $project
   *   Project object or null.
   *
   * @return int[]|null
   *   List of IDs of matching branches or null if none were found.
   */
  public static function findBySlug($slug, ProjectInterface $project = NULL) {
    $conditions = [
      'slug' => $slug,
    ];
    if ($project) {
      $conditions['project'] = $project->id();
    }
    return self::matches($conditions);
  }

  /**
   * Gets branch from the slug.
   *
   * @param string $slug
   *   Slug of the branch.
   * @param \Drupal\api\Interfaces\ProjectInterface $project
   *   Project where the branch belongs.
   *
   * @return \Drupal\api\Interfaces\BranchInterface|null
   *   Branch if it exists or null if not.
   */
  public static function getBySlug($slug, ProjectInterface $project) {
    if (empty($slug)) {
      return NULL;
    }

    $ids = self::matches([
      'slug' => $slug,
      'project' => $project->id(),
    ]);
    if (empty($ids)) {
      return NULL;
    }

    $id = reset($ids);
    return self::load($id);
  }

  /**
   * {@inheritdoc}
   */
  public static function preDelete(EntityStorageInterface $storage, array $entities) {
    parent::preDelete($storage, $entities);
    foreach ($entities as $entity) {
      /** @var \Drupal\api\Entity\Branch $entity */
      $entity->deleteRelated('docblock');
      $entity->deleteRelated('docblock_reference');
      $entity->deleteRelated('docblock_reference_count');
    }
  }

  /**
   * Deletes all related Docblock files data to the entity.
   *
   * @param string $entity_type
   *   Type of the entities to delete.
   * @param string $field_name
   *   Name of the field pointing at the DocBlock. Defaults to 'docblock'.
   *
   * @return \Drupal\api\Entity\DocBlock
   *   The called php_branch entity.
   */
  public function deleteRelated($entity_type, $field_name = 'branch') {
    // Queuing the deletion because otherwise it could time-out.
    $info = [
      'entity_id' => $this->id(),
      'entity_type' => $this->getEntityTypeId(),
      'related' => [
        $entity_type => $field_name,
      ],
    ];
    \Drupal::service('queue')->get('api_delete_related')->createItem($info);

    return $this;
  }

  /**
   * Attempts to locate a core branch corresponding to the given branch.
   *
   * @param \Drupal\api\Interfaces\BranchInterface $branch
   *   Branch object to find a core branch for.
   *
   * @return \Drupal\api\Interfaces\BranchInterface|null
   *   Branch object for the core branch, if there is one. If not, FALSE.
   */
  public static function findCoreBranch(BranchInterface $branch) {
    if (!$branch) {
      return NULL;
    }

    $core_project_ids = Project::getCoreProjects();
    if (!empty($core_project_ids)) {
      $ids = self::matches([
        'core_compatibility' => $branch->getCoreCompatibility(),
        'project' => [
          'operator' => 'IN',
          'value' => $core_project_ids,
        ],
      ]);
      if (!empty($ids)) {
        $core_branch_id = reset($ids);
        return self::load($core_branch_id);
      }
    }

    return NULL;
  }

  /**
   * Attempts to locate a core branch corresponding to the given branch.
   *
   * @param \Drupal\api\Interfaces\BranchInterface|string $branch
   *   Branch object to find a core branch for or core compatibility value.
   * @param bool $full_entities
   *   Return full entities or just IDs.
   *
   * @return int[]|\Drupal\api\Interfaces\BranchInterface[]|null
   *   IDs of branches with same core compatibility as the one given.
   */
  public static function sameCoreCompatibilityBranches($branch, $full_entities = FALSE) {
    if (!$branch) {
      return NULL;
    }
    $core_compatibility = ($branch instanceof BranchInterface) ? $branch->getCoreCompatibility() : $branch;
    $ids = self::matches([
      'core_compatibility' => $core_compatibility,
    ]);
    if (!$full_entities) {
      return $ids;
    }

    return self::loadMultiple($ids);
  }

  /**
   * Find branches with same core compatibility.
   *
   * @param string $core_compatibility
   *   Core compatibility to look for.
   * @param bool $full_entities
   *   Return full entities or just IDs.
   *
   * @return int[]|\Drupal\api\Interfaces\BranchInterface[]|null
   *   IDs of branches with same core compatibility as the one given.
   */
  public static function getByCoreCompatibility($core_compatibility, $full_entities = FALSE) {
    $ids = self::matches([
      'core_compatibility' => $core_compatibility,
    ]);
    if (!$full_entities) {
      return $ids;
    }

    return self::loadMultiple($ids);
  }

}
