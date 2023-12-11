<?php

namespace Drupal\api\Controller;

use Drupal\api\Entity\DocBlock;
use Drupal\api\Utilities;
use Drupal\Component\Utility\Xss;
use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Drupal\Core\Url;

/**
 * OpenSearch endpoints.
 *
 * @package Drupal\api\Controller
 */
class OpenSearchController extends ControllerBase {

  /**
   * Utilities service.
   *
   * @var \Drupal\api\Utilities
   */
  protected $utilities;

  /**
   * Construct method.
   *
   * @param \Drupal\api\Utilities $utilities
   *   Utilities service.
   */
  public function __construct(Utilities $utilities) {
    $this->utilities = $utilities;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $utilities = $container->get('api.utilities');
    return new static($utilities);
  }

  /**
   * Page callback: Prints out OpenSearch plugin discovery XML output.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Request object.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   XML page output response.
   *
   * @see https://developer.mozilla.org/en/Creating_OpenSearch_plugins_for_Firefox
   */
  public function page(Request $request) {
    $config = $this->config('api.settings');

    $short_name = $config->get('opensearch_name') ?? $this->t('Drupal API');
    $description = $config->get('opensearch_description') ?? $this->t('Drupal API documentation');
    $search_url = Url::fromRoute('api.search.global', [], ['absolute' => TRUE])->toString() . '/{searchTerms}';
    $suggest_url = Url::fromRoute('api.opensearch.suggest', ['term' => 'SEARCH_TERM'], ['absolute' => TRUE])->toString() . '/{searchTerms}';
    $suggest_url = str_replace('SEARCH_TERM/', '', $suggest_url);
    $self_url = Url::fromRoute('api.opensearch', [], ['absolute' => TRUE])->toString();
    $image = theme_get_setting('favicon.path') ?: 'internal:/core/misc/favicon.ico';
    $favicon_path = Url::fromUri($image, ['absolute' => TRUE])->toString();

    // Define the XML content.
    $openTag = new \SimpleXMLElement('<OpenSearchDescription></OpenSearchDescription>');
    $openTag->addAttribute('xmlns', 'http://a9.com/-/spec/opensearch/1.1/');
    $openTag->addChild('ShortName', $short_name);
    $openTag->addChild('Description', $description);

    $faviconUrl = $openTag->addChild('Image', $favicon_path);
    $faviconUrl->addAttribute('width', '16');
    $faviconUrl->addAttribute('height', '16');
    $faviconUrl->addAttribute('type', 'image/x-icon');

    $searchUrl = $openTag->addChild('Url');
    $searchUrl->addAttribute('type', 'text/html');
    $searchUrl->addAttribute('method', 'get');
    $searchUrl->addAttribute('rel', 'results');
    $searchUrl->addAttribute('template', $search_url);

    $suggestionsUrl = $openTag->addChild('Url');
    $suggestionsUrl->addAttribute('type', 'application/x-suggestions+json');
    $suggestionsUrl->addAttribute('rel', 'suggestions');
    $suggestionsUrl->addAttribute('template', $suggest_url);

    $selfUrl = $openTag->addChild('Url');
    $selfUrl->addAttribute('type', 'application/opensearchdescription+xml');
    $selfUrl->addAttribute('rel', 'self');
    $selfUrl->addAttribute('template', $self_url);

    return new Response(
      $openTag->asXML(),
      Response::HTTP_OK,
      ['Content-Type' => 'text/xml']
    );
  }

  /**
   * Page callback: Prints JSON-formatted potential matches for OpenSearch.
   *
   * @param string $term
   *   The string to search for.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Request object.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   Suggest results.
   *
   * @see http://www.opensearch.org/Specifications/OpenSearch/Extensions/Suggestions/1.0
   */
  public function suggest($term, Request $request) {
    $results = [];
    $term = Xss::filter($term);
    $matches = DocBlock::searchByTitle($term);
    if (!empty($matches)) {
      $matches = DocBlock::loadMultiple($matches);
      foreach ($matches as $match) {
        $results[] = $match->getTitle();
      }
    }

    return new JsonResponse([$term, array_values(array_unique($results))]);
  }

}
