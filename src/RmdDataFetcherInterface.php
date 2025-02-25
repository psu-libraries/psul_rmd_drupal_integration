<?php

declare(strict_types=1);

namespace Drupal\psul_rmd_drupal_integration;

/**
 * Interface for fetching data from the remote metadata database.
 */
interface RmdDataFetcherInterface {

  /**
   * Fields to fetch from the remote metadata database.
   *
   * @var array
   */
  const FIELDS = [
    "name" => "Name",
    "organization_name" => "Organization Name",
    "title" => "Title",
    "email" => "Email",
    "office_location" => "Office Location",
    "office_phone_number" => "Office Phone Number",
    "personal_website" => "Personal Website",
    "total_scopus_citations" => "Total Scopus Citations",
    "scopus_h_index" => "Scopus H-Index",
    "pure_profile_url" => "Pure Profile URL",
    "orcid_identifier" => "ORCID Identifier",
    "bio" => "About Me",
    "teaching_interests" => "Teaching Interests",
    "research_interests" => "Research Interests",
    "publications" => "Publications",
    "other_publications" => "Other Publications",
    "grants" => "Grants",
    "presentations" => "Presentations",
    "performances" => "Performances",
    "master_advising_roles" => "Master Advising Roles",
    "phd_advising_roles" => "PhD Advising Roles",
    "news_stories" => "News Stories",
    "education_history" => "Education History",
  ];

  /**
   * Get data from the remote metadata database.
   *
   * @param string $username
   *   The username to fetch data for.
   * @param string $attribute
   *   Return a specific attribute from the data.  Optional.
   *
   * @return array|string
   *   The data from the remote metadata database or empty array.
   */
  public function getProfileData(string $username, string $attribute = ''): array|string;

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

  /**
   * Add cache tag.
   *
   * @param array $tags
   *   Additional cache tags to add to data caches.
   */
  public function addCacheTags(array $tags): void;

}
