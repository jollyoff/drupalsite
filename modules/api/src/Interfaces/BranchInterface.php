<?php

namespace Drupal\api\Interfaces;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityChangedInterface;

/**
 * Provides an interface defining a branch entity type.
 */
interface BranchInterface extends ContentEntityInterface, EntityChangedInterface {

  /**
   * Gets the branch title.
   *
   * @return string
   *   Title of the branch.
   */
  public function getTitle();

  /**
   * Sets the branch title.
   *
   * @param string $title
   *   The branch title.
   *
   * @return \Drupal\api\Interfaces\BranchInterface
   *   The called branch entity.
   */
  public function setTitle($title);

  /**
   * Gets the branch weight.
   *
   * @return string
   *   Weight of the branch.
   */
  public function getWeight();

  /**
   * Sets the branch weight.
   *
   * @param string $weight
   *   The branch weight.
   *
   * @return \Drupal\api\Interfaces\BranchInterface
   *   The called branch entity.
   */
  public function setWeight($weight);

  /**
   * Gets the branch creation timestamp.
   *
   * @return int
   *   Creation timestamp of the branch.
   */
  public function getCreatedTime();

  /**
   * Sets the branch creation timestamp.
   *
   * @param int $timestamp
   *   The branch creation timestamp.
   *
   * @return \Drupal\api\Interfaces\BranchInterface
   *   The called branch entity.
   */
  public function setCreatedTime($timestamp);

  /**
   * Gets the project that the branch belongs to.
   *
   * @return \Drupal\api\Interfaces\ProjectInterface
   *   The project where this branch is located.
   */
  public function getProject();

  /**
   * Sets the project for the branch.
   *
   * @param \Drupal\api\Interfaces\ProjectInterface $project
   *   The project for the branch.
   *
   * @return \Drupal\api\Interfaces\BranchInterface
   *   The called branch entity.
   */
  public function setProject(ProjectInterface $project);

  /**
   * Gets the branch slug.
   *
   * @return string
   *   Branch slug.
   */
  public function getSlug();

  /**
   * Sets the branch slug.
   *
   * @param string $slug
   *   Branch slug.
   *
   * @return \Drupal\api\Interfaces\BranchInterface
   *   The called branch entity.
   */
  public function setSlug($slug);

  /**
   * Gets the branch core compatibility.
   *
   * @return string
   *   Branch core compatibility.
   */
  public function getCoreCompatibility();

  /**
   * Sets the branch core compatibility.
   *
   * @param string $core_compatibility
   *   Branch core compatibility.
   *
   * @return \Drupal\api\Interfaces\BranchInterface
   *   The called branch entity.
   */
  public function setCoreCompatibility($core_compatibility);

  /**
   * Gets the branch preferred.
   *
   * @return bool
   *   Branch preferred.
   */
  public function getPreferred();

  /**
   * Checks if the branch is preferred.
   *
   * @return bool
   *   Whether preferred is true or not.
   */
  public function isPreferred();

  /**
   * Sets the branch preferred.
   *
   * @param bool $preferred
   *   Branch preferred.
   *
   * @return \Drupal\api\Interfaces\BranchInterface
   *   The called branch entity.
   */
  public function setPreferred($preferred);

  /**
   * Gets the branch directories.
   *
   * @param bool $as_array
   *   Return the data as array of paths.
   *
   * @return string
   *   Branch directories.
   */
  public function getDirectories($as_array = FALSE);

  /**
   * Sets the branch directories.
   *
   * @param string $directories
   *   Branch directories.
   *
   * @return \Drupal\api\Interfaces\BranchInterface
   *   The called branch entity.
   */
  public function setDirectories($directories);

  /**
   * Gets the branch excluded directories.
   *
   * @param bool $as_array
   *   Return the data as array of paths.
   *
   * @return string
   *   Branch excluded directories.
   */
  public function getExcludedDirectories($as_array = FALSE);

  /**
   * Sets the branch excluded directories.
   *
   * @param string $excluded_directories
   *   Branch excluded directories.
   *
   * @return \Drupal\api\Interfaces\BranchInterface
   *   The called branch entity.
   */
  public function setExcludedDirectories($excluded_directories);

  /**
   * Gets the branch excluded files regex.
   *
   * @param bool $as_array
   *   Return the data as array of paths.
   *
   * @return string
   *   Branch excluded files regex.
   */
  public function getExcludeFilesRegexp($as_array = FALSE);

  /**
   * Sets the branch excluded files regex.
   *
   * @param string $exclude_files_regexp
   *   Branch excluded files regex.
   *
   * @return \Drupal\api\Interfaces\BranchInterface
   *   The called branch entity.
   */
  public function setExcludeFilesRegexp($exclude_files_regexp);

  /**
   * Gets the branch excluded Drupalisms regex.
   *
   * @param bool $as_array
   *   Return the data as array of paths.
   *
   * @return string
   *   Branch excluded Drupalisms regex.
   */
  public function getExcludeDrupalismRegexp($as_array = FALSE);

  /**
   * Sets the branch excluded Drupalisms regex.
   *
   * @param string $exclude_drupalism_regexp
   *   Branch excluded Drupalisms regex.
   *
   * @return \Drupal\api\Interfaces\BranchInterface
   *   The called branch entity.
   */
  public function setExcludeDrupalismRegexp($exclude_drupalism_regexp);

  /**
   * Gets the branch update frequency in seconds.
   *
   * @return int
   *   Branch update frequency in seconds.
   */
  public function getUpdateFrequency();

  /**
   * Sets the branch update frequency.
   *
   * @param int $update_frequency
   *   Branch update frequency.
   *
   * @return \Drupal\api\Interfaces\BranchInterface
   *   The called branch entity.
   */
  public function setUpdateFrequency($update_frequency);

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
   * Gets DocBlock entites linked to this branch.
   *
   * @param bool $full_entities
   *   Whether to get full entities or just the ids.
   *
   * @return \Drupal\api\Entity\DocBlock[]|int[]|null
   *   Array of DocBlock entities, or ids, or null if no docblocks are linked.
   */
  public function getDocBlocks($full_entities = FALSE);

}
