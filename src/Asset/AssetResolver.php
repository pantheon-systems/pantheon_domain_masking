<?php

namespace Drupal\pantheon_domain_masking\Asset;

use Drupal\Component\Utility\Crypt;
use Drupal\Component\Utility\NestedArray;
use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Asset\AssetResolver as BaseAssetResolver;
use Drupal\Core\Asset\AttachedAssetsInterface;

/**
 * The default asset resolver.
 */
class AssetResolver extends BaseAssetResolver {

  /**
   * {@inheritdoc}
   */
  public function getCssAssets(AttachedAssetsInterface $assets, $optimize, LanguageInterface $language = NULL) {
    if (!isset($language)) {
      $language = $this->languageManager->getCurrentLanguage();
    }
    $theme_info = $this->themeManager->getActiveTheme();
    // Add the theme name to the cache key since themes may implement
    // hook_library_info_alter().
    $libraries_to_load = $this->getLibrariesToLoad($assets);

    $site = '';
    $request = \Drupal::request();
    if ($request) {
      $site =  $request->getSchemeAndHttpHost() . $request->getBaseUrl();
    }
        
    $cid = 'css:' . $theme_info->getName() . ':' . $site . $language->getId() . Crypt::hashBase64(serialize($libraries_to_load)) . (int) $optimize;
    if ($cached = $this->cache->get($cid)) {
      return $cached->data;
    }

    $css = [];
    $default_options = [
      'type' => 'file',
      'group' => CSS_AGGREGATE_DEFAULT,
      'weight' => 0,
      'media' => 'all',
      'preprocess' => TRUE,
    ];

    foreach ($libraries_to_load as $library) {
      [$extension, $name] = explode('/', $library, 2);
      $definition = $this->libraryDiscovery->getLibraryByName($extension, $name);
      if (isset($definition['css'])) {
        foreach ($definition['css'] as $options) {
          $options += $default_options;
          // Copy the asset library license information to each file.
          $options['license'] = $definition['license'];

          // Files with a query string cannot be preprocessed.
          if ($options['type'] === 'file' && $options['preprocess'] && str_contains($options['data'], '?')) {
            $options['preprocess'] = FALSE;
          }

          // Always add a tiny value to the weight, to conserve the insertion
          // order.
          $options['weight'] += count($css) / 30000;

          // CSS files are being keyed by the full path.
          $css[$options['data']] = $options;
        }
      }
    }

    // Allow modules and themes to alter the CSS assets.
    $this->moduleHandler->alter('css', $css, $assets, $language);
    $this->themeManager->alter('css', $css, $assets, $language);

    if (!empty($css)) {
      // Sort CSS items, so that they appear in the correct order.
      uasort($css, [static::class, 'sort']);

      if ($optimize) {
        $css = \Drupal::service('asset.css.collection_optimizer')->optimize($css, $libraries_to_load, $language);
      }
    }
    $this->cache->set($cid, $css, CacheBackendInterface::CACHE_PERMANENT, ['library_info']);

    return $css;
  }

