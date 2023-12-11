<?php

namespace Drupal\api\Interfaces;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityChangedInterface;

/**
 * Provides an interface defining a project entity type.
 */
interface ProjectInterface extends ContentEntityInterface, EntityChangedInterface {

  /**
   * Gets the project title.
   *
   * @return string
   *   Title of the project.
   */
  public function getTitle();

  /**
   * Sets the project title.
   *
   * @param string $title
   *   The project title.
   *
   * @return \Drupal\api\Interfaces\ProjectInterface
   *   The called project entity.
   */
  public function setTitle($title);

  /**
   * Gets the project creation timestamp.
   *
   * @return int
   *   Creation timestamp of the project.
   */
  public function getCreatedTime();

  /**
   * Sets the project creation timestamp.
   *
   * @param int $timestamp
   *   The project creation timestamp.
   *
   * @return \Drupal\api\Interfaces\ProjectInterface
   *   The called project entity.
   */
  public function setCreatedTime($timestamp);

  /**
   * Gets the project slug.
   *
   * @return string
   *   Slug used for URLs for this project.
   */
  public function getSlug();

  /**
   * Sets the project slug.
   *
   * @param string $slug
   *   The project slug.
   *
   * @return \Drupal\api\Interfaces\ProjectInterface
   *   The called project entity.
   */
  public function setSlug($slug);

  /**
   * Gets the project type.
   *
   * @return string
   *   Type of this project.
   */
  public function getType();

  /**
   * Whether the project is a core project or not.
   *
   * @return bool
   *   Whether project is a 'core' project or not.
   */
  public function isCore();

  /**
   * Sets the project type.
   *
   * @param string $type
   *   The project type.
   *
   * @return \Drupal\api\Interfaces\ProjectInterface
   *   The called project entity.
   */
  public function setType($type);

  /**
   * Gets the project branches.
   *
   * @param bool $full_entity
   *   Whether to return full entities or just IDs.
   *
   * @return string[]|\Drupal\api\Entity\Branch[]
   *   Array of branch IDs for this project.
   */
  public function getBranches($full_entity = FALSE);

  /**
   * Gets the project's default branch, if specified.
   *
   * @param bool $fallback
   *   If no default branch is set or found, return any branch from the project.
   *
   * @return \Drupal\api\Entity\Branch|null
   *   Default project's branch or null.
   */
  public function getDefaultBranch($fallback = FALSE);

}
