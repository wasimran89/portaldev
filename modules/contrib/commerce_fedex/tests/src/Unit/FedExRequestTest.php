<?php

namespace Drupal\Tests\commerce_fedex\Unit;

use NicholasCreativeMedia\FedExPHP\Structs\ClientDetail;
use NicholasCreativeMedia\FedExPHP\Structs\VersionId;
use NicholasCreativeMedia\FedExPHP\Structs\WebAuthenticationCredential;
use NicholasCreativeMedia\FedExPHP\Structs\WebAuthenticationDetail;

/**
 * Test Class for the Fedex Shipping Plugin.
 *
 * @coversDefaultClass \Drupal\commerce_fedex\FedExRequest
 * @group commerce_fedex
 */
class FedExRequestTest extends FedExUnitTestBase {

  public const RATE_SERVICE_SERVICE_ID = 'crs';
  public const RATE_SERVICE_MAJOR_VERSION = 20;

  /**
   * A FedExRequest object to test.
   *
   * @var \Drupal\commerce_fedex\FedExRequest
   */
  protected $request;

  /**
   * A sample configuration to use.
   *
   * @var array
   */
  protected $configuration;

  /**
   * @covers ::getRateRequest
   */
  public function testRateRequest(): void {

    $rate_request = $this->request->getRateRequest($this->configuration);

    $client_detail = $rate_request->getClientDetail();
    $this->assertInstanceOf(ClientDetail::class, $client_detail);
    $this->assertEquals('1234567', $client_detail->getAccountNumber());
    $this->assertEquals('9876543', $client_detail->getMeterNumber());

    $web_authentication_detail = $rate_request->getWebAuthenticationDetail();
    $this->assertInstanceOf(WebAuthenticationDetail::class, $web_authentication_detail);

    $user_credential = $web_authentication_detail->getUserCredential();
    $this->assertInstanceOf(WebAuthenticationCredential::class, $user_credential);

    $this->assertEquals('testkey', $user_credential->getKey());
    $this->assertEquals('testpass', $user_credential->getPassword());
  }

  /**
   * @covers ::getRateService
   */
  public function testRateService(): void {
    $rate_service = $this->request->getRateService($this->configuration);

    /** @var \NicholasCreativeMedia\FedExPHP\Structs\VersionId $version */
    $version = $rate_service->version;
    $this->assertInstanceOf(VersionId::class, $version);

    $this->assertEquals(static::RATE_SERVICE_SERVICE_ID, $version->getServiceId());
    $this->assertEquals(static::RATE_SERVICE_MAJOR_VERSION, $version->getMajor());
  }

}
