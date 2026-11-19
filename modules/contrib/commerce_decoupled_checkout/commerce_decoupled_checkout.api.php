<?php

/**
 * @file
 * Documentation for Commerce Decoupled Checkout module APIs.
 */

/**
 * @addtogroup hooks
 * @{
 */

/**
 * Alters data for resource that an order create.
 *
 * @param array $data
 *   The data from post request.
 */
function hook_order_checkout_prepare_alter(array &$data) {
}

/**
 * Alters order data before the order is created.
 *
 * @param array $data
 *   The data from post request.
 * @param array $extra_order_data
 *   The extra order data from post request.
 */
function hook_decoupled_order_data_alter(array &$data, array &$extra_order_data) {
}

/**
 * Alters a draft order after it has been created.
 *
 * The hook could be used in case you need to add a promotion/coupon
 * to the order before payment is created.
 *
 * @param \Drupal\commerce_order\Entity\OrderInterface $order
 *   The order that has been created.
 * @param array $data
 *   The data from post request.
 */
function hook_decoupled_draft_order_alter(\Drupal\commerce_order\Entity\OrderInterface &$order, array &$data) {
}

/**
 * Alters an order after it has been created.
 *
 * @param \Drupal\commerce_order\Entity\OrderInterface $order
 *   The order that has been created.
 * @param array $data
 *   The data from post request.
 */
function hook_decoupled_order_alter(\Drupal\commerce_order\Entity\OrderInterface &$order, array &$data) {
}

/**
 * Alters data for resource that an payment create.
 *
 * @param array $data
 *   The data from post request.
 */
function hook_payment_create_prepare_alter(array &$data) {
}

/**
 * Alters order error message.
 *
 * @param array $message_data
 *   An array with message information, contains 'message' and 'params' keys.
 */
function hook_decoupled_checkout_create_order_error_message_alter(array &$message_data) {
}

/**
 * @} End of "addtogroup hooks".
 */
