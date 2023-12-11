<?php

namespace Drupal\api\Plugin\views\filter;

use Drupal\api\ExtendedQueries;
use Drupal\Core\Database\Connection;
use Drupal\views\Plugin\views\filter\StringFilter;
use Drupal\views\Plugin\ViewsHandlerManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Filter by DocReference tag id.
 *
 * @ViewsFilter("service_tags")
 */
class ServiceTags extends StringFilter {

  /**
   * Views Handler Plugin Manager.
   *
   * @var \Drupal\views\Plugin\ViewsHandlerManager
   */
  protected $joinHandler;

  /**
   * Constructs a new ServiceTags.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\views\Plugin\ViewsHandlerManager $join_handler
   *   Views Handler Plugin Manager.
   * @param \Drupal\Core\Database\Connection $connection
   *   The database connection.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, ViewsHandlerManager $join_handler, Connection $connection) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $connection);
    $this->joinHandler = $join_handler;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('plugin.manager.views.join'),
      $container->get('database')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function query() {
    $this->ensureMyTable();

    $reference_table = ExtendedQueries::entityToTable('docblock_reference');
    $def = [
      'table' => $reference_table,
      'field' => 'docblock',
      'left_table' => $this->tableAlias,
      'left_field' => 'id',
    ];
    $join = $this->joinHandler->createInstance('standard', $def);
    $this->tableAlias = $this->query->addTable($reference_table, $this->relationship, $join);
    $this->realField = 'object_name';

    parent::query();
  }

}
