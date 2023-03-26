<?php

namespace Drupal\drupalsite_core\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Block\Annotation\Block;

/**
 * Class MenuUserAnonymousBlock
 *
 * @package Drupal\drupalsite_core\Plugin\Block
 * @Block(
 *   id="menu_user_anonymous",
 *   admin_label="Menu User Anonymous",
 *   category="DrupalSite"
 * )
 */
class MenuUserAnonymousBlock extends BlockBase {
  public function build() {
    return[
      '#theme' => 'menu_user_anonymous'
    ];
  }

}
