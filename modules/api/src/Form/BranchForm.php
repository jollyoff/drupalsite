<?php

namespace Drupal\api\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Form controller for the branch entity edit forms.
 */
class BranchForm extends ContentEntityForm {

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    /** @var \Drupal\api\Interfaces\BranchInterface $entity */
    $entity = $this->getEntity();
    $result = $entity->save();
    $link = $entity->toLink($this->t('View'))->toString();

    $message_arguments = ['%label' => $this->entity->label()];
    $logger_arguments = $message_arguments + ['link' => $link];

    if ($result == SAVED_NEW) {
      $this->messenger()->addStatus($this->t('New branch %label has been created.', $message_arguments));
      $this->logger('api')->notice('Created new branch %label', $logger_arguments);
    }
    else {
      $this->messenger()->addStatus($this->t('The branch %label has been updated.', $message_arguments));
      $this->logger('api')->notice('Updated new branch %label.', $logger_arguments);
    }

    // If the branch was set as preferred, then unset the previous one.
    // We only do this if the branch was saved via the UI.
    if ($entity->getPreferred()) {
      $branches = $entity->getProject()->getBranches(TRUE);
      foreach ($branches as $branch) {
        if (
          ($branch->id() != $entity->id()) &&
          ($branch->isPreferred())
        ) {
          $branch->setPreferred(FALSE)->save();
          $this->messenger()->addStatus($this->t('%preferred branch is now the preferred one. %label was set to not preferred.', [
            '%preferred' => $entity->getTitle(),
            '%label' => $branch->getTitle(),
          ]));
        }
      }
    }

    $form_state->setRedirect('entity.branch.collection');
  }

}
