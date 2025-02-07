<?php

declare(strict_types=1);

namespace Drupal\psul_rmd_drupal_integration\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\psul_rmd_drupal_integration\RmdDataFetcherInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a PSU Libraries RMD Drupal Integration form.
 */
final class RmdProfileTestForm extends FormBase {

  /**
   * RMD Data Fetcher.
   *
   * @var \Drupal\psul_rmd_drupal_integration\RmdDataFetcherInterface
   */
  protected RmdDataFetcherInterface $fetcher;

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'psul_rmd_drupal_integration_rmd_profile_test';
  }

  /**
   * {@inheritdoc}
   */
  public function __construct(RmdDataFetcherInterface $fetcher) {
    $this->fetcher = $fetcher;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): RmdProfileTestForm {
    return new self($container->get('psul_rmd_drupal_integration.fetcher'));
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {

    $form['username'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Username'),
      '#required' => TRUE,
      '#description' => $this->t('Enter a username to fetch profile data from the Researcher Metadata Database.'),
    ];

    $form['actions'] = [
      '#type' => 'actions',
      'submit' => [
        '#type' => 'submit',
        '#value' => $this->t('Submit'),
      ],
    ];

    // Display the profile data if it exists.
    // This is set on form submission.
    if ($form_state->get('profile_data')) {
      $form['profile_data'] = [
        '#weight' => 10,
        '#type' => 'markup',
        '#markup' => $this->t('Profile data: <pre style="font-size:small;">@data</pre>', ['@data' => print_r($form_state->get('profile_data'), TRUE)]),
      ];
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   *
   * Get the profile data for the username and rebuild the form.  The data will
   * be displayed on the form.
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $username = $form_state->getValue('username');
    $profile_data = $this->fetcher->getProfileData($username);

    if ($profile_data) {
      $form_state->set('profile_data', $profile_data);
    }
    else {
      $form_state->set('profile_data', 'Usernmame not found');
    }

    $form_state->setRebuild();
  }

}
