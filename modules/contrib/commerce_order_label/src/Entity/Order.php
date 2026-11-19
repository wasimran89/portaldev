<?php

namespace Drupal\commerce_order_label\Entity;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\commerce_order\Entity\Order as CommerceOrder;

/**
 * Custom order entity to override label method.
 */
class Order extends CommerceOrder {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function label() {
    // Attempt to get default label.
    if ($this->getEntityType()->getKey('label') && !empty($this->getEntityKey('label'))) {
      $label = $this->getEntityKey('label');
    }
    else {
      // Fallback for order label.
      $orderId = $this->id();
      if (!empty($this->getOrderNumber())) {
        $orderId = $this->getOrderNumber();
      }
      $label = $this->t('Order #%order_id', ['%order_id' => $orderId]);
    }
    return $label;
  }

}
