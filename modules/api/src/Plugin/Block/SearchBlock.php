<?php

namespace Drupal\api\Plugin\Block;

use Drupal\api\Form\SearchForm;
use Drupal\api\Utilities;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\UncacheableDependencyTrait;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a 'Search API' Block.
 *
 * @Block(
 *   id = "api_search_block",
 *   admin_label = @Translation("API Search Block"),
 * )
 */
class SearchBlock extends BlockBase implements ContainerFactoryPluginInterface {

  use UncacheableDependencyTrait;

  /**
   * Form builder service.
   *
   * @var \Drupal\Core\Form\FormBuilderInterface
   */
  protected $formBuilder;

  /**
   * Drupal\api\Utilities definition.
   *
   * @var \Drupal\api\Utilities
   */
  protected $utilities;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, FormBuilderInterface $form_builder, Utilities $utilities) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->formBuilder = $form_builder;
    $this->utilities = $utilities;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('form_builder'),
      $container->get('api.utilities')
    );
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
      return [];
    }

    return [
      '#title' => $this->t('Search @project @branch', [
        '@project' => $project->getTitle(),
        '@branch' => $branch->getSlug(),
      ]),
      'form' => $this->formBuilder->getForm(SearchForm::class, $branch),
    ];
  }

}
