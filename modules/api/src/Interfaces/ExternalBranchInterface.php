<?php

namespace Drupal\api\Interfaces;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityChangedInterface;

/**
 * Provides an interface defining a branch entity type.
 */
interface ExternalBranchInterface extends ContentEntityInterface, EntityChangedInterface {

  /**
   * Gets the external branch title.
   *
   * @return string
   *   Title of the external branch.
   */
  public function getTitle();

  /**
   * Sets the external branch title.
   *
   * @param string $title
   *   The external branch title.
   *
   * @return \Drupal\api\Interfaces\ExternalBranchInterface
   *   The called external branch entity.
   */
  public function setTitle($title);

  /**
   * Gets the external branch creation timestamp.
   *
   * @return int
   *   Creation timestamp of the external branch.
   */
  public function getCreatedTime();

  /**
   * Sets the external branch creation timestamp.
   *
   * @param int $timestamp
   *   The external branch creation timestamp.
   *
   * @return \Drupal\api\Interfaces\ExternalBranchInterface
   *   The called external branch entity.
   */
  public function setCreatedTime($timestamp);

  /**
   * Gets the external branch update frequency in seconds.
   *
   * @return int
   *   External branch update frequency in seconds.
   */
  public function getUpdateFrequency();

  /**
   * Sets the branch update frequency.
   *
   * @param int $update_frequency
   *   Branch update frequency.
   *
   * @return \Drupal\api\Interfaces\ExternalBranchInterface
   *   The called external branch entity.
   */
  public function setUpdateFrequency($update_frequency);

  /**
   * Gets the external branch function list.
   *
   * @return string
   *   Function list of the external branch.
   */
  public function getFunctionList();

  /**
   * Sets the external branch function list.
   *
   * @param string $function_list
   *   External branch function list.
   *
   * @return \Drupal\api\Interfaces\ExternalBranchInterface
   *   The called external branch entity.
   */
  public function setFunctionList($function_list);

  /**
   * Gets the external branch search URL.
   *
   * @return string
   *   Search URL of the external branch.
   */
  public function getSearchUrl();

  /**
   * Sets the external branch search URL.
   *
   * @param string $search_url
   *   External branch search URL.
   *
   * @return \Drupal\api\Interfaces\ExternalBranchInterface
   *   The called external branch entity.
   */
  public function setSearchUrl($search_url);

  /**
   * Gets the external branch core compatibility.
   *
   * @return string
   *   External branch core compatibility.
   */
  public function getCoreCompatibility();

  /**
   * Sets the external branch core compatibility.
   *
   * @param string $core_compatibility
   *   External branch core compatibility.
   *
   * @return \Drupal\api\Interfaces\ExternalBranchInterface
   *   The called external branch entity.
   */
  public function setCoreCompatibility($core_compatibility);

  /**
   * Gets the external branch type.
   *
   * @return string
   *   Type of this external branch.
   */
  public function getType();

  /**
   * Sets the external branch type.
   *
   * @param string $type
   *   The external branch type.
   *
   * @return \Drupal\api\Interfaces\ExternalBranchInterface
   *   The called external branch entity.
   */
  public function setType($type);

  /**
   * Gets the external branch timeout in seconds.
   *
   * @return int
   *   External branch timeout in seconds.
   */
  public function getTimeout();

  /**
   * Sets the external branch timeout.
   *
   * @param int $timeout
   *   External branch timeout.
   *
   * @return \Drupal\api\Interfaces\ExternalBranchInterface
   *   The called external branch entity.
   */
  public function setTimeout($timeout);

  /**
   * Gets the external branch items per page in seconds.
   *
   * @return int
   *   External branch items per page in seconds.
   */
  public function getItemsPerPage();

  /**
   * Sets the external branch items per page.
   *
   * @param int $items_per_page
   *   External branch items per page.
   *
   * @return \Drupal\api\Interfaces\ExternalBranchInterface
   *   The called external branch entity.
   */
  public function setItemsPerPage($items_per_page);

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

}
