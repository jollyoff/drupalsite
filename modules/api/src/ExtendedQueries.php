<?php

namespace Drupal\api;

use Drupal\api\Entity\DocBlock;
use Drupal\api\Interfaces\BranchInterface;
use Drupal\api\Interfaces\DocBlockInterface;
use Drupal\Core\Url;
use Drupal\Core\Link;

/**
 * Class containing more complex queries.
 *
 * Falling back into SQL queries for performance reasons, and query complexity.
 *
 * These queries are usually used within the context of an entity, but can be
 * run in isolation. They use a more traditional query builder approach and the
 * return data is not entities, it's usually objects with a series of defined
 * fields.
 *
 * They might also contain some Drupalisms within their logic and in general, it
 * will be logic not linked directly to any entity but a combination of some.
 */
class ExtendedQueries {

  /**
   * Return the name of the table use in the database per entity.
   *
   * @param string $entity_id
   *   Machine name of the entity.
   *
   * @return string|string[]|null
   *   Single table name if parameter given, all map if no parameter given
   *   or null if nothing found.
   */
  public static function entityToTable($entity_id = NULL) {
    $map = [
      'project' => 'api_project',
      'branch' => 'api_branch',
      'docblock' => 'api_branch_docblock',
      'docblock_file' => 'api_branch_docblock_file',
      'docblock_function' => 'api_branch_docblock_function',
      'docblock_class_member' => 'api_branch_docblock_class_member',
      'docblock_namespace' => 'api_branch_docblock_namespace',
      'docblock_override' => 'api_branch_docblock_override',
      'docblock_reference' => 'api_branch_docblock_reference',
      'docblock_reference_count' => 'api_branch_docblock_reference_count',
      'external_branch' => 'api_external_branch',
      'external_documentation' => 'api_external_branch_documentation',
      'php_branch' => 'api_php_branch',
      'php_documentation' => 'api_php_branch_documentation',
    ];

    if (!is_null($entity_id)) {
      return $map[$entity_id] ?? NULL;
    }

    return $map;
  }

  /**
   * Returns the database connection service object.
   *
   * @return \Drupal\Core\Database\Connection
   *   Database connection object.
   */
  protected static function connection() {
    return \Drupal::service('database');
  }

  /**
   * Loads a bare documentation object, with or without overrides.
   *
   * @param int $id
   *   Documentation ID.
   * @param string $type
   *   Object type.
   * @param bool $with_overrides
   *   Include overrides or not.
   *
   * @return array|null
   *   Documentation object array, or NULL if not found.
   */
  public static function loadExtendedArray($id, $type = '', $with_overrides = FALSE) {
    if (!$id) {
      return NULL;
    }

    if ($with_overrides) {
      return self::loadExtendedWithOverrides($id);
    }

    $docblock_table = self::entityToTable('docblock');
    $function_table = self::entityToTable('docblock_function');
    $file_table = self::entityToTable('docblock_file');
    $branch_table = self::entityToTable('branch');

    $query = self::connection()->select($docblock_table, 'ad');
    $query->fields('ad');
    $query->condition('ad.id', $id);
    if ($type == 'function') {
      $query->leftJoin($function_table, 'afunc', 'afunc.docblock = ad.id');
      $query->fields('afunc', ['signature', 'parameters', 'return_value']);
    }
    elseif ($type == 'file') {
      $query->leftJoin($file_table, 'afile', 'afile.docblock = ad.id');
      $query->fields('afile', ['basename']);
    }
    $query->leftJoin($branch_table, 'b', 'ad.branch = b.id');
    $query->fields('b', ['project', 'core_compatibility']);

    $query->leftJoin($docblock_table, 'adfile', "adfile.file_name = ad.file_name AND adfile.object_type = 'file' AND adfile.branch = ad.branch");
    $query->addField('adfile', 'id', 'file_id');
    $query = $query->range(0, 1);

    $result = $query->execute()->fetchObject();

    return !empty($result) ? (array) $result : NULL;
  }

  /**
   * Loads a documentation object.
   *
   * @param string|int $object_name_or_id
   *   The string object name or integer documentation ID to load.
   * @param \Drupal\api\Interfaces\BranchInterface|null $branch
   *   Branch object. Ignored if $object_name_or_id is an integer.
   * @param string|array|null $object_type
   *   A string type, or array of strings: class, interface, function, etc.
   *   Can be omitted if $object_name_or_id is an integer.
   * @param string|null $file_name
   *   Name of the file the object is in (if needed). Ignored if
   *   $object_name_or_id is an integer.
   *
   * @return object|null
   *   Object with information about the matching documentation, or NULL if it
   *   does not exist.
   */
  public static function loadExtendedWithOverrides($object_name_or_id, BranchInterface $branch = NULL, $object_type = '', $file_name = NULL) {
    if (!is_array($object_type)) {
      $object_type = [$object_type];
    }

    $docblock_table = self::entityToTable('docblock');
    $function_table = self::entityToTable('docblock_function');
    $file_table = self::entityToTable('docblock_file');
    $override_table = self::entityToTable('docblock_override');

    // We don't have a cached value. Prepare the query.
    // Set up the basic query.
    $query = self::connection()->select($docblock_table, 'ad');
    $ao = $query->leftJoin($override_table, 'ao', 'ao.docblock = ad.id');
    $query
      ->fields('ad')
      ->fields($ao, ['documented_in_docblock', 'overrides_docblock']);

    if (is_numeric($object_name_or_id)) {
      // Quick query on just the documentation ID.
      $query->condition('ad.id', $object_name_or_id);
    }
    else {
      $query
        ->condition('ad.object_type', $object_type, 'IN')
        ->condition('ad.branch', $branch->id())
        ->condition('ad.object_name', $object_name_or_id);
      if (!is_null($file_name)) {
        $query->condition('ad.file_name', $file_name);
      }
    }

    // Set up extra fields, depending on the object type.
    if (in_array('function', $object_type)) {
      $afunc = $query->leftJoin($function_table, 'afunc', 'afunc.docblock = ad.id');
      $query->fields($afunc, ['signature', 'parameters', 'return_value']);
    }
    elseif (in_array('file', $object_type)) {
      $afile = $query->leftJoin($file_table, 'afile', 'afile.docblock = ad.id');
      $query->fields($afile, ['basename']);
    }

    // Set up field for the file's doc ID.
    $query->leftJoin($docblock_table, 'adfile', "adfile.file_name = ad.file_name AND adfile.object_type = 'file' AND adfile.branch = ad.branch");
    $query->addField('adfile', 'id', 'file_docblock');

    // Execute the query, and store in cache if it's successful.
    $query = $query->range(0, 1);
    $result = $query->execute();
    $members = [
      'documentation',
      'parameters',
      'return_value',
      'see',
      'deprecated',
      'throws',
      'var',
    ];
    foreach ($result as $result_object) {
      // Grab documentation from documented parent.
      if (!empty($result_object->documented_in_docblock) && $result_object->documented_in_docblock !== $result_object->id) {
        $documented_object = self::loadExtendedWithOverrides((int) $result_object->documented_in_docblock, $branch, $object_type);
        if (isset($documented_object)) {
          foreach ($members as $member) {
            if (isset($documented_object->$member)) {
              $result_object->$member = $documented_object->$member;
            }
          }
        }
      }

      return $result_object;
    }

    return NULL;
  }

