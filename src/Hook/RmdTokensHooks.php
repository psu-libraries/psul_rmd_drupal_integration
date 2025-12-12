<?php

namespace Drupal\psul_rmd_drupal_integration\Hook;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Utility\Token;
use Drupal\psul_rmd_drupal_integration\RmdDataFetcherInterface;

/**
 * Add Tokens to nodes with RMD data.
 */
class RmdTokensHooks {
  use StringTranslationTrait;

  /**
   * The module settings.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $settings;

  /**
   * Constructs the plugin instance.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   * @param \Drupal\psul_rmd_drupal_integration\RmdDataFetcherInterface $rmdDataFetcher
   *   The RMD data fetcher service.
   * @param \Drupal\Core\Utility\Token $token
   *   The token service.
   */
  public function __construct(
    private readonly ConfigFactoryInterface $configFactory,
    private readonly RmdDataFetcherInterface $rmdDataFetcher,
    private readonly Token $token,
  ) {
    $this->settings = $this->configFactory->get('psul_rmd_drupal_integration.settings');
  }

  /**
   * Implements hook_token_info_alter().
   */
  #[Hook('token_info')]
  public function tokensInfo(): array {
    if (!$this->settings->get('attached_content_type')) {
      return [];
    }

    // Define the RMD Data token type.
    $types['rmd-data'] = [
      'name' => $this->t('RMD Data'),
      'description' => $this->t('Tokens related to RMD (Research Metadata) profile data.'),
      'needs-data' => 'node',
    ];

    // Define individual field tokens under the RMD Data type.
    $rmd_tokens = [];
    foreach (RmdDataFetcherInterface::FIELDS as $key => $label) {
      if (in_array($key, RmdDataFetcherInterface::PUBLICATION_FIELDS)) {
        // Skip publication fields until we determine how these could be used.
        continue;
      }
      $rmd_tokens[$key] = [
        'name' => $label,
        'description' => $this->t('RMD field: @label', ['@label' => $label]),
      ];
    }

    // Add the parent RMD Data token to the node type.
    $node_tokens['rmd-data'] = [
      'name' => $this->t('RMD Data'),
      'description' => $this->t('RMD profile data for this node.'),
      'type' => 'rmd-data',
    ];

    return [
      'types' => $types,
      'tokens' => [
        'node' => $node_tokens,
        'rmd-data' => $rmd_tokens,
      ],
    ];
  }

  /**
   * Implements hook_tokens().
   */
  #[Hook('tokens')]
  public function tokens($type, $tokens, array $data, array $options, BubbleableMetadata $bubbleable_metadata): array {
    $replacements = [];

    // Handle node:rmd-data chaining.
    if ($type == 'node' && !empty($data['node'])) {
      /** @var \Drupal\node\NodeInterface $node */
      $node = $data['node'];

      // Only process nodes of the configured content type.
      if ($this->settings->get('attached_content_type') == $node->bundle()) {
        if ($rmd_tokens = $this->token->findWithPrefix($tokens, 'rmd-data')) {
          $username_field = $this->settings->get('attached_username_field');
          $username = $node->get($username_field)->value;
          $rmd_data = $this->rmdDataFetcher->getProfileData($username);

          $replacements += $this->token->generate('rmd-data', $rmd_tokens, ['rmd-data' => $rmd_data], $options, $bubbleable_metadata);
        }
      }
    }

    // Handle rmd-data tokens directly.
    if ($type == 'rmd-data' && !empty($data['rmd-data'])) {
      $rmd_data = $data['rmd-data'];

      foreach ($tokens as $name => $original) {
        if (isset(RmdDataFetcherInterface::FIELDS[$name]) && isset($rmd_data['attributes'][$name])) {
          if (is_array($rmd_data['attributes'][$name])) {
            // Some items are a simple array so separate them with a comma.
            $replacements[$original] = implode(", ", $rmd_data['attributes'][$name]);
          }
          else {
            $replacements[$original] = $rmd_data['attributes'][$name];
          }
        }
      }
    }
    return $replacements;
  }

}
