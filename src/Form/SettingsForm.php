<?php

declare(strict_types=1);

namespace Drupal\psul_rmd_drupal_integration\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure PSU Libraries RMD Drupal Integration settings for this site.
 */
final class SettingsForm extends ConfigFormBase {

  /**
   * {@inheritDoc}
   */
  public function __construct(
    protected EntityFieldManagerInterface $entityFieldManager,
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * {@inheritDoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_field.manager'),
      $container->get('entity_type.manager')
    );
  }

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
      '#config_target' => 'psul_rmd_drupal_integration.settings:api_url',
    ];

    $form['api_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('API Key'),
      '#description' => $this->t('API key to access the API. This is currently not required to pull profile data.'),
      '#config_target' => 'psul_rmd_drupal_integration.settings:api_key',
    ];

    $form['cache_ttl'] = [
      '#type' => 'number',
      '#title' => $this->t('Cache TTL'),
      '#default_value' => $this->config('psul_rmd_drupal_integration.settings')->get('cache_ttl') ?? 172800,
      '#required' => TRUE,
      '#description' => $this->t('Time in seconds to cache data from the API. Default is 172800 seconds (2 days).'),
      '#config_target' => 'psul_rmd_drupal_integration.settings:cache_ttl',
    ];

    $content_types = $this->entityTypeManager->getStorage('node_type')->loadMultiple();
    $options = [];
    foreach ($content_types as $content_type) {
      $options[$content_type->id()] = $content_type->label();
    }

    $form['attached'] = [
      '#type' => 'details',
      '#description' => $this->t('Expose the RMD data as extra fields on a specific content type.'),
      '#title' => $this->t('Content Settings'),
      '#open' => TRUE,
    ];

    $default_content_type = $this->config('psul_rmd_drupal_integration.settings')->get('attached_content_type') ?? '';

    $form['attached']['attached_content_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Content Type'),
      '#options' => $options,
      '#empty_option' => $this->t('- Select a Content Type -'),
      '#default_value' => $default_content_type,
      '#required' => FALSE,
      '#description' => $this->t('Specify the content type which should have RMD data added as extra fields.'),
      '#ajax' => [
        'callback' => '::updateUsernameFieldOptions',
        'event' => 'change',
        'wrapper' => 'attached-username-field-wrapper',
      ],
      '#config_target' => 'psul_rmd_drupal_integration.settings:attached_content_type',
    ];

    $form['attached']['attached_username_field'] = [
      '#type' => 'select',
      '#title' => $this->t('Username Field'),
      '#options' => $this->getUsernameFieldOptions($default_content_type),
      '#default_value' => $this->config('psul_rmd_drupal_integration.settings')->get('attached_username_field') ?? '',
      '#required' => FALSE,
      '#description' => $this->t('Specify the field on the node where the username is stored.'),
      '#prefix' => '<div id="attached-username-field-wrapper">',
      '#suffix' => '</div>',
      '#states' => [
        'visible' => [
          ':input[name="attached_content_type"]' => ['!value' => ''],
        ],
        'required' => [
          ':input[name="attached_content_type"]' => ['!value' => ''],
        ],
      ],
      '#validated' => TRUE,
      '#config_target' => 'psul_rmd_drupal_integration.settings:attached_username_field',
    ];

    $form['publications'] = [
      '#type' => 'details',
      '#description' => $this->t('Configure how publications should be displayed.'),
      '#title' => $this->t('Publication Settings'),
      '#open' => TRUE,
    ];

    $form['publications']['publications_display'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Publications to display'),
      '#options' => [
        'publications' => $this->t('Publications'),
        'grants' => $this->t('Grants'),
        'presentations' => $this->t('Presentations'),
        'performances' => $this->t('Performances'),
        'master_advising_roles' => $this->t('Master Advising Roles'),
        'phd_advising_roles' => $this->t('PhD Advising Roles'),
        'other_publications' => $this->t('Other Publications'),
        'news_stories' => $this->t('News Stories'),
      ],
      '#default_value' => $this->config('psul_rmd_drupal_integration.settings')->get('publications_display') ?? [],
      '#config_target' => 'psul_rmd_drupal_integration.settings:publications_display',
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * Ajax callback to set the username field options for selected content type.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state object.
   *
   * @return array
   *   Updated form object.
   */
  public function updateUsernameFieldOptions(array &$form, FormStateInterface $form_state): array {
    $content_type = $form_state->getValue('attached_content_type');
    $form['attached']['attached_username_field']['#options'] = $this->getUsernameFieldOptions($content_type);
    return $form['attached']['attached_username_field'];
  }

  /**
   * Get the username field options for the selected content type.
   *
   * @param string $content_type
   *   The content type.
   *
   * @return array
   *   Fields avaiable on the content type.
   */
  protected function getUsernameFieldOptions($content_type): array {
    $options = [];
    if ($content_type) {
      $fields = $this->entityFieldManager->getFieldDefinitions('node', $content_type);
      foreach ($fields as $field_name => $field_definition) {
        $options[$field_name] = $field_definition->getLabel();
      }
    }
    return $options;
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
      ->set('attached_content_type', $form_state->getValue('attached_content_type'))
      ->set('attached_username_field', $form_state->getValue('attached_username_field'))
      ->set('publications_display', $form_state->getValue('publications_display'))
      ->save();
    parent::submitForm($form, $form_state);
  }

}
