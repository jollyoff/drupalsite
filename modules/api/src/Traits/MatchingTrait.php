<?php

namespace Drupal\api\Traits;

use Drupal\Core\Entity\Query\QueryInterface;

/**
 * Enables querying elements within an entity passing conditions as array.
 *
 * @package Drupal\api\Traits
 */
trait MatchingTrait {

  /**
   * Applies a group of conditions to a query object.
   *
   * @param \Drupal\Core\Entity\Query\QueryInterface $query
   *   Query to apply conditions to.
   * @param array $conditions
   *   Group of conditions to apply.
   * @param array|null $range
   *   Pagination information.
   * @param array|null $sort
   *   Sorting information.
   * @param bool $access_check
   *   Check access of entities for current user.
   */
  protected static function applyConditions(QueryInterface &$query, array $conditions, array $range = NULL, array $sort = NULL, bool $access_check = TRUE) {
    $query->accessCheck($access_check);
    foreach ($conditions as $field => $value) {
      if ($field == 'or' || $field == 'and') {
        $group = ($field == 'or') ?
          $query->orConditionGroup() :
          $query->andConditionGroup();
        foreach ($value as $f => $v) {
          if (is_array($v)) {
            // Check proper array structure, otherwise ignore.
            if (!empty($v['operator'])) {
              $group->condition($f, $v['value'] ?? '', $v['operator']);
            }
          }
          else {
            $group->condition($f, $v);
          }
        }

        $query->condition($group);
      }
      elseif (is_array($value)) {
        // Check proper array structure, otherwise ignore.
        if (!empty($value['operator'])) {
          $query->condition($field, $value['value'] ?? '', $value['operator']);
        }
      }
      else {
        $query->condition($field, $value);
      }
    }

    // Paginated output.
    if (!is_null($range)) {
      $limit = ((int) $range['limit'] ?? NULL);
      $start = ($range['offset'] ?? 0);

      if ($limit) {
        $query->range($start, $limit);
      }
    }

    // And sort results.
    if (!is_null($sort)) {
      foreach ($sort as $field => $direction) {
        if (in_array($direction, ['ASC', 'DESC'])) {
          $query->sort($field, $direction);
        }
      }
    }
  }

}
