<?php

namespace Drupal\api\Plugin\Block;

use Drupal\api\Entity\DocBlock;
use Drupal\api\Interfaces\BranchInterface;
use Drupal\api\Utilities;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a 'Navigation API' Block.
 *
 * @Block(
 *   id = "api_navigation_block",
 *   admin_label = @Translation("API Navigation Block"),
 * )
 */
class NavigationBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * Drupal\api\Utilities definition.
   *
   * @var \Drupal\api\Utilities
   */
  protected $utilities;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, Utilities $utilities) {
    $this->utilities = $utilities;
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('api.utilities')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    // Enabled in all types by default.
    $types = $this->utilities->getPageTypesAndDescriptions();
    return [
      'navigation_block_display' => array_combine(
        array_keys($types),
        array_keys($types),
      ),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $form['navigation_block_display'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Show on'),
      '#options' => $this->utilities->getPageTypesAndDescriptions(),
      '#description' => $this->t('Show/hide the navigation block on certain types of API pages.'),
      '#default_value' => $this->configuration['navigation_block_display'],
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    $this->configuration['navigation_block_display'] = $form_state->getValue('navigation_block_display');
  }

  /**
   * {@inheritdoc}
   */
  public function access(AccountInterface $account, $return_as_object = FALSE) {
    $access = parent::access($account, $return_as_object);
    $permission_access = AccessResult::allowedIfHasPermission($account, 'access API reference');
    if ($return_as_object) {
      // If parent's access is allowed, then return the new check.
      return ($access->isAllowed()) ? $permission_access : $access;
    }

    return $access && $permission_access->isAllowed();
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    [
      'project' => $project,
      'branch' => $branch,
    ] = $this->utilities->getProjectAndBranchFromRoute();
    if (!$branch || !$project) {
      // Get defaults.
      if (!$project && $branch) {
        $project = $branch->getProject();
      }
      elseif ($project && !$branch) {
        $branch = $project->getDefaultBranch(TRUE);
      }
      else {
        $branch = $this->utilities->getDefaultBranchProject();
        if (!$branch) {
          return [];
        }
        $project = $branch->getProject();
      }
    }

    $links = $this->createLinks($branch);
    if (!$links) {
      return [];
    }

    return [
      '#title' => $this->t('API Navigation'),
      'content' => [
        '#theme' => 'item_list',
        '#items' => $links,
      ],
      '#cache' => [
        'contexts' => [
          'url.path',
        ],
      ],
    ];
  }

  /**
   * Translates the type to the right label.
   *
   * @param string $type
   *   Type string to translate.
   *
   * @return string
   *   Translated string or the same if nothing found.
   */
  protected function translateType($type) {
    $translation = [
      'groups' => $this->t('Topics'),
      'classes' => $this->t('Classes'),
      'functions' => $this->t('Functions'),
      'files' => $this->t('Files'),
      'namespaces' => $this->t('Namespaces'),
      'services' => $this->t('Services'),
      'elements' => $this->t('Elements'),
      'constants' => $this->t('Constants'),
      'globals' => $this->t('Globals'),
      'deprecated' => $this->t('Deprecated'),
    ];
    return $translation[$type] ?? $type;
  }

  /**
   * Creates links to be rendered in the block contents.
   *
   * @param \Drupal\api\Interfaces\BranchInterface $branch
   *   Branch for this block.
   *
   * @return \Drupal\Core\Link[]|null
   *   Array of links or null.
   */
  protected function createLinks(BranchInterface $branch) {
    // See if this block is configured to display on this type of API page.
    $page_type = $this->utilities->getPageTypeFromRoute();
    $saved_config = $this->configuration['navigation_block_display'];
    if (!$page_type || !isset($saved_config[$page_type]) || !$saved_config[$page_type]) {
      return [];
    }

    $links = [];
    $links[] = Link::createFromRoute($branch->getProject()->getTitle() . ' ' . $branch->getSlug(), 'api.branch_default_route', [
      'project' => $branch->getProject()->getSlug(),
      'argument' => $branch->getSlug(),
    ]);

    // And all listing types available.
    $types = DocBlock::getListingTypes($branch);
    foreach ($types as $type => $value) {
      if ($value) {
        $links[] = Link::createFromRoute($this->translateType($type), 'api.branch_explicit_route', [
          'project' => $branch->getProject()->getSlug(),
          'argument' => $type,
          'branch' => $branch->getSlug(),
        ]);
      }
    }

    return $links;
  }

}
