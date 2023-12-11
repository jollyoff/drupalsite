<?php

namespace Drupal\api\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * Validation constraint for valid system paths.
 *
 * @Constraint(
 *   id = "ApiValidFilePaths",
 *   label = @Translation("Valid paths", context = "Validation"),
 * )
 */
class ApiValidFilePathsConstraint extends Constraint {

  /**
   * The default violation message.
   *
   * @var string
   */
  public $message = 'Either the path %link_path is invalid or you do not have access to it.';

}
