<?php

namespace Drupal\api\Interfaces\DocBlock;

use Drupal\api\Interfaces\BranchInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\api\Interfaces\DocBlockInterface;

/**
 * Provides an interface defining a References entity type.
 */
interface DocReferenceInterface extends ContentEntityInterface {

  /**
   * Gets the object_name property of the entity.
   *
   * @return string
   *   The object_name of the DocReferenceInterface entity.
   */
  public function getObjectName();

  /**
   * Sets the object_name property.
   *
   * @param string $object_name
   *   Value to set.
   *
   * @return \Drupal\api\Interfaces\DocBlock\DocReferenceInterface
   *   DocReferenceInterface entity.
   */
  public function setObjectName($object_name);

  /**
   * Gets the branch property of the entity.
   *
   * @return \Drupal\api\Interfaces\BranchInterface
   *   The branch of the Reference entity.
   */
  public function getBranch();

  /**
   * Sets the branch property.
   *
   * @param \Drupal\api\Interfaces\BranchInterface $branch
   *   Value to set.
   *
   * @return \Drupal\api\Interfaces\DocBlock\DocReferenceInterface
   *   DocReferenceInterface entity.
   */
  public function setBranch(BranchInterface $branch);

  /**
   * Gets the object_type property of the entity.
   *
   * @return string
   *   The object_type of the DocReferenceInterface entity.
   */
  public function getObjectType();

  /**
   * Sets the object_type property.
   *
   * @param string $object_type
   *   Value to set.
   *
   * @return \Drupal\api\Interfaces\DocBlock\DocReferenceInterface
   *   DocReferenceInterface entity.
   */
  public function setObjectType($object_type);

  /**
   * Gets the docblock property of the entity.
   *
   * @return \Drupal\api\Interfaces\DocBlockInterface
   *   The docblock of the Reference entity.
   */
  public function getDocBlock();

  /**
   * Sets the docblock property.
   *
   * @param \Drupal\api\Interfaces\DocBlockInterface $docblock
   *   Value to set.
   *
   * @return \Drupal\api\Interfaces\DocBlock\DocReferenceInterface
   *   DocReferenceInterface entity.
   */
  public function setDocBlock(DocBlockInterface $docblock);

  /**
   * Gets the extends_docblock property of the entity.
   *
   * @return \Drupal\api\Interfaces\DocBlockInterface
   *   The extends_docblock of the Reference entity.
   */
  public function getExtendsDocBlock();

  /**
   * Sets the extends_docblock property.
   *
   * @param \Drupal\api\Interfaces\DocBlockInterface $docblock
   *   Value to set.
   *
   * @return \Drupal\api\Interfaces\DocBlock\DocReferenceInterface
   *   DocReferenceInterface entity.
   */
  public function setExtendsDocBlock(DocBlockInterface $docblock);

  /**
   * Insert/update/delete multiple references from the info given.
   *
   * @param array $docblock
   *   Docblock array.
   * @param \Drupal\api\Interfaces\DocBlockInterface $entity
   *   DocBlock entity.
   * @param \Drupal\api\Interfaces\BranchInterface $branch
   *   Branch entity.
   * @param string $namespace
   *   Namespace where references are placed.
   * @param array $use_aliases
   *   Aliases where references are used.
   * @param bool $do_namespaces
   *   Apply the logic to namespaces too or not.
   */
  public static function replaceMultiple(array $docblock, DocBlockInterface $entity, BranchInterface $branch, $namespace, array $use_aliases, $do_namespaces);

  /**
   * Deletes entities based on the criteria given.
   *
   * @param array $types
   *   To map with object_type column.
   * @param \Drupal\api\Interfaces\DocBlockInterface $entity
   *   DocBlock entity.
   * @param \Drupal\api\Interfaces\BranchInterface $branch
   *   Branch entity.
   */
  public static function deleteRelatedByType(array $types, DocBlockInterface $entity, BranchInterface $branch);

  /**
   * Adds a new reference and does some additional checks beforehand.
   *
   * @param \Drupal\api\Interfaces\BranchInterface $branch
   *   Branch where the reference will belong to.
   * @param string $type
   *   Type of the reference.
   * @param string $name
   *   Name of the object.
   * @param \Drupal\api\Interfaces\DocBlockInterface $docBlock
   *   DocBlock linked to the reference.
   *
   * @return \Drupal\api\Interfaces\DocBlock\DocReferenceInterface|null
   *   Created object or null.
   */
  public static function createReference(BranchInterface $branch, $type, $name, DocBlockInterface $docBlock);

  /**
   * Creates all class-related records (inheritance, members, etc) for a branch.
   *
   * It mirrors a big part of the logic implemented in "api_shutdown" in the D7
   * version of this module, where references to class members, overrides, etc
   * are created or updated once the rest of the information was parsed and
   * processed for a branch.
   *
   * @param \Drupal\api\Interfaces\BranchInterface $branch
   *   Branch to create records from.
   */
  public static function classRelations(BranchInterface $branch);

  /**
   * Checks if a DocReference object exists and return matches.
   *
   * @param array $conditions
   *   Array with conditions to match.
   * @param \Drupal\api\Interfaces\BranchInterface $branch
   *   Branch the element belongs to.
   * @param \Drupal\api\Interfaces\DocBlockInterface $docBlock
   *   DocBlock the element belongs to.
   *
   * @return int[]|null
   *   Matching entries IDs or null.
   */
  public static function matches(array $conditions, BranchInterface $branch, DocBlockInterface $docBlock);

}
