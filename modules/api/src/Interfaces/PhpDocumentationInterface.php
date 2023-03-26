<?php

namespace Drupal\api\Interfaces;

use Drupal\Core\Entity\ContentEntityInterface;

/**
 * Provides an interface defining a php documentation entity type.
 */
interface PhpDocumentationInterface extends ContentEntityInterface {

  /**
   * Gets the object_name property of the entity.
   *
   * @return string
   *   The object_name of the PHP documentation entity.
   */
  public function getObjectName();

  /**
   * Sets the object_name property.
   *
   * @param string $object_name
   *   Value to set.
   *
   * @return \Drupal\api\Interfaces\PhpDocumentationInterface
   *   PHP documentation entity.
   */
  public function setObjectName($object_name);

  /**
   * Gets the object_type property of the entity.
   *
   * @return string
   *   The object_type of the PHP documentation entity.
   */
  public function getObjectType();

  /**
   * Sets the object_type property.
   *
   * @param string $object_type
   *   Value to set.
   *
   * @return \Drupal\api\Interfaces\PhpDocumentationInterface
   *   PHP documentation entity.
   */
  public function setObjectType($object_type);

  /**
   * Gets the member_name property of the entity.
   *
   * @return string
   *   The member_name of the PHP documentation entity.
   */
  public function getMemberName();

  /**
   * Sets the member_name property.
   *
   * @param string $member_name
   *   Value to set.
   *
   * @return \Drupal\api\Interfaces\PhpDocumentationInterface
   *   PHP documentation entity.
   */
  public function setMemberName($member_name);

  /**
   * Gets the documentation property of the entity.
   *
   * @return string
   *   The documentation of the PHP documentation entity.
   */
  public function getDocumentation();

  /**
   * Sets the Documentation property.
   *
   * @param string $documentation
   *   Value to set.
   *
   * @return \Drupal\api\Interfaces\PhpDocumentationInterface
   *   PHP documentation entity.
   */
  public function setDocumentation($documentation);

  /**
   * Gets the php_branch property of the entity.
   *
   * @return \Drupal\api\Interfaces\PhpBranchInterface
   *   The php_branch of the PHP documentation entity.
   */
  public function getPhpBranch();

  /**
   * Sets the PhpBranch property.
   *
   * @param \Drupal\api\Interfaces\PhpBranchInterface $php_branch
   *   Value to set.
   *
   * @return \Drupal\api\Interfaces\PhpDocumentationInterface
   *   PHP documentation entity.
   */
  public function setPhpBranch(PhpBranchInterface $php_branch);

  /**
   * Gets the php.net link for the item.
   *
   * @return \Drupal\Core\Link
   *   Link object to the external source.
   */
  public function getExternalLink();

}
