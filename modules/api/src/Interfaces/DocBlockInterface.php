<?php

namespace Drupal\api\Interfaces;

use Drupal\Core\Entity\ContentEntityInterface;

/**
 * Provides an interface defining a DocBlock entity type.
 */
interface DocBlockInterface extends ContentEntityInterface {

  /**
   * Gets the object_name property of the entity.
   *
   * @return string
   *   The object_name of the DocBlock entity.
   */
  public function getObjectName();

  /**
   * Sets the object_name property.
   *
   * @param string $object_name
   *   Value to set.
   *
   * @return \Drupal\api\Interfaces\DocBlockInterface
   *   DocBlock entity.
   */
  public function setObjectName($object_name);

  /**
   * Gets the docblock comments.
   *
   * @return \Drupal\comment\CommentFieldItemList
   *   Comments settings for the docblock.
   */
  public function getComments();

  /**
   * Gets the title property of the entity.
   *
   * @return string
   *   The title of the DocBlock entity.
   */
  public function getTitle();

  /**
   * Sets the title property.
   *
   * @param string $title
   *   Value to set.
   *
   * @return \Drupal\api\Interfaces\DocBlockInterface
   *   DocBlock entity.
   */
  public function setTitle($title);

  /**
   * Gets the file_name property of the entity.
   *
   * @return string
   *   The file_name of the DocBlock entity.
   */
  public function getFileName();

  /**
   * Sets the file_name property.
   *
   * @param string $file_name
   *   Value to set.
   *
   * @return \Drupal\api\Interfaces\DocBlockInterface
   *   DocBlock entity.
   */
  public function setFileName($file_name);

  /**
   * Gets the object_type property of the entity.
   *
   * @return string
   *   The object_type of the DocBlock entity.
   */
  public function getObjectType();

  /**
   * Sets the object_type property.
   *
   * @param string $object_type
   *   Value to set.
   *
   * @return \Drupal\api\Interfaces\DocBlockInterface
   *   DocBlock entity.
   */
  public function setObjectType($object_type);

  /**
   * Gets the member_name property of the entity.
   *
   * @return string
   *   The member_name of the DocBlock entity.
   */
  public function getMemberName();

  /**
   * Sets the member_name property.
   *
   * @param string $member_name
   *   Value to set.
   *
   * @return \Drupal\api\Interfaces\DocBlockInterface
   *   DocBlock entity.
   */
  public function setMemberName($member_name);

  /**
   * Gets the documentation property of the entity.
   *
   * @return string
   *   The documentation of the DocBlock entity.
   */
  public function getDocumentation();

  /**
   * Sets the documentation property.
   *
   * @param string $documentation
   *   Value to set.
   *
   * @return \Drupal\api\Interfaces\DocBlockInterface
   *   DocBlock entity.
   */
  public function setDocumentation($documentation);

  /**
   * Gets the summary property of the entity.
   *
   * @return string
   *   The summary of the DocBlock entity.
   */
  public function getSummary();

  /**
   * Sets the summary property.
   *
   * @param string $summary
   *   Value to set.
   *
   * @return \Drupal\api\Interfaces\DocBlockInterface
   *   DocBlock entity.
   */
  public function setSummary($summary);

  /**
   * Gets the code property of the entity.
   *
   * @return string
   *   The code of the DocBlock entity.
   */
  public function getCode();

  /**
   * Sets the code property.
   *
   * @param string $code
   *   Value to set.
   *
   * @return \Drupal\api\Interfaces\DocBlockInterface
   *   DocBlock entity.
   */
  public function setCode($code);

  /**
   * Gets the see property of the entity.
   *
   * @return string
   *   The see of the DocBlock entity.
   */
  public function getSee();

  /**
   * Sets the see property.
   *
   * @param string $see
   *   Value to set.
   *
   * @return \Drupal\api\Interfaces\DocBlockInterface
   *   DocBlock entity.
   */
  public function setSee($see);

  /**
   * Gets the var property of the entity.
   *
   * @return string
   *   The var of the DocBlock entity.
   */
  public function getVar();

  /**
   * Sets the var property.
   *
   * @param string $var
   *   Value to set.
   *
   * @return \Drupal\api\Interfaces\DocBlockInterface
   *   DocBlock entity.
   */
  public function setVar($var);

