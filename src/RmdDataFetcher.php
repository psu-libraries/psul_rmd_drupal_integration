<?php

declare(strict_types=1);

namespace Drupal\psul_rmd_drupal_integration;

use Drupal\Component\Utility\Html;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Logger\LoggerChannelTrait;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;

/**
 * Class to fetch data from remote metadata database.
 */
class RmdDataFetcher implements RmdDataFetcherInterface {
  use LoggerChannelTrait;

  /**
   * Cache tags.
   */
  protected array $cacheTags = ['rmd_data'];

  /**
   * Configs.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   *   The configuration object.
   */
  protected ImmutableConfig $configs;

  /**
   * Constructs a RmdDataFetcher object.
   */
  public function __construct(
    private readonly CacheBackendInterface $cacheData,
    private readonly ClientInterface $httpClient,
    private readonly ConfigFactoryInterface $configFactory,
  ) {
    $this->configs = $this->configFactory->get('psul_rmd_drupal_integration.settings');
  }

  /**
   * {@inheritdoc}
   */
  public function addCacheTags(array $tags): void {
    $this->cacheTags = array_merge($tags, $this->cacheTags);
  }

  /**
   * {@inheritdoc}
   */
  public function getProfileData(string $username, string $attribute = ''): array|string {
    $this->addCacheTags(['rmd_data:profile:' . $username]);
    $data = $this->fetchData("users/{$username}/profile", $username);

    if (empty($data)) {
      return [];
    }

    if ($attribute) {
      return $data['attributes'][$attribute] ?? [];
    }

    return $data;
  }

  /**
   * {@inheritdoc}
   */
  public function getProfilePublications(string $username): array {
    $this->addCacheTags(['rmd_data:profile:' . $username]);
    $data = $this->fetchData("users/{$username}/profile", $username);

    $output = [];

    if (empty($data)) {
      return $output;
    }

    $publicationKeys = $this->configs->get('publications_display') ?? [];
    foreach ($publicationKeys as $key) {
      if (!empty($data['attributes'][$key])) {
        $output[$key] = [
          'title' => self::FIELDS[$key],
          'id' => Html::getUniqueId('RMD ' . self::FIELDS[$key]),
          'content' => [
            '#theme' => 'psul_rmd_publications',
            '#items' => $data['attributes'][$key],
          ],
        ];
      }
    }

    return $output;
  }

  /**
   * {@inheritdoc}
   */
  public function getOrgs(bool $flatten = TRUE): array {
    $this->addCacheTags(['rmd_data:orgs']);
    $data = $this->fetchData('organizations', 'orgs');

    if (!$flatten) {
      return $data ?? [];
    }

    $flat = [];
    foreach ($data as $org) {
      $flat[$org['id']] = $org['attributes']['name'];
    }

    sort($flat);
    return $flat ?? [];
  }

  /**
   * {@inheritdoc}
   */
  public function getOrgPublications(int $org_id, int $count = 50, int $offset = 0): array {
    $this->addCacheTags(['rmd_data:org_publications:' . $org_id]);
    $cache_key = "org:{$org_id}:{$count}:{$offset}";
    $data = $this->fetchData("organizations/{$org_id}/publications?offset={$offset}&limit={$count}", $cache_key);
    $formatted_data = [];

    foreach ($data as $item) {
      $formatted_data[] = $item['attributes'];
    }

    return $formatted_data ?? [];
  }

  /**
   * Fetch data from the remote metadata database.
   *
   * @param string $endpoint
   *   API endpoint to fetch data from (e.g. 'users/{user}/profile', 'orgs').
   * @param string $cache_key
   *   Unique cache key identifier for this request.
   *
   * @return array|null
   *   The fetched data or NULL.
   */
  protected function fetchData(string $endpoint, string $cache_key): array|null {
    $data = [];

    // Return the cached data if it exists.
    $cache_id = "psul_rmd_data:{$cache_key}";
    if ($cache = $this->cacheData->get($cache_id)) {
      $this->resetCacheTags();
      return $cache->data;
    }

    try {
      $url = $this->configs->get('api_url') ?? 'https://metadata.libraries.psu.edu/v1/';
      $url .= $endpoint;

      $response = $this->httpClient->request('GET', $url, [
        'headers' => [
          'accept' => 'application/json',
          'X-API-Key' => $this->configs->get('api_key') ?? '',
        ],
      ]);

      $data = $response->getBody()->getContents();
      $data = json_decode($data, TRUE);
      $data = $data['data'] ?? [];
      $this->cacheData->set(
        $cache_id,
        $data,
        time() + ($this->configs->get('cache_ttl') ?? 86400),
        $this->cacheTags,
      );
      $this->resetCacheTags();
    }
    catch (GuzzleException | \Exception $e) {
      $data = [];
      if ($e->getCode() === 404 && (str_contains($e->getMessage(), 'not found') || str_contains($e->getMessage(), 'Not Found'))) {
        $this->cacheData->set(
          $cache_id,
          $data,
          time() + ($this->configs->get('cache_ttl') ?? 86400),
          $this->cacheTags,
        );
        $this->resetCacheTags();
        return $data;
      }
      $this->getLogger('psul_rmd_drupal_integration')->error($e->getMessage());
    }

    return $data;
  }

  /**
   * Reset the cache tags array.
   */
  protected function resetCacheTags(): void {
    $this->cacheTags = ['rmd_data'];
  }

}