  /**
   * Finds matches for an object name in a branch.
   *
   * @param string $name
   *   Name to match (text found in the code or documentation).
   * @param string $namespaced_name
   *   Fully namespaced name to look for. Only used when $type is 'function',
   *   'function_or_constant', or 'class'. In these cases, the function tries to
   *   find matches of the namespaced name first, and if that fails, then it
   *   tries again with the plain name.
   * @param string $type
   *   Type of object to match (see api_link_name() for options).
   * @param \Drupal\api\Interfaces\BranchInterface|null $branch
   *   Object representing the branch to search. If NULL, use core compatibility
   *   instead.
   * @param string $core_compatibility
   *   If $branch is NULL, search all branches with this core compatibility.
   * @param array|null $external_branch_ids
   *   If set, instead of looking in core branches, look in API reference
   *   branches that have an ID in this array.
   *
   * @return array
   *   With results and additional information about the matches.
   */
  public static function findMatchesAdvanced($name, $namespaced_name, $type, BranchInterface $branch = NULL, $core_compatibility = '', $external_branch_ids = NULL) {
    $connection = self::connection();
    $branch_table = self::entityToTable('branch');
    $docblock_table = self::entityToTable('docblock');
    $external_documentation_table = self::entityToTable('external_documentation');
    $docblock_reference_table = self::entityToTable('docblock_reference');
    $docblock_file_table = self::entityToTable('docblock_file');

    // Build a query to find the matches.
    if ($external_branch_ids) {
      // This will not work for 'yaml_string' references.
      if ($type == 'yaml_string') {
        return [];
      }
      $using_external_branch = TRUE;
      $query = $connection->select($external_documentation_table, 'ad')
        ->fields('ad')
        ->condition('ad.external_branch', $external_branch_ids, 'IN');
    }
    else {
      $using_external_branch = FALSE;
      $query = $connection->select($docblock_table, 'ad')
        ->fields('ad', [
          'id',
          'branch',
          'object_name',
          'title',
          'object_type',
          'summary',
          'file_name',
        ]);
      $query->innerJoin($branch_table, 'b', 'ad.branch = b.id');
      $query->fields('b', ['slug', 'preferred', 'project']);
      if ($branch) {
        $query->condition('ad.branch', $branch->id());
      }
      else {
        $query->condition('b.core_compatibility', $core_compatibility);
      }
    }

    // Deal with namespaces and annotations.
    if ($type == 'annotation') {
      // We are looking for an annotation class.
      $query->innerJoin($docblock_reference_table, 'r_annotation', 'ad.id = r_annotation.docblock');
      $query->condition('r_annotation.object_type', 'annotation_class');
      $query->condition('ad.object_type', 'class');
    }

    $search_name_field = NULL;
    $original_name = '';
    if (in_array($type, ['function', 'function_or_constant', 'class'])) {
      $original_name = $name;
      $name = $namespaced_name;
      $query->addField('ad', 'namespaced_name', 'match_name');
      $match_name_field = 'ad.namespaced_name';
      $query->addField('ad', 'object_name', 'search_name');
      $search_name_field = 'ad.object_name';
    }
    elseif ($type != 'theme' && $type != 'file' && $type != 'yaml_string' && $type != 'element') {
      $query->addField('ad', 'object_name', 'match_name');
      $match_name_field = 'ad.object_name';
      $search_name_field = NULL;
    }

    // Figure out what potential names we should match on.
    $potential_names = [$name];
    $prefer_shorter = FALSE;
    $prefer_earlier = FALSE;

    if ($type == 'hook') {
      $potential_names = [
        'hook_' . $name,
        'hook_entity_' . $name,
        'hook_entity_bundle_' . $name,
        'hook_field_' . $name,
        'field_default_' . $name,
        'hook_user_' . $name,
        'hook_node_' . $name,
      ];
      $prefer_earlier = TRUE;
      $query->condition('ad.object_type', 'function');
    }
    elseif ($type == 'fieldhook') {
      $potential_names = [
        'hook_field_' . $name,
        'field_default_' . $name,
      ];
      $prefer_earlier = TRUE;
      $query->condition('ad.object_type', 'function');
    }
    elseif ($type == 'entityhook') {
      $potential_names = [
        'hook_entity_' . $name,
        'hook_entity_bundle_' . $name,
        'hook_node_' . $name,
      ];
      $prefer_earlier = TRUE;
      $query->condition('ad.object_type', 'function');
    }
    elseif ($type == 'userhook') {
      $potential_names = [
        'hook_user_' . $name,
      ];
      $prefer_earlier = TRUE;
      $query->condition('ad.object_type', 'function');
    }
    elseif ($type == 'alter hook') {
      $potential_names = ['hook_' . $name . '_alter'];
      $query->condition('ad.object_type', 'function');
    }
    elseif ($type == 'theme') {
      $potential_names = [];
      // Potential matches are the whole theme call, or with stripped off pieces
      // separated by __. And we look for template files preferably over
      // functions.
      $prefer_shorter = TRUE;
      $hook_elements = explode('__', $name);
      while (count($hook_elements) > 0) {
        $hook = implode('__', $hook_elements);
        $potential_names[] = str_replace('_', '-', $hook) . '.html.twig';
        $potential_names[] = str_replace('_', '-', $hook) . '.tpl.php';
        $potential_names[] = 'theme_' . $hook;
        array_pop($hook_elements);
      }
      // Because this needs to match theme files, match on object title (which
      // is the file base name).
      $query->condition('ad.object_type', ['file', 'function'], 'IN');
      $query->addField('ad', 'title', 'match_name');
      $match_name_field = 'ad.title';
    }
    elseif ($type == 'element') {
      // The string is the machine name of an element, and we want to link
      // to the element class.
      $query->innerJoin($docblock_reference_table, 'ars', "ad.id = ars.docblock AND ars.object_type = 'element'");
      $query->addField('ars', 'object_name', 'match_name');
      $match_name_field = 'ars.object_name';
      $search_name_field = 'none';
    }
    elseif ($type == 'function') {
      $query->condition('ad.object_type', 'function');
    }
    elseif ($type == 'service') {
      $query->condition('ad.object_type', 'service');
    }
    elseif ($type == 'global') {
      $query->condition('ad.object_type', 'global');
    }
    elseif ($type == 'function_or_constant') {
      $query->condition('ad.object_type', [
        'function',
        'constant',
        'class',
        'interface',
        'trait',
      ], 'IN');
    }
    elseif ($type == 'file') {
      // For files other than HTML type, the title is the basename of the file.
      // For HTML files, the title is taken from the HTML title element. So,
      // if we are matching in an API reference branch, try to match on the
      // title field, which is what we have. If we are matching in a regular
      // branch, join to the file table and match on the basename field.
      if ($using_external_branch) {
        $query->condition('ad.object_type', 'file');
        $query->addField('ad', 'title', 'match_name');
        $match_name_field = 'ad.title';
      }
      else {
        $query->leftJoin($docblock_file_table, 'af', 'ad.id = af.docblock');
        $query->addField('af', 'basename', 'match_name');
        $match_name_field = 'af.basename';
      }
    }
    elseif ($type == 'constant') {
      $query->condition('ad.object_type', 'constant');
    }
    elseif ($type == 'class') {
      $query->condition('ad.object_type', ['class', 'interface', 'trait'], 'IN');
    }
    elseif ($type == 'group') {
      $query->condition('ad.object_type', 'group');
    }
    elseif ($type == 'yaml_string') {
      // This is a bit of a different case. Here, we have a string and we are
      // trying to see if it was defined as a top-level key in a YAML services
      // or routing file. This would be stored in the {api_reference_storage}
      // table.
      $query->innerJoin($docblock_reference_table, 'ar', 'ad.id = ar.docblock');
      $query->addField('ar', 'object_name', 'match_name');
      $query->condition('ar.object_type', 'yaml string');
      $match_name_field = 'ar.object_name';
      $search_name_field = NULL;
    }

    // Execute the query and make an array of matches, making sure to only
    // keep the highest-priority matches.
    $query->condition($match_name_field, $potential_names, 'IN');

    $results = $query->execute()->fetchAll();
    return [
      'results' => $results,
      'potential_names' => $potential_names,
      'search_name_field' => $search_name_field,
      'prefer_shorter' => $prefer_shorter,
      'prefer_earlier' => $prefer_earlier,
      'using_external_branch' => $using_external_branch,
      'original_name' => $original_name,
    ];
  }

