<?php

namespace Drupal\Tests\api\Functional;

/**
 * Tests API pages access functionality.
 */
class ApiAccessTest extends WebPagesBase {

  /**
   * Tests access functionality.
   */
  public function testAccess() {
    // Should redirect to default branch and project if we have permissions.
    $this->drupalGet('api');
    $this->assertSession()->statusCodeEquals(200);
    $this->drupalGet('api/projects');
    $this->assertSession()->statusCodeEquals(200);
    $this->drupalGet('api/test');
    $this->assertSession()->statusCodeEquals(200);

    // Should show Page not found if we do not have permissions.
    $this->drupalLogout();
    $this->drupalGet('api');
    $this->assertSession()->statusCodeEquals(403);
    $this->drupalGet('api/projects');
    $this->assertSession()->statusCodeEquals(403);
    $this->drupalGet('api/test');
    $this->assertSession()->statusCodeEquals(403);

    // Should now redirect to default as the permission was granted.
    $this->drupalLogout();
    $this->allowAnonymousUsersToSeeApiPages();
    $this->drupalGet('api');
    $this->assertSession()->statusCodeEquals(200);
    $this->drupalGet('api/projects');
    $this->assertSession()->statusCodeEquals(200);
    $this->drupalGet('api/test');
    $this->assertSession()->statusCodeEquals(200);
  }

}
