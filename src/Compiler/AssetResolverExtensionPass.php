<?php

namespace Drupal\pantheon_domain_masking\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class AssetResolverExtensionPass implements CompilerPassInterface {

  /**
   * {@inheritdoc}
   */
  public function process(ContainerBuilder $container) {
    $definition = $container->getDefinition('asset.css.collection_optimizer');

    // Apply override only when CssCollectionOptimizerLazy is used 10.1+.
    if ($definition->getClass() !== 'Drupal\\Core\\Asset\\CssCollectionOptimizerLazy') {
      return;
    }

    $definition = $container->getDefinition('asset.resolver');
    $definition->setClass('Drupal\\pantheon_domain_masking\\Asset\\AssetResolver');
  }
}
