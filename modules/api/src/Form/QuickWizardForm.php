<?php

namespace Drupal\api\Form;

use Drupal\api\Entity\Branch;
use Drupal\api\Entity\Project;
use Drupal\api\Interfaces\ProjectInterface;
use Drupal\api\Utilities;
use Drupal\Component\Utility\Environment;
use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\Unicode;
use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Batch\BatchBuilder;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Quick wizard for api.
 */
class QuickWizardForm extends FormBase {

  /**
   * Drupal\api\Utilities definition.
   *
   * @var \Drupal\api\Utilities
   */
  protected $utilities;

  /**
   * Constructs the object.
   *
   * @param \Drupal\api\Utilities $utilities
   *   The utitities service.
   */
  public function __construct(Utilities $utilities) {
    $this->utilities = $utilities;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('api.utilities')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'api_quick_wizard_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $gitSupported = $this->utilities->hasGit();
    if (!$gitSupported) {
      // @codingStandardsIgnoreStart
      $form['no_git'] = [
        '#markup' => '<h3>' . $this->t('git is not supported') . '</h3><div>' .
          $this->t('Please <a href=":project_add">create a project</a> first, and then its <a href=":branch_add">additional branches</a>.', [
            ':project_add' => Url::fromRoute('entity.project.add_form')->toString(),
            ':branch_add' => Url::fromRoute('entity.branch.add_form')->toString(),
          ]) . '<br />' .
          $this->t('In order to use this feature, "git" command needs to be available on the server.') . '</div>',
      ];
      // @codingStandardsIgnoreEnd

      return $form;
    }

    // @codingStandardsIgnoreStart
    $form['wizard_intro'] = [
      '#markup' => '<div>' .
        $this->t('You can use the quick wizard to set up a project and all its branches. ') . '<br />' .
        $this->t('If the project already exists and was created via the quick wizard, it will add new branches and pull changes from existing ones. ') .
        '</div>',
    ];
    // @codingStandardsIgnoreEnd

    $form['git_url'] = [
      '#type' => 'url',
      '#title' => $this->t('Repository URL'),
      '#description' => $this->t('Use the "https://" format.'),
      '#required' => TRUE,
      '#placeholder' => $this->t('ie: https://git.drupalcode.org/project/api.git'),
    ];

    $form['git_branches'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Branches to parse'),
      '#description' => $this->t('Use this setting if the project has many branches and only a few are needed. Comma separated or one per line. If blank then all branches will be parsed.'),
      '#placeholder' => $this->t('ie: 7.x, 8.x-4.x, 2.x'),
    ];

    $form['type'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Type'),
      '#description' => $this->t('Normally "core", "module", "theme", or "library". The type "core" is special: core project functions are given priority when turning function, class, and other names into links in code listings.'),
      '#required' => TRUE,
      '#placeholder' => $this->t('ie: core, module, theme'),
      '#default_value' => 'module',
    ];

    $form['wizard_warning'] = [
      '#markup' => '<div><small><em> * ' . $this->t('Large repositories might take a while to process whilst doing the first clone of the project.') . '</em></small></div>',
    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit'),
      '#button_type' => 'primary',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $git_url = $form_state->getValue('git_url');
    if (!UrlHelper::isValid($git_url)) {
      $form_state->setErrorByName('git_url', 'The URL of the git repository is not valid.');
    }

    $git_branches = $form_state->getValue('git_branches');
    if (
      !empty($git_branches) &&
      str_contains($git_branches, ',') &&
      str_contains($git_branches, PHP_EOL)
    ) {
      $form_state->setErrorByName('git_url', 'You can use commas or line breaks, but not both.');
    }

    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $type = Html::escape($form_state->getValue('type'));

    $git_url = $form_state->getValue('git_url');
    $git_branches_array = [];

    $git_branches = $form_state->getValue('git_branches');
    if (!empty($git_branches)) {
      $git_branches = str_replace(' ', '', $git_branches);
      $separator = str_contains($git_branches, ',') ? ',' : PHP_EOL;
      $git_branches_array = array_filter(array_map('trim', explode($separator, $git_branches)));
    }

    // The first clone in big repos is usually the longest operation.
    Environment::setTimeLimit(0);

    // Check if project is already there.
    $project_name = $this->utilities->gitProjectName($git_url);
    $exists = Project::exists($project_name);
    if ($exists) {
      $project = Project::getBySlug($project_name);
      $results = $this->utilities->gitFetchBranches($project, $git_branches_array);
      if (empty($results)) {
        $this->messenger()->addError($this->t('Branches could not be pulled or checked out.'));
      }
    }
    else {
      $results = $this->utilities->gitClone($git_url, $git_branches_array);
      if (empty($results)) {
        $this->messenger()->addError($this->t('There was an error cloning the repository.'));
      }
      else {
        // Repo was cloned to the filesystem, create the project now.
        $project = $this->createProject($project_name, $type);
      }
    }

    if (!empty($results) && ($project instanceof ProjectInterface)) {
      // And branches (via Batch API).
      $batch_builder = (new BatchBuilder())
        ->setTitle($this->t('Creating project and branches'))
        ->setFinishCallback('\Drupal\api\Form\QuickWizardForm::finishedBatchCreation')
        ->setInitMessage($this->t('Batch is starting...'))
        ->setProgressMessage($this->t('Processed @current out of @total'))
        ->setErrorMessage($this->t('Batch has encountered an error'));
      foreach ($results as $result) {
        $batch_builder->addOperation(
          '\Drupal\api\Form\QuickWizardForm::upsertBranch',
          [$result, $project]
        );
      }
      batch_set($batch_builder->toArray());
    }
  }

  /**
   * Call to run when finishing creating everything.
   */
  public static function finishedBatchCreation() {
    $messenger = \Drupal::messenger();
    $messenger->addStatus(t('Your <a href=":projects">project</a> and <a href=":branches">branches</a> have been created. Please review the "Core compatibility" fields in each branch.', [
      ':projects' => Url::fromRoute('entity.project.collection')->toString(),
      ':branches' => Url::fromRoute('entity.branch.collection')->toString(),
    ]));
  }

  /**
   * Create or update a branch folder and entity.
   *
   * @param array $branch_info
   *   Information about the branch folder structure.
   * @param \Drupal\api\Entity\Project $project
   *   Project object.
   *
   * @return bool
   *   Whether the branch folder and entity was created or not.
   */
  public static function upsertBranch(array $branch_info, Project $project) {
    /** @var \Drupal\api\Utilities $utilities */
    $utilities = \Drupal::service('api.utilities');
    $base_folder = substr($branch_info['folder'], 0, -1 * (strlen($branch_info['branch']) + 1));

    $branch_folder = $utilities->createBranchFolder(
      $branch_info['branch'],
      $base_folder,
      $branch_info['repo']
    );
    if ($branch_folder == $branch_info['folder']) {
      $core_compatibility = $project->isCore() ?
        $branch_info['branch'] :
        $utilities->getCoreCompatibility($branch_info['folder'], $project->getSlug());

      if ($project->isCore()) {
        $utilities->addExtraDocumentation($branch_info['folder'], $branch_info['branch'], $project);
      }

      /** @var \Drupal\api\Interfaces\BranchInterface $branch */
      $branch = Branch::getBySlug($branch_info['branch'], $project) ?? Branch::create();
      $branch
        ->setTitle($branch_info['branch'])
        ->setSlug($branch_info['branch'])
        ->setCoreCompatibility($core_compatibility)
        ->setProject($project)
        ->setDirectories($branch_info['folder'])
        ->save();

      return TRUE;
    }

    return FALSE;
  }

  /**
   * Creates a Project entity.
   *
   * @param string $project_name
   *   Machine name of the project.
   * @param string $type
   *   Type of the project.
   *
   * @return \Drupal\Core\Entity\EntityBase|\Drupal\Core\Entity\EntityInterface
   *   Created project.
   */
  protected function createProject($project_name, $type) {
    $formatted_name = Unicode::ucwords(str_replace(['-', '_'], [' ', ' '], $project_name));

    /** @var \Drupal\api\Interfaces\ProjectInterface $project */
    $project = Project::create();
    $project
      ->setTitle($formatted_name)
      ->setType($type)
      ->setSlug($project_name)
      ->save();

    return $project;
  }

}
