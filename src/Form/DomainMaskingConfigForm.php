<?php

namespace Drupal\pantheon_domain_masking\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class DomainMaskingConfigForm.
 */
class DomainMaskingConfigForm extends ConfigFormBase {

  /**
   * Drupal\Core\Config\ConfigFactoryInterface definition.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Constructs a new DomainMaskingConfigForm object.
   */
  public function __construct(ConfigFactoryInterface $config_factory) {
    parent::__construct($config_factory);
    $this->configFactory = $config_factory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'pantheon_domain_masking.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'domain_masking_config_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('pantheon_domain_masking.settings');

    $form['enabled'] = [
      '#type' => 'radios',
      '#title' => $this->t('Enable domain masking?'),
      '#description' => $this->t('Once the module is enabled, all request will pass through this middleware. Use this to toggle the middleware on and off while configuring the domain.'),
      '#options' => [
        'yes' => $this->t('Enabled'),
        'no' => $this->t('Disabled'),
      ],
      '#default_value' => $config->get('enabled'),
    ];

    $form['domain'] = [
      '#type' => 'textfield',
      '#length' => 255,
      '#title' => $this->t('Public-facing domain:'),
      '#description' => $this->t('The public-facing domain name this site will respond to. Do not include the scheme.'),
      '#default_value' => $config->get('domain'),
    ];

    $pantheonEnv = $_ENV['PANTHEON_ENVIRONMENT'] ?: '[env]';
    $pantheonSiteName = $_ENV['PANTHEON_SITE_NAME'] ?: '[site-name]';
    $form['allow_platform'] = [
      '#type' => 'radios',
      '#title' => $this->t('Allow Platform domain access?'),
      '#description' => $this->t('Enable this option to allow un-masked access to %url.', [
        '%url' => $pantheonEnv . '-' . $pantheonSiteName . '.pantheonsite.io',
      ]),
      '#options' => [
        'yes' => $this->t('Allow'),
        'no' => $this->t('Do not allow'),
      ],
      '#default_value' => $config->get('allow_platform'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);

    // TODO: this actually fails if the user followed directions.
    // $host = $this->validateHost($form_state->getValue('domain'));
    // if ($host === FALSE) {
    //   $form_state->setErrorByName('domain', $this->t('Invalid domain specified.'));
    // }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);

    // Get the normalized host value.
    $host = $this->validateHost($form_state->getValue('domain'));

    $this->config('pantheon_domain_masking.settings')
      // ->set('domain', $host)
      ->set('domain', $form_state->getValue('domain'))
      ->set('enabled', $form_state->getValue('enabled'))
      ->set('allow_platform', $form_state->getValue('allow_platform'))
      ->save();
  }

  /**
   * Validate that the user passed a correct host value.
   *
   * @param string $userInput
   *   The input from the user.
   *
   * @return string|bool
   *   The validated host if available, or FALSE if the input is invalid.
   */
  public function validateHost($userInput) {
    $parsed = \parse_url($userInput);
    if (!isset($parsed['host'])) {
      return FALSE;
    }
    return $parsed['host'];
  }

}
