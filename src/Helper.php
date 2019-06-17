<?php

namespace Drupal\pantheon_domain_masking;

use Drupal\Core\Config\ConfigFactory;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\DependencyInjection\ContainerInterface;

class Helper {

  /**
   * The site settings.
   *
   * @var \Drupal\Core\Config\ConfigFactory
   */
  protected $configFactory;

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * {@inheritdoc}
   */
  public function __construct(ConfigFactory $config_factory, RequestStack $request_stack) {
    $this->configFactory = $config_factory;
    $this->requestStack = $request_stack;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('request_stack')
    );
  }

  /**
   * Determine whether the request is coming from a platform domain.
   *
   * @return boolean
   */
  public function isPlatformDomainRequest() {
    $req = $this->requestStack->getCurrentRequest();
    if ($req->headers->has('adv-cdn-origin') && $req->headers->get('adv-cdn-origin', '0') == 1) {
      return FALSE;
    }
    else {
      return TRUE;
    }
  }

  /**
   * Should the masking be enabled for this request?
   *
   * @return boolean
   */
  public function shouldMask() {
    $config = $this->configFactory->get('pantheon_domain_masking.settings');
    $mask = FALSE;
    $enabled = \filter_var($config->get('enabled', 'no'), FILTER_VALIDATE_BOOLEAN);
    if ($enabled === TRUE) {
      $mask = TRUE;
      if ($this->isPlatformDomainRequest()) {
        $allowPlatform = \filter_var($config->get('allow_platform', 'no'), FILTER_VALIDATE_BOOLEAN);
        if ($allowPlatform === TRUE) {
          $mask = FALSE;
        }
      }
    }

    return $mask;
  }

  /**
   * Is there a subpath in the config?
   *
   * @return boolean
   */
  public function hasSubpath() {
    return !empty($this->configFactory->get('subpath', ''));
  }

}
