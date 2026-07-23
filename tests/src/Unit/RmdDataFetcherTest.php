<?php

declare(strict_types=1);

namespace Drupal\Tests\psul_rmd_drupal_integration\Unit;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\psul_rmd_drupal_integration\RmdDataFetcher;
use Drupal\Tests\UnitTestCase;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

/**
 * Tests for the RMD data fetcher service.
 */
#[Group('psul_rmd_drupal_integration')]
#[CoversClass(RmdDataFetcher::class)]
final class RmdDataFetcherTest extends UnitTestCase {

  /**
   * Cache backend mock.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $cacheBackend;

  /**
   * HTTP client mock.
   *
   * @var \GuzzleHttp\ClientInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $httpClient;

  /**
   * Config factory mock.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $configFactory;

  /**
   * Config mock.
   *
   * @var \Drupal\Core\Config\ImmutableConfig|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $config;

  /**
   * Config values returned by the mock config object.
   */
  protected array $configValues;

  /**
   * Fetcher under test.
   */
  protected RmdDataFetcher $fetcher;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->cacheBackend = $this->createMock(CacheBackendInterface::class);
    $this->httpClient = $this->createMock(ClientInterface::class);
    $this->configFactory = $this->createMock(ConfigFactoryInterface::class);
    $this->config = $this->createMock(ImmutableConfig::class);

    $this->configValues = [
      'api_url' => 'https://metadata.example/v1/',
      'cache_ttl' => 3600,
      'publications_display' => ['publications', 'other_publications'],
    ];

    $this->configFactory->method('get')
      ->with('psul_rmd_drupal_integration.settings')
      ->willReturn($this->config);

    $this->config->method('get')
      ->willReturnCallback(fn (string $key) => $this->configValues[$key] ?? NULL);

    $this->fetcher = new RmdDataFetcher(
      $this->cacheBackend,
      $this->httpClient,
      $this->configFactory,
    );
  }

  /**
   * Tests cached profile data avoids an HTTP request.
   */
  #[Test]
  public function getProfileDataReturnsCachedAttributeWithoutHttpRequest(): void {
    $profile = $this->loadFixtureData('staff-profile.data.json');

    $this->cacheBackend->expects($this->once())
      ->method('get')
      ->with('psul_rmd_data:profile:msh6004')
      ->willReturn((object) ['data' => $profile]);

    $this->httpClient->expects($this->never())
      ->method('request');

    $result = $this->fetcher->getProfileData('msh6004', 'email');

    $this->assertSame('msh6004@psu.edu', $result);
  }

  /**
   * Tests profile data is fetched and cached from the API response.
   */
  #[Test]
  public function getProfileDataFetchesAndCachesFixtureResponse(): void {
    $profile = $this->loadFixtureData('faculty-profile.data.json');

    $this->cacheBackend->expects($this->once())
      ->method('get')
      ->with('psul_rmd_data:profile:hna2')
      ->willReturn(FALSE);

    $this->httpClient->expects($this->once())
      ->method('request')
      ->with('GET', 'https://metadata.example/v1/users/hna2/profile', [
        'headers' => [
          'accept' => 'application/json',
        ],
      ])
      ->willReturn(new Response(200, [], json_encode(['data' => $profile], JSON_THROW_ON_ERROR)));

    $this->cacheBackend->expects($this->once())
      ->method('set')
      ->with(
        'psul_rmd_data:profile:hna2',
        $profile,
        $this->callback(function (int $expiration): bool {
          $now = time();
          return $expiration >= $now + 3590 && $expiration <= $now + 3610;
        }),
        ['rmd_data:profile:hna2', 'rmd_data'],
      );

    $result = $this->fetcher->getProfileData('hna2', 'education_history');

    $this->assertSame($profile['attributes']['education_history'], $result);
  }

  /**
   * Tests profile data returns the full payload when no attribute is requested.
   */
  #[Test]
  public function getProfileDataReturnsAllDataWithoutAttribute(): void {
    $profile = $this->loadFixtureData('faculty-profile.data.json');

    $this->cacheBackend->expects($this->once())
      ->method('get')
      ->with('psul_rmd_data:profile:hna2')
      ->willReturn((object) ['data' => $profile]);

    $this->httpClient->expects($this->never())
      ->method('request');

    $result = $this->fetcher->getProfileData('hna2');

    $this->assertSame($profile, $result);
  }

  /**
   * Tests configured publication sections are returned with render arrays.
   */
  #[Test]
  public function getProfilePublicationsReturnsConfiguredPublicationSections(): void {
    $profile = $this->loadFixtureData('faculty-profile.data.json');

    $this->cacheBackend->expects($this->once())
      ->method('get')
      ->with('psul_rmd_data:profile:hna2')
      ->willReturn(FALSE);

    $this->httpClient->expects($this->once())
      ->method('request')
      ->willReturn(new Response(200, [], json_encode(['data' => $profile], JSON_THROW_ON_ERROR)));

    $this->cacheBackend->expects($this->once())
      ->method('set');

    $result = $this->fetcher->getProfilePublications('hna2');

    $this->assertSame(['publications', 'other_publications'], array_keys($result));
    $this->assertSame('Publications', $result['publications']['title']);
    $this->assertSame('rmd-publications', $result['publications']['id']);
    $this->assertSame('psul_rmd_publications', $result['publications']['content']['#theme']);
    $this->assertSame($profile['attributes']['publications'], $result['publications']['content']['#items']);

    $this->assertSame('Other Publications', $result['other_publications']['title']);
    $this->assertSame('rmd-other-publications', $result['other_publications']['id']);
    $this->assertSame($profile['attributes']['other_publications'], $result['other_publications']['content']['#items']);
  }

  /**
   * Tests a not-found user is cached as an empty result.
   */
  #[Test]
  public function getProfileDataCachesEmptyArrayForUserNotFound(): void {
    $this->cacheBackend->expects($this->once())
      ->method('get')
      ->with('psul_rmd_data:profile:missing-user')
      ->willReturn(FALSE);

    $this->httpClient->expects($this->once())
      ->method('request')
      ->willThrowException(new ClientException(
        'User not found',
        new Request('GET', 'https://metadata.example/v1/users/missing-user/profile'),
        new Response(404),
      ));

    $this->cacheBackend->expects($this->once())
      ->method('set')
      ->with(
        'psul_rmd_data:profile:missing-user',
        [],
        $this->callback(function (int $expiration): bool {
          $now = time();
          return $expiration >= $now + 3590 && $expiration <= $now + 3610;
        }),
        ['rmd_data:profile:missing-user', 'rmd_data'],
      );

    $result = $this->fetcher->getProfileData('missing-user');

    $this->assertSame([], $result);
  }

  /**
   * Loads a fixture file and returns the decoded profile data payload.
   */
  protected function loadFixtureData(string $fixture): array {
    $contents = file_get_contents(dirname(__DIR__, 2) . '/data/' . $fixture);
    $this->assertNotFalse($contents);

    $decoded = json_decode($contents, TRUE, 512, JSON_THROW_ON_ERROR);

    return $decoded['data'];
  }

}
