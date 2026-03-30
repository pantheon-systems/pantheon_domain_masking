<?php

namespace Drupal\pantheon_domain_masking\PathProcessor;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\PathProcessor\InboundPathProcessorInterface;
use Drupal\Core\PathProcessor\OutboundPathProcessorInterface;
use Drupal\Core\Render\BubbleableMetadata;
use Symfony\Component\HttpFoundation\Request;

/**
 * Processes inbound/outbound paths for domain masking with multilingual support.
 *
 * When domain masking uses a subpath (e.g., /ca-en) AND the site uses Drupal's
 * language negotiation via URL path prefix (e.g., /fr, /en), the subpath must
 * be stripped/added at the correct point in the path processing pipeline:
 *
 * Inbound:  /ca-en/fr/page → strip /ca-en → /fr/page → language strips /fr → /page
 * Outbound: /page → language adds /fr → /fr/page → add /ca-en → /ca-en/fr/page
 *
 * This processor runs at higher priority than the language processor for inbound
 * (so subpath is stripped first) and lower priority for outbound (so subpath is
 * added after the language prefix).
 *
 * Activated by setting 'multilingual' to TRUE in the module configuration:
 *   $config['pantheon_domain_masking.settings']['multilingual'] = TRUE;
 */
class SubpathPathProcessor implements InboundPathProcessorInterface, OutboundPathProcessorInterface {

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Constructs a new SubpathPathProcessor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   */
  public function __construct(ConfigFactoryInterface $config_factory) {
    $this->configFactory = $config_factory;
  }

  /**
   * {@inheritdoc}
   */
  public function processInbound($path, Request $request) {
    $subpath = $this->getActiveSubpath($request);
    if ($subpath === NULL) {
      return $path;
    }

    $prefix = '/' . $subpath;

    // Strip the subpath prefix from the beginning of the path.
    if ($path === $prefix) {
      return '/';
    }
    if (str_starts_with($path, $prefix . '/')) {
      return substr($path, strlen($prefix));
    }

    return $path;
  }

  /**
   * {@inheritdoc}
   */
  public function processOutbound($path, &$options = [], Request $request = NULL, BubbleableMetadata $bubbleable_metadata = NULL) {
    $subpath = $this->getActiveSubpath($request);
    if ($subpath === NULL) {
      return $path;
    }

    $prefix = '/' . $subpath;

    // Don't double-prefix.
    if (str_starts_with($path, $prefix . '/') || $path === $prefix) {
      return $path;
    }

    return $prefix . $path;
  }

  /**
   * Returns the active subpath if multilingual mode is enabled.
   *
   * @param \Symfony\Component\HttpFoundation\Request|null $request
   *   The current request, if available.
   *
   * @return string|null
   *   The subpath string (without leading slash), or NULL if inactive.
   */
  protected function getActiveSubpath(?Request $request): ?string {
    $config = $this->configFactory->get('pantheon_domain_masking.settings');

    // Only active when multilingual mode is explicitly enabled.
    if (!\filter_var($config->get('multilingual'), FILTER_VALIDATE_BOOLEAN)) {
      return NULL;
    }

    // Only active when masking is enabled and this is not a platform request.
    if (!\filter_var($config->get('enabled'), FILTER_VALIDATE_BOOLEAN)) {
      return NULL;
    }

    $subpath = $config->get('subpath');
    if (empty($subpath)) {
      return NULL;
    }

    // On platform domain requests (no adv-cdn-origin header), skip subpath
    // processing since there is no subpath in the URL.
    if ($request) {
      $isPlatform = !($request->headers->has('adv-cdn-origin')
        && $request->headers->get('adv-cdn-origin', '0') == 1);
      if ($isPlatform) {
        $allowPlatform = \filter_var($config->get('allow_platform'), FILTER_VALIDATE_BOOLEAN);
        if ($allowPlatform) {
          return NULL;
        }
      }
    }

    return $subpath;
  }

}
