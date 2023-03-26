<?php

namespace Drupal\api_search_core\Plugin\Search;

use Drupal\api\Entity\DocBlock;
use Drupal\api\ExtendedQueries;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Query\PagerSelectExtender;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Access\AccessibleInterface;
use Drupal\search\Plugin\SearchPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Executes a keyword search for DocBlocks against the database table.
 *
 * @SearchPlugin(
 *   id = "docblock_search",
 *   title = @Translation("DocBlocks")
 * )
 */
class DocBlockSearch extends SearchPluginBase implements AccessibleInterface {

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $container->get('database'),
      $container->get('entity_type.manager'),
      $container->get('module_handler'),
      $configuration,
      $plugin_id,
      $plugin_definition
    );
  }

  /**
   * Creates a UserSearch object.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   */
  public function __construct(Connection $database, EntityTypeManagerInterface $entity_type_manager, ModuleHandlerInterface $module_handler, array $configuration, $plugin_id, $plugin_definition) {
    $this->database = $database;
    $this->entityTypeManager = $entity_type_manager;
    $this->moduleHandler = $module_handler;
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->addCacheTags(['docblock_list']);
  }

  /**
   * {@inheritdoc}
   */
  public function access($operation = 'view', AccountInterface $account = NULL, $return_as_object = FALSE) {
    $result = AccessResult::allowedIf(!empty($account) && $account->hasPermission('access API reference'))->cachePerPermissions();
    return $return_as_object ? $result : $result->isAllowed();
  }

  /**
   * {@inheritdoc}
   */
  public function execute() {
    $results = [];
    if (!$this->isSearchExecutable()) {
      return $results;
    }

    $keys = $this->database->escapeLike($this->keywords);
    // Replace wildcards with MySQL/PostgreSQL wildcards.
    $keys = preg_replace('!\*+!', '%', $keys);

    // Find matching docblocks.
    $query = $this->database->select(ExtendedQueries::entityToTable('docblock'), 'docblock');
    $query->leftJoin(ExtendedQueries::entityToTable('docblock_function'), 'docblock_function', 'docblock_function.docblock = docblock.id');
    $query = $query
      ->extend(PagerSelectExtender::class)
      ->fields('docblock', ['id']);

    $orCondition = $query->orConditionGroup()
      ->condition('title', '%' . $keys . '%', 'LIKE')
      ->condition('parameters', '%' . $keys . '%', 'LIKE')
      ->condition('return_value', '%' . $keys . '%', 'LIKE')
      ->condition('documentation', '%' . $keys . '%', 'LIKE')
      ->condition('code', '%' . $keys . '%', 'LIKE');
    $query->condition($orCondition);

    $ids = $query
      ->limit()
      ->execute()
      ->fetchCol();

    $docBlocks = DocBlock::loadMultiple($ids);
    foreach ($docBlocks as $docBlock) {
      $branch = $docBlock->getBranch();
      $project = $branch->getProject();
      $snippet = search_excerpt($keys, $docBlock->getSummary());
      $snippet['#suffix'] = ' <small><em>' . $project->getTitle() . ' - ' . $branch->getSlug() . '</em></small> ';

      $results[] = [
        'title' => $docBlock->getTitle(),
        'link' => $docBlock->toUrl('canonical', ['absolute' => TRUE])->toString(),
        'snippet' => $snippet,
      ];
    }

    return $results;
  }

  /**
   * {@inheritdoc}
   */
  public function getHelp() {
    $help = [
      'list' => [
        '#theme' => 'item_list',
        '#items' => [
          $this->t('API search looks for DocBlock titles, documentation and code for exact and partial matches. Example: mar would match mar, delmar, and maryjane.'),
          $this->t('You can use * as a wildcard within your keyword. Example: m*r would match mar, delmar, and elementary.'),
        ],
      ],
    ];

    return $help;
  }

}