  /**
   * Finds matches for a class and member names given.
   *
   * @param string $class_name
   *   Name of the class.
   * @param string $member_name
   *   Name of the member.
   * @param \Drupal\api\Interfaces\BranchInterface|null $branch
   *   Branch object.
   * @param string $core_compatibility
   *   Core compatibility if branch is not given.
   *
   * @return object[]|null
   *   Resulting records.
   */
  public static function findMatchingMembersAdvanced($class_name, $member_name, BranchInterface $branch = NULL, $core_compatibility) {
    $connection = self::connection();
    $branch_table = self::entityToTable('branch');
    $docblock_table = self::entityToTable('docblock');
    $docblock_class_member_table = self::entityToTable('docblock_class_member');

    // Make a query to find the class.
    $query = $connection->select($docblock_table, 'ad_class');
    $query->innerJoin($branch_table, 'b', 'ad_class.branch = b.id');
    $query->fields('b', ['slug', 'preferred', 'project']);
    if ($branch) {
      $query->condition('ad_class.branch', $branch->id());
    }
    else {
      $query->condition('b.core_compatibility', $core_compatibility);
    }
    $query->condition('ad_class.namespaced_name', $class_name);

    // Join to find the members.
    $query->innerJoin($docblock_class_member_table, 'am', 'ad_class.id = am.class_docblock');

    // Join to find info about the member name.
    $query->innerJoin($docblock_table, 'ad_member', 'am.docblock = ad_member.id');

    $condition = $connection->condition('OR')
      ->condition('am.member_alias', $member_name)
      ->condition('ad_member.member_name', $member_name);
    $query->condition($condition);

    $query->addField('am', 'member_alias', 'member1');
    $query->addField('ad_member', 'member_name', 'member2');
    $query->addField('ad_class', 'namespaced_name', 'classname');
    $query->fields('ad_member', [
      'id',
      'branch',
      'object_name',
      'title',
      'object_type',
      'summary',
      'file_name',
    ]);

    return $query->execute()->fetchAll();
  }

