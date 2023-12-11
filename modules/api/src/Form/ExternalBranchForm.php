<?php

namespace Drupal\api\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Form controller for the PHP branch entity edit forms.
 */
class ExternalBranchForm extends ContentEntityForm {

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $entity = $this->getEntity();
    $result = $entity->save();
    $link = $entity->toLink($this->t('View'))->toString();

    $message_arguments = ['%label' => $this->entity->label()];
    $logger_arguments = $message_arguments + ['link' => $link];

    if ($result == SAVED_NEW) {
      $this->messenger()->addStatus($this->t('New External branch %label has been created.', $message_arguments));
      $this->logger('api')->notice('Created new External branch %label', $logger_arguments);
    }
    else {
      $this->messenger()->addStatus($this->t('The External branch %label has been updated.', $message_arguments));
      $this->logger('api')->notice('Updated new External branch %label.', $logger_arguments);
    }

    $form_state->setRedirect('entity.external_branch.collection');
  }

}
