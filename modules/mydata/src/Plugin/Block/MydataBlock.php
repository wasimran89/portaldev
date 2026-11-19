<?php

namespace Drupal\mydata\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * Provides a 'MydataBlock' block.
 *
 * @Block(
 *  id = "mydata_block",
 *  admin_label = @Translation("Mydata block"),
 * )
 */
class MydataBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    ////$build = [];
    //$build['mydata_block']['#markup'] = 'Implement MydataBlock.';
    \Drupal::service('page_cache_kill_switch')->trigger();

    $form = \Drupal::formBuilder()->getForm('Drupal\mydata\Form\MydataForm');

    return $form;
  }

}