  /**
   * Gets an array of all the plain functions belonging to a branch.
   *
   * @param \Drupal\api\Interfaces\BranchInterface $branch
   *   Branch to check data from.
   *
   * @return array
   *   Function dump information.
   */
  public static function getFunctionDumpByBranch(BranchInterface $branch) {
    $docblock_table = self::entityToTable('docblock');
    $functions_table = self::entityToTable('docblock_function');

    $query = self::connection()->select($docblock_table, 'd')
      ->fields('d', ['summary'])
      ->orderBy('d.title');
    $query->innerJoin($functions_table, 'f', 'd.id = f.docblock');
    $query->fields('f', ['signature']);
    $query->condition('d.branch', $branch->id())
      ->condition('d.class', 0)
      ->condition('d.object_type', 'function');
    $result = $query->execute();

    $output = [];
    foreach ($result as $object) {
      // Make sure the summary is free of HTML tags and newlines.
      $summary = $object->summary;
      $summary = strip_tags($summary);
      $summary = preg_replace('|\s+|', ' ', $summary);

      $output[] = [
        'signature' => $object->signature,
        'summary' => $summary,
      ];
    }

    return $output;
  }

  /**
   * Calculates all new reference counts for a given branch.
   *
   * @param \Drupal\api\Interfaces\BranchInterface $branch
   *   Branch to check counts.
   */
  public static function calculateReferenceCounts(BranchInterface $branch) {
    // Creating thousands of entities will take a long time and this table can
    // (and should) be recreated at any time without too much cost. Also,
    // making the calculations, joins, etc via entities will not scale.
    $connection = self::connection();
    $reference_count_table = self::entityToTable('docblock_reference_count');
    $namespace_table = self::entityToTable('docblock_namespace');
    $reference_table = self::entityToTable('docblock_reference');
    $override_table = self::entityToTable('docblock_override');
    $docblock_table = self::entityToTable('docblock');

    // Remove existing reference counts for this branch.
    $connection->delete($reference_count_table)
      ->condition('branch', $branch->id())
      ->execute();

    // Calculate use counts for classes, with fully-qualified namespaced class
    // name. The entries in $namespace_table have the namespace declarations,
    // which are missing the initial \ character.
    $select = $connection->select($namespace_table, 'an');
    $select->leftJoin($docblock_table, 'ad', 'an.docblock = ad.id');
    $select->addExpression("CONCAT('\\\\', an.class_name)", 'object_name');
    $select->addExpression($branch->id(), 'branch');
    $select->addExpression("'use'", 'reference_type');
    $select->addExpression('COUNT(*)', 'reference_count');
    $select->addExpression('UUID()', 'uuid');
    $select
      ->condition('an.object_type', 'use_alias')
      ->condition('ad.branch', $branch->id())
      ->groupBy('an.class_name');
    $connection->insert($reference_count_table)
      ->from($select)
      ->execute();

    // Calculate call counts for functions and methods, including usage of
    // constants, and extend/implements for classes/interfaces. For simple
    // functions, these will not have namespaces on them. For class members and
    // extend/implements of classes, they will have namespaces.
    $select = $connection->select($reference_table, 'ars');
    $select->addField('ars', 'object_name', 'object_name');
    $select->addExpression($branch->id(), 'branch');
    $select->addExpression("'call'", 'reference_type');
    $select->addExpression('COUNT(*)', 'reference_count');
    $select->addExpression('UUID()', 'uuid');
    $select
      ->condition('ars.object_type', [
        'function',
        'constant',
        'member-class',
        'computed-member',
        'class',
        'interface',
      ], 'IN')
      ->condition('ars.branch', $branch->id())
      ->groupBy('ars.object_name');
    $connection->insert($reference_count_table)
      ->from($select)
      ->execute();

    // Calculate string reference counts. Some strings have namespaces, and some
    // don't, similar to the 'call' reference count above.
    $select = $connection->select($reference_table, 'ars');
    $select->addField('ars', 'object_name', 'object_name');
    $select->addExpression($branch->id(), 'branch');
    $select->addExpression("'string'", 'reference_type');
    $select->addExpression('COUNT(*)', 'reference_count');
    $select->addExpression('UUID()', 'uuid');
    $select
      ->condition('ars.object_type', [
        'potential callback',
        'potential file',
        'service_class',
        'yaml string',
      ], 'IN')
      ->condition('ars.branch', $branch->id())
      ->groupBy('ars.object_name');
    $connection->insert($reference_count_table)
      ->from($select)
      ->execute();

    // Calculate override counts for methods and other class members. For
    // disambiguation, store the namespaced name of the class::member.
    $select = $connection->select($override_table, 'ao');
    $select->leftJoin($docblock_table, 'ad', 'ao.docblock = ad.id');
    $select->leftJoin($docblock_table, 'ado', 'ao.overrides_docblock = ado.id');
    // Object name is the name of the overridden method.
    $select->addExpression('ado.namespaced_name', 'object_name');
    $select->addExpression($branch->id(), 'branch');
    $select->addExpression("'override'", 'reference_type');
    $select->addExpression('COUNT(*)', 'reference_count');
    $select->addExpression('UUID()', 'uuid');
    $select
      // Some records in $override_table are just about where the
      // documentation is. Filter these out.
      ->condition('ao.overrides_docblock', 0, '<>')
      // Some overrides may be in other branches. Filter these out too.
      ->condition('ad.branch', $branch->id())
      ->condition('ado.branch', $branch->id())
      ->groupBy('ado.object_name');
    $connection->insert($reference_count_table)
      ->from($select)
      ->execute();
  }

