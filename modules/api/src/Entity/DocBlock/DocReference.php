<?php

namespace Drupal\api\Entity\DocBlock;

use Drupal\api\Entity\Branch;
use Drupal\api\Entity\DocBlock;
use Drupal\api\Entity\PhpDocumentation;
use Drupal\api\ExtendedQueries;
use Drupal\api\Interfaces\BranchInterface;
use Drupal\api\Interfaces\DocBlock\DocReferenceInterface;
use Drupal\api\Traits\MatchingTrait;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\api\Interfaces\DocBlockInterface;
use Drupal\api\Formatter;

/**
 * Defines the docblock reference entity class.
 *
 * @ContentEntityType(
 *   id = "docblock_reference",
 *   label = @Translation("DocBlock Reference"),
 *   label_collection = @Translation("DocBlock references"),
 *   handlers = {
 *     "access" = "Drupal\api\ApiAccessControlHandler",
 *     "views_data" = "Drupal\views\EntityViewsData",
 *     "storage_schema" = "Drupal\api\StorageSchema\ApiContentStorageSchema",
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\AdminHtmlRouteProvider",
 *     }
 *   },
 *   base_table = "api_branch_docblock_reference",
 *   admin_permission = "administer API reference",
 *   field_indexes = {
 *     "object_name",
 *     "object_type"
 *   },
 *   entity_keys = {
 *     "id" = "id",
 *     "uuid" = "uuid"
 *   },
 * )
 */
class DocReference extends ContentEntityBase implements DocReferenceInterface {

