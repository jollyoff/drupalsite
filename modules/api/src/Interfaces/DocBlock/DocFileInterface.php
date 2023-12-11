<?php

namespace Drupal\api\Interfaces\DocBlock;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\api\Interfaces\DocBlockInterface;

/**
 * Provides an interface defining a DocBlock file entity type.
 */
interface DocFileInterface extends ContentEntityInterface {

  /**
   * Gets the basename property of the entity.
   *
   * @return string
   *   The basename of the DocBlock file entity.
   */
  public function getBasename();

  /**
   * Sets the basename property.
   *
   * @param string $basename
   *   Value to set.
   *
   * @return \Drupal\api\Interfaces\DocBlock\DocFileInterface
   *   DocBlock file entity.
   */
  public function setBasename($basename);

  /**
   * Gets the docblock property of the entity.
   *
   * @return \Drupal\api\Interfaces\DocBlockInterface
   *   The docblock of the DocBlock file entity.
   */
  public function getDocBlock();

  /**
   * Sets the docblock property.
   *
   * @param \Drupal\api\Interfaces\DocBlockInterface $docblock
   *   Value to set.
   *
   * @return \Drupal\api\Interfaces\DocBlock\DocFileInterface
   *   DocBlock file entity.
   */
  public function setDocBlock(DocBlockInterface $docblock);

  /**
   * Gets the DocFile creation timestamp.
   *
   * @return int
   *   Creation timestamp of the DocFile.
   */
  public function getCreatedTime();

  /**
   * Sets the DocFile creation timestamp.
   *
   * @param int $timestamp
   *   The DocFile creation timestamp.
   *
   * @return \Drupal\api\Interfaces\BranchInterface
   *   The called DocFile entity.
   */
  public function setCreatedTime($timestamp);

  /**
   * Checks if a file object exists and return matches.
   *
   * @param array $conditions
   *   Array with conditions to match.
   * @param \Drupal\api\Interfaces\DocBlockInterface $docBlock
   *   DocBlock the element belongs to.
   *
   * @return int[]|null
   *   Matching entries IDs or null.
   */
  public static function matches(array $conditions, DocBlockInterface $docBlock);

  /**
   * Find files that were created before the given timestamp.
   *
   * @param int $timestamp
   *   Timestamp to check.
   *
   * @return int[]|null
   *   Matching entries or null.
   */
  public static function findCreatedBefore($timestamp);

}
