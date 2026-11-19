<?php

namespace Drupal\commerce_decoupled_checkout\Plugin\rest\resource;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ModifiedResourceResponse;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Provides a resource for voiding payments.
 *
 * @RestResource(
 *   id = "commerce_decoupled_checkout_payment_void",
 *   label = @Translation("Commerce Payment void"),
 *   uri_paths = {
 *     "create" = "/commerce/payment/void/{order_id}/{payment_id}"
 *   }
 * )
 */
class PaymentVoidResource extends ResourceBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

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
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, array $serializer_formats, LoggerInterface $logger, EntityTypeManagerInterface
  $entity_type_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $serializer_formats, $logger);
    $this->entityTypeManager = $entity_type_manager;
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
      $container->get('entity_type.manager')
    );
  }

  /**
   * Voids commerce payment.
   *
   * @param $order_id
   *   Commerce Order ID.
   * @param $payment_id
   *   Commerce Payment ID.
   *
   * @return \Drupal\rest\ModifiedResourceResponse
   *   OK on success.
   */
  public function post($order_id, $payment_id) {
    try {

      // Load order and make sure it exists.
      /** @var \Drupal\commerce_order\Entity\OrderInterface $order */
      $order = $this->entityTypeManager->getStorage('commerce_order')
        ->load($order_id);
      if (empty($order)) {
        throw new \Exception('Order ' . $order_id . ' does not exist.');
      }

      // Load payment and make sure it exists.
      /** @var \Drupal\commerce_payment\Entity\PaymentInterface $payment */
      $payment = $this->entityTypeManager->getStorage('commerce_payment')
        ->load($payment_id);
      if (empty($payment)) {
        throw new \Exception('Payment ' . $payment_id . ' does not exist.');
      }

      // Make sure that sent payment is for the order that was sent in the
      // payload.
      if ($order_id != $payment->getOrderId()) {
        throw new \Exception('Payment is attached to the different order.');
      }

      // Protect completed and voided orders from additional voiding.
      if (in_array($payment->getState()->getId(), ['voided', 'authorization_voided', 'completed'])) {
        throw new \Exception('Payment ' . $payment_id . ' cannot be voided.');
      }

      // Finalize voiding.
      /** @var \Drupal\commerce_payment\Entity\PaymentGatewayInterface $payment_gateway */
      $payment_gateway = $payment->getPaymentGateway();
      /** @var \Drupal\commerce_payment_example\Plugin\Commerce\PaymentGateway\OnsiteInterface $payment_gateway_plugin */
      $payment_gateway_plugin = $payment_gateway->getPlugin();
      $payment_gateway_plugin->voidPayment($payment);

      return new ModifiedResourceResponse('OK');

    } catch (\Exception $exception) {
      $this->logger->error($exception->getMessage());
      throw new BadRequestHttpException($exception->getMessage());
    }
  }
}
