<?php

namespace Drupal\commerce_decoupled_checkout\Plugin\rest\resource;

use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\OnsitePaymentGatewayInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ModifiedResourceResponse;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Provides a resource for payments initialization for a certain order.
 *
 * @RestResource(
 *   id = "commerce_decoupled_checkout_payment_create",
 *   label = @Translation("Commerce Payment create"),
 *   uri_paths = {
 *     "create" = "/commerce/payment/create/{order_id}"
 *   }
 * )
 */
class PaymentCreateResource extends ResourceBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * Constructs a new object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param array $serializer_formats
   *   The available serialization formats.
   * @param \Psr\Log\LoggerInterface $logger
   *   A logger instance.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Entity type manager.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   Module handler service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, array $serializer_formats, LoggerInterface $logger, EntityTypeManagerInterface $entity_type_manager, ModuleHandlerInterface $module_handler) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $serializer_formats, $logger);
    $this->entityTypeManager = $entity_type_manager;
    $this->moduleHandler = $module_handler;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->getParameter('serializer.formats'),
      $container->get('logger.factory')->get('commerce_decoupled_checkout'),
      $container->get('entity_type.manager'),
      $container->get('module_handler')
    );
  }

  /**
   *
   * Creates a new payment for the given order.
   *
   * @param $order_id
   *   Commerce Order ID the payment is for.
   *
   * @param array $data
   *   $data = [
   *     'gateway' => 'paypal_test', // required. Commerce Payment Gateway name.
   *     'type' => 'paypal_ec', // required. Commerce Payment Type name.
   *     'details' => [], // optional. Payment details associated with the payment.
   *     'capture' => FALSE, // optional. Defines if the payment has to be finalized.
   *   ];
   *
   * @return \Drupal\rest\ModifiedResourceResponse
   *   Response with created payment object.
   */
  public function post($order_id, array $data) {

    // Invoke hook alter for prepare data.
    $data['order_id'] = $order_id;
    $this->moduleHandler->alter('payment_create_prepare', $data);

    try {
      // Make sure payment details array is initialized.
      $data['details'] = !empty($data['details']) ? $data['details'] : [];

      // Load order and make sure it exists.
      /** @var \Drupal\commerce_order\Entity\OrderInterface $order */
      $order = $this->entityTypeManager->getStorage('commerce_order')
        ->load($order_id);
      if (empty($order)) {
        throw new \Exception('Order ' . $order_id . ' does not exist.');
      }

      // No need to create payment for completed/canceled orders.
      if ($order->getState()->value !== 'draft') {
        throw new \Exception('Order ' . $order_id . ' is not in the draft state.');
      }

      // Load commerce payment gateway and make sure it exists.
      /** @var \Drupal\commerce_payment\Entity\PaymentGatewayInterface $payment_gateway */
      $payment_gateway = $this->entityTypeManager->getStorage('commerce_payment_gateway')
        ->load($data['gateway']);
      if (empty($payment_gateway)) {
        throw new \Exception('Payment gateway "' . $data['gateway'] . '" does not exist.');
      }

      /** @var \Drupal\commerce_payment\Entity\PaymentMethodInterface $payment_method */
      $payment_method = $this->entityTypeManager->getStorage('commerce_payment_method')
        ->create([
          'payment_gateway' => $payment_gateway,
          'type' => $data['type'],
          'uid' => $order->getCustomerId(),
          'billing_profile' => $order->getBillingProfile(),
        ]);

      /** @var \Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\OnsitePaymentGatewayInterface $payment_gateway_plugin */
      $payment_gateway_plugin = $payment_gateway->getPlugin();

      // Make sure the payment is onsite payment. Otherwise not sure how can we
      // support it.
      if (!$payment_gateway_plugin instanceof OnsitePaymentGatewayInterface) {
        throw new \Exception('The payment gateway is not onsite payment and therefore not supported.');
      }

      $payment_gateway_plugin->createPaymentMethod($payment_method, $data['details']);

      /** @var \Drupal\commerce_payment\Entity\PaymentInterface $payment */
      $payment = $this->entityTypeManager->getStorage('commerce_payment')
        ->create([
          'amount' => $order->getTotalPrice(),
          'payment_gateway' => $payment_gateway->id(),
          'order_id' => $order->id(),
          'payment_method' => $payment_method,
        ]);

      // If payment does not have to be finalized now, then just initialize it
      // and leave it here. It can be captured through separate REST request.
      // Otherwise create & capture it immediately.
      if (empty($data['capture'])) {
        $payment_gateway_plugin->createPayment($payment, FALSE);
      }
      else {
        $payment_gateway_plugin->createPayment($payment);
      }

      // Add payment details to the order.
      $order->payment_gateway = $payment->getPaymentGatewayId();
      $order->payment_method = $payment->getPaymentMethodId();

      // Complete the order if the payment was successfully captured.
      $payment_state = $payment->getState();
      if ($payment_state->value == 'completed') {
        $order_state = $order->getState();
        $order_state_transitions = $order_state->getTransitions();
        if (!empty($order_state_transitions['place'])) {
          $order_state->applyTransition($order_state_transitions['place']);
        }

        // Add total paid amount.
        $order->setTotalPaid($order->getTotalPrice());
      }

      // Finally save all changes to the order.
      $order->save();
    } catch (\Exception $exception) {
      $this->logger->error($exception->getMessage());
      throw new BadRequestHttpException($exception->getMessage());
    }

    return new ModifiedResourceResponse($payment, 201);
  }
}
