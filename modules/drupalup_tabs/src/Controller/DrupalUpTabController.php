<?php

namespace Drupal\drupalup_tabs\Controller;

/**
 * Example controller that we are going to use for tabs.
 */
class DrupalUpTabController {

  /**
   * Main tab.
   */
  public function mainTab() {
    return [
      '#markup' => 'Main tab','#attached' => [ 'library' => 'core/drupal.dialog.ajax' ]
    ];
  }

  /**
   * Sub tab one.
   */
  public function subTabOne() {
    return [
      '#markup' => 'Sub tab one.','#attached' => [ 'library' => 'core/drupal.dialog.ajax' ]
    ];
  }

  /**
   * Sub tab two.
   */
  public function subTabTwo() {
    return [
      '#markup' => 'Sub tab two.','#attached' => [ 'library' => 'core/drupal.dialog.ajax' ]
    ];
  }

}