  /**
   * {@inheritdoc}
   */
  public function getJsAssets(AttachedAssetsInterface $assets, $optimize, LanguageInterface $language = NULL) {
    if (!isset($language)) {
      $language = $this->languageManager->getCurrentLanguage();
    }
    $theme_info = $this->themeManager->getActiveTheme();
    // Add the theme name to the cache key since themes may implement
    // hook_library_info_alter(). Additionally add the current language to
    // support translation of JavaScript files via hook_js_alter().
    $libraries_to_load = $this->getLibrariesToLoad($assets);
    
    $site = '';
    $request = \Drupal::request();
    if ($request) {
      $site =  $request->getSchemeAndHttpHost() . $request->getBaseUrl();
    }

    $cid = 'js:' . $theme_info->getName() . ':' . $site . $language->getId() . ':' . Crypt::hashBase64(serialize($libraries_to_load)) . (int) (count($assets->getSettings()) > 0) . (int) $optimize;

    if ($cached = $this->cache->get($cid)) {
      [$js_assets_header, $js_assets_footer, $settings, $settings_in_header] = $cached->data;
    }
    else {
      $javascript = [];
      $default_options = [
        'type' => 'file',
        'group' => JS_DEFAULT,
        'weight' => 0,
        'cache' => TRUE,
        'preprocess' => TRUE,
        'attributes' => [],
        'version' => NULL,
      ];

      // Collect all libraries that contain JS assets and are in the header.
      $header_js_libraries = [];
      foreach ($libraries_to_load as $library) {
        [$extension, $name] = explode('/', $library, 2);
        $definition = $this->libraryDiscovery->getLibraryByName($extension, $name);
        if (isset($definition['js']) && !empty($definition['header'])) {
          $header_js_libraries[] = $library;
        }
      }
      // The current list of header JS libraries are only those libraries that
      // are in the header, but their dependencies must also be loaded for them
      // to function correctly, so update the list with those.
      $header_js_libraries = $this->libraryDependencyResolver->getLibrariesWithDependencies($header_js_libraries);

      foreach ($libraries_to_load as $library) {
        [$extension, $name] = explode('/', $library, 2);
        $definition = $this->libraryDiscovery->getLibraryByName($extension, $name);
        if (isset($definition['js'])) {
          foreach ($definition['js'] as $options) {
            $options += $default_options;
            // Copy the asset library license information to each file.
            $options['license'] = $definition['license'];

            // 'scope' is a calculated option, based on which libraries are
            // marked to be loaded from the header (see above).
            $options['scope'] = in_array($library, $header_js_libraries) ? 'header' : 'footer';

            // Preprocess can only be set if caching is enabled and no
            // attributes are set.
            $options['preprocess'] = $options['cache'] && empty($options['attributes']) ? $options['preprocess'] : FALSE;

            // Always add a tiny value to the weight, to conserve the insertion
            // order.
            $options['weight'] += count($javascript) / 30000;

            // Local and external files must keep their name as the associative
            // key so the same JavaScript file is not added twice.
            $javascript[$options['data']] = $options;
          }
        }
      }

      // Allow modules and themes to alter the JavaScript assets.
      $this->moduleHandler->alter('js', $javascript, $assets, $language);
      $this->themeManager->alter('js', $javascript, $assets, $language);

      // Sort JavaScript assets, so that they appear in the correct order.
      uasort($javascript, [static::class, 'sort']);

      // Prepare the return value: filter JavaScript assets per scope.
      $js_assets_header = [];
      $js_assets_footer = [];
      foreach ($javascript as $key => $item) {
        if ($item['scope'] == 'header') {
          $js_assets_header[$key] = $item;
        }
        elseif ($item['scope'] == 'footer') {
          $js_assets_footer[$key] = $item;
        }
      }

      if ($optimize) {
        $collection_optimizer = \Drupal::service('asset.js.collection_optimizer');
        $js_assets_header = $collection_optimizer->optimize($js_assets_header, $libraries_to_load);
        $js_assets_footer = $collection_optimizer->optimize($js_assets_footer, $libraries_to_load);
      }

      // If the core/drupalSettings library is being loaded or is already
      // loaded, get the JavaScript settings assets, and convert them into a
      // single "regular" JavaScript asset.
      $libraries_to_load = $this->getLibrariesToLoad($assets);
      $settings_required = in_array('core/drupalSettings', $libraries_to_load) || in_array('core/drupalSettings', $this->libraryDependencyResolver->getLibrariesWithDependencies($assets->getAlreadyLoadedLibraries()));
      $settings_have_changed = count($libraries_to_load) > 0 || count($assets->getSettings()) > 0;

      // Initialize settings to FALSE since they are not needed by default. This
      // distinguishes between an empty array which must still allow
      // hook_js_settings_alter() to be run.
      $settings = FALSE;
      if ($settings_required && $settings_have_changed) {
        $settings = $this->getJsSettingsAssets($assets);
        // Allow modules to add cached JavaScript settings.
        $this->moduleHandler->invokeAllWith('js_settings_build', function (callable $hook, string $module) use (&$settings, $assets) {
          $hook($settings, $assets);
        });
      }
      $settings_in_header = in_array('core/drupalSettings', $header_js_libraries);
      $this->cache->set($cid, [$js_assets_header, $js_assets_footer, $settings, $settings_in_header], CacheBackendInterface::CACHE_PERMANENT, ['library_info']);
    }

    if ($settings !== FALSE) {
      // Attached settings override both library definitions and
      // hook_js_settings_build().
      $settings = NestedArray::mergeDeepArray([$settings, $assets->getSettings()], TRUE);
      // Allow modules and themes to alter the JavaScript settings.
      $this->moduleHandler->alter('js_settings', $settings, $assets);
      $this->themeManager->alter('js_settings', $settings, $assets);
      // Update the $assets object accordingly, so that it reflects the final
      // settings.
      $assets->setSettings($settings);
      // Convert ajaxPageState to a compressed string from an array, since it is
      // used by ajax.js to pass to AJAX requests as a query parameter.
      if (isset($settings['ajaxPageState']['libraries'])) {
        $settings['ajaxPageState']['libraries'] = UrlHelper::compressQueryParameter($settings['ajaxPageState']['libraries']);
      }
      $settings_as_inline_javascript = [
        'type' => 'setting',
        'group' => JS_SETTING,
        'weight' => 0,
        'data' => $settings,
      ];
      $settings_js_asset = ['drupalSettings' => $settings_as_inline_javascript];
      // Prepend to the list of JS assets, to render it first. Preferably in
      // the footer, but in the header if necessary.
      if ($settings_in_header) {
        $js_assets_header = $settings_js_asset + $js_assets_header;
      }
      else {
        $js_assets_footer = $settings_js_asset + $js_assets_footer;
      }
    }
    return [
      $js_assets_header,
      $js_assets_footer,
    ];
  }
}
