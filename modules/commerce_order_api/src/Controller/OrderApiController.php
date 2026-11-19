<?php

namespace Drupal\commerce_order_api\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Drupal\commerce_order\Entity\Order;
use Drupal\commerce_order\Entity\OrderItem;
use Drupal\commerce_order\Resolver\ChainOrderTypeResolverInterface;
use Drupal\commerce_cart\CartProviderInterface;
use Drupal\commerce_payment\Entity\Payment;
use Drupal\commerce_payment\Entity\PaymentGateway;
use Drupal\commerce_shipping\Entity\Shipment;
use Drupal\commerce_shipping\Entity\ShippingMethod;
use Drupal\commerce_store\Entity\Store;
use Drupal\profile\Entity\Profile;
use Drupal\Core\Database\Database;
use Drupal\user\Entity\User;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\address\Plugin\Field\FieldType\AddressItem;

/**
 * Provides a REST API endpoint for placing orders from the cart with payments and shipping details.
 */
class OrderApiController extends ControllerBase {

  protected $cartProvider;
  protected $orderTypeResolver;

  public function __construct(CartProviderInterface $cart_provider, ChainOrderTypeResolverInterface $order_type_resolver) {
    $this->cartProvider = $cart_provider;
    $this->orderTypeResolver = $order_type_resolver;
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('commerce_cart.cart_provider'),
      $container->get('commerce_order.chain_order_type_resolver')
    );
  }

  public function placeOrder(Request $request) {
    $data = json_decode($request->getContent(), TRUE);

    if (!$data || empty($data['uid']) || empty($data['payment_gateway']) || empty($data['shipping_address']) || empty($data['shipping_method'])) {
      return new JsonResponse(['error' => 'Invalid request data. Required: uid, payment_gateway, shipping_address, shipping_method'], 400);
    }

    $user = User::load($data['uid']);
    if (!$user) {
      return new JsonResponse(['error' => 'User not found'], 404);
    }

    // Get the user's cart
    $carts = $this->cartProvider->getCarts($user);
    if (empty($carts)) {
      return new JsonResponse(['error' => 'No active cart found'], 404);
    }

    $cart = reset($carts);
    $order_items = $cart->getItems();

    if (empty($order_items)) {
      return new JsonResponse(['error' => 'Cart is empty'], 400);
    }

    // Generate the next order number
    $database = Database::getConnection();
    $query = $database->select('commerce_number_pattern_sequence', 'o')
      ->fields('o', ['number'])
      ->range(0, 1);
    $last_order_number = $query->execute()->fetchField();
    $next_order_number = $last_order_number ? $last_order_number + 1 : 100000;

    // Calculate total price
    $total_price = NULL;
    foreach ($order_items as $order_item) {
      $item_price = $order_item->getTotalPrice();
      if ($item_price) {
        $total_price = $total_price ? $total_price->add($item_price) : $item_price;
      }
    }

    if (!$total_price) {
      return new JsonResponse(['error' => 'Invalid total price'], 400);
    }

    // Create Shipping Profile
    $shipping_address_data = $data['shipping_address'];
    $shipping_profile = Profile::create([
      'type' => 'customer',
      'uid' => $user->id(),
      'address' => $shipping_address_data,
    ]);

    // Explicitly set the phone number field
    if (!empty($shipping_address_data['field_phone_number'])) {
      $shipping_profile->set('field_phone_number', $shipping_address_data['field_phone_number']);
    }

    $shipping_profile->save();

    // Validate and Set Shipping Method
    $shipping_method = ShippingMethod::load($data['shipping_method']);
    if (!$shipping_method) {
      return new JsonResponse(['error' => 'Invalid shipping method'], 400);
    }

    // Create the order
    $order = Order::create([
      'type' => 'default',
      'state' => 'draft',
      'uid' => $user->id(),
      'store_id' => $cart->getStoreId(),
      'order_items' => $order_items,
      'order_number' => $next_order_number,
      'placed' => time(),
      'total_price' => $total_price,
      'shipping_profile' => $shipping_profile,
      'billing_profile' => $shipping_profile, //optional
    ]);

    $order->save();

    // **Update the commerce_number_pattern_sequence table**
    $existing_number = $database->select('commerce_number_pattern_sequence', 'o')
      ->fields('o', ['number'])
      ->range(0, 1)
      ->execute()
      ->fetchField();

    if ($existing_number) {
      // Update the existing sequence number
      $database->update('commerce_number_pattern_sequence')
        ->fields(['number' => $next_order_number])
        ->execute();
    } else {
      // Insert a new record if none exists
      $database->insert('commerce_number_pattern_sequence')
        ->fields(['number' => $next_order_number])
        ->execute();
    }

    // Create Shipment Entity
    $shipment = Shipment::create([
      'type' => 'default',
      'order_id' => $order->id(),
      'shipping_method' => $shipping_method->id(),
      'shipping_profile' => $shipping_profile,
      'state' => 'ready',
      'title' => 'Shipping for Order #' . $order->id(),
    ]);

    $shipment->save();

    // Process Payment
    $payment_response = $this->processPayment($order, $data['payment_gateway'], $data['payment_details']);

    if ($payment_response['success']) {
      // Clear the cart after successful payment
      foreach ($cart->getItems() as $cart_item) {
        $cart->removeItem($cart_item);
      }
      $cart->save();

      return new JsonResponse([
        'message' => 'Order placed and paid successfully',
        'order_id' => $order->id(),
        'order_number' => $order->getOrderNumber(),
        'order_state' => $order->getState()->getId(),
        'payment_id' => $payment_response['payment_id'],
        'shipping_method' => $shipping_method->getPlugin()->getLabel(),
        'shipping_address' => $shipping_address_data,
      ]);
    } else {
      return new JsonResponse([
        'message' => 'Order placed but payment failed',
        'order_id' => $order->id(),
        'error' => $payment_response['error'],
      ], 400);
    }
  }

  /**
   * Processes payment for an order.
   */

  //OFFLINE
  private function processPayment(Order $order, $payment_gateway_id) {
    $payment_gateway = PaymentGateway::load($payment_gateway_id);
    if (!$payment_gateway) {
      return ['success' => false, 'error' => 'Invalid payment gateway'];
    }

    $total_price = $order->getTotalPrice();
    if (!$total_price || $total_price->getNumber() <= 0) {
      return ['success' => false, 'error' => 'Invalid order total price'];
    }

    // Create payment
    $payment = Payment::create([
      'state' => 'In Process',
      'amount' => $total_price,
      'payment_gateway' => $payment_gateway->id(),
      'order_id' => $order->id(),
      'uid' => $order->getCustomerId(),
    ]);

    try {
      $payment->save();
      $order->set('state', 'In Process');
      $order->save();

      return ['success' => true, 'payment_id' => $payment->id()];
    } catch (\Exception $e) {
      return ['success' => false, 'error' => $e->getMessage()];
    }
  }

  /*
  payload

  {
    "uid": 350,
    "payment_gateway": "offline",
    "shipping_address": {
      "given_name": "John",
      "family_name": "Doe",
      "address_line1": "123 Main St",
      "locality": "New York",
      "administrative_area": "NY",
      "postal_code": "10001",
      "country_code": "US",
      "field_phone_number":"9096115973"
    },
    "shipping_method": "6"
  }

  */

  //ONLINE
  /*
  private function processPayment(Order $order, $payment_gateway_id, $payment_details) {
    $payment_gateway = PaymentGateway::load($payment_gateway_id);
    if (!$payment_gateway) {
      return ['success' => false, 'error' => 'Invalid payment gateway'];
    }

    $total_price = $order->getTotalPrice();
    if (!$total_price || $total_price->getNumber() <= 0) {
      return ['success' => false, 'error' => 'Invalid order total price'];
    }

    // Ensure the payment method plugin supports credit card transactions
    $plugin = $payment_gateway->getPlugin();
    if (!$plugin instanceof \Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\OnsiteInterface) {
      return ['success' => false, 'error' => 'This payment gateway does not support credit card transactions'];
    }

    // Create the payment method (credit card)
    $payment_method_storage = \Drupal::entityTypeManager()->getStorage('commerce_payment_method');

    try {
      $payment_method = $payment_method_storage->create([
        'type' => 'credit_card',
        'payment_gateway' => $payment_gateway_id,
        'uid' => $order->getCustomerId(),
        'reusable' => FALSE, // Set to TRUE if storing the card for future use
      ]);

      $payment_method->set('card_number', $payment_details['card_number']);
      $payment_method->set('expiration_month', $payment_details['expiration_month']);
      $payment_method->set('expiration_year', $payment_details['expiration_year']);
      $payment_method->set('cvv', $payment_details['cvv']);
      $payment_method->setBillingProfile($this->createBillingProfile($payment_details['billing_address']));
      $payment_method->save();

      // Create the payment entity
      $payment = Payment::create([
        'state' => 'new',
        'amount' => $total_price,
        'payment_gateway' => $payment_gateway_id,
        'payment_method' => $payment_method,
        'order_id' => $order->id(),
        'uid' => $order->getCustomerId(),
        'remote_id' => '', // Populate this with the transaction ID after successful payment
      ]);

      // Process the payment
      $plugin->createPayment($payment, 'authorize'); // Change to 'capture' if you want to charge immediately
      $payment->save();

      // Mark order as paid
      $order->set('state', 'completed');
      $order->save();

      return ['success' => true, 'payment_id' => $payment->id()];
    } catch (\Exception $e) {
      return ['success' => false, 'error' => $e->getMessage()];
    }
  }

  Payload
  {
    "uid": 350,
    "payment_gateway": "authorize_net",
    "payment_details": {
      "card_number": "4111111111111111",
      "expiration_month": "12",
      "expiration_year": "2025",
      "cvv": "123",
      "billing_address": {
        "given_name": "John",
        "family_name": "Doe",
        "address_line1": "123 Main St",
        "locality": "New York",
        "administrative_area": "NY",
        "postal_code": "10001",
        "country_code": "US"
      }
    },
    "shipping_address": {
      "given_name": "John",
      "family_name": "Doe",
      "address_line1": "123 Main St",
      "locality": "New York",
      "administrative_area": "NY",
      "postal_code": "10001",
      "country_code": "US",
      "field_phone_number": "9096115973"
    },
    "shipping_method": "6"
  }
  */

}
