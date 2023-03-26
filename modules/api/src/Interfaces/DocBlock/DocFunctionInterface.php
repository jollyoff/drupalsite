<?php

namespace Drupal\api\Interfaces\DocBlock;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\api\Interfaces\DocBlockInterface;

/**
 * Provides an interface defining a DocBlock function entity type.
 */
interface DocFunctionInterface extends ContentEntityInterface {

  /**
   * Gets the signature property of the entity.
   *
   * @return string
   *   The signature of the DocBlock function entity.
   */
  public function getSignature();

  /**
   * Sets the signature property.
   *
   * @param string $signature
   *   Value to set.
   *
   * @return \Drupal\api\Interfaces\DocBlock\DocFunctionInterface
   *   DocBlock function entity.
   */
  public function setSignature($signature);

  /**
   * Gets the parameters property of the entity.
   *
   * @return string
   *   The parameters of the DocBlock function entity.
   */
  public function getParameters();

  /**
   * Sets the parameters property.
   *
   * @param string $parameters
   *   Value to set.
   *
   * @return \Drupal\api\Interfaces\DocBlock\DocFunctionInterface
   *   DocBlock function entity.
   */
  public function setParameters($parameters);

  /**
   * Gets the return_value property of the entity.
   *
   * @return string
   *   The return_value of the DocBlock function entity.
   */
  public function getReturnValue();

  /**
   * Sets the return_value property.
   *
   * @param string $return_value
   *   Value to set.
   *
   * @return \Drupal\api\Interfaces\DocBlock\DocFunctionInterface
   *   DocBlock function entity.
   */
  public function setReturnValue($return_value);

  /**
   * Gets the docblock property of the entity.
   *
   * @return \Drupal\api\Interfaces\DocBlockInterface
   *   The docblock of the DocBlock function entity.
   */
  public function getDocBlock();

  /**
   * Sets the docblock property.
   *
   * @param \Drupal\api\Interfaces\DocBlockInterface $docblock
   *   Value to set.
   *
   * @return \Drupal\api\Interfaces\DocBlock\DocFunctionInterface
   *   DocBlock function entity.
   */
  public function setDocBlock(DocBlockInterface $docblock);

  /**
   * Checks if a function object exists and return matches.
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

}
