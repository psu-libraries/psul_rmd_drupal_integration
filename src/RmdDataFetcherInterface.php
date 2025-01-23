<?php

declare(strict_types=1);

namespace Drupal\psul_rmd_drupal_integration;

/**
 * @todo Add interface description.
 */
interface RmdDataFetcherInterface {

  /**
   * Get data from the remote metadata database.
   *
   * @param string $username
   *   The username to fetch data for.
   * @param string $attribute
   *   Return a specific attribute from the data.  Optional.
   *
   * @return
   */
  public function getProfileData(string $username, string $attribute = ''): array;

}
