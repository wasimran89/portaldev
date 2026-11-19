<?php

namespace Drupal\mydata\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * Provides a 'UsernameBlock' block.
 *
 * @Block(
 *  id = "username_block",
 *  admin_label = @Translation("Username block"),
 * )
 */
class UsernameBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    $form = \Drupal::formBuilder()->getForm('Drupal\mydata\Form\Username');

    return $form;
  }
}
