<?php

namespace Drupal\api\Plugin\QueueWorker;

use Drupal\api\Entity\DocBlock;
use Drupal\api\Entity\DocBlock\DocReferenceCount;
use Drupal\api\Entity\DocBlock\DocReference;
use Drupal\api\Entity\PhpDocumentation;
use Drupal\api\Entity\ExternalDocumentation;
use Drupal\Core\Queue\QueueWorkerBase;

/**
 * Defines 'api_parse_queue_worker' queue worker.
 *
 * Run via `drush` like this `drush queue:run api_parse_queue`.
 *
 * @QueueWorker(
 *   id = "api_parse_queue",
 *   title = @Translation("Parser Queue Worker")
 * )
 */
class ParseQueueWorker extends QueueWorkerBase {

  /**
   * {@inheritdoc}
   */
  public function processItem($data) {
    if (!$this->validateItem($data)) {
      return FALSE;
    }

    $branch = $this->loadBranch($data['branch_type'], $data['branch_id']);
    $action = $data['action'];
    $documentation_object = $data['data'] ?? NULL;

    if ($action == 'parse' && !empty($documentation_object)) {
      switch ($branch->getEntityTypeId()) {
        case 'branch':
          DocBlock::createOrUpdate($documentation_object, $branch);
          break;

        case 'php_branch':
          PhpDocumentation::createOrUpdate($documentation_object, $branch);
          break;

        case 'external_branch':
          ExternalDocumentation::createOrUpdate($documentation_object, $branch);
          break;
      }
    }
    elseif ($action == 'calculate_counts') {
      DocReferenceCount::calculateReferenceCounts($branch);
    }
    elseif ($action == 'class_relations') {
      DocReference::classRelations($branch);
    }
  }

  /**
   * Validate item array to make sure all key elements are there.
   *
   * @param array $data
   *   Item to validate.
   *
   * @return bool
   *   Whether the item was valid or not.
   */
  protected function validateItem(array $data) {
    if (empty($data['branch_id']) || empty($data['branch_type'])) {
      return FALSE;
    }

    if (empty($data['action'])) {
      return FALSE;
    }

    $branch = $this->loadBranch($data['branch_type'], $data['branch_id']);
    if (!$branch) {
      return FALSE;
    }

    return TRUE;
  }

  /**
   * Loads a given branch object based on the type and id.
   *
   * @param string $type
   *   Type of branch.
   * @param int $id
   *   ID of the branch.
   *
   * @return \Drupal\Core\Entity\EntityInterface|null
   *   Branch object or null if nothing was found.
   */
  protected function loadBranch($type, $id) {
    return \Drupal::entityTypeManager()->getStorage($type)->load($id);
  }

}