  use MatchingTrait;

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['object_name'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Object name'));

    $fields['object_type'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Object type'))
      ->setRequired(TRUE);

    $fields['docblock'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('DocBlock'))
      ->setDescription(t('DocBlock element'))
      ->setSetting('target_type', 'docblock')
      ->setSetting('handler', 'default')
      ->setCardinality(1)
      ->setRequired(TRUE);

    $fields['extends_docblock'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Extends DocBlock'))
      ->setDescription(t('If this is a class or interface inheritance, the computed ID value'))
      ->setSetting('target_type', 'docblock')
      ->setSetting('handler', 'default')
      ->setCardinality(1);

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
  public function getExtendsDocBlock() {
    $docblock = $this->get('extends_docblock')->referencedEntities();
    return !empty($docblock[0]) ? $docblock[0] : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function setExtendsDocBlock(DocBlockInterface $docblock) {
    $this->set('extends_docblock', $docblock);
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
   * Remove any references for the entity and branch.
   *
   * @param \Drupal\api\Interfaces\DocBlockInterface $entity
   *   Entity where references are linked to.
   * @param \Drupal\api\Interfaces\BranchInterface $branch
   *   Branch where references are linked to.
   * @param bool $do_namespaces
   *   Do namespaces too or not.
   */
  public static function removeReferences(DocBlockInterface $entity, BranchInterface $branch, bool $do_namespaces) {
    // Remove any existing references.
    self::deleteRelatedByType([
      'function',
      'potential hook',
      'potential fieldhook',
      'potential entityhook',
      'potential userhook',
      'potential theme',
      'potential element',
      'potential alter',
      'potential callback',
      'potential file',
      'constant',
      'member-parent',
      'member-self',
      'member',
      'member-class',
      'yaml string',
      'service_tag',
      'service_class',
    ], $entity, $branch);
    if ($do_namespaces) {
      DocNamespace::deleteRelatedByType([
        'namespace',
        'use_alias',
      ], $entity);
    }
  }

  /**
   * Add references for the entity and branch.
   *
   * @param \Drupal\api\Interfaces\DocBlockInterface $entity
   *   Entity where references are linked to.
   * @param \Drupal\api\Interfaces\BranchInterface $branch
   *   Branch where references are linked to.
   * @param array $references
   *   References to add.
   * @param bool $do_namespaces
   *   Do namespaces too or not.
   * @param string $namespace
   *   Namespace for the references.
   * @param array $use_aliases
   *   Aliases for the reference.
   * @param string $shortname
   *   Shortname for the references.
   */
  public static function addReferences(DocBlockInterface $entity, BranchInterface $branch, array $references, $do_namespaces, $namespace, array $use_aliases, $shortname) {
    foreach ($references as $type => $list) {
      switch ($type) {
        case 'namespace':
          if ($do_namespaces) {
            // In this case, $list is actually a string containing the namespace
            // for a file, not a list.
            /** @var \Drupal\api\Interfaces\DocBlock\DocNamespaceInterface $docNamespace */
            $docNamespace = DocNamespace::create();
            $docNamespace
              ->setDocBlock($entity)
              ->setObjectType($type)
              ->setClassName($list)
              ->setClassAlias('')
              ->save();
          }
          break;

        case 'use_alias':
          if ($do_namespaces) {
            // Save all of the use aliases.
            foreach ($list as $alias => $class) {
              /** @var \Drupal\api\Interfaces\DocBlock\DocNamespaceInterface $docNamespace */
              $docNamespace = DocNamespace::create();
              $docNamespace
                ->setDocBlock($entity)
                ->setObjectType($type)
                ->setClassName($class)
                ->setClassAlias($alias)
                ->save();
            }
          }
          break;

        case 'member-class':
          // Don't save a reference to the item itself.
          unset($list[$shortname]);
          foreach ($list as $call) {
            // These are references to ClassName::method(). Make sure
            // they are fully namespaced.
            $call = Formatter::fullClassname($call, $namespace, $use_aliases);
            self::createReference($branch, $type, $call, $entity);
          }
          break;

        case 'potential callback':
        case 'function':
        case 'member-self':
          // Don't save a reference to the item itself.
          unset($list[$shortname]);
          foreach ($list as $call) {
            // If the name contains a backslash, and the first occurrence is not
            // at the beginning, make sure it starts with a backslash
            // so it is a fully-namespaced reference.
            $pos = strpos($call, '\\');
            if ($pos !== FALSE && $pos !== 0) {
              $call = '\\' . $call;
            }
            self::createReference($branch, $type, $call, $entity);
          }
          break;

        default:
          foreach ($list as $call) {
            // If the name contains a backslash, and the first occurrence is not
            // at the beginning, make sure it starts with a backslash
            // so it is a fully-namespaced reference.
            $pos = strpos($call, '\\');
            if ($pos !== FALSE && $pos !== 0) {
              $call = '\\' . $call;
            }
            self::createReference($branch, $type, $call, $entity);
          }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function replaceMultiple(array $docblock, DocBlockInterface $entity, BranchInterface $branch, $namespace, array $use_aliases, $do_namespaces) {
    $references = $docblock['references'];
    $name = $docblock['object_name'];

    // Find the name without class prefix, if any.
    $shortname = $name;
    if (($pos = strpos($name, '::')) !== FALSE && $pos > 1) {
      $shortname = substr($name, $pos + 2);
    }

    // Remove and re-create the references.
    self::removeReferences($entity, $branch, $do_namespaces);
    self::addReferences($entity, $branch, $references, $do_namespaces, $namespace, $use_aliases, $shortname);
  }

  /**
   * {@inheritdoc}
   */
  public static function createReference(BranchInterface $branch, $type, $name, DocBlockInterface $docBlock) {
    // Don't make references to built-in PHP functions.
    $is_php_function = FALSE;
    if ($type == 'function') {
      $is_php_function = count(PhpDocumentation::findByName($name));
    }

    // Avoid trying to save really long object names.
    $name = mb_substr($name, 0, 127);
    $name = Formatter::validateEncoding($name);

    if (!$is_php_function) {
      /** @var \Drupal\api\Interfaces\DocBlock\DocReferenceInterface $reference */
      $reference = self::create();
      $reference
        ->setObjectName($name)
        ->setObjectType($type)
        ->setDocBlock($docBlock)
        ->setBranch($branch)
        ->save();

      return $reference;
    }

    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public static function deleteRelatedByType(array $types, DocBlockInterface $entity, BranchInterface $branch) {
    // https://www.drupal.org/node/3051072
    $storage_handler = \Drupal::entityTypeManager()->getStorage('docblock_reference');
    $ids = self::matches([
      'object_type' => [
        'operator' => 'IN',
        'value' => $types,
      ],
    ], $branch, $entity);
    foreach (array_chunk($ids, 50) as $chunk) {
      $entities = $storage_handler->loadMultiple($chunk);
      $storage_handler->delete($entities);
    }
  }

  /**
   * Update members information related to the members given.
   *
   * @param array $members
   *   Members information.
   * @param int $class_id
   *   ID of the class.
   */
  protected static function updateMembers(array $members, $class_id) {
    /** @var \Drupal\api\Interfaces\DocBlockInterface $docBlock */
    $classDocBlock = DocBlock::load($class_id);

    // Delete all references.
    $classDocBlock->deleteRelated('docblock_class_member', 'class_docblock');

    // And crate new ones.
    $processed = [];
    foreach ($members as $list) {
      foreach ($list as $member) {
        $member_alias = ($member['alias'] != $member['member_name']) ?
          $member['alias'] :
          NULL;

        $processed_key = $member_alias . '-' . $member['docblock'] . '-' . $classDocBlock->id();
        if (empty($processed[$processed_key])) {
          /** @var \Drupal\api\Interfaces\DocBlock\DocClassMemberInterface $classMember */
          $classMember = DocClassMember::create();
          $classMember
            ->setDocBlock(DocBlock::load($member['docblock']))
            ->setClassDocBlock($classDocBlock)
            ->setMemberAlias($member_alias)
            ->save();
          $processed[$processed_key] = TRUE;
        }
      }
    }
  }

  /**
   * Update override information related to the members given.
   *
   * @param array $members
   *   Members information.
   */
  protected static function updateOverrides(array $members) {
    // Save the overrides info, and if we are getting documentation from an
    // inherited member, update the summary on the main object. This is only
    // done for the direct members of this class.
    foreach ($members as $list) {
      foreach ($list as $member) {
        if ($member['direct_member']) {
          /** @var \Drupal\api\Interfaces\DocBlockInterface $docBlock */
          $docBlock = DocBlock::load($member['docblock']);

          if ($member['documented_in_docblock'] != $member['docblock']) {
            $docBlock->setSummary($member['summary'])->save();
          }

          // Delete the old information and insert new.
          $docBlock->deleteRelated('docblock_override');

          /** @var \Drupal\api\Interfaces\DocBlock\DocOverrideInterface $override */
          $override = DocOverride::create();
          $override->setDocBlock($docBlock);

          if (!empty($member['overrides_docblock'])) {
            $override->setOverridesDocBlock(DocBlock::load($member['overrides_docblock']));
          }
          if (!empty($member['documented_in_docblock'])) {
            $override->setDocumentedInDocBlock(DocBlock::load($member['documented_in_docblock']));
          }
          $override->save();
        }
      }
    }
  }

  /**
   * Finds the trait aliases for a given class.
   *
   * @param \Drupal\api\Interfaces\DocBlockInterface $docBlock
   *   Class object.
   *
   * @return array
   *   List of trait aliases
   */
  protected static function findTraitAliases(DocBlockInterface $docBlock) {
    $results = DocNamespace::matches([
      'object_type' => 'trait_alias',
    ], $docBlock);
    $items = DocNamespace::loadMultiple($results);

    $return = [];
    foreach ($items as $item) {
      /** @var \Drupal\api\Interfaces\DocBlock\DocNamespaceInterface $item */
      $alias = $item->getClassAlias();
      $name = $item->getClassName();
      $pos = strpos($name, '::');
      if ($pos >= 1) {
        $class = substr($name, 0, $pos);
        $member = substr($name, $pos + 2);
        $return[$class][$member] = $alias;
      }
    }

    return $return;
  }

  /**
   * Finds the trait precedences for a given class.
   *
   * @param \Drupal\api\Interfaces\DocBlockInterface $docBlock
   *   Class object.
   *
   * @return array
   *   List of trait precedences
   */
  protected static function findTraitPrecedence(DocBlockInterface $docBlock) {
    $results = DocNamespace::matches([
      'object_type' => 'trait_precedence',
    ], $docBlock);
    $items = DocNamespace::loadMultiple($results);

    $omit = [];
    foreach ($items as $item) {
      /** @var \Drupal\api\Interfaces\DocBlock\DocNamespaceInterface $item */
      $class = $item->getClassAlias();
      $method = $item->getClassName();
      $omit[$class][] = $method;
    }

    return $omit;
  }

  /**
   * Calculates a bare list of this class's direct members.
   *
   * @param \Drupal\api\Interfaces\DocBlockInterface $docBlock
   *   DocBlock object.
   * @param array $member_info
   *   Member information.
   *
   * @return array
   *   Array with added members information from the given object.
   */
  protected static function makeBareMemberList(DocBlockInterface $docBlock, array $member_info) {
    $members = [
      'function' => [],
      'property' => [],
      'constant' => [],
    ];

    foreach ($member_info[$docBlock->id()] as $type => $list) {
      foreach ($list as $member_did) {
        $item = ExtendedQueries::loadExtendedArray($member_did, $type);
        if (!$item) {
          continue;
        }

        $has_docs = Formatter::elementHasDocumentation($item);
        $member_name = $item['member_name'];
        $members[$type][$member_name] = [
          'docblock' => $item['id'],
          'overrides_docblock' => NULL,
          'documented_in_docblock' => ($has_docs) ? $item['id'] : NULL,
          'summary' => $item['summary'],
          'alias' => $member_name,
          'member_name' => $member_name,
          'direct_member' => TRUE,
        ];
      }
    }

    return $members;
  }

  /**
   * Calculates class member information for a class, including inheritance.
   *
   * Calculate the full list of class members, including inherited, and figure
   * out what each one is overriding and where each one is documented.
   *
   * @param int $class_id
   *   Documentation ID of the class to calculate.
   * @param array $parent_info
   *   Array of collected parent information.
   * @param array $member_info
   *   Array of collected direct member information.
   * @param array $cache_array
   *   Caching array as we will most likely encounter a class multiple times.
   *
   * @return array[]
   *   List of calculated members.
   */
  protected static function calculateClassMembers($class_id, array $parent_info, array $member_info, array &$cache_array) {
    $members = [
      'function' => [],
      'property' => [],
      'constant' => [],
    ];

    if (!$class_id || !isset($member_info[$class_id])) {
      return $members;
    }

    // See if we already calculated members for this class, during another
    // class traversal.
    if (isset($cache_array[$class_id])) {
      return $cache_array[$class_id];
    }

    /** @var \Drupal\api\Interfaces\DocBlockInterface $docBlock */
    $docBlock = DocBlock::load($class_id);

    // Add this class's direct members to the list, and put it in the
    // cache for now, to avoid loops.
    $members = self::makeBareMemberList($docBlock, $member_info);
    $cache_array[$class_id] = $members;

    // Add in the parents.
    if (!isset($parent_info[$class_id])) {
      return $members;
    }
    // See if there are any aliases or insteadof statements for trait members.
    $aliases = self::findTraitAliases($docBlock);
    $omit = self::findTraitPrecedence($docBlock);

    foreach ($parent_info[$class_id] as $parent_name => $parent) {
      $parent_members = self::calculateClassMembers($parent, $parent_info, $member_info, $cache_array);
      self::memberListMerge(
        $members,
        $parent_members,
        $aliases[$parent_name] ?? [],
        $omit[$parent_name] ?? []
      );
    }

    // Save in the cache and return.
    $cache_array[$class_id] = $members;
    return $members;
  }

  /**
   * Merges a member list with parent member list.
   *
   * @param array $members
   *   List of member information, modified by reference.
   * @param array $parent_members
   *   List of parent member information to merge in.
   * @param array $aliases
   *   List of aliases for member names (functions only).
   * @param array $omit
   *   List of methods in parent class to omit due to insteadof statements.
   */
  protected static function memberListMerge(array &$members, array $parent_members, array $aliases, array $omit) {
    foreach ($parent_members as $type => $new_type_list) {
      foreach ($new_type_list as $member_name => $info) {
        if (in_array($member_name, $omit)) {
          continue;
        }
        $alias = (isset($aliases[$member_name]) && $type == 'function') ?
          $aliases[$member_name] :
          $member_name;
        if (isset($members[$type][$alias])) {
          // We already knew about this member. Save override info, but only
          // for direct members.
          if ($members[$type][$alias]['direct_member']) {
            if (!$members[$type][$alias]['overrides_docblock']) {
              // We just found what the known member is overriding.
              $members[$type][$alias]['overrides_docblock'] = $info['docblock'];
            }
            if (!$members[$type][$alias]['documented_in_docblock']) {
              // The old member didn't have documentation, maybe this one does.
              $members[$type][$alias]['documented_in_docblock'] = $info['documented_in_docblock'];
            }
            if (!$members[$type][$alias]['summary']) {
              // The old member didn't have a summary, maybe this one does.
              $members[$type][$alias]['summary'] = $info['summary'];
            }
          }
        }
        else {
          // It's a new member inherited from a parent, add it in.
          $info['alias'] = $alias;
          $info['direct_member'] = FALSE;
          $members[$type][$alias] = $info;
        }
      }
    }
  }

  /**
   * Updates several DocEntities based on the parameters given.
   *
   * @param int $class_id
   *   ID of the referencing DocBlock.
   * @param array $parent_info
   *   List of class parents.
   * @param array $member_info
   *   List of class members.
   */
  protected static function updateClassReferenceInfo($class_id, array $parent_info, array $member_info) {
    if (!$class_id) {
      return;
    }

    $cache_array = [];
    $members = self::calculateClassMembers($class_id, $parent_info, $member_info, $cache_array);
    self::updateOverrides($members);
    self::updateMembers($members, $class_id);
    ExtendedQueries::updateCalculatedReferences($member_info, $class_id);
  }

  /**
   * Get 'service_tags' DocReference IDs for given DocBlock IDs.
   *
   * @param array $ids
   *   List of DocBlock IDs.
   *
   * @return int[]|null
   *   List of matching DocReference IDs or null.
   */
  public static function getServiceTags(array $ids) {
    return self::matches([
      'object_type' => 'service_tag',
      'docblock' => [
        'operator' => 'IN',
        'value' => $ids,
      ],
    ]);
  }

  /**
   * Get 'class' DocReference IDs for given DocBlock IDs.
   *
   * @param \Drupal\api\Interfaces\BranchInterface $branch
   *   Branch to check in.
   * @param \Drupal\api\Interfaces\DocBlockInterface $docBlock
   *   Docblock to check in.
   *
   * @return int[]|null
   *   List of matching DocReference IDs or null.
   */
  public static function getClassReference(BranchInterface $branch = NULL, DocBlockInterface $docBlock = NULL) {
    return self::matches([
      'object_type' => 'class',
    ], $branch, $docBlock);
  }

  /**
   * {@inheritdoc}
   */
  public static function matches(array $conditions, BranchInterface $branch = NULL, DocBlockInterface $docBlock = NULL) {
    /** @var \Drupal\Core\Entity\Query\QueryInterface $query */
    $query = \Drupal::entityQuery('docblock_reference');
    if ($branch) {
      $query->condition('branch', $branch->id());
    }
    if ($docBlock) {
      $query->condition('docblock', $docBlock->id());
    }
    self::applyConditions($query, $conditions);

    return $query->execute();
  }

  /**
   * Fills the inherited elements array and keeps track of classes_changed.
   *
   * @param array $classes_changed
   *   Classes changed.
   * @param array $classes_todo
   *   Classes to do.
   * @param array $classes_added
   *   Classes added.
   * @param \Drupal\api\Interfaces\DocBlockInterface $classDocBlock
   *   Class DocBlock entity.
   * @param \Drupal\api\Interfaces\BranchInterface $branch
   *   Branch where all this info belongs to.
   */
  protected static function fillClassInheritedArray(array &$classes_changed, array &$classes_todo, array &$classes_added, DocBlockInterface $classDocBlock, BranchInterface $branch) {
    // If this is one of the "changed" classes, see if anything else inherits
    // from this class. If so, we need to check it too. This could be a class
    // that has already computed this as its extends_docblock, or a class in the
    // same core compatibility that extends a class by this namespaced name
    // but maybe didn't know the DID yet. Don't worry about the possible
    // edge case of a class that had found another one previously and this one
    // would now be better.
    if (in_array($classDocBlock->id(), $classes_changed)) {
      $conditions = [
        'object_type' => [
          'operator' => 'IN',
          'value' => ['class', 'interface', 'trait'],
        ],
        'or' => [
          'extends_docblock' => $classDocBlock->id(),
          'object_name' => $classDocBlock->getNamespacedName(),
        ],
      ];
      $sameCoreBranches = Branch::sameCoreCompatibilityBranches($branch);
      if ($sameCoreBranches) {
        $conditions['branch'] = [
          'operator' => 'IN',
          'value' => $sameCoreBranches,
        ];
      }

      $results = self::matches($conditions, NULL, NULL);
      if ($results) {
        $entities = self::loadMultiple($results);
        foreach ($entities as $entity) {
          /** @var \Drupal\api\Interfaces\DocBlock\DocReferenceInterface $entity */
          if (!$entity->getDocBlock()) {
            continue;
          }
          $reference_docblock_id = $entity->getDocBlock()->id();
          if (!isset($classes_added[$reference_docblock_id])) {
            $classes_added[$reference_docblock_id] = $reference_docblock_id;
            if (!in_array($reference_docblock_id, $classes_todo)) {
              $classes_todo[] = $reference_docblock_id;
            }
            if (!in_array($reference_docblock_id, $classes_changed)) {
              $classes_changed[] = $reference_docblock_id;
            }
          }
        }
      }
    }
  }

  /**
   * Fills the class_parents array and keeps track of classes_changed.
   *
   * @param array $class_parents
   *   Parents array.
   * @param array $classes_changed
   *   Classes changed.
   * @param array $classes_todo
   *   Classes to do.
   * @param array $classes_added
   *   Classes added.
   * @param \Drupal\api\Interfaces\DocBlockInterface $classDocBlock
   *   Class DocBlock entity.
   * @param \Drupal\api\Interfaces\BranchInterface $branch
   *   Branch where all this info belongs to.
   */
  protected static function fillClassParentsArray(array &$class_parents, array &$classes_changed, array &$classes_todo, array &$classes_added, DocBlockInterface $classDocBlock, BranchInterface $branch = NULL) {
    // See if this class extends or inherits anything else, or uses traits,
    // and if so, calculate the IDs of the "parent" classes, traits, or
    // interfaces. Do this in the right order for inheritance: traits
    // trump class extends, and class extends trump interfaces.
    $parents = [];
    $parents_to_check = [];
    $types = ['trait', 'class', 'interface'];
    foreach ($types as $type) {
      $parent_ids = self::matches(['object_type' => $type], $branch, $classDocBlock);
      if ($parent_ids) {
        $parents_to_check = array_merge($parents_to_check, $parent_ids);
      }
    }

    foreach ($parents_to_check as $parent) {
      /** @var \Drupal\api\Interfaces\DocBlock\DocReferenceInterface $parent_reference */
      $parent_reference = self::load($parent);

      // Figure out the ID of this parent.
      /** @var \Drupal\api\Interfaces\DocBlockInterface $bestClassDocBlock */
      $bestClassDocBlock = DocBlock::bestByClassByName(
        $parent_reference->getObjectName(),
        $parent_reference->getBranch()
      );

      if ($bestClassDocBlock) {
        $parents[$parent_reference->getObjectName()] = $bestClassDocBlock->id();
        // See if it's different from what we already had.
        $currentExtendsDocBlock = $parent_reference->getExtendsDocBlock();
        if (
          !$currentExtendsDocBlock ||
          ($bestClassDocBlock->id() != $currentExtendsDocBlock->id())
        ) {
          // Update the reference storage record with this new ID.
          $parent_reference
            ->setExtendsDocBlock($bestClassDocBlock)
            ->save();
          // This class changed.
          if (!in_array($classDocBlock->id(), $classes_changed)) {
            $classes_changed[] = $classDocBlock->id();
          }
        }

        // If this class's parent is already marked as "changed", then this
        // class needs to be marked as "changed" also.
        if (
          in_array($bestClassDocBlock->id(), $classes_changed) &&
          !in_array($classDocBlock->id(), $classes_changed)
        ) {
          $classes_changed[] = $classDocBlock->id();
        }

        // We also need to get the members of parent classes, so add it to the
        // to do list.
        if (($bestClassDocBlock->id()) && !isset($classes_added[$bestClassDocBlock->id()])) {
          if (!in_array($bestClassDocBlock->id(), $classes_todo)) {
            $classes_todo[] = $bestClassDocBlock->id();
          }
          $classes_added[$bestClassDocBlock->id()] = $bestClassDocBlock->id();
        }
      }
    }
    $class_parents[$classDocBlock->id()] = $parents;
  }

  /**
   * Fills the class_members array with relevant information.
   *
   * @param array $class_members
   *   Array to fill.
   * @param \Drupal\api\Interfaces\DocBlockInterface $classDocBlock
   *   Class DocBlock entity.
   * @param \Drupal\api\Interfaces\BranchInterface $branch
   *   Branch where all this info belongs to.
   */
  protected static function fillClassMembersArray(array &$class_members, DocBlockInterface $classDocBlock, BranchInterface $branch = NULL) {
    // We're going to need the direct members of this class. Since methods,
    // properties, and constants can share names, do each separately.
    $direct_members_ids = DocBlock::matches([
      'object_type' => [
        'operator' => 'IN',
        'value' => ['function', 'property', 'constant'],
      ],
      'class' => $classDocBlock->id(),
    ], $branch);

    $result = DocBlock::loadMultiple($direct_members_ids);
    if (!empty($result)) {
      $direct_members = [
        'function' => [],
        'property' => [],
        'constant' => [],
      ];
      foreach ($result as $item) {
        /** @var \Drupal\api\Interfaces\DocBlockInterface $item */
        $direct_members[$item->getObjectType()][$item->getMemberName()] = $item->id();
      }
      $class_members[$classDocBlock->id()] = $direct_members;
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function classRelations(BranchInterface $branch) {
    // Figure out which classes and interfaces we need to update.
    $classes_touched = DocBlock::matches([
      'object_type' => [
        'operator' => 'IN',
        'value' => ['class', 'interface', 'trait'],
      ],
    ], $branch);

    // For each of these classes, and any that extend/implement them, we need
    // to recompute:
    // - The IDs for extended/implemented classes/interfaces.
    // - IDs for classes that extend/implement this class/interface.
    // - For traits, the traits and classes that use them.
    // - The {api_branch_docblock_class_members} table, which figures out all
    //   class members including inherited members.
    // - The {api_branch_docblock_override} table, which figures out for a class
    //   member if it is overriding another class's member, and where it is
    //   documented.
    // - The "computed-member" information in {api_branch_docblock_reference}
    //   table.
    // So, the first step is to recompute class inheritance. This is stored by
    // name in {api_branch_docblock_reference}, and we need to find the actual
    // IDs of the classes. Do this one by one, since we need to compute some
    // other stuff anyway as we go.
    $classes_todo = array_values($classes_touched);
    $classes_changed = $classes_touched;
    // Avoid infinite loops by keeping track of which classes we've already
    // checked.
    $classes_added = $classes_touched;
    // Keep track of the IDs of classes that extend/implement others, and the
    // members of each class.
    $class_parents = [];
    $class_members = [];

    // Keep track of processed entries to avoid infinite loops. The D7 module
    // used "drupal_static" in a few places to help with this.
    $todo_done = [];
    $references_done = [];
    $direct_methods_done = [];

    // Use while-loop as the $classes_todo array might grow while processing
    // the entries.
    while ($class_id = array_shift($classes_todo)) {
      if (isset($todo_done[$class_id])) {
        continue;
      }

      /** @var \Drupal\api\Interfaces\DocBlockInterface $classDocBlock */
      $classDocBlock = DocBlock::load($class_id);

      self::fillClassMembersArray($class_members, $classDocBlock);
      self::fillClassParentsArray($class_parents, $classes_changed, $classes_todo, $classes_added, $classDocBlock);
      self::fillClassInheritedArray($classes_changed, $classes_todo, $classes_added, $classDocBlock, $branch);

      $todo_done[$class_id] = TRUE;
    }

    // OK, at this point we have a list of all the classes that we need to
    // update member, override, and class parent information for. And we have
    // in hand a list of the parent IDs and the direct members for each one.
    // So go through this list and redo the members and overrides tables.
    foreach ($classes_changed as $cid) {
      if (!isset($references_done[$cid])) {
        self::updateClassReferenceInfo($cid, $class_parents, $class_members);
        $references_done[$cid] = TRUE;
      }
    }

    // Now that all the class members have been updated, calculate
    // computed-member references for member-parent calls in the references
    // table. These are cases where ChildClass::foo() calls parent::bar(),
    // and we need to figure out the full name of the parent member. Do this
    // for all class methods in $classes_changed.
    foreach ($classes_changed as $cid) {
      if (!isset($direct_methods_done[$cid])) {
        if (isset($class_members[$cid]) && count($class_members[$cid]['function'])) {
          $direct_methods = array_values($class_members[$cid]['function']);
          ExtendedQueries::createNewReferences($direct_methods);
        }
        $direct_methods_done[$cid] = TRUE;
      }
    }
  }

}
