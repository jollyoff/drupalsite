<?php

namespace Drupal\api\Interfaces;

use Drupal\Core\Entity\ContentEntityInterface;

/**
 * Provides an interface defining a external documentation entity type.
 */
interface ExternalDocumentationInterface extends ContentEntityInterface {

  /**
   * Gets the title property of the entity.
   *
   * @return string
   *   The title of the External documentation entity.
   */
  public function getTitle();

  /**
   * Sets the title property.
   *
   * @param string $title
   *   Value to set.
   *
   * @return \Drupal\api\Interfaces\ExternalDocumentationInterface
   *   External documentation entity.
   */
  public function setTitle($title);

  /**
   * Gets the object_name property of the entity.
   *
   * @return string
   *   The object_name of the External documentation entity.
   */
  public function getObjectName();

  /**
   * Sets the object_name property.
   *
   * @param string $object_name
   *   Value to set.
   *
   * @return \Drupal\api\Interfaces\ExternalDocumentationInterface
   *   External documentation entity.
   */
  public function setObjectName($object_name);

  /**
   * Gets the object_type property of the entity.
   *
   * @return string
   *   The object_type of the External documentation entity.
   */
  public function getObjectType();

  /**
   * Sets the object_type property.
   *
   * @param string $object_type
   *   Value to set.
   *
   * @return \Drupal\api\Interfaces\ExternalDocumentationInterface
   *   External documentation entity.
   */
  public function setObjectType($object_type);

  /**
   * Gets the namespaced_name property of the entity.
   *
   * @return string
   *   The namespaced_name of the External documentation entity.
   */
  public function getNamespacedName();

  /**
   * Sets the namespaced_name property.
   *
   * @param string $namespaced_name
   *   Value to set.
   *
   * @return \Drupal\api\Interfaces\ExternalDocumentationInterface
   *   External documentation entity.
   */
  public function setNamespacedName($namespaced_name);

  /**
   * Gets the member_name property of the entity.
   *
   * @return string
   *   The member_name of the External documentation entity.
   */
  public function getMemberName();

  /**
   * Sets the member_name property.
   *
   * @param string $member_name
   *   Value to set.
   *
   * @return \Drupal\api\Interfaces\ExternalDocumentationInterface
   *   External documentation entity.
   */
  public function setMemberName($member_name);

  /**
   * Gets the summary property of the entity.
   *
   * @return string
   *   The summary of the External documentation entity.
   */
  public function getSummary();

  /**
   * Sets the Summary property.
   *
   * @param string $summary
   *   Value to set.
   *
   * @return \Drupal\api\Interfaces\ExternalDocumentationInterface
   *   External documentation summary entity.
   */
  public function setSummary($summary);

  /**
   * Gets the external_branch property of the entity.
   *
   * @return \Drupal\api\Interfaces\ExternalBranchInterface
   *   The external_branch of the External documentation entity.
   */
  public function getExternalBranch();

  /**
   * Sets the external_branch property.
   *
   * @param \Drupal\api\Interfaces\ExternalBranchInterface $external_branch
   *   Value to set.
   *
   * @return \Drupal\api\Interfaces\ExternalDocumentationInterface
   *   External documentation entity.
   */
  public function setExternalBranch(ExternalBranchInterface $external_branch);

  /**
   * Gets the url property of the entity.
   *
   * @return string
   *   The url of the External documentation entity.
   */
  public function getUrl();

  /**
   * Sets the Url property.
   *
   * @param string $url
   *   Value to set.
   *
   * @return \Drupal\api\Interfaces\ExternalDocumentationInterface
   *   External documentation url entity.
   */
  public function setUrl($url);

  /**
   * Gets the external link for the item.
   *
   * @return \Drupal\Core\Link
   *   Link object to the external source.
   */
  public function getExternalLink();

  /**
   * Find matches for the given conditions.
   *
   * @param array $conditions
   *   Conditions to apply.
   * @param \Drupal\api\Interfaces\ExternalBranchInterface|null $branch
   *   Branch to link the matches, if given any.
   *
   * @return int[]|null
   *   List of matching IDs or null.
   */
  public static function matches(array $conditions, ExternalBranchInterface $branch = NULL);

  /**
   * Find matches by namespaced name and type within the given branches.
   *
   * @param string $namespace_name
   *   Namespaced name of the object.
   * @param string $type
   *   Object type.
   * @param array $branch_ids
   *   List of branch IDs to match on.
   *
   * @return int[]|null
   *   Matching IDs or null.
   */
  public static function findByNamespaceNameAndType($namespace_name, $type, array $branch_ids);

  /**
   * Find matches by member name within the given branches.
   *
   * @param string $member_name
   *   Member name of the object.
   * @param array $branch_ids
   *   List of branch IDs to match on.
   * @param bool $function_or_not_functions
   *   Return functions or elements that are not functions.
   *
   * @return int[]|null
   *   Matching IDs or null.
   */
  public static function findByMemberName($member_name, array $branch_ids, $function_or_not_functions);

  /**
   * Checks if a external documentation object exists and return matches.
   *
   * @param string $url
   *   Url of the function to look for.
   * @param \Drupal\api\Interfaces\ExternalBranchInterface $branch
   *   Branch the function belongs to.
   *
   * @return \Drupal\api\Interfaces\ExternalDocumentationInterface[]|null
   *   Matching entries or null.
   */
  public static function findByUrl($url, ExternalBranchInterface $branch);

}