  /**
   * Gets the throws property of the entity.
   *
   * @return string
   *   The throws of the DocBlock entity.
   */
  public function getThrows();

  /**
   * Sets the throws property.
   *
   * @param string $throws
   *   Value to set.
   *
   * @return \Drupal\api\Interfaces\DocBlockInterface
   *   DocBlock entity.
   */
  public function setThrows($throws);

  /**
   * Gets the deprecated property of the entity.
   *
   * @return string
   *   The deprecated of the DocBlock entity.
   */
  public function getDeprecated();

  /**
   * Sets the deprecated property.
   *
   * @param string $deprecated
   *   Value to set.
   *
   * @return \Drupal\api\Interfaces\DocBlockInterface
   *   DocBlock entity.
   */
  public function setDeprecated($deprecated);

  /**
   * Gets the namespace property of the entity.
   *
   * @return string
   *   The namespace of the DocBlock entity.
   */
  public function getNamespace();

  /**
   * Sets the namespace property.
   *
   * @param string $namespace
   *   Value to set.
   *
   * @return \Drupal\api\Interfaces\DocBlockInterface
   *   DocBlock entity.
   */
  public function setNamespace($namespace);

  /**
   * Gets the namespaced_name property of the entity.
   *
   * @return string
   *   The namespaced_name of the DocBlock entity.
   */
  public function getNamespacedName();

  /**
   * Sets the namespaced_name property.
   *
   * @param string $namespaced_name
   *   Value to set.
   *
   * @return \Drupal\api\Interfaces\DocBlockInterface
   *   DocBlock entity.
   */
  public function setNamespacedName($namespaced_name);

  /**
   * Gets the modifiers property of the entity.
   *
   * @return string
   *   The modifiers of the DocBlock entity.
   */
  public function getModifiers();

  /**
   * Sets the modifiers property.
   *
   * @param string $modifiers
   *   Value to set.
   *
   * @return \Drupal\api\Interfaces\DocBlockInterface
   *   DocBlock entity.
   */
  public function setModifiers($modifiers);

  /**
   * Gets the start_line property of the entity.
   *
   * @return int
   *   The start_line of the DocBlock entity.
   */
  public function getStartLine();

  /**
   * Sets the start_line property.
   *
   * @param int $start_line
   *   Value to set.
   *
   * @return \Drupal\api\Interfaces\DocBlockInterface
   *   DocBlock entity.
   */
  public function setStartLine($start_line);

  /**
   * Gets the is_drupal property of the entity.
   *
   * @return bool
   *   The is_drupal of the DocBlock entity.
   */
  public function getIsDrupal();

  /**
   * Gets the is_drupal property of the entity.
   *
   * @return bool
   *   The is_drupal of the DocBlock entity.
   */
  public function isDrupal();

  /**
   * Sets the is_drupal property.
   *
   * @param bool $is_drupal
   *   Value to set.
   *
   * @return \Drupal\api\Interfaces\DocBlockInterface
   *   DocBlock entity.
   */
  public function setIsDrupal($is_drupal);

  /**
   * Gets the class property of the entity.
   *
   * @return \Drupal\api\Interfaces\DocBlockInterface
   *   The class of the DocBlock entity.
   */
  public function getClass();

  /**
   * Sets the class property.
   *
   * @param \Drupal\api\Interfaces\DocBlockInterface $class
   *   Value to set.
   *
   * @return \Drupal\api\Interfaces\DocBlockInterface
   *   DocBlock entity.
   */
  public function setClass(DocBlockInterface $class);

  /**
   * Gets the branch property of the entity.
   *
   * @return \Drupal\api\Interfaces\BranchInterface
   *   The branch of the DocBlock entity.
   */
  public function getBranch();

  /**
   * Sets the branch property.
   *
   * @param \Drupal\api\Interfaces\BranchInterface $branch
   *   Value to set.
   *
   * @return \Drupal\api\Interfaces\DocBlockInterface
   *   DocBlock entity.
   */
  public function setBranch(BranchInterface $branch);

  /**
   * Gets the function object related to the entity.
   *
   * @return \Drupal\api\Interfaces\DocBlock\DocFunctionInterface
   *   The function related to the DocBlock entity.
   */
  public function getDocFunction();

