<?php

namespace Drupal\Tests\api\Functional;

use Drupal\Core\Url;

/**
 * Simple tests to ensure that the base set up is correct.
 *
 * @group api
 */
class LoadTest extends TestBase {

  /**
   * Tests that the home page as well as other routes load with a 200 response.
   */
  public function testLoad() {
    $branch = $this->getBranch();

    $this->drupalGet(Url::fromRoute('<front>'));
    $this->assertSession()->statusCodeEquals(200);

    $this->drupalGet('admin/config/development/api');
    $this->assertSession()->statusCodeEquals(200);

    $this->drupalGet('api');
    $this->assertSession()->statusCodeEquals(200);

    $this->drupalGet('api/projects');
    $this->assertSession()->statusCodeEquals(200);

    $this->drupalGet('api/' . $branch->getProject()->getSlug());
    $this->assertSession()->statusCodeEquals(200);

    $this->drupalGet('api/' . $branch->getProject()->getSlug() . '/' . $branch->getSlug());
    $this->assertSession()->statusCodeEquals(200);

    $this->drupalGet('api/' . $branch->getProject()->getSlug() . '/classes');
    $this->assertSession()->statusCodeEquals(200);

    $this->drupalGet('api/' . $branch->getProject()->getSlug() . '/classes/' . $branch->getSlug());
    $this->assertSession()->statusCodeEquals(200);
  }

  /**
   * Tests redirects to the default branch when it is not present in the URL.
   */
  public function testRedirectsToDefaultBranch() {
    $branch = $this->getBranch();

    $this->drupalGet('api/' . $branch->getProject()->getSlug() . '/classes');
    $this->assertSession()->addressEquals('api/' . $branch->getProject()->getSlug() . '/classes/' . $branch->getSlug());

    $this->drupalGet('api/' . $branch->getProject()->getSlug() . '/classes.php/class/Sample');
    $this->assertSession()->addressEquals('api/' . $branch->getProject()->getSlug() . '/classes.php/class/Sample/' . $branch->getSlug());

    $this->drupalGet('api/' . $branch->getProject()->getSlug() . '/classes.php/interface/SampleInterface');
    $this->assertSession()->addressEquals('api/' . $branch->getProject()->getSlug() . '/classes.php/interface/SampleInterface/' . $branch->getSlug());

    $this->drupalGet('api/' . $branch->getProject()->getSlug() . '/groups');
    $this->assertSession()->addressEquals('api/' . $branch->getProject()->getSlug() . '/groups/' . $branch->getSlug());
  }

}
