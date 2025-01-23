<?php

declare(strict_types=1);

namespace Drupal\psul_rmd_drupal_integration;

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
   * @var array
   *
   * @var array
   */
  protected $data = [];

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
  public function getProfileData(string $username, string $attribute = ''): array {
    $this->fetchUserData($username);

    if (!isset($this->data['data'])) {
      return [];
    }

    if ($attribute) {
      return $this->data['data'][$attribute] ?? [];
    }

    return $this->data['data'] ?? [];
  }

  /**
   * Fetch user data from the remote metadata database.
   */
  protected function fetchUserData(string $username, string $endpoint = 'profile') {
    if (isset($this->data['data'])) {
      return $this->data;
    }

    $cache_id = "psul_rmd_data:{$endpoint}:{$username}";
    if ($cache = $this->cacheData->get($cache_id)) {
      return $cache->data;
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

      $this->cacheData->set(
        $cache_id,
        $data,
        time() + $config->get('cache_ttl') ?? 172800
      );

      $this->data = $data;
    }
    catch (GuzzleException | \Exception $e) {
      $this->data = ['data' => []];
      $this->getLogger('psul_rmd_drupal_integration')->error($e->getMessage());
    }
  }




}
