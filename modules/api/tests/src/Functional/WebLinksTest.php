<?php

namespace Drupal\Tests\api\Functional;

/**
 * Tests that links are generated correctly on API pages.
 */
class WebLinksTest extends WebPagesBase {

  /**
   * Runs all the tests in this section, so setUp() doesn't have to run again.
   */
  public function testAll() {
    $this->verifyInCodeLinks();
    $this->verifyDocumentationLinks();
    $this->verifyClassLinks();
    $this->verify404();
  }

  /**
   * Tests that links are generated correctly in code.
   */
  protected function verifyInCodeLinks() {
    // Test a bunch of links on the sample_in_code_links page.
    $links = [
      'sample_function' => 'sample.php/function/sample_function',
      'sample_one' => 'sample.php/function/theme_sample_one',
      'sample_two' => 'sample.php/function/theme_sample_two',
      'sample_three' => 'sample-three.tpl.php',
      'sample_four__option' => 'sample.php/function/theme_sample_four',
      'sample_name' => 'sample.php/function/hook_sample_name',
      'another_sample' => 'sample.php/function/hook_another_sample_alter',
      'duplicate_function' => $this->branchInfo['project'] . '/' . $this->branchInfo['branch_name'] . '/search/duplicate_function',
      'SubSample' => 'classes.php/class/SubSample',
      'SAMPLE_CONSTANT' => 'sample.php/constant/SAMPLE_CONSTANT',
      'Samples' => 'sample.php/group/samp_GRP-6.x/',
      'sample_global' => 'sample.php/global/sample_global',
    ];

    $this->drupalGet('api/' . $this->branchInfo['project'] . '/sample.php/function/sample_in_code_links');

    foreach ($links as $text => $url) {
      $this->assertLinkUrlSubstring($text, $url, 'Link to ' . $text . ' exists', $text . ' link went to right place');
    }

    // Test for text that should be there but not linked.
    $no_links = [
      'nonexistent_hook',
      'nonexistent_theme_hook',
      'nonexistent_alter_name',
      'title',
      'nonexistent_global',
    ];

    $this->drupalGet('api/' . $this->branchInfo['project'] . '/sample.php/function/sample_in_code_links');
    foreach ($no_links as $text) {
      $this->assertSession()->responseContains($text);
      $this->assertSession()->linkNotExists($text, $text . ' is not a link');
    }
  }

  /**
   * Tests that links are generated correctly in documentation.
   */
  protected function verifyDocumentationLinks() {
    // Test a variety of links on the documentation page for sample_function().
    $links = [
      'duplicate_function' => $this->branchInfo['project'] . '/' . $this->branchInfo['branch_name'] . '/search/duplicate_function',
      'http://example.com' => 'http://example.com',
      'this is a link for the parameter' => 'http://php.net',
      'SAMPLE_CONSTANT' => 'sample.php/constant/SAMPLE_CONSTANT',
      'Samples' => 'sample.php/group/samp_GRP-6.x',
    ];

    $this->drupalGet('api/' . $this->branchInfo['project'] . '/sample.php/function/sample_function');
    foreach ($links as $text => $url) {
      $this->assertLinkUrlSubstring($text, $url, 'Link to ' . $text . ' exists', $text . ' link went to right place');
    }

    // Test some links on the duplicate_function() page.
    $links = [
      'Subscribers' => 'sample.php/group/samp_GRP-6.x',
      'newsletters (categories)' => 'classes.php/group/class_samples',
      'subscription' => 'sample.php/group/samp_GRP-6.x',
      'newsletter issues' => 'classes.php/group/class_samples',
    ];

    $this->drupalGet('api/' . $this->branchInfo['project'] . '/sample.php/function/duplicate_function');
    foreach ($links as $text => $url) {
      $this->assertLinkUrlSubstring($text, $url, 'Link to ' . $text . ' exists', $text . ' link went to right place');
    }
  }

  /**
   * Tests a variety of class member links.
   */
  protected function verifyClassLinks() {
    $pages = [
      'Sample::foo' => 'api/' . $this->branchInfo['project'] . '/classes.php/function/Sample::foo',
      'Sample::baz' => 'api/' . $this->branchInfo['project'] . '/classes.php/function/Sample::baz',
      'SubSample::bar' => 'api/' . $this->branchInfo['project'] . '/classes.php/function/SubSample::bar',
    ];

    $links = [
      [
        'page' => 'Sample::foo',
        'text' => 'baz',
        'url' => 'classes.php/function/Sample',
      ],
      [
        'page' => 'Sample::foo',
        'text' => 'property',
        'url' => 'classes.php/property/Sample',
      ],
      [
        'page' => 'Sample::foo',
        'text' => 'CONSTANT',
        'url' => 'classes.php/constant/Sample',
      ],
      [
        'page' => 'Sample::baz',
        'text' => 'foo',
        'url' => 'classes.php/function/Sample',
      ],
      [
        'page' => 'Sample::baz',
        'text' => 'bar',
        'url' => $this->branchInfo['project'] . '/' . $this->branchInfo['branch_name'] . '/search/bar',
      ],
      [
        'page' => 'SubSample::bar',
        'text' => 'foo',
        'url' => 'classes.php/function/Sample',
      ],
      [
        'page' => 'SubSample::bar',
        'text' => 'baz',
        'url' => 'classes.php/function/Sample',
      ],
    ];

    foreach ($links as $item) {
      $this->drupalGet($pages[$item['page']]);
      $this->assertLinkUrlSubstring($item['text'], $item['url'], 'Link to ' . $item['text'] . ' exists', $item['text'] . ' link went to right place');
    }

    $no_links = [
      [
        'page' => 'Sample::foo',
        'text' => 'bar',
      ],
    ];

    foreach ($no_links as $item) {
      $this->drupalGet($pages[$item['page']]);
      $this->assertSession()->responseContains($item['text']);
      $this->assertSession()->linkNotExists($item['text'], $item['text'] . ' is not a link');
    }
  }

  /**
   * Tests that the 'apis' path can be set to be the 404 handler.
   */
  protected function verify404() {
    $this->allowAnonymousUsersToSeeApiPages();
    $this->clearCache();

    // Set the 404 handler to be the 'apis' path.
    $this->drupalGet('admin/config/system/site-information');
    $this->submitForm([
      'site_frontpage' => '/api/projects',
      'site_404' => '/apis',
    ], 'Save configuration');
    $this->assertSession()->responseContains('The configuration options have been saved');

    // Try to go to path 'sample_function' and verify it redirects to the
    // function page.
    $this->drupalGet('sample_function');
    $this->assertUrlContains('sample.php/function/sample_function', 'sample_function 404 went to right page');
    $this->assertSession()->responseContains('This is a sample list');

    // Try to go to the path 'foobarbaz' and verify it redirects to a search
    // page.
    $this->drupalGet('foobarbaz');
    $this->assertSession()->responseContains('cannot be found');
    $this->assertSession()->responseContains('Sorry');
    $this->assertSession()->responseContains('foobarbaz');
  }

}
