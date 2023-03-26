<?php

namespace Drupal\api\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * Validation constraint for regular expressions.
 *
 * @Constraint(
 *   id = "ApiValidRegex",
 *   label = @Translation("Valid regex", context = "Validation"),
 * )
 */
class ApiValidRegexConstraint extends Constraint {

  /**
   * The default violation message.
   *
   * @var string
   */
  public $message = 'The regular expresion %regex is invalid.';

}
