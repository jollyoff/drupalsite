<?php

namespace Drupal\api\Entity;

use Drupal\api\Entity\DocBlock\DocClassMember;
use Drupal\api\Entity\DocBlock\DocFile;
use Drupal\api\Entity\DocBlock\DocFunction;
use Drupal\api\Entity\DocBlock\DocNamespace;
use Drupal\api\Entity\DocBlock\DocOverride;
use Drupal\api\Entity\DocBlock\DocReference;
use Drupal\api\Formatter;
use Drupal\api\Interfaces\BranchInterface;
use Drupal\api\Traits\MatchingTrait;
use Drupal\comment\Plugin\Field\FieldType\CommentItemInterface;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\api\Interfaces\DocBlockInterface;
use Drupal\Core\Url;

/**
 * Defines the docblock entity class.
 *
 * @ContentEntityType(
 *   id = "docblock",
 *   label = @Translation("DocBlock"),
 *   label_collection = @Translation("DocBlocks"),
 *   handlers = {
 *     "access" = "Drupal\api\ApiAccessControlHandler",
 *     "views_data" = "Drupal\views\EntityViewsData",
 *     "storage_schema" = "Drupal\api\StorageSchema\ApiContentStorageSchema",
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\AdminHtmlRouteProvider",
 *     }
 *   },
 *   base_table = "api_branch_docblock",
 *   admin_permission = "administer API reference",
 *   field_indexes = {
 *     "title",
 *     "object_name",
 *     "object_type",
 *     "file_name",
 *     "member_name",
 *     "namespace",
 *     "namespaced_name",
 *     "is_drupal"
 *   },
 *   entity_keys = {
 *     "id" = "id",
 *     "uuid" = "uuid"
 *   },
 *   links = {
 *     "canonical" = "/docblock/{docblock}"
 *   },
 * )
 */
class DocBlock extends ContentEntityBase implements DocBlockInterface {

