<?php

declare(strict_types=1);

namespace Drupal\psul_rmd_drupal_integration;

use Drupal\Component\Utility\Html;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Logger\LoggerChannelTrait;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\ClientInterface;

/**
 * Class to fetch data from remote metadata database.
 */
class RmdDataFetcher implements RmdDataFetcherInterface {
  use LoggerChannelTrait;

  /**
   * Data fetched from the remote metadata database.
   *
   * This will be keyed to the $username to prevent data collisions.
   *
   * @var array
   */
  protected $data = [];

  /**
   * Cache tags.
   */
  protected array $cacheTags = ['rmd_data'];

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
  ) {}

  /**
   * {@inheritdoc}
   */
  public function addCacheTags(array $tags): void {
    $this->cacheTags = array_merge($tags, ['rmd_data']);
  }

  /**
   * {@inheritdoc}
   */
  public function getProfileData(string $username, string $attribute = ''): array|string {
    $this->addCacheTags(['rmd_data:profile:' . $username]);
    $this->fetchUserData($username);

    if (!isset($this->data[$username])) {
      return [];
    }

    if ($attribute) {
      return $this->data[$username]['attributes'][$attribute] ?? [];
    }

    return $this->data[$username] ?? [];
  }

  /**
   * {@inheritdoc}
   */
  public function getProfilePublications(string $username): array {
    $this->addCacheTags(['rmd_data:profile:' . $username]);
    $this->fetchUserData($username);

    $data = $this->data[$username] ?? [];
    $output = [];

    foreach ($this->publicationKeys as $key => $label) {

      if (!empty($data['attributes'][$key])) {
        $output[$key] = [
          'title' => $label,
          'id' => Html::getUniqueId('RMD ' . $label),
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
   */
  protected function fetchUserData(string $username, string $endpoint = 'profile'): void {
    if (isset($this->data[$username])) {
      return;
    }

    $cache_id = "psul_rmd_data:{$endpoint}:{$username}";
    if ($cache = $this->cacheData->get($cache_id)) {
      $this->data[$username] = $cache->data;
      return;
    }

    $config = $this->configFactory->get('psul_rmd_drupal_integration.settings');

    try {
      $url = $config->get('api_url') ?? 'https://metadata.libraries.psu.edu/v1/';
      $url .= "users/{$username}/{$endpoint}";
      $response = $this->httpClient->request('GET', $url, [
        'headers' => [
          'accept' => 'application/json',
        ],
      ]);

      $data = $response->getBody()->getContents();
      $data = json_decode($data, TRUE);
      $this->data[$username] = $data['data'] ?? [];
      $this->cacheData->set(
        $cache_id,
        $this->data[$username],
        time() + $config->get('cache_ttl') ?? 86400,
        $this->cacheTags,
      );
    }
    catch (GuzzleException | \Exception $e) {
      $this->data = [$username => []];
      if ($e->getCode() === 404 && str_contains($e->getMessage(), 'User not found')) {
        $this->cacheData->set(
          $cache_id,
          [$username => []],
          time() + $config->get('cache_ttl') ?? 86400,
          $this->cacheTags,
        );
        return;
      }
      $this->getLogger('psul_rmd_drupal_integration')->error($e->getMessage());
    }
  }

}
