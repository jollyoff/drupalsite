<?php

namespace Drupal\api\Interfaces\DocBlock;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\api\Interfaces\DocBlockInterface;

/**
 * Provides an interface defining a DocBlock class member entity type.
 */
interface DocClassMemberInterface extends ContentEntityInterface {

  /**
   * Gets the member_alias property of the entity.
   *
   * @return string
   *   The member_alias of the DocBlock class member entity.
   */
  public function getMemberAlias();

  /**
   * Sets the member_alias property.
   *
   * @param string $member_alias
   *   Value to set.
   *
   * @return \Drupal\api\Interfaces\DocBlock\DocClassMemberInterface
   *   DocBlock class member entity.
   */
  public function setMemberAlias($member_alias);

  /**
   * Gets the docblock property of the entity.
   *
   * @return \Drupal\api\Interfaces\DocBlockInterface
   *   The docblock of the DocBlock class member entity.
   */
  public function getDocBlock();

  /**
   * Sets the docblock property.
   *
   * @param \Drupal\api\Interfaces\DocBlockInterface $docblock
   *   Value to set.
   *
   * @return \Drupal\api\Interfaces\DocBlock\DocClassMemberInterface
   *   DocClassMemberInterface entity.
   */
  public function setDocBlock(DocBlockInterface $docblock);

  /**
   * Gets the class docblock property of the entity.
   *
   * @return \Drupal\api\Interfaces\DocBlockInterface
   *   The class docblock of the DocBlock class member entity.
   */
  public function getClassDocBlock();

  /**
   * Sets the class docblock property.
   *
   * @param \Drupal\api\Interfaces\DocBlockInterface $docblock
   *   Value to set.
   *
   * @return \Drupal\api\Interfaces\DocBlock\DocClassMemberInterface
   *   DocClassMemberInterface entity.
   */
  public function setClassDocBlock(DocBlockInterface $docblock);

  /**
   * Find matches according to the given conditions for the given class.
   *
   * @param array $conditions
   *   Conditions to apply.
   * @param \Drupal\api\Interfaces\DocBlockInterface $class
   *   Class to search matching members from.
   *
   * @return int[]|null
   *   List of matching IDs or null.
   */
  public static function matches(array $conditions, DocBlockInterface $class = NULL);

}
