<?php

namespace Drupal\drupalsite_core\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Block\Annotation\Block;

/**
 * Class DsSocialNetworkBlock
 *
 * @package Drupal\drupalsite_core\Plugin\Block
 * @Block(
 *   id="ds_social_network",
 *   admin_label="Social Network",
 *   category="DrupalSite"
 * )
 */
class DsSocialNetworkBlock extends BlockBase {
  public function build() {
    return[
      '#theme' => 'ds_social_network'
    ];
  }
}
