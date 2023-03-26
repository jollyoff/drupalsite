<?php

namespace Drupal\api\Traits;

use Drupal\api\Entity\Branch;
use Drupal\api\Entity\DocBlock;
use Drupal\api\Entity\Project;
use Drupal\api\Formatter;
use Drupal\api\Interfaces\BranchInterface;
use Drupal\api\Interfaces\DocBlockInterface;
use Drupal\api\Interfaces\ProjectInterface;
use Drupal\Component\Utility\Html;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Brings elements from the route after validating them.
 *
 * @package Drupal\api\Traits
 */
trait RouteElementsTrait {

  /**
   * Gets an object belonging to a file of a certain type.
   *
   * @param string $item
   *   Information about the object to get.
   * @param \Drupal\api\Interfaces\DocBlockInterface $file
   *   File where the object will be.
   * @param string $type
   *   Type of the item.
   * @param bool $throw_exception
   *   Whether to throw a not found exception or just return null.
   *
   * @return \Drupal\api\Interfaces\DocBlockInterface|null
   *   DocBlock object.
   */
  protected function getItem($item, DocBlockInterface $file, $type, $throw_exception = FALSE) {
    // As $item comes from URL argument, ':' might be encoded as '%3A'.
    $item = urldecode($item);
    $docBlock_ids = DocBlock::findByNameAndType($item, $type, $file->getBranch());
    if (!$docBlock_ids && $throw_exception) {
      throw new NotFoundHttpException();
    }

    $docBlocks = DocBlock::loadMultiple($docBlock_ids);
    foreach ($docBlocks as $docBlock) {
      if ($docBlock->getFileName() == $file->getFileName()) {
        return $docBlock;
      }
    }

    throw new NotFoundHttpException();
  }

  /**
   * Gets a file object by its path and branch.
   *
   * @param string $filename
   *   Path and name of the file as given by the URL argument.
   * @param \Drupal\api\Interfaces\BranchInterface $branch
   *   Branch where the file is supposed to be.
   * @param bool $throw_exception
   *   Whether to throw a not found exception or just return null.
   *
   * @return \Drupal\api\Interfaces\DocBlockInterface|null
   *   File object.
   */
  protected function getFile($filename, BranchInterface $branch, $throw_exception = FALSE) {
    // As $filename comes from URL argument, '/' might be encoded as '%21'.
    $filename = urldecode($filename);
    $filename = Formatter::getReplacementName($filename, 'file', TRUE);
    $file_id = DocBlock::findFileByFileName($filename, $branch);
    if (
      empty($file_id) &&
      (strpos($filename, Formatter::V1_3_FILEPATH_SEPARATOR_REPLACEMENT) !== FALSE)
    ) {
      // May be an API 1.3 style path. See if we can load a valid object with
      // the old replacement pattern.
      $filename = str_replace(
        Formatter::V1_3_FILEPATH_SEPARATOR_REPLACEMENT,
        Formatter::FILEPATH_SEPARATOR,
        $filename
      );
      $file_id = DocBlock::findFileByFileName($filename, $branch);
    }

    $file = $file_id ? DocBlock::load($file_id) : NULL;
    if (!$file && $throw_exception) {
      throw new NotFoundHttpException();
    }

    return $file;
  }

  /**
   * Filters the parameter and gets the project linked to it by ID or slug.
   *
   * @param string|\Drupal\api\Interfaces\ProjectInterface $project
   *   Project, ID or slug of the project.
   * @param bool $throw_exception
   *   Whether to throw a not found exception or just return null.
   *
   * @return \Drupal\api\Interfaces\ProjectInterface|null
   *   Project, if found, or null.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
   *   If no project is found and $throw_exception is TRUE.
   */
  protected function getProject($project, $throw_exception = FALSE) {
    if ($project instanceof ProjectInterface) {
      return $project;
    }

    $project = Html::escape($project);
    $project = Project::getBySlug($project);

    if (!$project && $throw_exception) {
      throw new NotFoundHttpException();
    }

    return $project;
  }

  /**
   * Filters the parameter and gets the branch linked to it by ID or slug.
   *
   * @param string|\Drupal\api\Interfaces\BranchInterface $branch
   *   Branch, ID or slug of the branch.
   * @param \Drupal\api\Interfaces\ProjectInterface $project
   *   Project the branch belongs to.
   * @param bool $throw_exception
   *   Whether to throw a not found exception or just return null.
   *
   * @return \Drupal\api\Interfaces\BranchInterface|null
   *   Branch, if found, or null.
   */
  protected function getBranch($branch, ProjectInterface $project, $throw_exception = FALSE) {
    if ($branch instanceof BranchInterface) {
      return ($branch->getProject()->id() == $project->id()) ?
        $branch :
        NULL;
    }

    $branch = Html::escape($branch);
    $branch = Branch::getBySlug($branch, $project);
    if (!$branch && $throw_exception) {
      throw new NotFoundHttpException();
    }

    return $branch;
  }

}
