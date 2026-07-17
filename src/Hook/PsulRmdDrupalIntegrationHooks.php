<?php

namespace Drupal\psul_rmd_drupal_integration\Hook;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\psul_rmd_drupal_integration\RmdDataFetcherInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Hook implementations for psul_rmd_drupal_integration.
 */
class PsulRmdDrupalIntegrationHooks {
  use StringTranslationTrait;

  /**
   * The module settings.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $settings;

  public function __construct(
    private readonly ConfigFactoryInterface $configFactory,
    private readonly RmdDataFetcherInterface $rmdDataFetcher,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly ModuleHandlerInterface $moduleHandler,
    #[Autowire(service: 'cache.rmd_data')]
    private readonly CacheBackendInterface $cacheBackend,
  ) {
    $this->settings = $this->configFactory->get('psul_rmd_drupal_integration.settings');
  }

  /**
   * Implements hook_theme().
   */
  #[Hook('theme')]
  public function theme($existing, $type, $theme, $path) {
    return [
      'psul_rmd_publications' => [
        'variables' => [
          'items' => [],
        ],
        'template' => 'psul-rmd-publications',
      ],
      'psul_rmd_item' => [
        'variables' => [
          'title' => '',
          'content' => '',
          'type' => '',
          'profile_name' => '',
        ],
        'template' => 'psul-rmd-item',
      ],
      'psul_rmd_item__orcid_identifier' => [
        'variables' => [
          'title' => '',
          'content' => '',
          'profile_name' => '',
        ],
        'template' => 'psul-rmd-item--orcid-identifier',
      ],
    ];
  }

  /**
   * Implements hook_theme_suggestions_HOOK_alter() for psul_rmd_item.
   */
  #[Hook('theme_suggestions_psul_rmd_item_alter')]
  public function themeSuggestionsPsulRmdItemAlter(array &$suggestions, array $variables) {
    if (!empty($variables['type'])) {
      $suggestions[] = 'psul_rmd_item__' . $variables['type'];
    }
  }

  /**
   * Implements hook_entity_extra_field_info().
   */
  #[Hook('entity_extra_field_info')]
  public function entityExtraFieldInfo(): array {
    $extra = [];
    $content_type = $this->settings->get('attached_content_type');
    $username_field = $this->settings->get('attached_username_field');
    if ($content_type && $username_field) {
      foreach (RmdDataFetcherInterface::FIELDS as $key => $label) {
        $extra['node'][$content_type]['display']['rmd_data_' . $key] = [
          'label' => $this->t('RMD Data: @label', [
            '@label' => $label,
          ]),
          'description' => $this->t('@label from the Researcher Metadata Database API.', [
            '@label' => $label,
          ]),
          'weight' => 100,
          'visible' => FALSE,
        ];
      }
      $extra['node'][$content_type]['display']['rmd_publications'] = [
        'label' => $this->t('RMD Data: All Publications'),
        'description' => $this->t('Publications from the RMD API.'),
        'weight' => 100,
        'visible' => FALSE,
      ];
    }
    return $extra;
  }

  /**
   * Implements hook_entity_presave().
   */
  #[Hook('entity_presave')]
  public function entityPresave(EntityInterface $entity) {
    $username = $this->shouldHaveRmdData($entity);
    if ($username) {
      $cache_id = 'psul_rmd_data:profile:' . $entity->label();
      $this->cacheBackend->delete($cache_id);
    }
  }

  /**
   * Implements hook_preprocess_node().
   */
  #[Hook('preprocess_node')]
  public function preprocessNode(array &$variables) {
    $username = $this->shouldHaveRmdData($variables['node']);
    if (!$username) {
      return;
    }
    // Check if any rmd_ fields are displayed for the current view mode.
    $view_mode = $variables['view_mode'];
    $display = $this->entityTypeManager->getStorage('entity_view_display')->load('node.' . $variables['node']->getType() . '.' . $view_mode);
    // Use the default display if no display is defined for the view mode.
    if (!$display) {
      $display = $this->entityTypeManager->getStorage('entity_view_display')->load('node.' . $variables['node']->getType() . '.default');
      if (!$display) {
        return;
      }
    }
    /** @var \Drupal\Core\Entity\Entity\EntityViewDisplay $display */
    $fields = $display->get('content');
    $rmd_fields_displayed = array_filter(array_keys($fields), function ($field_name) {
      return str_starts_with($field_name, 'rmd_');
    });
    if (empty($rmd_fields_displayed)) {
      // There are no RMD fields displayed on this view mode.
      return;
    }
    // Add hook to allow others to skip adding RMD data to a node.
    $skip_node = FALSE;
    $this->moduleHandler->alter('psul_rmd_drupal_integration_preprocess_node', $skip_node, $variables['node']);
    if ($skip_node) {
      return;
    }
    // Fetch RMD data using the username.
    $this->rmdDataFetcher->addCacheTags([
      'node:' . $variables['node']->id(),
    ]);
    $rmd_data = $this->rmdDataFetcher->getProfileData($username);
    // Skip if no data is returned.
    if (!$rmd_data || empty($rmd_data['attributes'])) {
      return;
    }
    // Add the RMD data to the node variables.
    foreach (RmdDataFetcherInterface::FIELDS as $key => $label) {
      if (in_array('rmd_data_' . $key, $rmd_fields_displayed) && !empty($rmd_data['attributes'][$key])) {
        $variables['content']['rmd_data_' . $key] = [
          '#theme' => 'psul_rmd_item',
          '#type' => $key,
          '#title' => $label,
          '#content' => $rmd_data['attributes'][$key] ?? '',
          '#profile_name' => $rmd_data['attributes']['name'],
        ];
      }
    }
    // Add cache tags and parameters to the render array so that we can
    // clear rmd_data specifically and bust page caches when ttl is
    // reached.
    $variables['#cache']['tags'][] = 'rmd_data:profile:' . $username;
    if (empty($variables['#cache']['max-age'])) {
      $variables['#cache']['max-age'] = $this->settings->get('cache_ttl') ?? 172800;
    }
    if (isset($rmd_fields_displayed['publications'])) {
      $variables['content']['rmd_publications'] = $this->rmdDataFetcher->getProfilePublications($username);
    }
  }

  /**
   * Checks if the given entity should have RMD data.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to check.
   *
   * @return string|null
   *   The username if the entity should have RMD data, or NULL otherwise.
   */
  protected function shouldHaveRmdData(EntityInterface $entity): ?string {
    $content_type = $this->settings->get('attached_content_type');
    $username_field = $this->settings->get('attached_username_field');

    // Skip if the content type or username field are not set.
    if (!$content_type || !$username_field) {
      return NULL;
    }

    // Skip if the current entity is not the correct content type or does not have
    // the username field.
    if (!($entity instanceof FieldableEntityInterface) || $entity->bundle() !== $content_type || !$entity->hasField($username_field)) {
      return NULL;
    }

    // Get the username from the entity and return NULL if it is not found.
    $username = $entity->get($username_field)->getString();
    return $username ?: NULL;
  }

}
