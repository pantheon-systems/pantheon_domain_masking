<?php

namespace Drupal\pantheon_domain_masking;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;
use Drupal\pantheon_domain_masking\Compiler\AssetResolverExtensionPass;

class PantheonDomainMaskingServiceProvider extends ServiceProviderBase {

  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container) {
    $container->addCompilerPass(new AssetResolverExtensionPass());
  }

}