  /**
   * Gets the file object related to the entity.
   *
   * @return \Drupal\api\Interfaces\DocBlock\DocFileInterface
   *   The file related to the DocBlock entity.
   */
  public function getDocFile();

  /**
   * Gets the class member objects related to the entity.
   *
   * @return \Drupal\api\Interfaces\DocBlock\DocFunctionInterface[]
   *   The class members related to the DocBlock entity.
   */
  public function getDocClassMembers();

  /**
   * Get direct and indirect class members DocBlock objects.
   *
   * @param string $member_name
   *   Optionally filter by member name.
   * @param bool $function_or_not_functions
   *   Return function or non-function objects, NULL for all.
   *
   * @return \Drupal\api\Interfaces\DocBlockInterface[]|null
   *   Direct and indirect member objects or null.
   */
  public function getDocBlockClassMembers($member_name = NULL, $function_or_not_functions = NULL);

  /**
   * Gets the object children (applicable to classes and similar).
   *
   * @param string $type
   *   Type of references.
   * @param bool $same_branch
   *   Get children from same branch only.
   *
   * @return int[]|null
   *   IDs of the children or null.
   */
  public function getChildren($type = 'class', $same_branch = FALSE);

  /**
   * Gets the object ancestors (applicable to classes and similar).
   *
   * @param string $type
   *   Type of references.
   * @param bool $same_branch
   *   Get ancestors from same branch only.
   *
   * @return int[]|null
   *   IDs of the ancestors or null.
   */
  public function getAncestors($type = 'class', $same_branch = FALSE);

  /**
   * Checks if a docblock object exists and return matches.
   *
   * @param array $conditions
   *   Array with conditions to match.
   * @param \Drupal\api\Interfaces\BranchInterface $branch
   *   Branch the element belongs to.
   * @param array|null $range
   *   Pagination information if desired.
   * @param array|null $sort
   *   Sorting information if desired.
   *
   * @return int[]|null
   *   Matching entries IDs or null.
   */
  public static function matches(array $conditions, BranchInterface $branch, array $range = NULL, array $sort = NULL);

  /**
   * Returns list of files for a given branch.
   *
   * @param \Drupal\api\Interfaces\BranchInterface $branch
   *   Branch to search in.
   *
   * @return int[]|null
   *   IDs of the matching entries or null.
   */
  public static function findFiles(BranchInterface $branch);

  /**
   * Search DocBlocks by partial match (or exact) on the title.
   *
   * @param string $term
   *   Term to search in the title.
   * @param \Drupal\api\Interfaces\BranchInterface $branch
   *   Branch where to look.
   * @param int $limit
   *   Number of results.
   * @param bool $exact_match
   *   Do exact title match.
   *
   * @return int[]|null
   *   Matching results.
   */
  public static function searchByTitle($term, BranchInterface $branch = NULL, $limit = 10, $exact_match = FALSE);

  /**
   * Find class matches by namespaced_name in the given branches.
   *
   * @param string $namespaced_name
   *   Namespaced name.
   * @param \Drupal\api\Interfaces\BranchInterface[] $branch_ids
   *   Branch IDs to search in.
   *
   * @return int[]|null
   *   IDs of the matches or null.
   */
  public static function findClassesByNamespacedName($namespaced_name, array $branch_ids);

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
   * Deletes all related Docblock files data to the entity.
   *
   * @param string $entity_type
   *   Type of the entities to delete.
   * @param string $field_name
   *   Name of the field pointing at the DocBlock. Defaults to 'docblock'.
   *
   * @return \Drupal\api\Entity\DocBlock
   *   The called php_branch entity.
   */
  public function deleteRelated($entity_type, $field_name = 'docblock');

  /**
   * Gets a full list of elements belonging to a branch..
   *
   * @param \Drupal\api\Interfaces\BranchInterface $branch
   *   Branch the element belongs to.
   * @param array|null $range
   *   Pagination information if desired.
   *
   * @return int[]|null
   *   Matching entries IDs or null.
   */
  public static function getFullList(BranchInterface $branch, array $range = NULL);

  /**
   * Gets the mainpage DocBlock id of the mainpage element of a branch, if any.
   *
   * @param \Drupal\api\Interfaces\BranchInterface $branch
   *   Branch to check in.
   *
   * @return int|null
   *   Id of the mainpage DocBlock or null.
   */
  public static function getMainpage(BranchInterface $branch);

