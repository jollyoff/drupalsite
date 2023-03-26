<?php

namespace Drupal\api\Controller;

use Drupal\api\Entity\DocBlock;
use Drupal\api\Interfaces\BranchInterface;
use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\Xss;
use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Autocomplete endpoints.
 *
 * @package Drupal\api\Controller
 */
class AutocompleteController extends ControllerBase {

  /**
   * Searches a branch for a given term.
   *
   * @param \Drupal\api\Interfaces\BranchInterface $branch
   *   Branch to search in.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Request object.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   Autocomplete results.
   */
  public function searchAutocomplete(BranchInterface $branch, Request $request) {
    $input = $request->query->get('q');
    if (!$input) {
      return new JsonResponse([]);
    }

    $input = Xss::filter($input);
    $ids = DocBlock::searchByTitle($input, $branch, 50);
    $docBlocks = $ids ? DocBlock::loadMultiple($ids) : [];
    $results = [];
    foreach ($docBlocks as $docBlock) {
      $title = Html::escape($docBlock->getTitle());
      if (!isset($results[$title])) {
        $results[$title] = [
          'value' => $title,
          'label' => $title,
        ];
      }
    }

    // We don't want the keys, it was just to avoid duplicates.
    $results = array_values($results);
    if (count($results) > 10) {
      $chunk = array_chunk($results, 10);
      $results = $chunk[0];
    }

    return new JsonResponse($results);
  }

}
