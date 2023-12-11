<?php

namespace Drupal\api\Plugin\views\argument;

use Drupal\api\Formatter;
use Drupal\views\Plugin\views\argument\StringArgument;

/**
 * Basic filename handler to process arguments that may have '/' in them.
 *
 * @ViewsArgument("file_name")
 */
class FilenameArgument extends StringArgument {

  /**
   * {@inheritdoc}
   */
  public function query($group_by = FALSE) {
    // Reverse the '/' character that was done when calling the view.
    // https://www.drupal.org/project/drupal/issues/672606
    $this->argument = Formatter::getReplacementName($this->argument, 'file', TRUE);
    parent::query($group_by);
  }

}
