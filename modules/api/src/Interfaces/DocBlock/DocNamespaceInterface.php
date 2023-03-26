<?php

namespace Drupal\api\Interfaces\DocBlock;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\api\Interfaces\DocBlockInterface;

/**
 * Provides an interface defining a DocBlock namespace entity type.
 */
interface DocNamespaceInterface extends ContentEntityInterface {

  /**
   * Gets the object_type property of the entity.
   *
   * @return string
   *   The object_type of the DocBlock namespace entity.
   */
  public function getObjectType();

  /**
   * Sets the object_type property.
   *
   * @param string $object_type
   *   Value to set.
   *
   * @return \Drupal\api\Interfaces\DocBlock\DocNamespaceInterface
   *   DocBlock namespace entity.
   */
  public function setObjectType($object_type);

  /**
   * Gets the class_alias property of the entity.
   *
   * @return string
   *   The class_alias of the DocBlock namespace entity.
   */
  public function getClassAlias();

  /**
   * Sets the class_alias property.
   *
   * @param string $class_alias
   *   Value to set.
   *
   * @return \Drupal\api\Interfaces\DocBlock\DocNamespaceInterface
   *   DocBlock namespace entity.
   */
  public function setClassAlias($class_alias);

  /**
   * Gets the class_name property of the entity.
   *
   * @return string
   *   The class_name of the DocBlock namespace entity.
   */
  public function getClassName();

  /**
   * Sets the class_name property.
   *
   * @param string $class_name
   *   Value to set.
   *
   * @return \Drupal\api\Interfaces\DocBlock\DocNamespaceInterface
   *   DocBlock namespace entity.
   */
  public function setClassName($class_name);

  /**
   * Gets the docblock property of the entity.
   *
   * @return \Drupal\api\Interfaces\DocBlockInterface
   *   The docblock of the DocBlock namespace entity.
   */
  public function getDocBlock();

  /**
   * Sets the docblock property.
   *
   * @param \Drupal\api\Interfaces\DocBlockInterface $docblock
   *   Value to set.
   *
   * @return \Drupal\api\Interfaces\DocBlock\DocNamespaceInterface
   *   DocBlock namespace entity.
   */
  public function setDocBlock(DocBlockInterface $docblock);

  /**
   * Deletes entities based on the criteria given.
   *
   * @param array $types
   *   To map with object_type column.
   * @param \Drupal\api\Interfaces\DocBlockInterface $entity
   *   DocBlock entity.
   */
  public static function deleteRelatedByType(array $types, DocBlockInterface $entity);

  /**
   * Checks if a namespace object exists and return matches.
   *
   * @param array $conditions
   *   Array with conditions to match.
   * @param \Drupal\api\Interfaces\DocBlockInterface|null $docBlock
   *   DocBlock the element belongs to.
   *
   * @return int[]|null
   *   Matching entries IDs or null.
   */
  public static function matches(array $conditions, DocBlockInterface $docBlock = NULL);

  /**
   * Gets namespaces objects IDs linked to a docblock object.
   *
   * @param \Drupal\api\Interfaces\DocBlockInterface $docBlock
   *   DocBlock object.
   *
   * @return int[]|null
   *   List of IDs or null.
   */
  public static function getByDocBlock(DocBlockInterface $docBlock);

  /**
   * Get namespaces objects IDs with same name and type.
   *
   * @param string $class_name
   *   Name of the class.
   * @param string $type
   *   Type of the result.
   *
   * @return int[]|null
   *   List of IDs or null.
   */
  public static function getByClassName($class_name, $type = 'namespace');

}