  /**
   * Updates calculated references based on the member information given.
   *
   * @param array $member_info
   *   Member information.
   * @param int $class_id
   *   ID of the class.
   */
  public static function updateCalculatedReferences(array $member_info, $class_id) {
    $connection = self::connection();
    $reference_table = self::entityToTable('docblock_reference');
    $docblock_table = self::entityToTable('docblock');
    $class_members_table = self::entityToTable('docblock_class_member');

    // Update the calculated member reference storage for this class, first
    // deleting any existing computed-member entries. These are for when
    // you have self::foo() or parent::bar() calls inside a method. We will
    // delete all computed-member entries, and recalculate the self:: ones here;
    // the parent:: ones are recalculated in api_shutdown() because we do not
    // necessarily have the parent class member information in the database
    // at this point.
    if (isset($member_info[$class_id]) && count($member_info[$class_id]['function'])) {
      // Delete all previous computed-member entries for calls from direct
      // methods of this class.
      $direct_methods = array_values($member_info[$class_id]['function']);
      $connection->delete($reference_table)
        ->condition('object_type', 'computed-member')
        ->condition('docblock', $direct_methods, 'IN')
        ->execute();

      // Calculate computed-member entries for member-self references. We're
      // taking an entry that says something like ThisClass::foo() calls
      // self::bar(), and trying to calculate the fully namespaced name of
      // self::bar(), which might be ThisClass::bar() or SomeParentClass::bar().
      $select = $connection->select($reference_table, 'r')
        ->condition('r.object_type', 'member-self')
        ->condition('r.docblock', $direct_methods, 'IN');
      // This joins to the documentation record of the calling method.
      $select->innerJoin($docblock_table, 'd', 'r.docblock = d.id');
      // This links to the now-updated member list of the class the calling
      // method is in.
      $select->innerJoin($class_members_table, 'm', 'd.class = m.class_docblock');
      // We're looking for a method whose name is r.object_name. It is either:
      // - m.member_alias (if it came from a trait with an alias)
      // - m.member_alias is NULL and it's the member name in the member's
      //   documentation record.
      $select->innerJoin($docblock_table, 'dm', 'm.docblock = dm.id');
      $and = $connection->condition('AND')
        ->where('dm.member_name = r.object_name')
        ->isNull('m.member_alias');
      $or = $connection->condition('OR')
        ->condition($and)
        ->where('m.member_alias = r.object_name');
      $select->condition($or);
      $select->condition('dm.object_type', 'function');

      // Now make up the needed fields for the api_branch_docblock_reference
      // records, and insert them into the table.
      $select->distinct();
      $select->addField('dm', 'namespaced_name', 'object_name');
      $select->addField('r', 'branch', 'branch');
      $select->addField('r', 'docblock', 'docblock');
      // Note: SelectQuery adds expressions to the query at the end of the field
      // list.
      $select->addExpression("'computed-member'", 'object_type');
      $select->addExpression('UUID()', 'uuid');

      $connection->insert($reference_table)
        ->fields(['object_name', 'branch', 'docblock', 'object_type', 'uuid'])
        ->from($select)
        ->execute();
    }
  }

  /**
   * Enters new records based on a complex query of direct methods.
   *
   * @param array $direct_methods
   *   List of direct methods.
   */
  public static function createNewReferences(array $direct_methods) {
    $connection = self::connection();
    $reference_table = self::entityToTable('docblock_reference');
    $docblock_table = self::entityToTable('docblock');
    $class_members_table = self::entityToTable('docblock_class_member');

    $select = $connection->select($reference_table, 'r')
      ->condition('r.object_type', 'member-parent')
      ->condition('r.docblock', $direct_methods, 'IN');
    // This joins to the documentation record of the calling method.
    $select->innerJoin($docblock_table, 'cd', 'r.docblock = cd.id');
    // This finds the parent (extends) class record.
    $select->innerJoin($reference_table, 'e', 'e.docblock = cd.class');
    $select
      ->condition('e.object_type', 'class')
      ->isNotNull('e.extends_docblock');
    // This finds the members of the parent class.
    $select->innerJoin($class_members_table, 'pm', 'pm.class_docblock = e.extends_docblock');
    // We're looking for a method whose name is r.object_name. It is either:
    // - pm.member_alias (if it came from a trait with an alias)
    // - pm.member_alias is NULL and it's the member name in the member's
    //   documentation record.
    $select->innerJoin($docblock_table, 'dm', 'pm.id = dm.id');
    $and = $connection->condition('AND')
      ->where('dm.member_name = r.object_name')
      ->isNull('pm.member_alias');
    $and2 = $connection->condition('AND')
      ->where('pm.member_alias = r.object_name')
      ->isNotNull('pm.member_alias');
    $or = $connection->condition('OR')
      ->condition($and)
      ->condition($and2);
    $select->condition($or);
    $select->condition('dm.object_type', 'function');

    // Now make up the needed fields for the api_reference_storage records,
    // and insert them into the table.
    $select->distinct();
    $select->addField('dm', 'namespaced_name', 'object_name');
    $select->addField('r', 'branch', 'branch');
    $select->addField('r', 'docblock', 'docblock');
    // Note: SelectQuery adds expressions to the query at the end of the field
    // list.
    $select->addExpression("'computed-member'", 'object_type');
    $select->addExpression('UUID()', 'uuid');

    $connection->insert($reference_table)
      ->fields(['object_name', 'branch', 'docblock', 'object_type', 'uuid'])
      ->from($select)
      ->execute();
  }

