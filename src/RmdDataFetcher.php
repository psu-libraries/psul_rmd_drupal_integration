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
   * Publication types.
   *
   * @var array
   */
  protected $publicationKeys = [
    'publications' => 'Publications',
    'grants' => 'Grants',
    'presentations' => 'Presentations',
    'performances' => 'Performances',
    'master_advising_roles' => 'Master Advising Roles',
    'phd_advising_roles' => 'PhD Advising Roles',
    'other_publications' => 'Other Publications',
  ];

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
    $data = $this->fetchUserData($username);

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
    $data = $this->fetchUserData($username);

    $output = [];

    if (empty($data)) {
      return $output;
    }

    $publicationKeys = $this->configs->get('publications_display') ?? [];
    foreach ($publicationKeys as $key) {
      if (!empty($data['attributes'][$key])) {
        $output[$key] = [
          'title' => $this->publicationKeys[$key],
          'id' => Html::getUniqueId('RMD ' . $this->publicationKeys[$key]),
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
   * Fetch user data from the remote metadata database.
   *
   * @param string $username
   *   Username to fetch data for.
   * @param string $endpoint
   *   The endpoint to fetch data from.
   *
   * @return array|null
   *   The user data or NULL.
   */
  protected function fetchUserData(string $username, string $endpoint = 'profile'): array|null {
    $data = [];

    // Return the cached data if it exists.
    $cache_id = "psul_rmd_data:{$endpoint}:{$username}";
    if ($cache = $this->cacheData->get($cache_id)) {
      $this->resetCacheTags();
      return $cache->data;
    }

    try {
      $url = $this->configs->get('api_url') ?? 'https://metadata.libraries.psu.edu/v1/';
      $url .= "users/{$username}/{$endpoint}";
      $response = $this->httpClient->request('GET', $url, [
        'headers' => [
          'accept' => 'application/json',
        ],
      ]);

      $data = $response->getBody()->getContents();
      $data = json_decode($data, TRUE);
      $data = $data['data'] ?? [];
      $this->cacheData->set(
        $cache_id,
        $data,
        time() + $this->configs->get('cache_ttl') ?? 86400,
        $this->cacheTags,
      );
      $this->resetCacheTags();
    }
    catch (GuzzleException | \Exception $e) {
      $data = [];
      if ($e->getCode() === 404 && str_contains($e->getMessage(), 'User not found')) {
        $this->cacheData->set(
          $cache_id,
          $data,
          time() + $this->configs->get('cache_ttl') ?? 86400,
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
