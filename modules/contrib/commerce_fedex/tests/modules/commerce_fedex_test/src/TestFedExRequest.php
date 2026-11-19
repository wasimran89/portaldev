<?php

namespace Drupal\commerce_fedex_test;

use Drupal\commerce_fedex\FedExRequest;
use NicholasCreativeMedia\FedExPHP\Services\RateService;

/**
 * Override request service to return a simulated rate request.
 */
class TestFedExRequest extends FedExRequest {

  /**
   * {@inheritdoc}
   */
  public function getRateService(array $configuration, array $wsdlOptions = [], $resetSoapClient = TRUE): RateService {
    return new TestRateService();
  }

}
