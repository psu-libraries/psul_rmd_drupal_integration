# PSU Libraries Researcher Metadata Database (RMD) Integration

Integrate a Drupal site with the PSU Libraries [Researcher Metadata Database](https://metadata.libraries.psu.edu/). The intent of this module is to allow data to be pulled from the RMD API and expose that data to Drupal.

Currently, only the User profile API endpoint has been implemented.  Other endpoints will require an API key to use.

## Setup

Use the following steps to add an enable the module.  We will make the module public and register it in packagist at a later date.


```bash
composer config repositories.psul_rmd_drupal_integration github https://github.com/psu-libraries/psul_rmd_drupal_integration;
composer require psul-libraries/psul_rmd_drupal_integration`;
drush en psul_rmd_drupal_integration;
```

## Usage

### User Profile Data
The user profile API endpoint does not require and API key but other

```php
$username = 'hna2';

// Fetch user publications using the RmdDataFetcher service.
$rmd_data_fetcher = \Drupal::service('rmd_data_fetcher');
$publications = $rmd_data_fetcher->getProfilePublications($username);

$publications = $rmd_data_fetcher->getProfilePublications($username);

// Get All data unformatted.
$rmd_data = $rmd_data_fetcher->getProfileData($username);

// Get specific attribute.
$orcid_url = $rmd_data_fetcher->getProfileData($username, 'orcid_identifier');
$bio = $rmd_data_fetcher->getProfileData($username, 'bio');
```
