<?php

namespace Drupal\api\Form;

use Drupal\api\Interfaces\BranchInterface;
use Drupal\Component\Utility\Xss;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Implements the SearchForm form controller.
 */
class SearchForm extends FormBase {

  /**
   * Form constructor.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param \Drupal\api\Interfaces\BranchInterface|null $branch
   *   Branch currently loaded.
   *
   * @return array
   *   The form structure.
   */
  public function buildForm(array $form, FormStateInterface $form_state, BranchInterface $branch = NULL) {
    if (empty($branch)) {
      return [];
    }

    $form['#attributes']['class'] = 'api-search-form';
    $form['#branch'] = $branch;

    // Value provided via URL?.
    $query = $this->getRequest()->query->get('q') ?? '';
    $query = Xss::filter($query);

    $form['search'] = [
      '#title' => $this->t('Function, class, file, topic, etc.'),
      '#title_display' => 'invisible',
      '#description' => $this->t('Partial match search is supported'),
      '#type' => 'textfield',
      '#default_value' => $query,
      '#required' => TRUE,
      '#attributes' => ['class' => ['api-search-keywords']],
      '#autocomplete_route_name' => 'api.search.autocomplete',
      '#autocomplete_route_parameters' => ['branch' => $branch->id()],
    ];
    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Search'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'api_search_form';
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $search = Xss::filter($form_state->getValue('search'));

    $branch = $form['#branch'];

    $form_state->setRedirectUrl(Url::fromRoute('api.search.project.branch.term', [
      'branch' => $branch->getSlug(),
      'project' => $branch->getProject()->getSlug(),
      'term' => $search,
    ]));
  }

}
