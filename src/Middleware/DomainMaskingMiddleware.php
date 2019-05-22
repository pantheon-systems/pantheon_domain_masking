<?php

namespace Drupal\pantheon_domain_masking\Middleware;

use Drupal\Core\Config\ConfigFactory;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * Class DomainMaskingMiddleware.
 */
class DomainMaskingMiddleware implements HttpKernelInterface {

  /**
   * The decorated kernel.
   *
   * @var \Symfony\Component\HttpKernel\HttpKernelInterface
   */
  protected $httpKernel;

  /**
   * The site settings.
   *
   * @var \Drupal\Core\Config\ConfigFactory
   */
  protected $configFactory;

  /**
   * Create a new StackOptionsRequest instance.
   *
   * @param \Symfony\Component\HttpKernel\HttpKernelInterface $http_kernel
   *   The decorated kernel.
   * @param \Drupal\Core\Config\ConfigFactory $config_factory
   *   The site settings.
   */
  public function __construct(HttpKernelInterface $http_kernel, ConfigFactory $config_factory) {
    $this->httpKernel = $http_kernel;
    $this->configFactory = $config_factory;
  }

  /**
   * {@inheritdoc}
   */
  public function handle(Request $request, $type = self::MASTER_REQUEST, $catch = TRUE) {
    if (PHP_SAPI !== 'cli') {
      $config = $this->configFactory->get('pantheon_domain_masking.settings');

      // First check to see if we're even enabled.
      $enabled = \filter_var($config->get('enabled', 'no'), FILTER_VALIDATE_BOOLEAN);

      if ($enabled === TRUE) {
        $mask = TRUE;

        // If we're coming from a platform domain, and the user has chosen to
        // allow platform domains, don't mask.
        $currentHost = $request->headers->get('host');
        if (\substr($currentHost, -16) == '.pantheonsite.io') {
          $allowPlatform = \filter_var($config->get('allow_platform', no), FILTER_VALIDATE_BOOLEAN);
          if ($allowPlatform === TRUE) {
            $mask = FALSE;
          }
        }

        if ($mask === TRUE) {
var_dump($domain); die;
          $domain = $config->get('domain');
          $request->headers->set('host', $domain);
        }
      }
    }
    return $this->httpKernel->handle($request, $type, $catch);
  }

}
