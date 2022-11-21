<?php

namespace Drupal\pantheon_domain_masking\Middleware;

use Drupal\Core\Config\ConfigFactory;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * Class DomainMaskingMiddleware.
 */
class DomainMaskingMiddleware implements HttpKernelInterface {

  const DOMAIN_MASKING_MAIN_REQUEST = self::MAIN_REQUEST ?? self::MASTER_REQUEST;

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
   * The original request when this middleware was first run.
   *
   * @var \Symfony\Component\HttpFoundation\Request;
   */
  protected $origRequest;

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
  #[\ReturnTypeWillChange]
  public function handle(Request $request, int $type = self::DOMAIN_MASKING_MAIN_REQUEST, bool $catch = TRUE) {
    if (PHP_SAPI !== 'cli') {
      $config = $this->configFactory->get('pantheon_domain_masking.settings');
      $this->origRequest = clone $request;
      $host = $this->origRequest->headers->get('host');

      // First check to see if we're even enabled.
      $enabled = \filter_var($config->get('enabled', 'no'), FILTER_VALIDATE_BOOLEAN);

      if ($enabled === TRUE) {
        $mask = TRUE;

        // Set a vary header before we read custom server vars.
        header('Vary: adv-cdn-origin', FALSE);

        // If we're coming from a platform domain, and the user has chosen to
        // allow platform domains, don't mask.
        if ($this->isPlatformDomainRequest()) {
          $allowPlatform = \filter_var($config->get('allow_platform', 'no'), FILTER_VALIDATE_BOOLEAN);
          if ($allowPlatform === TRUE) {
            $mask = FALSE;
          }
        }

        if ($mask === TRUE) {
          $domain = $config->get('domain');
          $request->headers->set('host', $domain);

          // Cookie jawn.
          ini_set('session.cookie_domain', '.' . $domain);

          $host = $domain;

          // Can't do subpaths without domain masking.
          $subpath = $config->get('subpath', '');
          if (!empty($subpath)) {
            // More cookie jawn.
            ini_set('session.cookie_path', "/${subpath}");

            // Add the subpath back into the request, if not already present.
            $newRequestArray = $request->server->all();
            if (strpos($newRequestArray['SCRIPT_NAME'], "/${subpath}/") !== 0) {
              $newRequestArray['SCRIPT_NAME'] = "/${subpath}" . $newRequestArray['SCRIPT_NAME'];
            }
            if (strpos($newRequestArray['REQUEST_URI'], "/${subpath}") !== 0) {
              $newRequestArray['REQUEST_URI'] = "/${subpath}" . $newRequestArray['REQUEST_URI'];
            }
            // When using Apache's ProxyPass directive you might end up with
            // double slashes, which might cause endless loops. Remove those.
            $newRequestArray['REQUEST_URI'] = $this->stripExtraPathSlashes($newRequestArray['REQUEST_URI']);
            if (strpos($newRequestArray['SCRIPT_FILENAME'], "/${subpath}/") === FALSE) {
              $newRequestArray['SCRIPT_FILENAME'] = \dirname($newRequestArray['SCRIPT_FILENAME']) . "/${subpath}/" . \basename($newRequestArray['SCRIPT_FILENAME']);
            }
            $newRequestArray['HTTP_HOST'] = $host;
            // Replace the request being used by this middleware.
            $dupRequest = $request->duplicate(NULL, NULL, NULL, NULL, NULL, $newRequestArray);
            $request = $dupRequest;
          }

          // Legacy globals.
          $proto = 'https';
          if (isset($_SERVER['HTTP_USER_AGENT_HTTPS']) && $_SERVER['HTTP_USER_AGENT_HTTPS'] != 'ON') {
            $proto = 'http';
          }
          $base_path = "/${subpath}";
          $base_url = $base_root = "${proto}://${host}" . (empty($subpath) ? '' : "/${subpath}");
          $GLOBALS['base_path'] = $base_path;
          $GLOBALS['base_url'] = $base_url;
          $GLOBALS['base_root'] = $base_root;
        }
      }
    }

    return $this->httpKernel->handle($request, $type, $catch);
  }

  /**
   * Determines whether or not the original request was to a platform domain.
   *
   * @return boolean
   */
  protected function isPlatformDomainRequest(Request $request = NULL) {
    $targetReq = $request ?: $this->origRequest;
    if ($targetReq) {
      if ($targetReq->headers->has('adv-cdn-origin') && $targetReq->headers->get('adv-cdn-origin', '0') == 1) {
        return FALSE;
      }
      else {
        return TRUE;
      }
    }
    else {
      return TRUE;
    }
  }
  
  /**
   * Cleans up extra slashes in the path
   *
   * @return string
   */
  protected function stripExtraPathSlashes(String $url) {
    $parts = parse_url($url);
    // Thanks: https://www.php.net/manual/en/function.parse-url.php#106731
    $scheme   = isset($parts['scheme']) ? $parts['scheme'] . '://' : '';
    $host     = $parts['host'] ?? '';
    $port     = isset($parts['port']) ? ':' . $parts['port'] : '';
    $user     = $parts['user'] ?? '';
    $pass     = isset($parts['pass']) ? ':' . $parts['pass']  : '';
    $pass     = ($user || $pass) ? "$pass@" : '';
    $path     = $parts['path'] ?? '';
    $query    = isset($parts['query']) ? '?' . $parts['query'] : '';
    $fragment = isset($parts['fragment']) ? '#' . $parts['fragment'] : '';
    // remove double slashes from the path
    $path = \str_replace('//', '/', $path);
    return "$scheme$user$pass$host$port$path$query$fragment";
  }

}
