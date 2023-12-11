<?php

namespace Drupal\api\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates the Multiple Paths Exist constraint.
 */
class ApiValidFilePathsConstraintValidator extends ConstraintValidator {

  /**
   * {@inheritdoc}
   */
  public function validate($value, Constraint $constraint) {
    if (empty($value)) {
      return;
    }

    $paths = array_filter(explode(PHP_EOL, $value));
    foreach ($paths as $path) {
      $path = trim($path);
      $path = rtrim($path, '/\\');
      if (!is_dir($path)) {
        $this->context->addViolation($constraint->message, [
          '%link_path' => $path,
        ]);
      }
    }
  }

}