  use MatchingTrait;
  use EntityChangedTrait;

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['title'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Title'))
      ->setDescription(t('Display title'))
      ->setRequired(TRUE);

    $fields['object_name'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Object name'))
      ->setDescription(t('Name of object'))
      ->setRequired(TRUE);

    $fields['object_type'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Object type'))
      ->setDescription(t('Type of object: function, class, etc.'))
      ->setRequired(TRUE);

    $fields['file_name'] = BaseFieldDefinition::create('string')
      ->setLabel(t('File name'))
      ->setDescription(t('Full path of file.'))
      ->setRequired(TRUE);

    $fields['member_name'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Member name'))
      ->setDescription(t('For class members, the name without Class::'));

    $fields['documentation'] = BaseFieldDefinition::create('string_long')
      ->setLabel(t('Documentation'))
      ->setDescription(t('Full documentation'))
      ->setRequired(TRUE);

    $fields['summary'] = BaseFieldDefinition::create('string_long')
      ->setLabel(t('Summary'))
      ->setDescription(t('Short description from documentation'))
      ->setRequired(TRUE);

    $fields['code'] = BaseFieldDefinition::create('string_long')
      ->setLabel(t('Code'))
      ->setDescription(t('Formatted source code'))
      ->setRequired(TRUE);

    $fields['see'] = BaseFieldDefinition::create('string_long')
      ->setLabel(t('See'))
      ->setDescription(t('See also references'));

    $fields['var'] = BaseFieldDefinition::create('string_long')
      ->setLabel(t('Var'))
      ->setDescription(t('Variable type from var tag'));

    $fields['throws'] = BaseFieldDefinition::create('string_long')
      ->setLabel(t('Throws'))
      ->setDescription(t('Throws section'));

    $fields['deprecated'] = BaseFieldDefinition::create('string_long')
      ->setLabel(t('Deprecated'))
      ->setDescription(t('Deprecated descriptions'));

    $fields['namespace'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Namespace'))
      ->setDescription(t('Namespace, if any'));

    $fields['namespaced_name'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Namespaced name'))
      ->setRequired(TRUE)
      ->setDescription(t('Fully-qualified namespaced name, starting with backslash'));

    $fields['modifiers'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Modifiers'))
      ->setDescription(t('Modifiers such as static, abstract, etc.'));

    $fields['start_line'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Start line'))
      ->setDescription(t('Start line of the object in file'));

    $fields['is_drupal'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Is Drupal'))
      ->setDescription(t('Whether this is or is not a Drupal object'));

    $fields['class'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Class'))
      ->setDescription(t('DocBlock ID of class this is part of'))
      ->setSetting('target_type', 'docblock')
      ->setSetting('handler', 'default')
      ->setCardinality(1);

    $fields['branch'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Branch'))
      ->setDescription(t('The identifier of the branch this documentation is part of.'))
      ->setSetting('target_type', 'branch')
      ->setSetting('handler', 'default')
      ->setRequired(TRUE)
      ->setCardinality(1);

    $fields['comments'] = BaseFieldDefinition::create('comment')
      ->setLabel(t('Comments'))
      ->setDescription(t('Comments for documentation objects.'))
      ->setSetting('comment_type', 'api_comment')
      ->setDefaultValueCallback('\Drupal\api\Entity\DocBlock::commentStatusValue');

    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function toUrl($rel = 'canonical', array $options = []) {
    if ($rel == 'canonical') {
      $url = Formatter::objectUrl($this);
      if ($url) {
        return Url::fromUri($url)->setOptions($options);
      }
    }

    return parent::toUrl($rel, $options);
  }

  /**
   * Returns the current default value for the field.
   *
   * @return array
   *   Value for the field.
   */
  public static function commentStatusValue() {
    return [
      'status' => \Drupal::config('api.comments')->get('status') ?? CommentItemInterface::OPEN,
    ];
  }

  /**
   * Apply comments settings to all existing DocBlocks.
   */
  public static function applyCommentsStatusToAll() {
    $status = \Drupal::config('api.comments')->get('status');
    if (!is_null($status)) {
      // We don't need hooks, so mass update query.
      \Drupal::database()->update('api_branch_docblock')
        ->fields(['comments' => $status])
        ->execute();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getComments() {
    return $this->get('comments');
  }

  /**
   * {@inheritdoc}
   */
  public function label() {
    return $this->getTitle();
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
  public function getFileName() {
    return $this->get('file_name')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setFileName($file_name) {
    $this->set('file_name', $file_name);
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
  public function getCode() {
    return $this->get('code')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setCode($code) {
    $this->set('code', $code);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getSee() {
    return $this->get('see')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setSee($see) {
    $this->set('see', $see);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getVar() {
    return $this->get('var')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setVar($var) {
    $this->set('var', $var);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getThrows() {
    return $this->get('throws')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setThrows($throws) {
    $this->set('throws', $throws);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getDeprecated() {
    return $this->get('deprecated')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setDeprecated($deprecated) {
    $this->set('deprecated', $deprecated);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getNamespace() {
    return $this->get('namespace')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setNamespace($namespace) {
    $this->set('namespace', $namespace);
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
  public function getModifiers() {
    return $this->get('modifiers')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setModifiers($modifiers) {
    $this->set('modifiers', $modifiers);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getStartLine() {
    return $this->get('start_line')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setStartLine($start_line) {
    $this->set('start_line', $start_line);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getIsDrupal() {
    return $this->get('is_drupal')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function isDrupal() {
    return $this->getIsDrupal();
  }

  /**
   * {@inheritdoc}
   */
  public function setIsDrupal($is_drupal) {
    $this->set('is_drupal', $is_drupal);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getClass() {
    return $this->get('class')->referencedEntities()[0] ?? NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function setClass(DocBlockInterface $class) {
    $this->set('class', $class);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getBranch() {
    $referenced_entities = $this->get('branch')->referencedEntities();
    return $referenced_entities ? $referenced_entities[0] : NULL;
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
  public function getDocFunction() {
    $function_ids = DocFunction::matches([], $this);
    if ($function_ids) {
      $function_id = array_shift($function_ids);
      return DocFunction::load($function_id);
    }

    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getDocFile() {
    $file_ids = DocFile::matches([], $this);
    if ($file_ids) {
      $file_id = array_shift($file_ids);
      return DocFile::load($file_id);
    }

    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getDocClassMembers() {
    $class_members = DocClassMember::matches([], $this);
    if ($class_members) {
      return DocClassMember::loadMultiple($class_members);
    }

    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getDocBlockClassMembers($member_name = NULL, $function_or_not_functions = NULL) {
    if ($this->getObjectType() !== 'class') {
      return NULL;
    }

    $members = [];

    $conditions = [
      'class' => $this->id(),
    ];
    if (!is_null($member_name)) {
      $conditions['member_name'] = $member_name;
    }
    if (!is_null($function_or_not_functions)) {
      $operator = ($function_or_not_functions) ? '=' : '<>';
      $conditions['object_type'] = [
        'operator' => $operator,
        'value' => 'function',
      ];
    }

    $direct_members = self::matches($conditions, $this->getBranch());
    if ($direct_members) {
      $members = self::loadMultiple($direct_members);
    }

    $class_members = $this->getDocClassMembers();
    if ($class_members) {
      foreach ($class_members as $class_member) {
        $include = TRUE;

        $name_used = (!empty($class_member->getMemberAlias())) ?
          $class_member->getMemberAlias() :
          $class_member->getDocBlock()->getMemberName();
        if (!is_null($member_name) && ($member_name != $name_used)) {
          $include = FALSE;
        }

        if (!is_null($function_or_not_functions)) {
          $type = $class_member->getDocBlock()->getObjectType();
          if ($type == 'function' && !$function_or_not_functions) {
            $include = FALSE;
          }
          elseif ($type != 'function' && $function_or_not_functions) {
            $include = FALSE;
          }
        }

        if ($include && !in_array($class_member->getDocBlock()->id(), $direct_members)) {
          $members[] = $class_member->getDocBlock();
        }
      }
    }

    return $members;
  }

  /**
   * Get matches by referencing docblocks.
   *
   * @param array $reference_ids
   *   Array of DocReference IDs.
   * @param \Drupal\api\Interfaces\BranchInterface $branch
   *   Branch where to look for results.
   * @param bool $extends_match
   *   Get the ID of the extends_docblock instead of the docblock property.
   *
   * @return int[]|null
   *   List of IDs or null.
   */
  protected static function getMatchesByReferences(array $reference_ids, BranchInterface $branch = NULL, $extends_match = FALSE) {
    if (empty($reference_ids)) {
      return NULL;
    }

    $references = DocReference::loadMultiple($reference_ids);
    $ids = [];
    foreach ($references as $reference) {
      if ($extends_match && $reference->getExtendsDocBlock()) {
        $ids[] = $reference->getExtendsDocBlock()->id();
      }
      elseif (!$extends_match && $reference->getDocBlock()) {
        $ids[] = $reference->getDocBlock()->id();
      }
    }

    if (empty($ids)) {
      return NULL;
    }

    // And now get the objects info.
    return self::matches([
      'id' => [
        'operator' => 'IN',
        'value' => $ids,
      ],
      'object_type' => [
        'operator' => 'IN',
        'value' => ['class', 'interface'],
      ],
    ], $branch, NULL, ['namespaced_name' => 'ASC']);
  }

  /**
   * {@inheritdoc}
   */
  public function getAncestors($type = 'class', $same_branch = FALSE) {
    $branch = ($same_branch) ? $this->getBranch() : NULL;

    // First get elements extending this object.
    $references = DocReference::matches([
      'docblock' => $this->id(),
      'object_type' => $type,
    ], $branch);

    return self::getMatchesByReferences($references, $branch, TRUE);
  }

  /**
   * {@inheritdoc}
   */
  public function getChildren($type = 'class', $same_branch = FALSE) {
    $branch = ($same_branch) ? $this->getBranch() : NULL;

    // First get elements extending this object.
    $references = DocReference::matches([
      'extends_docblock' => $this->id(),
      'object_type' => $type,
    ], $branch);

    return self::getMatchesByReferences($references, $branch);
  }

  /**
   * {@inheritdoc}
   */
  public function getDocOverrides() {
    $results = DocOverride::matches([], $this);
    return ($results) ? DocOverride::loadMultiple($results) : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getDocReferences($type = '', $extending_internal_only = TRUE, $name_match = NULL) {
    $conditions = [];
    if ($type) {
      if (is_array($type)) {
        $conditions['object_type'] = [
          'operator' => 'IN',
          'value' => $type,
        ];
      }
      else {
        $conditions['object_type'] = $type;
      }
    }
    if ($extending_internal_only) {
      $conditions['extends_docblock'] = [
        'operator' => '<>',
        'value' => '',
      ];
    }

    if (!is_null($name_match)) {
      $conditions['object_name'] = $name_match;
      $references = DocReference::matches($conditions, $this->getBranch());
    }
    else {
      $references = DocReference::matches($conditions, $this->getBranch(), $this);
    }

    if ($references) {
      return DocReference::loadMultiple($references);
    }

    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function toBasicArray() {
    return [
      'object_name' => $this->getObjectName(),
      'namespaced_name' => $this->getNamespacedName(),
      'title' => $this->getTitle(),
      'member_name' => $this->getMemberName(),
      'summary' => $this->getSummary(),
      'object_type' => $this->getObjectType(),
      'file_name' => $this->getFileName(),
      'url' => $this->toUrl('canonical', [
        'absolute' => TRUE,
      ])->toString(),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function preDelete(EntityStorageInterface $storage, array $entities) {
    parent::preDelete($storage, $entities);
    foreach ($entities as $entity) {
      /** @var \Drupal\api\Entity\DocBlock $entity */
      $entity->deleteRelated('docblock_file');
      $entity->deleteRelated('docblock_function');
      $entity->deleteRelated('docblock_namespace');
      $entity->deleteRelated('docblock_class_member');
      $entity->deleteRelated('docblock_class_member', 'class_docblock');
      $entity->deleteRelated('docblock_override');
      $entity->deleteRelated('docblock_override', 'overrides_docblock');
      $entity->deleteRelated('docblock_override', 'documented_in_docblock');
      $entity->deleteRelated('docblock_reference');
      $entity->deleteRelated('docblock_reference', 'extends_docblock');
    }
  }

  /**
   * {@inheritdoc}
   */
  public function deleteRelated($entity_type, $field_name = 'docblock') {
    // https://www.drupal.org/node/3051072
    $storage_handler = \Drupal::entityTypeManager()->getStorage($entity_type);
    $ids = \Drupal::entityQuery($entity_type)
      ->accessCheck(FALSE)
      ->condition($field_name, $this->id())
      ->execute();
    foreach (array_chunk($ids, 50) as $chunk) {
      $entities = $storage_handler->loadMultiple($chunk);
      $storage_handler->delete($entities);
    }

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public static function getFullList(BranchInterface $branch, array $range = NULL) {
    return self::matches([
      'object_type' => [
        'operator' => '<>',
        'value' => 'mainpage',
      ],
    ], $branch, $range, ['object_name' => 'ASC']);
  }

  /**
   * {@inheritdoc}
   */
  public static function getMainpage(BranchInterface $branch) {
    $result = self::matches([
      'object_type' => 'mainpage',
    ], $branch);
    return array_shift($result);
  }

  /**
   * {@inheritdoc}
   */
  public static function getListingTypes(BranchInterface $branch) {
    $results = [];

    // Make the conditions for each type of listing. The objective is to
    // count to see if there is at least one object of that type to list on the
    // corresponding listing page.
    $type_conditions = [
      'groups' => ['object_type' => 'group'],
      'classes' => [
        'object_type' => [
          'operator' => 'IN',
          'value' => ['class', 'interface', 'trait'],
        ],
        'class' => 0,
      ],
      'functions' => ['object_type' => 'function'],
      'constants' => ['object_type' => 'constant'],
      'globals' => ['object_type' => 'global'],
      'files' => ['object_type' => 'file'],
      'namespaces' => [
        'namespace' => [
          'operator' => '<>',
          'value' => '',
        ],
      ],
      'deprecated' => [
        'deprecated' => [
          'operator' => '<>',
          'value' => '',
        ],
      ],
      'services' => ['object_type' => 'service'],
      'elements' => ['object_type' => 'element'],
    ];

    foreach ($type_conditions as $type => $conditions) {
      $res = ($type == 'elements') ?
        DocReference::matches($conditions, $branch) :
        self::matches($conditions, $branch);
      $results[$type] = ($res && count($res));
    }

    return $results;
  }

  /**
   * {@inheritdoc}
   */
  public static function findFiles(BranchInterface $branch) {
    return self::matches([
      'object_type' => 'file',
    ], $branch);
  }

  /**
   * {@inheritdoc}
   */
  public static function matches(array $conditions, BranchInterface $branch = NULL, array $range = NULL, array $sort = NULL) {
    /** @var \Drupal\Core\Entity\Query\QueryInterface $query */
    $query = \Drupal::entityQuery('docblock');
    if ($branch) {
      $query->condition('branch', $branch->id());
    }

    self::applyConditions($query, $conditions, $range, $sort);

    return $query->execute();
  }

  /**
   * {@inheritdoc}
   */
  public static function findByMemberName($member_name, array $branch_ids, $function_or_not_functions) {
    $operator = ($function_or_not_functions) ? '=' : '<>';
    $conditions = [
      'member_name' => $member_name,
      'branch' => [
        'operator' => 'IN',
        'value' => $branch_ids,
      ],
      'object_type' => [
        'operator' => $operator,
        'value' => 'function',
      ],
    ];
    return DocBlock::matches($conditions);
  }

  /**
   * {@inheritdoc}
   */
  public static function findClassesByNamespacedName($namespaced_name, array $branch_ids) {
    return DocBlock::matches([
      'object_type' => 'class',
      'namespaced_name' => $namespaced_name,
      'branch' => [
        'operator' => 'IN',
        'value' => $branch_ids,
      ],
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public static function searchByTitle($term, BranchInterface $branch = NULL, $limit = 10, $exact_match = FALSE) {
    $operator = ($exact_match) ? '=' : 'CONTAINS';
    return DocBlock::matches([
      'title' => [
        'operator' => $operator,
        'value' => $term,
      ],
    ], $branch, ['limit' => (int) $limit, 'offset' => 0], ['title' => 'ASC']);
  }

  /**
   * {@inheritdoc}
   */
  public static function findSimilar(DocBlockInterface $docBlock, $same_branch = TRUE) {
    $conditions = [];

    // Find objects of the same name within this branch.
    if ($docBlock->getObjectType() == 'file') {
      // For files, the object name includes the path, so match on the title.
      $conditions['title'] = $docBlock->getTitle();
    }
    else {
      // For other objects, match on the object name and find matching names
      // within the same branch.
      $conditions['object_name'] = $docBlock->getObjectName();
    }
    $conditions['object_type'] = $docBlock->getObjectType();
    $conditions['id'] = [
      'operator' => '<>',
      'value' => $docBlock->id(),
    ];

    if ($same_branch) {
      return DocBlock::matches($conditions, $docBlock->getBranch());
    }

    $other_branches = array_diff($docBlock->getBranch()->getProject()->getBranches(), [$docBlock->getBranch()->id()]);
    if (empty($other_branches)) {
      return NULL;
    }

    $conditions['branch'] = [
      'operator' => 'IN',
      'value' => $other_branches,
    ];

    return DocBlock::matches($conditions);

  }

  /**
   * {@inheritdoc}
   */
  public static function totalCount() {
    return (int) \Drupal::entityQuery('docblock')
      ->accessCheck(TRUE)
      ->count()
      ->execute();
  }

  /**
   * Insert or update docblock.
   *
   * @param array $docblock
   *   Docblock information.
   * @param \Drupal\api\Interfaces\BranchInterface $branch
   *   Branch where it belongs to.
   * @param array $processed_ids
   *   Already processed ids to avoid duplication.
   *
   * @return \Drupal\api\Interfaces\DocBlockInterface|null
   *   Created or updated entity or null.
   */
  protected static function upsert(array $docblock, BranchInterface $branch, array $processed_ids) {
    // Check if we have any existing records matching the criteria.
    $existing_ids = self::matches(
      [
        'object_name' => $docblock['object_name'],
        'file_name' => $docblock['file_name'],
        'object_type' => $docblock['object_type'],
      ],
      $branch
    );

    // Create or update now the main record.
    if (!empty($existing_ids)) {
      $id = reset($existing_ids);
      if (in_array($id, $processed_ids)) {
        \Drupal::logger('api')->warning(
          'Duplicate item found in file %file at line %line in %branch. Only first instance of %name is saved',
          [
            '%file' => $docblock['file_name'],
            '%line' => $docblock['start_line'],
            '%name' => $docblock['object_name'],
            '%branch' => $branch->getTitle(),
          ]
        );
        // Don't save this one.
        return NULL;
      }

      /** @var \Drupal\api\Entity\DocBlock $entity */
      $entity = self::load($id);
      foreach ($docblock as $field => $value) {
        if ($entity->hasField($field)) {
          $entity->set($field, $value);
        }
      }
      $entity->save();
    }
    else {
      $docblock['branch'] = $branch->id();
      $entity = self::create($docblock);
      $entity->save();
    }

    return $entity;
  }

  /**
   * Insert or update related information for a docblock.
   *
   * @param array $docblock
   *   Docblock array.
   * @param \Drupal\api\Interfaces\DocBlockInterface $entity
   *   DocBlock entity.
   * @param \Drupal\api\Interfaces\BranchInterface $branch
   *   Branch entity.
   * @param string $namespace
   *   Namespace where the info belongs to.
   * @param array $use_aliases
   *   Aliases used in this docblock.
   */
  protected static function upsertRelated(array $docblock, DocBlockInterface $entity, BranchInterface $branch, $namespace, array $use_aliases) {
    /** @var \Drupal\api\Parser $parser */
    $parser = \Drupal::service('api.parser');

    // Now let's go for all the related entities.
    switch ($docblock['object_type']) {
      case 'function':
        $entity->deleteRelated('docblock_function');

        $parser->addTextDefaults($docblock, 'docblock_function');
        /** @var \Drupal\api\Interfaces\DocBlock\DocFunctionInterface $docFunction */
        $docFunction = DocFunction::create();
        $docFunction
          ->setDocBlock($entity)
          ->setSignature($docblock['signature'])
          ->setParameters($docblock['parameters'])
          ->setReturnValue($docblock['return_value'])
          ->save();
        DocReference::replaceMultiple($docblock, $entity, $branch, $namespace, $use_aliases, FALSE);
        break;

      case 'service':
        DocReference::replaceMultiple($docblock, $entity, $branch, $namespace, $use_aliases, FALSE);
        break;

      case 'file':
        $entity->deleteRelated('docblock_file');

        /** @var \Drupal\api\Interfaces\DocBlock\DocFileInterface $docFile */
        $docFile = DocFile::create();
        $docFile
          ->setDocBlock($entity)
          ->setBasename($docblock['basename'])
          ->save();
        DocReference::replaceMultiple($docblock, $entity, $branch, $namespace, $use_aliases, TRUE);
        break;

      case 'interface':
      case 'class':
      case 'trait':
        DocReference::deleteRelatedByType([
          'class',
          'interface',
          'trait',
          'annotation_class',
          'annotation',
          'element',
        ], $entity, $branch);
        DocNamespace::deleteRelatedByType([
          'trait_alias',
          'trait_precedence',
        ], $entity);

        foreach ($docblock['extends'] as $extend) {
          $refname = Formatter::fullClassname($extend, $namespace, $use_aliases);
          DocReference::createReference($branch, 'class', $refname, $entity);
        }
        foreach ($docblock['implements'] as $implement) {
          $refname = Formatter::fullClassname($implement, $namespace, $use_aliases);
          DocReference::createReference($branch, 'interface', $refname, $entity);
        }

        if (isset($docblock['references']['use_trait'])) {
          foreach ($docblock['references']['use_trait'] as $alias => $info) {
            $class = $info['class'];
            $refname = Formatter::fullClassname($class, $namespace, $use_aliases);
            $refalias = Formatter::fullClassname($alias, $namespace, $use_aliases);
            if ($refname != $refalias) {
              \Drupal::logger('api')->warning('Aliases for use statements for traits are not supported in %filename for alias %alias of %class in %project %branch', [
                '%filename' => $docblock['file_name'],
                '%alias' => $alias,
                '%class' => $class,
                '%project' => $branch->getProject()->getTitle(),
                '%branch' => $branch->getTitle(),
              ]);
            }
            DocReference::createReference($branch, 'trait', $refname, $entity);
            // If there are insteadof/alias details for this trait, save them
            // in the namespaces table (because it has the right columns).
            if (isset($info['details'])) {
              foreach ($info['details'] as $type => $list) {
                foreach ($list as $name => $item) {
                  if ($type == 'precedence') {
                    // This is an insteadof statement.
                    $name = Formatter::fullClassname($name, $namespace, $use_aliases);

                    /** @var \Drupal\api\Interfaces\DocBlock\DocNamespaceInterface $docNamespace */
                    $docNamespace = DocNamespace::create();
                    $docNamespace
                      ->setDocBlock($entity)
                      ->setObjectType('trait_' . $type)
                      ->setClassName($item)
                      ->setClassAlias($name)
                      ->save();
                  }
                  elseif (in_array($name, ['public', 'protected', 'private'])) {
                    \Drupal::logger('api')->warning('Trait inheritance that changes visibility is not supported in %filename for %item in %project %branch', [
                      '%filename' => $docblock['file_name'],
                      '%item' => $item,
                      '%project' => $branch->getProject()->getTitle(),
                      '%branch' => $branch->getTitle(),
                    ]);
                  }
                  else {
                    /** @var \Drupal\api\Interfaces\DocBlock\DocNamespaceInterface $docNamespace */
                    $docNamespace = DocNamespace::create();
                    $docNamespace
                      ->setDocBlock($entity)
                      ->setObjectType('trait_' . $type)
                      ->setClassName($refname . '::' . $item)
                      ->setClassAlias($name)
                      ->save();
                  }
                }
              }
            }
          }
        }
        if (isset($docblock['references']['annotation'])) {
          foreach ($docblock['references']['annotation'] as $class) {
            DocReference::createReference($branch, 'annotation', $class, $entity);
          }
        }
        if (isset($docblock['references']['element'])) {
          foreach ($docblock['references']['element'] as $element_type) {
            DocReference::createReference($branch, 'element', $element_type, $entity);
          }
        }
        if ($docblock['annotation_class']) {
          DocReference::createReference($branch, 'annotation_class', $docblock['object_name'], $entity);
        }
        break;

    }

    DocReference::deleteRelatedByType([
      'group',
    ], $entity, $branch);
    if (isset($docblock['groups'])) {
      foreach ($docblock['groups'] as $group_name) {
        DocReference::createReference($branch, 'group', $group_name, $entity);
      }
    }
  }

  /**
   * Update or insert new docblock item.
   *
   * Unlike the PHP/External branches, the $docblocks_array element contains
   * multiple docblock elements, so the logic here is far more complex.
   * We will be creating DocBlock elements as well as functions, members,
   * namespaces, etc.
   *
   * DocBlock will refer to Drupal entity.
   * docblock will refer to the results from parsing.
   *
   * @param array $docblocks_array
   *   Fields and values to insert/udpate.
   * @param \Drupal\api\Interfaces\BranchInterface $branch
   *   Branch where this item belongs to.
   *
   * @return bool
   *   Whether everything went as expected or not.
   */
  public static function createOrUpdate(array $docblocks_array, BranchInterface $branch) {
    /** @var \Drupal\api\Parser $parser */
    $parser = \Drupal::service('api.parser');

    $docblocks = $docblocks_array;
    if (empty($docblocks)) {
      return FALSE;
    }
    $nested_groups = [];
    $namespace = '';
    $use_aliases = [];
    $processed_ids = [];
    $class_ids = [];
    $old_ids = self::findByFileName($docblocks[0]['file_name'], $branch);

    $docblocks = $parser->fileDocblockFirst($docblocks);

    // Take care of the (bad, but possible) case where the doc block for one
    // of the items in the file (class, function, etc.) has a @defgroup
    // or @mainpage in it, by separating the doc block from the item.
    $docblocks = $parser->splitByTags($docblocks, ['mainpage', 'defgroup']);

    foreach ($docblocks as $docblock) {
      // Do the heavy lifting for the docblock and then save if all went well.
      if ($parser->processDocblock($docblock, $namespace, $use_aliases, $nested_groups, $class_ids, $branch)) {
        $parser->addTextDefaults($docblock);

        $entity = self::upsert($docblock, $branch, $processed_ids);
        if (empty($entity)) {
          // This was already processed, so ignore the rest.
          continue;
        }

        // Keep track of class IDs.
        if (in_array($docblock['object_type'], ['class', 'interface', 'trait'])) {
          $class_ids[$docblock['object_name']] = $entity->id();
          // In the D7 version, we'd keep this in memory for a final
          // recalculation of class elements (members, overrides, etc), but
          // here we will just do a query on the database, so we don't need
          // to keep track of it.
        }

        // Add all related entities now.
        self::upsertRelated($docblock, $entity, $branch, $namespace, $use_aliases);

        // Mark this record as processed.
        $processed_ids[] = $entity->id();
      }
    }

    // Clean out all of the doc objects from this file that no longer exist.
    $old_ids = array_diff($old_ids, $processed_ids);
    if (count($old_ids)) {
      self::deleteMultiple($old_ids);
    }

    return TRUE;
  }

  /**
   * Deletes a list of given entities by their IDs.
   *
   * @param array $ids
   *   IDs to delete.
   */
  public static function deleteMultiple(array $ids) {
    // https://www.drupal.org/node/3051072
    $storage_handler = \Drupal::entityTypeManager()->getStorage('docblock');
    foreach (array_chunk($ids, 50) as $chunk) {
      $entities = $storage_handler->loadMultiple($chunk);
      $storage_handler->delete($entities);
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function findByFileName($file_name, BranchInterface $branch) {
    return self::matches(['file_name' => $file_name], $branch);
  }

  /**
   * {@inheritdoc}
   */
  public static function findByNameAndType($name, $type, BranchInterface $branch) {
    return self::matches([
      'object_name' => $name,
      'object_type' => $type,
    ], $branch);
  }

  /**
   * {@inheritdoc}
   */
  public static function getGroupListingPage($type, BranchInterface $branch) {
    $result = DocBlock::matches([
      'object_name' => 'listing_page_' . $type,
      'object_type' => 'group',
    ], $branch);
    return array_shift($result);
  }

  /**
   * {@inheritdoc}
   */
  public static function findFileByFileName($file_name, BranchInterface $branch) {
    $result = self::matches([
      'file_name' => $file_name,
      'object_type' => 'file',
    ], $branch);

    return array_shift($result);
  }

  /**
   * Find elements by group tag inside a branch.
   *
   * @param string $group
   *   Name of the group.
   * @param \Drupal\api\Interfaces\BranchInterface $branch
   *   Branch to search in.
   * @param string $exclude_this_file
   *   If filled in, ignore results in this file.
   *
   * @return int[]|null
   *   List of IDs or null if nothing found.
   */
  public static function findByDefgroup($group, BranchInterface $branch, $exclude_this_file = '') {
    $conditions = [
      'object_name' => $group,
      'object_type' => 'group',
    ];
    if (!empty($exclude_this_file)) {
      $conditions['file_name'] = [
        'operator' => '<>',
        'value' => $exclude_this_file,
      ];
    }
    return self::matches($conditions, $branch);
  }

  /**
   * {@inheritdoc}
   */
  public static function bestByClassByName($class_name, BranchInterface $branch) {
    if (!$branch) {
      return NULL;
    }

    // Check current branch.
    $found = self::matches([
      'namespaced_name' => $class_name,
    ], $branch);

    if (!$found) {
      // Check core branch.
      $core = Branch::findCoreBranch($branch);
      if ($core) {
        $found = self::matches([
          'namespaced_name' => $class_name,
        ], $core);
      }
    }

    if (!$found) {
      // See if there is a branch with matching core compatibility at least.
      $sameCoreBranches = Branch::sameCoreCompatibilityBranches($branch);
      if ($sameCoreBranches) {
        $found = self::matches([
          'namespaced_name' => $class_name,
          'branch' => [
            'operator' => 'IN',
            'value' => $sameCoreBranches,
          ],
        ], NULL);
      }
    }

    // Unique match.
    if ($found && count($found) == 1) {
      $id = array_shift($found);
      return self::load($id);
    }

    // Didn't find a unique match or didn't find a match at all.
    return NULL;
  }

}
