<?php

declare(strict_types=1);

namespace Drupal\psul_rmd_drupal_integration;

/**
 * Interface for fetching data from the remote metadata database.
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
   * @return array
   *   The data from the remote metadata database or empty array.
   */
  public function getProfileData(string $username, string $attribute = ''): array;

  /**
   * Return publication data from RMD.
   *
   * @param string $username
   *   The username to fetch data for.
   *
   * @return array
   *   The publication data from the remote metadata database or empty array.
   */
  public function getProfilePublications(string $username): array;

}
