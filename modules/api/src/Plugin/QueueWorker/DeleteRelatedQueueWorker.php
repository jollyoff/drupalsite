<?php

namespace Drupal\api\Plugin\QueueWorker;

use Drupal\Core\Queue\QueueWorkerBase;

/**
 * Defines 'api_delete_related_worker' queue worker.
 *
 * Run via `drush` like `drush queue:run api_delete_related`.
 *
 * @QueueWorker(
 *   id = "api_delete_related",
 *   title = @Translation("Delete Related Queue Worker")
 * )
 */
class DeleteRelatedQueueWorker extends QueueWorkerBase {

  /**
   * {@inheritdoc}
   */
  public function processItem($data) {
    if (!$this->validateItem($data)) {
      return FALSE;
    }

    $related = $data['related'];
    foreach ($related as $entity_type => $field) {
      $this->deleteParsedData($entity_type, $field, $data['entity_id']);
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
    if (
      empty($data['entity_id']) ||
      empty($data['entity_type']) ||
      empty($data['related'])
    ) {
      return FALSE;
    }

    $entity = $this->loadEntity($data['entity_type'], $data['entity_id']);
    if ($entity) {
      // If there is an entity, we should NOT delete its related data, as this
      // functionality is meant to run after the entity has been deleted.
      return FALSE;
    }

    return TRUE;
  }

  /**
   * Loads a given entity object based on the type and id.
   *
   * @param string $type
   *   Type of entity.
   * @param int $id
   *   ID of the entity.
   *
   * @return \Drupal\Core\Entity\EntityInterface|null
   *   Branch object or null if nothing was found.
   */
  protected function loadEntity($type, $id) {
    return \Drupal::entityTypeManager()->getStorage($type)->load($id);
  }

  /**
   * Deletes all related data to the entity.
   *
   * @param string $entity_type
   *   Type of the entities to delete.
   * @param string $field
   *   Field to check.
   * @param string $value
   *   Value of the field.
   */
  public function deleteParsedData($entity_type, $field, $value) {
    // https://www.drupal.org/node/3051072
    $storage_handler = \Drupal::entityTypeManager()->getStorage($entity_type);
    $ids = \Drupal::entityQuery($entity_type)
      ->accessCheck(FALSE)
      ->condition($field, $value)
      ->execute();
    foreach (array_chunk($ids, 50) as $chunk) {
      $entities = $storage_handler->loadMultiple($chunk);
      $storage_handler->delete($entities);
    }
  }

}
