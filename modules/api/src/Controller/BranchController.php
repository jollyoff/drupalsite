<?php

namespace Drupal\api\Controller;

use Drupal\api\Interfaces\BranchInterface;
use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\Request;

/**
 * Additional branch specific operations.
 *
 * @package Drupal\api\Controller
 */
class BranchController extends ControllerBase {

  /**
   * Marks a branch for re-parsing.
   *
   * @param \Drupal\api\Interfaces\BranchInterface $branch
   *   Branch to mark for re-parsing.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Request object.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   Redirect with a message.
   */
  public function parse(BranchInterface $branch, Request $request) {
    $branch->reParse();

    $this->messenger()->addStatus($this->t('Branch @branch was set for re-parsing.', [
      '@branch' => $branch->label(),
    ]));

    return $this->redirect('entity.branch.collection');
  }

}