  /**
   * Counts or lists references to a function or file.
   *
   * @param \Drupal\api\Interfaces\DocBlockInterface $docBlock
   *   DocBlock object.
   * @param \Drupal\api\Interfaces\BranchInterface $branch
   *   Object representing the branch to check.
   * @param string $type
   *   Type of reference. One of:
   *   - 'calls': $name is the name of a function, find function calls.
   *   - 'implementations': $name is the name of a hook, find implementations.
   *   - 'invokes': $name is the name of a hook, find invocations.
   *   - 'theme_invokes': $name is the name of a theme function or file, find
   *     theme calls.
   *   - 'element_invokes': $name is the name of an element, find #type uses of
   *     this element.
   *   - 'theme_references': $name is the name of a theme function or file, find
   *     string references to the theme hook name.
   *   - 'references': $name is the name of a function, find string references.
   *   - 'overrides': $exclude_id is the ID of a method, find overriding ones.
   *   - 'constants': $name is the name of a constant, find uses.
   *   - 'uses': $name is the fully-namespaced name of a class, find files that
   *     have use declarations.
   *   - 'annotations': $exclude_id is the ID of an annotation class. Find
   *     classes using it as annotation.
   *   - 'yml_config': $name is a YML config file name, find string references
   *     to the file name without .yml.
   *   - 'yml_keys': $name is a YML config file name, and $exclude_id is its
   *     documentation ID. Find references to keys in this file.
   *   - 'services': $name is a class, find services that reference it.
   *   - 'use': $name is a service, find functions that reference its name.
   * @param bool $count
   *   If TRUE (default), return a count. If FALSE, return an array.
   * @param int|null $exclude_id
   *   (optional) Document ID to exclude from the query, or to use as the ID for
   *   overrides and element_invokes.
   * @param int $limit
   *   (optional) Limit the number of references returned to this amount.
   * @param bool $is_drupal
   *   (optional) If set to FALSE, and $type is a Drupal-specific type (related
   *   to themes, hooks, or YAML), just return an empty list or zero count.
   *
   * @return int|array
   *   The number of references or an array of references.
   */
  public static function findReferences(DocBlockInterface $docBlock, BranchInterface $branch, $type, $count = TRUE, $exclude_id = 0, $limit = 0, $is_drupal = TRUE) {
    // Early return: if this is a Drupal type and $is_drupal is FALSE.
    $drupal_types = [
      'implementations',
      'theme_invokes',
      'element_invokes',
      'theme_references',
      'invokes',
      'yml_config',
      'yml_keys',
    ];
    if (!$is_drupal && in_array($type, $drupal_types)) {
      return ($count) ? 0 : [];
    }

    $name = ($docBlock->getClass() && ($type == 'calls' || $type == 'constants')) ?
      $docBlock->getNamespacedName() :
      $docBlock->getTitle();
    // In a few cases, we want to look for either the base name or the
    // namespaced name.
    $altname = $docBlock->getNamespacedName();
    if ($type == 'uses' || $type == 'services') {
      $name = $altname;
    }

    $references_type = 'potential callback';
    $group = FALSE;
    if ($type == 'yml_config') {
      // Verify this is a *.yml file. If not, just return.
      if (strpos($name, '.yml') != strlen($name) - 4) {
        return ($count) ? 0 : [];
      }
      // Strip off the .yml and look for string references.
      $name = substr($name, 0, strlen($name) - 4);
      $type = 'references';
      $references_type = 'potential file';
    }

    $connection = self::connection();
    $docblock_table = self::entityToTable('docblock');
    $docblock_reference_table = self::entityToTable('docblock_reference');
    $docblock_override_table = self::entityToTable('docblock_override');
    $docblock_namespace_table = self::entityToTable('docblock_namespace');

    $matches = [];
    $branch_id = $branch->id();
    $base_query = NULL;

    if ($type == 'calls' || $type == 'constants') {
      // Use reference storage to find functions that call this one, or
      // use this constant.
      $base_query = $connection->select($docblock_reference_table, 'r');
      $d = $base_query->leftJoin($docblock_table, 'd', 'r.docblock = d.id');
      $base_query->condition('d.branch', $branch_id)
        ->condition('d.id', $exclude_id, '<>')
        ->condition('r.object_type', [
          'function',
          'constant',
          'member-class',
          'computed-member',
        ], 'IN')
        ->condition('r.object_name', $name);
    }
    elseif ($type == 'overrides') {
      // Use overrides storage to find overrides of the ID passed as "exclude".
      $base_query = $connection->select($docblock_override_table, 'o');
      $d = $base_query->leftJoin($docblock_table, 'd', 'o.docblock = d.id');
      $base_query->condition('d.branch', $branch_id)
        ->condition('o.overrides_docblock', $exclude_id);
    }
    elseif ($type == 'references') {
      // Use reference storage to find functions that use this name as a string.
      $base_query = $connection->select($docblock_reference_table, 'r');
      $d = $base_query->innerJoin($docblock_table, 'd', 'r.docblock = d.id');
      $base_query->condition($d . '.branch', $branch_id)
        ->condition($d . '.id', $exclude_id, '<>')
        ->condition('r.object_type', $references_type);
      if (isset($altname)) {
        $base_query->condition('r.object_name', [$name, $altname], 'IN');
      }
      else {
        $base_query->condition('r.object_name', $name);
      }
    }
    elseif ($type == 'use') {
      // Use reference storage to find functions that reference a service. It
      // could have a . in it, so it is either stored as 'potential file' or
      // 'potential callback'.
      $base_query = $connection->select($docblock_reference_table, 'r');
      $d = $base_query->innerJoin($docblock_table, 'd', 'r.docblock = d.id');
      $base_query->condition($d . '.branch', $branch_id)
        ->condition($d . '.id', $exclude_id, '<>')
        ->condition('r.object_type', ['potential file', 'potential callback'], 'IN');
      $base_query->condition('r.object_name', $name);
    }
    if ($type == 'services') {
      // Use reference storage to find services using this class.
      $base_query = $connection->select($docblock_reference_table, 'r');
      $d = $base_query->leftJoin($docblock_table, 'd', 'r.docblock = d.id');
      $base_query->condition('d.branch', $branch_id)
        ->condition('d.id', $exclude_id, '<>')
        ->condition('r.object_type', ['service_class'], 'IN')
        ->condition('r.object_name', $name);
    }
    elseif ($type == 'annotations') {
      // In this case, $exclude_id is the documentation ID of a class,
      // presumably with an 'annotation_class' reference in the table. Find
      // other classes that annotated with this same class name, which will
      // have 'annotation' references.
      $base_query = $connection->select($docblock_reference_table, 'r_annotation')
        ->condition('r_annotation.docblock', $exclude_id)
        ->condition('r_annotation.object_type', 'annotation_class');
      $base_query->innerJoin($docblock_reference_table, 'r_uses', "r_annotation.object_name = r_uses.object_name AND r_uses.object_type = 'annotation'");
      $base_query->innerJoin($docblock_table, 'd', 'r_uses.docblock = d.id');
      $base_query
        ->condition('d.branch', $branch_id);
      $group = TRUE;
    }
    elseif ($type == 'yml_keys') {
      // In this case, $exclude_id is the documentation ID of a file. We
      // want to find the string keys that file has in reference storage, and
      // then match those with 'potential callback' or 'potential file'
      // references.
      $base_query = $connection->select($docblock_reference_table, 'r');
      $base_query->innerJoin($docblock_table, 'd', 'r.docblock = d.id');
      $base_query
        ->condition('d.branch', $branch_id)
        ->condition('r.object_type', ['potential callback', 'potential file'], 'IN');
      $base_query->innerJoin($docblock_reference_table, 'rstrings', 'r.object_name = rstrings.object_name');
      $base_query
        ->condition('rstrings.docblock', $exclude_id)
        ->condition('rstrings.object_type', 'yaml string');
      // A given function/method might have multiple matching YML keys, so we
      // need to group this query.
      $group = TRUE;
    }
    elseif ($type == 'uses') {
      // Use namespace storage to find files with use declarations for this
      // class. Note that $name probably starts with a backslash, while use
      // declarations do not.
      $name = substr(Formatter::fullClassname($name), 1);
      $base_query = $connection->select($docblock_namespace_table, 'n');
      $d = $base_query->leftJoin($docblock_table, 'd', 'n.docblock = d.id');
      $base_query->condition('d.branch', $branch_id)
        ->condition('d.id', $exclude_id, '<>')
        ->condition('n.object_type', 'use_alias')
        ->condition('n.class_name', $name);
    }
    elseif ($type == 'implementations') {
      // Use pattern matching to find functions that look like implementations
      // of this one. i.e. something_hookname, where "something" doesn't start
      // with an underscore. Note that LIKE uses _ as "match any one character",
      // so _ has to be escaped in this query. Limit this to Drupal functions.
      if (strpos($name, 'hook_') === 0) {
        $hook_name = substr($name, 5);
        // If the hook has an UPPER_CASE_SECTION, allow that to match anything,
        // for cases like hook_update_N() and hook_form_FORM_ID_alter(). Note
        // that the upper-case section could be in the middle or end.
        $hook_name = preg_replace('/_[A-Z][A-Z_]*_/', '\_%\_', $hook_name);
        $hook_name = preg_replace('/_[A-Z][A-Z_]*$/', '\_%', $hook_name);
        $base_query = $connection->select($docblock_table, 'd')
          ->condition('d.object_name', '%\_' . $hook_name, 'LIKE')
          ->condition('d.object_name', '\_%', 'NOT LIKE')
          ->condition('d.object_name', 'hook\_%', 'NOT LIKE')
          ->condition('d.object_type', 'function')
          ->condition('d.branch', $branch_id)
          ->condition('d.id', $exclude_id, '<>')
          ->condition('d.is_drupal', 1);
      }
    }
    elseif ($type == 'theme_invokes' || $type == 'theme_references') {
      // Use reference storage to find functions that call this theme, or
      // string references to this theme. Limit this to Drupal functions on the
      // calls.
      $theme_name = '';
      if (strpos($name, 'theme_') === 0) {
        // It's presumably a theme function.
        $theme_name = substr($name, 6);
      }
      elseif (strpos($name, '.tpl.php') == strlen($name) - 8) {
        // It's presumably a theme template file.
        $name = basename($name);
        $theme_name = str_replace('-', '_', substr($name, 0, strlen($name) - 8));
      }
      elseif (preg_match('|^(.*)\.[^.]*\.twig$|', $name, $matches)) {
        // It's a *.*.twig file.
        $name = basename($name);
        $theme_name = str_replace('-', '_', basename($matches[1]));
      }

      if (strlen($theme_name) > 0) {
        // We could get calls to things like theme('name') or theme('name__sub')
        // (or the corresponding strings) recorded in the references. Match
        // either. Note _ escaping for LIKE queries.
        $base_query = $connection->select($docblock_reference_table, 'r');
        $d = $base_query->innerJoin($docblock_table, 'd', 'r.docblock = d.id');
        $base_query->condition($d . '.branch', $branch_id)
          ->condition($d . '.id', $exclude_id, '<>')
          ->condition('d.is_drupal', 1)
          ->condition(
            $connection->condition('OR')
              ->condition('r.object_name', $theme_name)
              ->condition('r.object_name', $theme_name . '\_\_%', 'LIKE')
          );
        if ($type == 'theme_invokes') {
          $base_query->condition('r.object_type', 'potential theme');
        }
        else {
          $base_query->condition('r.object_type', 'potential callback');
        }
      }
    }
    elseif ($type == 'invokes') {
      // Use reference storage to find functions that invoke this hook.
      // The reference storage holds the string that was actually found inside
      // the invoking function. So $name could be one of:
      // - "hook_$string",
      // - "hook_field_$string"
      // - "field_default_$string"
      // - "hook_user_$string"
      // - "hook_entity_$string"
      // - "hook_entity_bundle_$string"
      // - "hook_node_$string"
      // - "hook_$string_alter"
      // For these, {reference_storage} has object_type of 'potential hook',
      // 'potential fieldhook', etc. So, we need to build a query that will find
      // these matches between $string (field object_name) and $name (passed
      // into this function). And only do this for Drupal functions.
      $or_clauses = $connection->condition('OR');
      $found = FALSE;

      if (strpos($name, 'hook_') === 0) {
        $hook_name = substr($name, 5);
        $or_clauses->condition(
          $connection->condition('AND')
            ->condition('r.object_type', 'potential hook')
            ->condition('r.object_name', $hook_name)
        );
        $found = TRUE;
        if (strpos($hook_name, 'user_') === 0) {
          $sub_hook_name = substr($hook_name, 5);
          $or_clauses->condition(
            $connection->condition('AND')
              ->condition('r.object_type', 'potential userhook')
              ->condition('r.object_name', $sub_hook_name)
          );
        }
        elseif (strpos($hook_name, 'entity_') === 0) {
          $sub_hook_name = substr($hook_name, 7);
          $or_clauses->condition(
            $connection->condition('AND')
              ->condition('r.object_type', 'potential entityhook')
              ->condition('r.object_name', $sub_hook_name)
          );
          if (strpos($sub_hook_name, 'bundle_') === 0) {
            $sub_sub_hook_name = substr($sub_hook_name, 7);
            $or_clauses->condition(
              $connection->condition('AND')
                ->condition('r.object_type', 'potential entityhook')
                ->condition('r.object_name', $sub_sub_hook_name)
            );
          }
        }
        elseif (strpos($hook_name, 'field_') === 0) {
          $sub_hook_name = substr($hook_name, 6);
          $or_clauses->condition(
            $connection->condition('AND')
              ->condition('r.object_type', 'potential fieldhook')
              ->condition('r.object_name', $sub_hook_name)
          );
        }
        elseif (strpos($hook_name, 'node_') === 0) {
          $sub_hook_name = substr($hook_name, 5);
          $or_clauses->condition(
            $connection->condition('AND')
              ->condition('r.object_type', 'potential entityhook')
              ->condition('r.object_name', $sub_hook_name)
          );
        }
        elseif (strrpos($hook_name, '_alter') === strlen($hook_name) - 6) {
          $sub_hook_name = substr($hook_name, 0, strlen($hook_name) - 6);
          $or_clauses->condition(
            $connection->condition('AND')
              ->condition('r.object_type', 'potential alter')
              ->condition('r.object_name', $sub_hook_name)
          );
        }
      }
      elseif (strpos($name, 'field_default_') === 0) {
        $hook_name = substr($name, 14);
        $or_clauses->condition(
          $connection->condition('AND')
            ->condition('r.object_type', 'potential fieldhook')
            ->condition('r.object_name', $hook_name)
        );
        $found = TRUE;
      }

      // If we found at least one match, run this query we've built.
      if ($found) {
        $base_query = $connection->select($docblock_reference_table, 'r');
        $d = $base_query->innerJoin($docblock_table, 'd', 'r.docblock = d.id');
        $base_query->condition('d.branch', $branch_id)
          ->condition('d.id', $exclude_id, '<>')
          ->condition('d.is_drupal', 1)
          ->condition($or_clauses);
      }
    }
    elseif ($type == 'element_invokes') {
      // $exclude_id is the ID of a potential element class. This must have
      // an 'element' reference in reference storage. Then we're looking for
      // 'potential element' references to that element machine name.
      $base_query = $connection->select($docblock_reference_table, 'r')
        ->condition('r.docblock', $exclude_id)
        ->condition('r.object_type', 'element');
      $base_query->innerJoin($docblock_reference_table, 'r2', 'r.object_name = r2.object_name AND r.branch = r2.branch');
      $base_query->condition('r2.object_type', 'potential element');
      $d = $base_query->innerJoin($docblock_table, 'd', 'r2.docblock = d.id');
    }

    // See if we built a query to execute.
    if (is_null($base_query)) {
      return ($count) ? 0 : [];
    }

    // Execute the query.
    if ($count) {
      // We're looking for a count here.
      if ($group) {
        $base_query->addExpression('COUNT(DISTINCT d.id)', 'num');
      }
      else {
        $base_query->addExpression('COUNT(d.id)', 'num');
      }
      return $base_query->execute()->fetchField();
    }

    // If we get here, we want to return a list.
    if ($group) {
      $base_query->groupBy('d.id');
    }
    $base_query->fields('d',
      [
        'id',
        'branch',
        'object_name',
        'title',
        'summary',
        'file_name',
        'object_type',
        'class',
        'is_drupal',
      ])
      ->orderBy('d.title', 'ASC');
    if ($limit > 0) {
      $base_query->range(0, $limit);
    }

    $result = $base_query->execute()->fetchAll();
    $list = [];
    foreach ($result as $object) {
      $docBlock = DocBlock::load($object->id);
      $list[] = [
        'function' => Link::fromTextAndUrl($docBlock->getTitle(), Url::fromUri(Formatter::objectUrl($docBlock)))->toString(),
        'file' => Formatter::linkFile($docBlock),
        'description' => Formatter::linkDocumentation($docBlock->getSummary(), $branch, NULL, ($docBlock->getClass() ? $docBlock->getClass()->id() : NULL), FALSE, FALSE, $docBlock->isDrupal()),
      ];
    }

    return $list;
  }

}
