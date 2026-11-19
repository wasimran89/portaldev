<?php

namespace Drupal\jcius_custom\Plugin\Block;

use Drupal\system\Plugin\Block\SystemBrandingBlock;

/**
 * Provides a block to display 'Site branding' elements altered.
 */
class  SystemBrandingBlockAlter extends SystemBrandingBlock {
  
  /**
   * {@inheritdoc}
   */
  public function build() {
    // This is the build method called now you can make your changes here.
    $build = [];
    $site_config = $this->configFactory->get('system.site');
    
    $build['site_slogan'] = [
      '#markup' => $site_config->get('slogan'),
      '#access' => $this->configuration['use_site_slogan'],
    ];
    $build['site_logo']['#uri'] = "https://devportal.jcius.com/qrsample.png";
    return $build;
  }
  
}
?>