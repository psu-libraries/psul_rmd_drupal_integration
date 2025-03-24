# PSU Libraries Researcher Metadata Database (RMD) Integration

Integrate a Drupal site with the PSU Libraries [Researcher Metadata Database](https://metadata.libraries.psu.edu/) (RMD). The intent of this module is to allow data to be pulled from the RMD API and expose that data to Drupal.

Currently, only the User profile API endpoint has been implemented.  Other endpoints will require an API key to use.

## Setup

Use the following steps to add an enable the module.  We will make the module public and register it in packagist at a later date.


```bash
composer config repositories.psul_rmd_drupal_integration github https://github.com/psu-libraries/psul_rmd_drupal_integration;
composer require psul-libraries/psul_rmd_drupal_integration`;
drush en psul_rmd_drupal_integration;
```

### Configuration

Go to **Configuration > Web Services > PSU Libraries: RMD Settings** to configure the module.

- **API URL**: The base URL for API requests.  You should not need to use this.
- **API Key**: The API Key is not required for Profile data but may be required to pull in future data.
- **Cache TTL**: Set how long the RMD Data should be cached (in seconds)
- **Content Settings**: The RMD data can be exposed as Extra Fields on nodes.  This will allow the data to be placed using Display settings or layout builder.

## Usage

### User Profile Data
The user profile API endpoint does not require an API key.

```php
$username = 'hna2';

// Fetch user publications using the RmdDataFetcher service.
$rmd_data_fetcher = \Drupal::service('psul_rmd_drupal_integration.fetcher');
$publications = $rmd_data_fetcher->getProfilePublications($username);

// Get All data unformatted.
$rmd_data = $rmd_data_fetcher->getProfileData($username);

// Get specific attribute.
$orcid_url = $rmd_data_fetcher->getProfileData($username, 'orcid_identifier');
$bio = $rmd_data_fetcher->getProfileData($username, 'bio');
```

### Data Caching
This modules use the Permanent Cache Bin module aggressively cache the data
from RMD.

You can clear the RMD cached data a couple of ways.  *Note:* The rmd_data cache will be cleared
for individual nodes upon save.

**Drush**

```bash
drush pcbf rmd_data
```
**UI**

Go to `/admin/config/development/performance` and click the "Clear permament cache for rmd_data" button.

**Programmatically**

```php
\Drupal::service('cache.rmd_data')->deleteAllPermanent();
```
