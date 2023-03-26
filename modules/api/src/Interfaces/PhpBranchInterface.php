<?php

namespace Drupal\api\Interfaces;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityChangedInterface;

/**
 * Provides an interface defining a branch entity type.
 */
interface PhpBranchInterface extends ContentEntityInterface, EntityChangedInterface {

  /**
   * Gets the php_branch title.
   *
   * @return string
   *   Title of the php_branch.
   */
  public function getTitle();

  /**
   * Sets the php_branch title.
   *
   * @param string $title
   *   The php_branch title.
   *
   * @return \Drupal\api\Interfaces\PhpBranchInterface
   *   The called php_branch entity.
   */
  public function setTitle($title);

  /**
   * Gets the php_branch creation timestamp.
   *
   * @return int
   *   Creation timestamp of the php_branch.
   */
  public function getCreatedTime();

  /**
   * Sets the php_branch creation timestamp.
   *
   * @param int $timestamp
   *   The php_branch creation timestamp.
   *
   * @return \Drupal\api\Interfaces\PhpBranchInterface
   *   The called php_branch entity.
   */
  public function setCreatedTime($timestamp);

  /**
   * Gets the php_branch queued timestamp.
   *
   * @return int
   *   Queued timestamp of the php_branch.
   */
  public function getQueued();

  /**
   * Sets the php_branch queued timestamp.
   *
   * @param int $timestamp
   *   The php_branch queued timestamp.
   *
   * @return \Drupal\api\Interfaces\PhpBranchInterface
   *   The called php_branch entity.
   */
  public function setQueued($timestamp);

  /**
   * Marks a branch for re-parsing.
   *
   * @return int
   *   Either SAVED_NEW or SAVED_UPDATED, depending on the operation performed.
   */
  public function reParse();

  /**
   * Gets the php_branch update frequency in seconds.
   *
   * @return int
   *   PHP branch update frequency in seconds.
   */
  public function getUpdateFrequency();

  /**
   * Sets the branch update frequency.
   *
   * @param int $update_frequency
   *   Branch update frequency.
   *
   * @return \Drupal\api\Interfaces\PhpBranchInterface
   *   The called php_branch entity.
   */
  public function setUpdateFrequency($update_frequency);

  /**
   * Gets the php_branch function list.
   *
   * @return string
   *   Function list of the php_branch.
   */
  public function getFunctionList();

  /**
   * Sets the php_branch function list.
   *
   * @param string $function_list
   *   PHP branch function list.
   *
   * @return \Drupal\api\Interfaces\PhpBranchInterface
   *   The called php_branch entity.
   */
  public function setFunctionList($function_list);

  /**
   * Gets the php_branch function url pattern.
   *
   * @return string
   *   Function url pattern of the php_branch.
   */
  public function getFunctionUrlPattern();

  /**
   * Sets the php_branch function url pattern.
   *
   * @param string $function_url_pattern
   *   PHP branch function url pattern.
   *
   * @return \Drupal\api\Interfaces\PhpBranchInterface
   *   The called php_branch entity.
   */
  public function setFunctionUrlPattern($function_url_pattern);

}
