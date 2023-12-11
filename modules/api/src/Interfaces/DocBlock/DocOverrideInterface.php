<?php

namespace Drupal\api\Interfaces\DocBlock;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\api\Interfaces\DocBlockInterface;

/**
 * Provides an interface defining a DocBlock overrides entity type.
 */
interface DocOverrideInterface extends ContentEntityInterface {

  /**
   * Gets the docblock property of the entity.
   *
   * @return \Drupal\api\Interfaces\DocBlockInterface
   *   The docblock of the DocBlock override entity.
   */
  public function getDocBlock();

  /**
   * Sets the docblock property.
   *
   * @param \Drupal\api\Interfaces\DocBlockInterface $docblock
   *   Value to set.
   *
   * @return \Drupal\api\Interfaces\DocBlock\DocOverrideInterface
   *   DocOverrideInterface entity.
   */
  public function setDocBlock(DocBlockInterface $docblock);

  /**
   * Gets the overrides docblock property of the entity.
   *
   * @return \Drupal\api\Interfaces\DocBlockInterface|null
   *   The overrides docblock of the DocBlock override entity.
   */
  public function getOverridesDocBlock();

  /**
   * Sets the overrides docblock property.
   *
   * @param \Drupal\api\Interfaces\DocBlockInterface $docblock
   *   Value to set.
   *
   * @return \Drupal\api\Interfaces\DocBlock\DocOverrideInterface
   *   DocOverrideInterface entity.
   */
  public function setOverridesDocBlock(DocBlockInterface $docblock);

  /**
   * Gets the documented docblock property of the entity.
   *
   * @return \Drupal\api\Interfaces\DocBlockInterface
   *   The documented docblock of the DocBlock override entity.
   */
  public function getDocumentedInDocBlock();

  /**
   * Sets the documented docblock property.
   *
   * @param \Drupal\api\Interfaces\DocBlockInterface $docblock
   *   Value to set.
   *
   * @return \Drupal\api\Interfaces\DocBlock\DocOverrideInterface
   *   DocOverrideInterface entity.
   */
  public function setDocumentedInDocBlock(DocBlockInterface $docblock);

  /**
   * Checks if a override object exists and return matches.
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

}
