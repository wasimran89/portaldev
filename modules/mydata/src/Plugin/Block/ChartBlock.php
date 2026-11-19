<?php

namespace Drupal\mydata\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * Provides a 'ChartBlock' block.
 *
 * @Block(
 *  id = "chart_block",
 *  admin_label = @Translation("Chart block"),
 * )
 */
class ChartBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    $form = \Drupal::formBuilder()->getForm('Drupal\mydata\Form\Chart');

    return $form;
  }
}