  /**
   * Gets the different listing types present for a branch.
   *
   * @param \Drupal\api\Interfaces\BranchInterface $branch
   *   Branch to check in.
   *
   * @return array
   *   List of types and whether they are present or not.
   */
  public static function getListingTypes(BranchInterface $branch);

  /**
   * Get a special group list_page_object_type tag per branch.
   *
   * @param string $type
   *   Type of listing.
   * @param \Drupal\api\Interfaces\BranchInterface $branch
   *   Branch object.
   *
   * @return int|null
   *   Matching element or null.
   */
  public static function getGroupListingPage($type, BranchInterface $branch);

  /**
   * Get DocReferences generated from the given object.
   *
   * @param string|array $type
   *   Type (or types) of references to return.
   * @param bool $extending_internal_only
   *   Results that extended internal classes only.
   * @param string $name_match
   *   Match by name instead of ID.
   *
   * @return \Drupal\api\Entity\DocBlock\DocReference[]|null
   *   DocReference objects or null.
   */
  public function getDocReferences($type = '', $extending_internal_only = TRUE, $name_match = NULL);

  /**
   * Get the overrides for a docblock.
   *
   * @return \Drupal\api\Entity\DocBlock\DocOverride[]|null
   *   Overrides or null.
   */
  public function getDocOverrides();

  /**
   * Returns a basic array representation of the object.
   *
   * @return array
   *   Representation of the entity in array format.
   */
  public function toBasicArray();

  /**
   * Get the total number of entities of this custom type.
   *
   * @return int
   *   Total number of entities of this custom type.
   */
  public static function totalCount();

  /**
   * Update or insert new docblock item.
   *
   * @param array $docblocks_array
   *   Fields and values to insert/udpate.
   * @param \Drupal\api\Interfaces\BranchInterface $branch
   *   Branch where this item belongs to.
   *
   * @return bool
   *   Whether everything went as expected or not.
   */
  public static function createOrUpdate(array $docblocks_array, BranchInterface $branch);

  /**
   * Find entries for a given file name and branch.
   *
   * @param string $file_name
   *   Name of the file to check.
   * @param BranchInterface $branch
   *   Branch where the file is located.
   *
   * @return int[]|null
   *   Entities IDs matching the criteria or null.
   */
  public static function findByFileName($file_name, BranchInterface $branch);

  /**
   * Find entries for a given name and type and branch.
   *
   * @param string $name
   *   Name of the object to check.
   * @param string $type
   *   Type of the object.
   * @param BranchInterface $branch
   *   Branch where the object is located.
   *
   * @return int[]|null
   *   Entities IDs matching the criteria or null.
   */
  public static function findByNameAndType($name, $type, BranchInterface $branch);

  /**
   * Find the entry for a given file name and branch with object_type = "file".
   *
   * If multiple entries are found it returns the first one only.
   *
   * @param string $file_name
   *   Name of the file to check.
   * @param BranchInterface $branch
   *   Branch where the file is located.
   *
   * @return int[]|null
   *   Entity ID matching the criteria or null.
   */
  public static function findFileByFileName($file_name, BranchInterface $branch);

  /**
   * Calculates the current best guess as to the ID of a class.
   *
   * @param string $class_name
   *   The namespaced class name to find.
   * @param \Drupal\api\Interfaces\BranchInterface $branch_id
   *   The branch this name was referenced in.
   *
   * @return \Drupal\api\Interfaces\DocBlockInterface|null
   *   The documentation object of a matching class in the current branch, the
   *   core branch for this core compatibility, or another branch in this core
   *   compatibility, or null if no match was found. The match has to be unique
   *   within the scope. If multiple matches are found, null will be returned.
   */
  public static function bestByClassByName($class_name, BranchInterface $branch_id);

  /**
   * Find objects with similar name within the same branch or project branches.
   *
   * @param \Drupal\api\Interfaces\DocBlockInterface $docBlock
   *   DocBlock used as reference.
   * @param bool $same_branch
   *   Check within same branch or other branches.
   *
   * @return int[]|null
   *   IDs of the results of null if nothing found.
   */
  public static function findSimilar(DocBlockInterface $docBlock, $same_branch = TRUE);

}
