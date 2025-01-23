<?php

declare(strict_types=1);

namespace Drupal\psul_rmd_drupal_integration\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configure PSU Libraries Research Metadata Database (RMD) Drupal Integration settings for this site.
 */
final class SettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'psul_rmd_drupal_integration_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return ['psul_rmd_drupal_integration.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['api_url'] = [
      '#type' => 'url',
      '#title' => $this->t('API URL'),
      '#default_value' => $this->config('psul_rmd_drupal_integration.settings')->get('api_url') ?? 'https://metadata.libraries.psu.edu/v1/',
      '#required' => TRUE,
      '#description' => $this->t('URL to the API endpoint. This should end with a slash.  The API documentation can be found at <a href="https://metadata.libraries.psu.edu/api_docs">https://metadata.libraries.psu.edu/api_docs</a>.'),
    ];

    $form['api_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('API Key'),
      '#default_value' => $this->config('psul_rmd_drupal_integration.settings')->get('api_key') ?? '',
      '#description' => $this->t('API key to access the API. This is currently not required to pull profile data.'),
    ];

    $form['cache_ttl'] = [
      '#type' => 'number',
      '#title' => $this->t('Cache TTL'),
      '#default_value' => $this->config('psul_rmd_drupal_integration.settings')->get('cache_ttl') ?? 172800,
      '#required' => TRUE,
      '#description' => $this->t('Time in seconds to cache data from the API. Default is 172800 seconds (2 days).'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $api_url = $form_state->getValue('api_url');
    if (!filter_var($api_url, FILTER_VALIDATE_URL) || substr($api_url, -1) !== '/') {
      $form_state->setErrorByName('api_url', $this->t('The API URL must be a valid URL and end with a slash.'));
    }
    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $this->config('psul_rmd_drupal_integration.settings')
      ->set('api_url', $form_state->getValue('api_url'))
      ->set('api_key', $form_state->getValue('api_key'))
      ->set('cache_ttl', $form_state->getValue('cache_ttl'))
      ->save();
    parent::submitForm($form, $form_state);
  }

}
