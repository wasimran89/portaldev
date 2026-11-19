<?php

namespace Drupal\commerce_fedex_dry_ice\EventSubscriber;

use Drupal\commerce_fedex\Event\CommerceFedExEvents;
use Drupal\commerce_fedex\Event\RateRequestEvent;
use Drupal\commerce_shipping\PackageTypeManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Provides a FedEx event subscriber.
 *
 * @package Drupal\commerce_fedex_dry_ice\EventSubscriber
 */
class FedExEventSubscriber implements EventSubscriberInterface {
  use StringTranslationTrait;

  /**
   * The package type manager.
   *
   * @var \Drupal\commerce_shipping\PackageTypeManagerInterface
   */
  protected $packageTypeManager;

  /**
   * Constructs a new ShippingMethodBase object.
   *
   * @param \Drupal\commerce_shipping\PackageTypeManagerInterface $package_type_manager
   *   The package type manager.
   */
  public function __construct(PackageTypeManagerInterface $package_type_manager) {
    $this->packageTypeManager = $package_type_manager;
  }

  /**
   * Alter the rate request event.
   *
   * @param \Drupal\commerce_fedex\Event\RateRequestEvent $event
   *   The rate request event.
   */
  public function onBeforeRateRequest(RateRequestEvent $event): void {
    $rateRequest = $event->getRateRequest();
    $shipment = $event->getShipment();

    // @todo Alter the rate request.
  }

  /**
   * {@inheritDoc}
   */
  public static function getSubscribedEvents() {
    $events = [];
    $events[CommerceFedExEvents::BEFORE_RATE_REQUEST][] = ['onBeforeRateRequest'];

    return $events;
  }

}
