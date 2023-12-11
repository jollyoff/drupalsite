<?php

namespace Drupal\api\Interfaces\DocBlock;

use Drupal\api\Interfaces\BranchInterface;
use Drupal\Core\Entity\ContentEntityInterface;

/**
 * Provides an interface defining a ReferenceCount entity type.
 */
interface DocReferenceCountInterface extends ContentEntityInterface {

  /**
   * Gets the object_name property of the entity.
   *
   * @return string
   *   The object_name of the DocReferenceCountInterface entity.
   */
  public function getObjectName();

  /**
   * Sets the object_name property.
   *
   * @param string $object_name
   *   Value to set.
   *
   * @return \Drupal\api\Interfaces\DocBlock\DocReferenceCountInterface
   *   DocReferenceCountInterface entity.
   */
  public function setObjectName($object_name);

  /**
   * Gets the branch property of the entity.
   *
   * @return \Drupal\api\Interfaces\BranchInterface
   *   The branch of the ReferenceCount entity.
   */
  public function getBranch();

  /**
   * Sets the branch property.
   *
   * @param \Drupal\api\Interfaces\BranchInterface $branch
   *   Value to set.
   *
   * @return \Drupal\api\Interfaces\DocBlock\DocReferenceCountInterface
   *   DocReferenceCountInterface entity.
   */
  public function setBranch(BranchInterface $branch);

  /**
   * Gets the reference_type property of the entity.
   *
   * @return string
   *   The reference_type of the DocReferenceCountInterface entity.
   */
  public function getReferenceType();

  /**
   * Sets the reference_type property.
   *
   * @param string $reference_type
   *   Value to set.
   *
   * @return \Drupal\api\Interfaces\DocBlock\DocReferenceCountInterface
   *   DocReferenceCountInterface entity.
   */
  public function setReferenceType($reference_type);

  /**
   * Gets the reference_count property of the entity.
   *
   * @return int
   *   The reference_count of the DocReferenceCountInterface entity.
   */
  public function getReferenceCount();

  /**
   * Sets the reference_count property.
   *
   * @param int $reference_count
   *   Value to set.
   *
   * @return \Drupal\api\Interfaces\DocBlock\DocReferenceCountInterface
   *   DocReferenceCountInterface entity.
   */
  public function setReferenceCount($reference_count);

}
