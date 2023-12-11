<?php

namespace Drupal\Tests\api\Functional;

/**
 * Tests that legacy redirects are correct.
 */
class LegacyTest extends TestBase {

  /**
   * Tests that legacy URL redirects are correct.
   */
  public function testApiLegacy() {
    $tests = [
      // Tests for functions.
      [
        'legacy_url' => 'api/function/sample_function',
        'redirect' => 'api/test/sample.php/function/sample_function/6',
      ],
      [
        'legacy_url' => 'api/function/sample_function/6',
        'redirect' => 'api/test/sample.php/function/sample_function/6',
      ],
      // Tests for constants.
      [
        'legacy_url' => 'api/constant/SAMPLE_CONSTANT',
        'redirect' => 'api/test/sample.php/constant/SAMPLE_CONSTANT/6',
      ],
      [
        'legacy_url' => 'api/constant/SAMPLE_CONSTANT/6',
        'redirect' => 'api/test/sample.php/constant/SAMPLE_CONSTANT/6',
      ],
      // Tests for globals.
      [
        'legacy_url' => 'api/global/sample_global',
        'redirect' => 'api/test/sample.php/global/sample_global/6',
      ],
      [
        'legacy_url' => 'api/global/sample_global/6',
        'redirect' => 'api/test/sample.php/global/sample_global/6',
      ],
      // Tests for topics.
      [
        'legacy_url' => 'api/group/samp_GRP-6.x',
        'redirect' => 'api/test/sample.php/group/samp_GRP-6.x/6',
      ],
      [
        'legacy_url' => 'api/group/samp_GRP-6.x/6',
        'redirect' => 'api/test/sample.php/group/samp_GRP-6.x/6',
      ],

      // Listing legacy pages.
      [
        'legacy_url' => 'api/groups',
        'redirect' => 'api/test/groups/6',
      ],
      [
        'legacy_url' => 'api/groups/6',
        'redirect' => 'api/test/groups/6',
      ],
      [
        'legacy_url' => 'api/constants',
        'redirect' => 'api/test/constants/6',
      ],
      [
        'legacy_url' => 'api/constants/6',
        'redirect' => 'api/test/constants/6',
      ],
      [
        'legacy_url' => 'api/groups',
        'redirect' => 'api/test/groups/6',
      ],
      [
        'legacy_url' => 'api/groups/6',
        'redirect' => 'api/test/groups/6',
      ],
      [
        'legacy_url' => 'api/files',
        'redirect' => 'api/test/files/6',
      ],
      [
        'legacy_url' => 'api/files/6',
        'redirect' => 'api/test/files/6',
      ],
      [
        'legacy_url' => 'api/functions',
        'redirect' => 'api/test/functions/6',
      ],
      [
        'legacy_url' => 'api/functions/6',
        'redirect' => 'api/test/functions/6',
      ],
    ];

    foreach ($tests as $test) {
      $this->drupalGet($test['legacy_url']);
      $this->assertSession()->addressEquals($test['redirect']);
    }
  }

}
