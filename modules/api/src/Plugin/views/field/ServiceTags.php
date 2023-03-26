<?php

namespace Drupal\api\Plugin\views\field;

use Drupal\api\Entity\DocBlock\DocReference;
use Drupal\views\Plugin\views\display\DisplayPluginBase;
use Drupal\views\Plugin\views\field\PrerenderList;
use Drupal\views\ViewExecutable;

/**
 * Implementation of the service_tags field plugin.
 *
 * @ViewsField("service_tags")
 */
class ServiceTags extends PrerenderList {

  /**
   * {@inheritdoc}
   */
  public function init(ViewExecutable $view, DisplayPluginBase $display, array &$options = NULL) {
    parent::init($view, $display, $options);
    $this->additional_fields['docblock'] = [
      'table' => 'api_branch_docblock',
      'field' => 'id',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function query() {
    $this->addAdditionalFields();
  }

  /**
   * {@inheritdoc}
   */
  public function preRender(&$values) {
    // This is mostly borrowed from the taxonomy handler.
    $this->field_alias = $this->aliases['docblock'];
    $ids = [];
    foreach ($values as $result) {
      if (!empty($result->{$this->aliases['docblock']})) {
        $ids[] = $result->{$this->aliases['docblock']};
      }
    }

    if ($ids) {
      $result = DocReference::getServiceTags($ids);
      if ($result) {
        $tags = DocReference::loadMultiple($result);
        foreach ($tags as $tag) {
          /** @var \Drupal\api\Interfaces\DocBlock\DocReferenceInterface $tag */
          $this->items[$tag->getDocBlock()->id()][$tag->getObjectName()]['name'] = $tag->getObjectName();
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  // @codingStandardsIgnoreLine
  public function render_item($count, $item) {
    return $item['name'];
  }

}
