<?php

namespace Drupal\weather_block\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a 'WeatherBlock' block.
 *
 * @Block(
 *   id = "weather_block",
 *   admin_label = @Translation("Weather block"),
 * )
 */
class WeatherBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    $form = \Drupal::formBuilder()->getForm('\Drupal\weather_block\Form\CityForm');
    $output = '<div>';
    $output .= '<img src="../../../../../../themes/custom/drupalsite/assets/images/5538410.png" alt="Weather Image" />';
    $output .= '</div>';

    return [
      '#markup' => $output,
      '#prefix' => render($form),
    ];
  }

}
