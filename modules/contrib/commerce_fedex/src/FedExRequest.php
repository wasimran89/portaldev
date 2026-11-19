<?php

namespace Drupal\commerce_fedex;

use NicholasCreativeMedia\FedExPHP\Services\RateService;
use NicholasCreativeMedia\FedExPHP\Services\ShipService;
use NicholasCreativeMedia\FedExPHP\Services\TrackService;
use NicholasCreativeMedia\FedExPHP\Structs\ClientDetail;
use NicholasCreativeMedia\FedExPHP\Structs\ProcessShipmentRequest;
use NicholasCreativeMedia\FedExPHP\Structs\RateRequest;
use NicholasCreativeMedia\FedExPHP\Structs\TrackRequest;
use NicholasCreativeMedia\FedExPHP\Structs\WebAuthenticationCredential;
use NicholasCreativeMedia\FedExPHP\Structs\WebAuthenticationDetail;

/**
 * Manage FedEx API services.
 *
 * @package Drupal\commerce_fedex
 */
class FedExRequest implements FedExRequestInterface {

  /**
   * The call magic method implementation.
   *
   * @param string $name
   *   The name.
   * @param array $arguments
   *   The arguments.
   *
   * @return mixed
   *   The value.
   */
  public function __call(string $name, array $arguments) {
    if (strpos($name, 'get') !== 0) {
      throw new \RuntimeException("Method $name not found");
    }

    $name = substr($name, 3);
    $configuration = $arguments[0];

    if (strpos($name, 'Service') !== FALSE) {
      if (!class_exists("\\NicholasCreativeMedia\\FedExPHP\\Services\\$name")) {
        throw new \RuntimeException("FedEx service $name not found");
      }

      $wsdl_options = $arguments[1] ?: [];
      $reset_soap_client = $arguments[2] ?? TRUE;

      return new $name(
        $wsdl_options,
        $reset_soap_client,
        $this->getMode($configuration)
      );
    }

    if (strpos($name, 'Request') !== FALSE) {
      if (!class_exists("\\NicholasCreativeMedia\\FedExPHP\\Services\\$name")) {
        throw new \RuntimeException("Request Type $name not found");
      }

      return new $name(
        $this->getWebAuthenticationDetail($configuration),
        $this->getClientDetail($configuration)
      );
    }

    throw new \RuntimeException("Method $name not found");
  }

  /**
   * {@inheritdoc}
   */
  public function getRateRequest(array $configuration): RateRequest {
    $rate_request = new RateRequest(
      $this->getWebAuthenticationDetail($configuration),
      $this->getClientDetail($configuration)
    );

    return $rate_request;
  }

  /**
   * {@inheritdoc}
   */
  public function getRateService(array $configuration, array $wsdlOptions = [], bool $resetSoapClient = TRUE): RateService {
    $rate_service = new RateService($wsdlOptions, $resetSoapClient, $this->getMode($configuration));

    return $rate_service;
  }

  /**
   * {@inheritdoc}
   */
  public function getTrackRequest(array $configuration): TrackRequest {
    $track_request = new TrackRequest(
      $this->getWebAuthenticationDetail($configuration),
      $this->getClientDetail($configuration)
    );

    return $track_request;
  }

  /**
   * {@inheritdoc}
   */
  public function getTrackService(array $configuration, array $wsdlOptions = [], bool $resetSoapClient = TRUE): TrackService {
    $track_service = new TrackService($wsdlOptions, $resetSoapClient, $this->getMode($configuration));

    return $track_service;
  }

  /**
   * {@inheritdoc}
   */
  public function getShipRequest(array $configuration): ProcessShipmentRequest {
    $ship_request = new ProcessShipmentRequest(
      $this->getWebAuthenticationDetail($configuration),
      $this->getClientDetail($configuration)
    );

    return $ship_request;
  }

  /**
   * {@inheritdoc}
   */
  public function getShipService(array $configuration, array $wsdlOptions = [], bool $resetSoapClient = TRUE): ShipService {
    $ship_service = new ShipService(
      $wsdlOptions,
      $resetSoapClient,
      $this->getMode($configuration)
    );

    return $ship_service;
  }

  /**
   * Get the web authentication detail.
   *
   * @param array $configuration
   *   The configuration.
   *
   * @return \NicholasCreativeMedia\FedExPHP\Structs\WebAuthenticationDetail
   *   The web authentication detail.
   */
  public function getWebAuthenticationDetail(array $configuration): WebAuthenticationDetail {
    $web_authentication_detail = new WebAuthenticationDetail();
    $web_authentication_detail->setUserCredential(new WebAuthenticationCredential(
      $configuration['api_information']['api_key'],
      $configuration['api_information']['api_password']
    ));

    return $web_authentication_detail;
  }

  /**
   * Get the client detail.
   *
   * @param array $configuration
   *   The configuration.
   *
   * @return \NicholasCreativeMedia\FedExPHP\Structs\ClientDetail
   *   The client detail.
   */
  public function getClientDetail(array $configuration): ClientDetail {
    $client_detail = new ClientDetail(
      $configuration['api_information']['account_number'],
      $configuration['api_information']['meter_number'],
      '123'
    );

    return $client_detail;
  }

  /**
   * {@inheritdoc}
   */
  public function getVersion($service) {
    $class = '\NicholasCreativeMedia\FedExPHP\Services\\' . $service;

    if (!class_exists($class)) {
      throw new \RuntimeException("Service $class not found");
    }

    /** @var \NicholasCreativeMedia\FedExPHP\Services\FedExService $class */
    return $class::version();
  }

  /**
   * Gets the mode to use to connect.
   *
   * @param array $configuration
   *   The configuration array.
   *
   * @return string
   *   The mode (test or live).
   */
  protected function getMode(array $configuration): string {
    return $configuration['api_information']['mode'] ?: 'test';
  }

}
