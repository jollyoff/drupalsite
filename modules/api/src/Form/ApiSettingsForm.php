<?php

namespace Drupal\api\Form;

use Drupal\api\Formatter;
use Drupal\api\Utilities;
use Drupal\api\Entity\Branch;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\StreamWrapper\PublicStream;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Settings for api.
 */
class ApiSettingsForm extends ConfigFormBase {

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
  protected function getEditableConfigNames() {
    return [
      'api.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'api_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('api.settings');

    // @codingStandardsIgnoreStart
    $form['intro'] = [
      '#markup' => '<div>' .
        '<h3>' . $this->t('Definitions') . '</h3><ul>' .
        '<li>' . $this->t('Project: A module, theme, Drupal Core, or other group of files that the API module is parsing.') . '</li>' .
        '<li>' . $this->t('Branch: Within a Project, a particular version of the files (7.x, 8.x-1.x, 2.x, etc.).') . '</li>' .
        '<li>' . $this->t('Core compatibility: usually 7.x, 9.x, etc. Branches with matching core compatibility are used to make cross-project links.') . '</li>' .
        '<li>' . $this->t('Reparse: Force a parse of every file in the branch, starting next time the branch is updated.') . '</li>' .
        '<li>' . $this->t('OpenSearch: This module provides an OpenSearch discovery link in the HTML header, which needs a name and description. See <a href="http://en.wikipedia.org/wiki/OpenSearch">OpenSearch on Wikipedia</a> for more information.') . '</li>' .
        '</ul>' .
        '</div><hr />',
    ];
    // @codingStandardsIgnoreEnd

    $gitSupported = $this->utilities->hasGit();
    if ($gitSupported) {
      $link = Link::createFromRoute($this->t('Quick wizard'), 'api.wizard')->toString();
      $form['quick_wizard_link'] = [
        '#markup' => '<div><h3>' . $link . '</h3>' . $this->t('<strong><em>git</em> is supported</strong>. You can use the quick wizard link above to set up a project and all its branches') . '</div>',
      ];
    }
    $form['git_base_path'] = [
      '#type' => 'textfield',
      '#default_value' => $config->get('git_base_path') ?? \DRUPAL_ROOT . '/' . PublicStream::basePath(),
      '#title' => $this->t('Base path to store git repositories'),
      '#description' => $this->t('Cloned repositories will be stored in unique folders within this base path.'),
      '#access' => $gitSupported,
    ];

    $form['config_intro'] = [
      '#markup' => '<h3>' . $this->t('Configuration') . '</h3><div>' . $this->t('Additional configuration elements for services like the parser.') . '</div>',
    ];

    $branches = Branch::loadMultiple();
    if (count($branches)) {
      $branches_array = [];
      foreach ($branches as $branch) {
        /** @var \Drupal\api\Entity\Branch $branch */
        $project = $branch->getProject();
        $branches_array[$project->getTitle()][$branch->getSlug() . '|' . $branch->id()] = $branch->getTitle() . ' - ' . $project->getTitle();
        ksort($branches_array[$project->getTitle()]);
      }

      $form['default_branch_project'] = [
        '#type' => 'select',
        '#required' => TRUE,
        '#empty_option' => $this->t('- Select -'),
        '#options' => $branches_array,
        '#default_value' => $config->get('default_branch_project') ?? '',
        '#title' => $this->t('Default project and branch'),
        '#description' => $this->t('Name of the default project and branch to display if none is given in a URL. This will also determine which is the default core compatibility for the site.'),
      ];
    }

    $form['branches_per_cron'] = [
      '#type' => 'number',
      '#default_value' => $config->get('branches_per_cron') ?? 5,
      '#required' => TRUE,
      '#min' => 0,
      '#title' => $this->t('Branches to parse per cron run'),
      '#description' => $this->t('Number of branches to parse per cron. If the limit is too high, the cron run might time out. Set to 0 for no limit.'),
    ];

    $form['remove_orphan_files'] = [
      '#type' => 'number',
      '#default_value' => $config->get('remove_orphan_files') ?? 0,
      '#required' => TRUE,
      '#min' => 0,
      '#title' => $this->t('Days to remove orphan files after exclusion (0 = never)'),
      '#description' => $this->t('If files are excluded after parsing, some entries might be orphan. Use this setting to delete these entries and all of its related DocBlocks. <br /><b>This will cause data loss and is not reversible, so the recommended setting is to leave at 0, where orphan entries will never be deleted.</b>'),
    ];

    $form['breaks_where'] = [
      '#type' => 'textfield',
      '#default_value' => $config->get('breaks_where') ?? Formatter::BREAKS_WHERE,
      '#title' => $this->t('Allowed places for line breaks'),
      '#description' => $this->t('In listing tables, long item names will be allowed to break at these spots (space-separated). Careful of the order if any of these appear in the HTML tag being used!'),
    ];

    $form['breaks_tag'] = [
      '#type' => 'textfield',
      '#default_value' => $config->get('breaks_tag') ?? Formatter::BREAKS_TAG,
      '#title' => $this->t('HTML tag or entity to use to indicate breaks'),
    ];

    $form['opensearch'] = [
      '#markup' => '<hr /><h3>' . $this->t('OpenSearch') . '</h3>',
    ];
    $form['opensearch_name'] = [
      '#type' => 'textfield',
      '#default_value' => $config->get('opensearch_name') ?? $this->t('Drupal API'),
      '#title' => $this->t('Name of site'),
    ];

    $form['opensearch_description'] = [
      '#type' => 'textfield',
      '#default_value' => $config->get('opensearch_description') ?? $this->t('Drupal API documentation'),
      '#title' => $this->t('Description of site'),
    ];

    $form['status'] = [
      '#markup' => '<hr /><h3>' . $this->t('Status') . '</h3>',
    ];
    if ($config->get('default_branch_project')) {
      $form['setup_status'] = [
        '#markup' => '<p>' . $this->t('You have a default branch, project, and core compatibility set up, so after parsing, you should be able to view API pages.') . '</p>',
      ];
    }
    else {
      $form['setup_status'] = [
        '#markup' => '<p>' . $this->t('Without a default branch, and project (and core compatibility), you cannot view API pages') . '</p>',
      ];
    }

    $form['#theme'] = 'system_config_form';
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    if (!is_dir($form_state->getValue('git_base_path'))) {
      $form_state->setErrorByName('git_base_path', $this->t('Either the path is invalid or you do not have access to it.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);
    $this->saveValues($form, $form_state);
  }

  /**
   * Save form submitted values.
   */
  public function saveValues(array &$form, FormStateInterface $form_state) {
    $breaks_where = $form_state->getValue('breaks_where');
    $breaks_tag = $form_state->getValue('breaks_tag');
    $default_branch_project = $form_state->getValue('default_branch_project');
    $branches_per_cron = $form_state->getValue('branches_per_cron', 5);
    $remove_orphan_files = $form_state->getValue('remove_orphan_files', 0);
    $opensearch_name = $form_state->getValue('opensearch_name');
    $opensearch_description = $form_state->getValue('opensearch_description');
    $git_base_path = $form_state->getValue('git_base_path');

    $this->config('api.settings')
      ->set('breaks_where', $breaks_where)
      ->set('breaks_tag', $breaks_tag)
      ->set('default_branch_project', $default_branch_project)
      ->set('branches_per_cron', $branches_per_cron)
      ->set('remove_orphan_files', $remove_orphan_files)
      ->set('opensearch_name', $opensearch_name)
      ->set('opensearch_description', $opensearch_description)
      ->set('git_base_path', $git_base_path)
      ->save();
  }

}
