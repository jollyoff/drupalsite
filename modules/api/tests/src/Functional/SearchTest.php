<?php

namespace Drupal\Tests\api\Functional;

/**
 * Tests the API module search functionality.
 */
class SearchTest extends WebPagesBase {

  /**
   * Tests search functionality.
   */
  public function testSearch() {
    // Test a search that should go directly to the single result.
    $this->drupalGet('api/' . $this->branchInfo['project'] . '/' . $this->branchInfo['branch_name'] . '/search');
    $this->submitForm(['search' => 'sample_function'], 'Search');
    $this->assertUrlContains('api/' . $this->branchInfo['project'] . '/sample.php/function/sample_function', 'Search went directly to sample_function page');

    // Try it with the URL rather than visiting the search page.
    $this->drupalGet('api/' . $this->branchInfo['project'] . '/' . $this->branchInfo['branch_name'] . '/search/sample_function');
    $this->assertUrlContains('api/' . $this->branchInfo['project'] . '/sample.php/function/sample_function', 'Search went directly to sample_function page');

    // Now try it with a search that should have multiple results.
    $this->drupalGet('api/' . $this->branchInfo['project'] . '/' . $this->branchInfo['branch_name'] . '/search');
    $this->submitForm(['search' => 'foo'], 'Search');
    $this->assertUrlContains('api/' . $this->branchInfo['project'] . '/' . $this->branchInfo['branch_name'] . '/search/foo', 'Search went to search results listing page');
    $this->assertSession()->linkNotExists('Other projects', 'Link to other projects is not present since there are no others');
    $this->assertTitleContains('Search for foo', 'Search result listing has right title');
    $this->assertSession()->responseContains('Search for foo');
    $this->assertSession()->linkExists('foo_sample_name', 0, 'foo_sample_name is listed');
    $this->assertSession()->linkExists('Sample2InSubDir::foo2', 0, 'Sample2InSubdir::foo2 is listed');
    $this->assertSession()->linkExists('Sample::foo', 0, 'Sample::foo is listed');
    $this->assertSession()->linkExists('SampleInSubDir::foo', 0, 'SampleInSubDir::foo is listed');
    $this->assertSession()->linkExists('SampleInSubDir::$foo', 0, 'SampleInSubDir::$foo is listed');

    // Turn on the API search block and Visit a invalid path then
    // Search through the block to see if it redirects to the result.
    $this->drupalPlaceBlock('api_search_block');
    $this->clearCache();

    $this->drupalGet('api/' . $this->branchInfo['project'] . '/sample.php/function/foo_sample_name');
    $this->submitForm(['search' => 'sample_function'], 'Search');
    $this->assertUrlContains('api/' . $this->branchInfo['project'] . '/sample.php/function/sample_function', 'Search went directly to sample_function page from a invalid path');
  }

}
