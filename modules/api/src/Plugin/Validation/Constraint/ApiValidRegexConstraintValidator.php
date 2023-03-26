<?php

namespace Drupal\api\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates the ApiValidRegex constraint.
 */
class ApiValidRegexConstraintValidator extends ConstraintValidator {

  /**
   * {@inheritdoc}
   */
  public function validate($value, Constraint $constraint) {
    if (empty($value)) {
      return;
    }

    $regular_expressions = array_filter(explode(PHP_EOL, $value));
    foreach ($regular_expressions as $regex) {
      $regex = trim($regex);
      // Validate by calling preg_match() and checking for error.
      if (@preg_match($regex, 'a') === FALSE) {
        $this->context->addViolation($constraint->message, [
          '%regex' => $regex,
        ]);
      }
    }
  }

}
