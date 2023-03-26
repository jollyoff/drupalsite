<?php

namespace Drupal\api\ListBuilder;

use Drupal\api\Entity\ExternalDocumentation;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Link;
use Drupal\Core\Routing\RedirectDestinationInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a list controller for the external_branch entity type.
 */
class ExternalBranchListBuilder extends EntityListBuilder {

  /**
   * The date formatter service.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * The redirect destination service.
   *
   * @var \Drupal\Core\Routing\RedirectDestinationInterface
   */
  protected $redirectDestination;

  /**
   * Constructs a new ExternalBranchListBuilder object.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type definition.
   * @param \Drupal\Core\Entity\EntityStorageInterface $storage
   *   The entity storage class.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $date_formatter
   *   The date formatter service.
   * @param \Drupal\Core\Routing\RedirectDestinationInterface $redirect_destination
   *   The redirect destination service.
   */
  public function __construct(EntityTypeInterface $entity_type, EntityStorageInterface $storage, DateFormatterInterface $date_formatter, RedirectDestinationInterface $redirect_destination) {
    parent::__construct($entity_type, $storage);
    $this->dateFormatter = $date_formatter;
    $this->redirectDestination = $redirect_destination;
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    return new static(
      $entity_type,
      $container->get('entity_type.manager')->getStorage($entity_type->id()),
      $container->get('date.formatter'),
      $container->get('redirect.destination')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function render() {
    $build['table'] = parent::render();

    $total = $this->getStorage()
      ->getQuery()
      ->count()
      ->execute();

    $build['summary']['#markup'] = $this->t('Total External branches: @total', ['@total' => $total]);

    if ($total && ExternalDocumentation::totalCount() > 0) {
      $build['footer']['#markup'] = '<hr />' . $this->t('<a class="@classes" href="@url">Generated documentation</a>', [
        '@url' => Url::fromRoute('entity.external_documentation.collection')->toString(),
        '@classes' => 'button button--secondary',
      ]);
    }

    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['external_branch'] = $this->t('External Branch');
    $header['function_list'] = $this->t('Function list');
    $header['core_compatibility'] = $this->t('Core compatibility');
    $header['type'] = $this->t('Type');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /** @var \Drupal\api\Interfaces\ExternalBranchInterface $entity */
    $row['external_branch'] = $entity->toLink();
    $row['function_list'] = Link::fromTextAndUrl(
      $this->t('Function list'),
      Url::fromUri($entity->getFunctionList(), [
        'attributes' => [
          'target' => '_blank',
        ],
      ])
    );
    $row['core_compatibility'] = $entity->getCoreCompatibility();
    $row['type'] = $entity->getType();
    return $row + parent::buildRow($entity);
  }

  /**
   * {@inheritdoc}
   */
  protected function getDefaultOperations(EntityInterface $entity) {
    $operations = parent::getDefaultOperations($entity);
    $destination = $this->redirectDestination->getAsArray();
    foreach ($operations as $key => $operation) {
      $operations[$key]['query'] = $destination;
    }
    return $operations;
  }

}
