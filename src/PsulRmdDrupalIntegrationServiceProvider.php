<?php

namespace Drupal\psul_rmd_drupal_integration;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;

/**
 * Class RmdDataServiceProvider.
 *
 * @package Drupal\psul_rmd_drupal_integration
 */
class PsulRmdDrupalIntegrationServiceProvider extends ServiceProviderBase {

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container) {
    try {
      $container->getDefinition('cache.backend.permanent_database');
    }
    catch (ServiceNotFoundException $exception) {
      // The cache.rmd_data service depends on the pcb cache backend,
      // since this might not exist for the updates from beta2
      // we remove the service so that the update hook can run.
      $container->removeDefinition('cache.rmd_data');
    }
  }

}
