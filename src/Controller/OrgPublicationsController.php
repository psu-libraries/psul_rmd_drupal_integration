<?php

declare(strict_types=1);

namespace Drupal\psul_rmd_drupal_integration\Controller;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Controller\ControllerBase;
use Drupal\psul_rmd_drupal_integration\RmdDataFetcherInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Returns responses for PSU Libraries Hours routes.
 */
class OrgPublicationsController extends ControllerBase {

  /**
   * The controller constructor.
   */
  public function __construct(
    private readonly RmdDataFetcherInterface $rmdDataFetcher,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new self(
      $container->get('psul_rmd_drupal_integration.fetcher'),
    );
  }

  /**
   * Build a page to display publications for an organization from RMD.
   *
   * @param int $org_id
   *   Organization ID.
   */
  public function orgPublicationsPage(int $org_id): array {
    // dpm($org_id);
    $publications = $this->rmdDataFetcher->getOrgPublications($org_id);

    $build = [
    //   '#theme' => 'table',
    //   '#items' => $publications,
    ];

    dpm($publications);

    $cache_metadata = new CacheableMetadata();
    $cache_metadata->addCacheTags([
      'rmd_data',
      'rmd_data:org_publications:' . $org_id,
    ]);
    $cache_metadata->applyTo($build);

    return $build;
  }

  /**
   * Page title callback for organization publications page.
   */
  public function orgPublicationsPageTitle(int $org_id): string {
    $orgs = $this->rmdDataFetcher->getOrgs();

    if (isset($orgs[$org_id])) {
      return $orgs[$org_id] . ' Publications';
    }

    throw new NotFoundHttpException();
  }

}
