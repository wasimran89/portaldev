<?php

namespace Drupal\commerce_fedex\Plugin\Commerce\ShippingMethod;

use Drupal\address\AddressInterface;
use Drupal\address\Plugin\Field\FieldType\AddressItem;
use Drupal\commerce_fedex\Event\CommerceFedExEvents;
use Drupal\commerce_fedex\Event\RateRequestEvent;
use Drupal\commerce_fedex\FedExAddressResolver;
use Drupal\commerce_price\Price;
use Drupal\commerce_shipping\Entity\ShipmentInterface;
use Drupal\commerce_shipping\Plugin\Commerce\PackageType\PackageTypeInterface;
use Drupal\commerce_shipping\Plugin\Commerce\ShippingMethod\ShippingMethodBase;
use Drupal\commerce_shipping\Plugin\Commerce\ShippingMethod\SupportsTrackingInterface;
use Drupal\commerce_shipping\ShipmentItem;
use Drupal\commerce_shipping\ShippingRate;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\SubformState;
use Drupal\Core\Plugin\DefaultLazyPluginCollection;
use Drupal\Core\Url;
use Drupal\physical\LengthUnit as PhysicalLengthUnits;
use Drupal\physical\Volume;
use Drupal\physical\VolumeUnit;
use Drupal\physical\Weight as PhysicalWeight;
use Drupal\physical\WeightUnit as PhysicalWeightUnits;
use NicholasCreativeMedia\FedExPHP\Enums\DropoffType;
use NicholasCreativeMedia\FedExPHP\Enums\LinearUnits;
use NicholasCreativeMedia\FedExPHP\Enums\LinearUnits as FedexLengthUnits;
use NicholasCreativeMedia\FedExPHP\Enums\PhysicalPackagingType;
use NicholasCreativeMedia\FedExPHP\Enums\RateRequestType;
use NicholasCreativeMedia\FedExPHP\Enums\WeightUnits as FedexWeightUnits;
use NicholasCreativeMedia\FedExPHP\Services\RateService;
use NicholasCreativeMedia\FedExPHP\Structs\Address;
use NicholasCreativeMedia\FedExPHP\Structs\Dimensions;
use NicholasCreativeMedia\FedExPHP\Structs\Money;
use NicholasCreativeMedia\FedExPHP\Structs\Party;
use NicholasCreativeMedia\FedExPHP\Structs\RateRequest;
use NicholasCreativeMedia\FedExPHP\Structs\RequestedPackageLineItem;
use NicholasCreativeMedia\FedExPHP\Structs\RequestedShipment;
use NicholasCreativeMedia\FedExPHP\Structs\Weight as FedexWeight;
use Psr\Log\LogLevel;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides the FedEx shipping method.
 *
 * @CommerceShippingMethod(
 *   id = "fedex",
 *   label = @Translation("FedEx"),
 *   services = {
 *     "FEDEX_2_DAY" = @Translation("FedEx 2nd Day"),
 *     "FEDEX_2_DAY_AM" = @Translation("FedEx 2nd Day AM"),
 *     "FEDEX_EXPRESS_SAVER" = @Translation("FedEx Express Saver"),
 *     "FEDEX_GROUND" = @Translation("FedEx Ground"),
 *     "FIRST_OVERNIGHT" = @Translation("FedEx First Overnight"),
 *     "GROUND_HOME_DELIVERY" = @Translation("FedEx Ground Home Delivery"),
 *     "INTERNATIONAL_ECONOMY" = @Translation("FedEx International Economy"),
 *     "INTERNATIONAL_FIRST" = @Translation("FedEx International First"),
 *     "INTERNATIONAL_PRIORITY" = @Translation("FedEx International Priority"),
 *     "PRIORITY_OVERNIGHT" = @Translation("FedEx Priority Overnight"),
 *     "SMART_POST" = @Translation("FedEx Smart Post"),
 *     "STANDARD_OVERNIGHT" = @Translation("FedEx Standard Overnight")
 *   }
 * )
 */
class FedEx extends ShippingMethodBase implements SupportsTrackingInterface {
  use FedExAddressResolver;

  /**
   * Constant for Domestic Shipping.
   */
  public const SHIPPING_TYPE_DOMESTIC = 'domestic';

  /**
   * Constant for International Shipping.
   */
  public const SHIPPING_TYPE_INTERNATIONAL = 'intl';

  /**
   * Package All items in one box, ignoring dimensions.
   */
  public const PACKAGE_ALL_IN_ONE = 'allinone';

  /**
   * Package each line item in its own box, ignoring dimensions.
   */
  public const PACKAGE_INDIVIDUAL = 'individual';

  /**
   * Calculate volume to determine how many boxes are needed.
   */
  public const PACKAGE_CALCULATE = 'calculate';

  /**
   * How long the rate request will be cached.
   *
   * We might want to make this configurable in the future.
   */
  public const RATE_CACHE_DURATION = 3600;

  /**
   * The event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected $eventDispatcher;

  /**
   * The FedEx Service Plugin Manager.
   *
   * @var \Drupal\commerce_fedex\FedExPluginManager
   */
  protected $fedExServiceManager;

  /**
   * The Service Plugins.
   *
   * @var \Drupal\Core\Plugin\DefaultLazyPluginCollection
   */
  protected $plugins;

  /**
   * Commerce Fedex Logger Channel.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $watchdog;

  /**
   * The price rounder.
   *
   * @var \Drupal\commerce_price\RounderInterface
   */
  protected $rounder;

  /**
   * The FedEx Connection.
   *
   * @var \Drupal\commerce_fedex\FedExRequestInterface
   */
  protected $fedExRequest;

  /**
   * The cache backend service.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected $cacheBackend;

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The order item storage service.
   *
   * @var \Drupal\commerce_order\OrderItemStorageInterface
   */
  protected $orderItemStorage;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->watchdog = $container->get('logger.channel.commerce_fedex');
    $instance->fedExRequest = $container->get('commerce_fedex.fedex_request');
    $instance->fedExServiceManager = $container->get('plugin.manager.commerce_fedex_service');
    $instance->eventDispatcher = $container->get('event_dispatcher');
    $instance->rounder = $container->get('commerce_price.rounder');
    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->orderItemStorage = $instance->entityTypeManager->getStorage('commerce_order_item');
    $instance->cacheBackend = $container->get('cache.fedex');
    if (empty($instance->configuration['plugins'])) {
      $instance->configuration['plugins'] = [];
    }
    $instance->updatePlugins()->getPlugins();
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {

    return [
      'api_information' => [
        'api_key' => '',
        'api_password' => '',
        'account_number' => '',
        'meter_number' => '',
        'mode' => 'test',
      ],
      'options' => [
        'packaging' => static::PACKAGE_ALL_IN_ONE,
        'rate_request_type' => RateRequestType::VALUE_NONE,
        'dropoff' => DropoffType::VALUE_REGULAR_PICKUP,
        'insurance' => FALSE,
        'rate_multiplier' => 1.0,
        'round' => PHP_ROUND_HALF_UP,
        'log' => [],
        'tracking_url' => 'https://www.fedex.com/apps/fedextrack/?action=track&tracknumbers=[tracking_code]',
      ],
      'plugins' => [],
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {

    $form = parent::buildConfigurationForm($form, $form_state);

    // Select all services by default.
    if (empty($this->configuration['services'])) {
      $service_ids = array_keys($this->services);
      $this->configuration['services'] = array_combine($service_ids, $service_ids);
    }

    $form['api_information'] = [
      '#type' => 'details',
      '#title' => $this->t('API information'),
      '#description' => $this->isConfigured() ? $this->t('Update your FedEx API information.') : $this->t('Fill in your FedEx API information.'),
      '#weight' => $this->isConfigured() ? 10 : -10,
      '#open' => !$this->isConfigured(),
    ];
    $form['api_information']['api_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('API key'),
      '#description' => $this->t('Enter your FedEx API key.'),
      '#default_value' => $this->configuration['api_information']['api_key'],
    ];
    $form['api_information']['api_password'] = [
      '#type' => 'password',
      '#title' => $this->t('API password'),
      '#description' => $this->t('Enter your FedEx API password only if you wish to change its value.'),
      '#default_value' => '',
    ];
    $form['api_information']['account_number'] = [
      '#type' => 'number',
      '#min' => 0,
      '#step' => 1,
      '#title' => $this->t('Account number'),
      '#description' => $this->t('Enter your FedEx account number.'),
      '#default_value' => $this->configuration['api_information']['account_number'],
    ];
    $form['api_information']['meter_number'] = [
      '#type' => 'number',
      '#min' => 0,
      '#step' => 1,
      '#title' => $this->t('Meter number'),
      '#description' => $this->t('Enter your FedEx meter number.'),
      '#default_value' => $this->configuration['api_information']['meter_number'],
    ];
    $form['api_information']['mode'] = [
      '#type' => 'select',
      '#title' => $this->t('Mode'),
      '#description' => $this->t('Choose whether to use the test or live mode.'),
      '#options' => [
        'test' => $this->t('Test'),
        'live' => $this->t('Live'),
      ],
      '#default_value' => $this->configuration['api_information']['mode'],
    ];
    $form['options'] = [
      '#type' => 'details',
      '#title' => $this->t('Fedex Options'),
      '#description' => $this->t('Additional options for Fedex'),
    ];
    $form['options']['packaging'] = [
      '#type' => 'select',
      '#title' => $this->t('Packaging strategy'),
      '#description' => $this->t('Select your packaging strategy. "All items in one box" will ignore package type and product dimensions, and assume all items go in one box. "Each item in its own box" will create a box for each line item in the order, "Calculate" Will attempt to figure out how many boxes are needed based on package type volumes and product volumes similar to commerce_fedex 7.x'),
      '#options' => [
        static::PACKAGE_ALL_IN_ONE => $this->t('All items in one box'),
        static::PACKAGE_INDIVIDUAL => $this->t('Each item in its own box'),
        static::PACKAGE_CALCULATE => $this->t('Calculate'),
      ],
      '#default_value' => $this->configuration['options']['packaging'],
    ];
    $form['options']['rate_request_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Pricing options'),
      '#description' => $this->t('Select the pricing option to use when requesting a rate quote. Note that discounted rates are only available when sending production requests.'),
      '#options' => [
        RateRequestType::VALUE_LIST => $this->t('Standard pricing (LIST)'),
        RateRequestType::VALUE_NONE => $this->t("This FedEx account's discounted pricing (ACCOUNT)"),
      ],
      '#default_value' => $this->configuration['options']['rate_request_type'],
    ];
    $form['options']['dropoff'] = [
      '#type' => 'select',
      '#title' => $this->t('Dropoff type'),
      '#description' => $this->t('Default dropoff/pickup location for your FedEx shipments'),
      '#options' => static::enumToList(DropoffType::getValidValues()),
      '#default_value' => $this->configuration['options']['dropoff'],
    ];
    $form['options']['insurance'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Include insurance'),
      '#description' => $this->t('Include insurance value of shippable line items in FedEx rate requests'),
      '#default_value' => $this->configuration['options']['insurance'],
    ];
    $form['options']['rate_multiplier'] = [
      '#type' => 'number',
      '#title' => $this->t('Rate multiplier'),
      '#description' => $this->t('A number that each rate returned from FedEx will be multiplied by. For example, enter 1.5 to mark up shipping costs to 150%.'),
      '#min' => 0.01,
      '#step' => 0.01,
      '#default_value' => $this->configuration['options']['rate_multiplier'],
    ];
    $form['options']['round'] = [
      '#type' => 'select',
      '#title' => $this->t('Round type'),
      '#description' => $this->t('Choose how the shipping rate should be rounded.'),
      '#options' => [
        PHP_ROUND_HALF_UP => 'Half up',
        PHP_ROUND_HALF_DOWN => 'Half down',
        PHP_ROUND_HALF_EVEN => 'Half even',
        PHP_ROUND_HALF_ODD => 'Half odd',
      ],
      '#default_value' => $this->configuration['options']['round'],
    ];
    $form['options']['log'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Log the following messages for debugging'),
      '#options' => [
        'request' => $this->t('API request messages'),
        'response' => $this->t('API response messages'),
      ],
      '#default_value' => $this->configuration['options']['log'],
    ];
    $form['options']['tracking_url'] = [
      '#type' => 'url',
      '#size' => 100,
      '#title' => $this->t('Tracking URL base'),
      '#description' => $this->t('The base URL for assembling a tracking URL. If the [tracking_code] token is omitted, the code will be appended to the end of the URL.'),
      '#default_value' => $this->configuration['options']['tracking_url'],
    ];

    foreach ($this->fedExServiceManager->getDefinitions() as $plugin_id => $definition) {
      /** @var \Drupal\commerce_fedex\Plugin\Commerce\FedEx\FedExPluginInterface $plugin */
      $plugin = $this->plugins->get($plugin_id);
      $subform = [
        '#type' => 'details',
        '#title' => $definition['options_label']->render(),
        '#description' => $definition['options_description']->render(),
      ];
      $form[$plugin_id] = $plugin->buildConfigurationForm($subform, $form_state);
      if ($form[$plugin_id] == $subform) {
        unset($form[$plugin_id]);
      }
    }
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {

    parent::validateConfigurationForm($form, $form_state);

    foreach ($this->fedExServiceManager->getDefinitions() as $plugin_id => $definition) {
      if (!empty($form[$plugin_id])) {
        /** @var \Drupal\commerce_fedex\Plugin\Commerce\FedEx\FedExPluginInterface $plugin */
        $plugin = $this->plugins->get($plugin_id);
        $plugin->validateConfigurationForm($form[$plugin_id], SubformState::createForSubform($form[$plugin_id], $form_state->getCompleteForm(), $form_state));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    if (!$form_state->getErrors()) {

      $values = $form_state->getValue($form['#parents']);

      $this->configuration['api_information']['api_key'] = $values['api_information']['api_key'];
      if (!empty($values['api_information']['api_password'])) {
        $this->configuration['api_information']['api_password'] = $values['api_information']['api_password'];
      }
      $this->configuration['api_information']['account_number'] = $values['api_information']['account_number'];
      $this->configuration['api_information']['meter_number'] = $values['api_information']['meter_number'];
      $this->configuration['api_information']['mode'] = $values['api_information']['mode'];
      $this->configuration['options']['packaging'] = $values['options']['packaging'];
      $this->configuration['options']['rate_request_type'] = $values['options']['rate_request_type'];
      $this->configuration['options']['dropoff'] = $values['options']['dropoff'];
      $this->configuration['options']['insurance'] = $values['options']['insurance'];
      $this->configuration['options']['rate_multiplier'] = $values['options']['rate_multiplier'];
      $this->configuration['options']['round'] = $values['options']['round'];
      $this->configuration['options']['log'] = $values['options']['log'];

      unset($this->configuration['plugins']);
      foreach ($this->fedExServiceManager->getDefinitions() as $plugin_id => $definition) {
        /** @var \Drupal\commerce_fedex\Plugin\Commerce\FedEx\FedExPluginInterface $plugin */
        $plugin = $this->plugins->get($plugin_id);
        if (!empty($form[$plugin_id])) {
          $plugin->submitConfigurationForm($form[$plugin_id], SubformState::createForSubform($form[$plugin_id], $form_state->getCompleteForm(), $form_state));
        }
        $this->configuration['plugins'][$plugin_id]['configuration'] = $plugin->getConfiguration();

      }
    }
    parent::submitConfigurationForm($form, $form_state);
  }

  /**
   * Logs Requests and responses from FedEx.
   *
   * @param string $message
   *   The message to log.
   * @param mixed $data
   *   The FedEx Request or response object.
   * @param string $level
   *   The Log level.
   * @param bool $skip_config_check
   *   Whether to skip the check to log or not.
   */
  protected function logRequest(string $message, $data, string $level = LogLevel::INFO, bool $skip_config_check = FALSE): void {
    /* If we are using the old configuration */
    if (is_array($this->configuration['options']['log'])) {

    }

    if ($skip_config_check || @$this->configuration['options']['log']['request']) {
      $this->watchdog->log($level, "$message <br><pre>@rate_request</pre>", [
        '@rate_request' => var_export($data, TRUE),
      ]);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function calculateRates(ShipmentInterface $shipment) {

    if ($shipment->getShippingProfile()->get('address')->isEmpty()) {
      return [];
    }

    if (empty($shipment->getPackageType())) {
      $shipment->setPackageType($this->getDefaultPackageType());
    }

    $error_reporting = error_reporting();
    // A deprecation message surfaces from the referenced
    // nicholascreativemedia/fedexphp library.
    // Ignore that warning, as that library will soon be replaced.
    error_reporting($error_reporting & ~E_DEPRECATED);
    $rate_service = $this->fedExRequest->getRateService($this->configuration);
    error_reporting($error_reporting);
    $rate_request = $this->getRateRequest($rate_service, $shipment);

    $rate_request_cache_key = 'rate-request-' . hash('sha256', serialize($rate_request));
    $rate_request_cache = $this->cacheBackend->get($rate_request_cache_key);
    if ($rate_request_cache !== FALSE) {
      $this->logRequest('Retrieving Cached FedEx request.', $rate_request);
      $response = $rate_request_cache->data;
    }
    else {
      $this->logRequest('Sending FedEx request.', $rate_request);
      $response = $rate_service->getRates($rate_request);
    }
    $rates = [];

    if ($response) {
      $this->logRequest('FedEx response received.', $response);
      $allowed_severities = ['SUCCESS', 'NOTE', 'WARNING'];
      $highest_severity = $response->getHighestSeverity();
      $severity_map = [
        'NOTE' => LogLevel::NOTICE,
        'WARNING' => LogLevel::WARNING,
        'ERROR' => LogLevel::ERROR,
        'FAILURE' => LogLevel::ERROR,
      ];

      // Log any notifications returned from FedEx.
      foreach ($response->getNotifications() as $notification) {
        if (array_key_exists($notification->getSeverity(), $severity_map)) {
          $this->watchdog->log($severity_map[$notification->getSeverity()], '@message (Code @code)', [
            '@message' => $notification->getMessage(),
            '@code' => $notification->getCode(),
          ]);
        }
      }

      if (in_array($highest_severity, $allowed_severities)) {
        $multiplier = (!empty($this->configuration['options']['rate_multiplier']))
          ? $this->configuration['options']['rate_multiplier']
          : 1.0;
        $round = !empty($this->configuration['options']['round'])
          ? $this->configuration['options']['round']
          : PHP_ROUND_HALF_UP;
        foreach ($response->getRateReplyDetails() as $rate_reply_details) {
          if (array_key_exists($rate_reply_details->getServiceType(), $this->getServices())) {
            // FedEx returns both Account and List prices within the same
            // RatedShipment object. Loop through each Rate Detail to find the
            // appropriate Account/List rate as configured.
            foreach ($rate_reply_details->getRatedShipmentDetails() as $rated_shipment) {
              $rate_details = $rated_shipment->getShipmentRateDetail();
              $rate_type = strpos($rate_details->getRateType(), 'ACCOUNT') ? RateRequestType::VALUE_NONE : RateRequestType::VALUE_LIST;
              if ($this->configuration['options']['rate_request_type'] !== $rate_type) {
                continue;
              }

              $service = $this->services[$rate_reply_details->getServiceType()];
              $cost = $rate_details->getTotalNetChargeWithDutiesAndTaxes();
              $price = new Price((string) $cost->getAmount(), $cost->getCurrency());
              if ($multiplier != 1) {
                $price = $price->multiply((string) $multiplier);
              }
              $price = $this->rounder->round($price, $round);

              $rates[] = new ShippingRate([
                'shipping_method_id' => $this->parentEntity->id(),
                'service' => $service,
                'amount' => $price,
              ]);
            }
          }
        }
        $this->cacheBackend->set($rate_request_cache_key, $response, time() + self::RATE_CACHE_DURATION);
      }
    }
    else {
      $this->logRequest('FedEx sent no response back.', $rate_request, LogLevel::NOTICE, TRUE);
    }

    return $rates;
  }

  /**
   * Updates the configuration array with currently discovered plugins.
   *
   * @return $this
   */
  public function updatePlugins(): self {
    $definitions = $this->fedExServiceManager->getDefinitions();
    $this->configuration['plugins'] = array_intersect_key($this->configuration['plugins'], $definitions);
    foreach ($definitions as $plugin_id => $definition) {
      if (empty($this->configuration['plugins'][$plugin_id])) {
        $this->configuration['plugins'][$plugin_id] = [
          'plugin_id' => $plugin_id,
          'configuration' => ['id' => $plugin_id],
        ];
      }
    }
    return $this;
  }

  /**
   * Loads the Plugin Collection.
   *
   * @return $this
   */
  public function getPlugins(): self {
    $plugins = [];
    foreach ($this->configuration['plugins'] as $plugin_id => $plugin_data) {
      $plugins[$plugin_id] = $plugin_data['configuration'];
    }
    $this->plugins = new DefaultLazyPluginCollection($this->fedExServiceManager, $plugins);
    return $this;
  }

  /**
   * Convert a FedEx API enum array to an options list.
   *
   * @param array $enums
   *   The FedEx API enum array.
   *
   * @return array
   *   The options list.
   */
  public static function enumToList(array $enums): array {
    return array_combine($enums, array_map(static function ($d) {
      return ucwords(strtolower(str_replace('_', ' ', $d)));
    }, $enums));
  }

  /**
   * Gets a FedEx address object from the provided Drupal address object.
   *
   * @param \Drupal\address\AddressInterface $address
   *   The Drupal address object.
   *
   * @return \NicholasCreativeMedia\FedExPHP\Structs\Party
   *   The address for FedEx.
   */
  protected function getAddressForFedEx(AddressInterface $address): Party {
    $custom_resolver = 'addressResolve' . $address->getCountryCode();

    if (method_exists($this, $custom_resolver)) {
      $party = $this->$custom_resolver($address);
    }
    else {
      $party = new Party();

      $party->setAddress(new Address(
        array_filter([
          $address->getAddressLine1(),
          $address->getAddressLine2(),
        ]),
        $address->getLocality(),
        $address->getAdministrativeArea(),
        $address->getPostalCode(),
        NULL,
        $address->getCountryCode(),
        NULL,
        FALSE
      ));
    }
    return $party;
  }

  /**
   * Gets the requested package line items.
   *
   * @param \Drupal\commerce_shipping\Entity\ShipmentInterface $shipment
   *   The shipment.
   *
   * @return \NicholasCreativeMedia\FedExPHP\Structs\RequestedPackageLineItem[]
   *   The requested package line items.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\TypedData\Exception\MissingDataException
   */
  protected function getRequestedPackageLineItems(ShipmentInterface $shipment): array {
    $requested_package_line_items = [];

    switch ($this->configuration['options']['packaging']) {
      case static::PACKAGE_ALL_IN_ONE:
        $requested_package_line_items = $this->getRequestedPackageLineItemsAllInOne($shipment);
        break;

      case static::PACKAGE_INDIVIDUAL:
        $requested_package_line_items = $this->getRequestedPackageLineItemsIndividual($shipment);
        break;

      case static::PACKAGE_CALCULATE:
        $requested_package_line_items = $this->getRequestedPackageLineItemsCalculate($shipment);
        break;
    }

    return $requested_package_line_items;
  }

  /**
   * Gets the volume of the provided package type.
   *
   * @param \Drupal\commerce_shipping\Plugin\Commerce\PackageType\PackageTypeInterface $package_type
   *   The package type.
   *
   * @return float
   *   The volume.
   */
  protected function getPackageVolume(PackageTypeInterface $package_type): float {
    $unit = $package_type->getHeight()->getUnit();

    $number = $package_type
      ->getHeight()
      ->multiply($package_type->getWidth()->convert($unit)->getNumber())
      ->multiply($package_type->getLength()->convert($unit)->getNumber())
      ->getNumber();

    return (float) $number;
  }

  /**
   * Gets a cleaned title string for use in sending to FedEx.
   *
   * @param \Drupal\commerce_shipping\ShipmentItem $shipment_item
   *   The shipment item.
   *
   * @return string
   *   The cleaned title.
   */
  protected function getCleanTitle(ShipmentItem $shipment_item): string {
    $title = $shipment_item->getTitle();
    $title = preg_replace('/[^A-Za-z0-9 ]/', ' ', $title);
    $title = preg_replace('/ +/', ' ', $title);

    return trim($title);
  }

  /**
   * Gets package line items for PACKAGE_ALL_IN_ONE strategy.
   *
   * @param \Drupal\commerce_shipping\Entity\ShipmentInterface $shipment
   *   The shipment.
   *
   * @return array
   *   The package line items.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\TypedData\Exception\MissingDataException
   */
  protected function getRequestedPackageLineItemsAllInOne(ShipmentInterface $shipment): array {

    $packages = $this->splitPackages($shipment);

    $requested_package_line_items = [];

    foreach ($packages as $delta => $package) {
      $requested_package_line_item = new RequestedPackageLineItem();
      $shipment_title = $shipment->getTitle();

      if (!is_string($shipment_title)) {
        $shipment_title = $shipment_title->render();
      }

      $requested_package_line_item
        ->setSequenceNumber($delta + 1)
        ->setGroupPackageCount(1)
        ->setWeight(static::packageTotalWeight($package, $shipment->getPackageType()))
        ->setDimensions(static::packageToFedexDimensions($shipment->getPackageType()))
        ->setPhysicalPackaging(PhysicalPackagingType::VALUE_BOX)
        ->setItemDescription($shipment_title);

      if ($this->configuration['options']['insurance']) {
        $requested_package_line_item->setInsuredValue(new Money(
          $shipment->getTotalDeclaredValue()->getCurrencyCode(),
          $shipment->getTotalDeclaredValue()->getNumber()
        ));
      }

      $requested_package_line_items[] = $this->adjustPackage($requested_package_line_item, $package, $shipment);
    }

    return $requested_package_line_items;
  }

  /**
   * Gets package line items for PACKAGE_CALCULATE strategy.
   *
   * @param \Drupal\commerce_shipping\Entity\ShipmentInterface $shipment
   *   The shipment.
   *
   * @return array
   *   The package line items.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\TypedData\Exception\MissingDataException
   */
  protected function getRequestedPackageLineItemsCalculate(ShipmentInterface $shipment): array {
    $requested_package_line_items = $this->getRequestedPackageLineItemsAllInOne($shipment);
    $packages = $this->splitPackages($shipment);
    foreach ($requested_package_line_items as $requested_package_line_item) {
      /** @var \NicholasCreativeMedia\FedExPHP\Structs\RequestedPackageLineItem $requested_package_line_item */
      $count = static::calculatePackageCount($requested_package_line_item, $packages[$requested_package_line_item->getSequenceNumber() - 1]);
      if ($count) {
        $requested_package_line_item->setGroupPackageCount($count);
        $requested_package_line_item->getWeight()->setValue($requested_package_line_item->getWeight()->getValue() / $count);
      }
    }

    return $requested_package_line_items;
  }

  /**
   * Gets package line items for PACKAGE_INDIVIDUAL strategy.
   *
   * @param \Drupal\commerce_shipping\Entity\ShipmentInterface $shipment
   *   The shipment.
   *
   * @return array
   *   The package line items.
   */
  protected function getRequestedPackageLineItemsIndividual(ShipmentInterface $shipment): array {
    $requested_package_line_items = [];
    $weightUnits = '';
    foreach ($shipment->getItems() as $delta => $shipment_item) {
      $requested_package_line_item = new RequestedPackageLineItem();
      if ($weightUnits === '') {
        /* All packages must have the same Weight Unit */
        $weightUnits = $shipment_item->getWeight()->getUnit();
      }

      $requested_package_line_item
        ->setSequenceNumber($delta + 1)
        ->setGroupPackageCount(1)
        ->setWeight(self::physicalWeightToFedex($shipment_item->getWeight()->convert($weightUnits)))
        ->setDimensions(self::packageToFedexDimensions($shipment->getPackageType()))
        ->setPhysicalPackaging(PhysicalPackagingType::VALUE_BOX)
        ->setItemDescription($this->getCleanTitle($shipment_item));

      if ($this->configuration['options']['insurance']) {
        $requested_package_line_item->setInsuredValue(new Money(
          $shipment_item->getDeclaredValue()->getCurrencyCode(),
          $shipment_item->getDeclaredValue()->getNumber()
        ));
      }
      $this->adjustPackage($requested_package_line_item, [$shipment_item], $shipment);
      $requested_package_line_items[] = $requested_package_line_item;
    }

    return $requested_package_line_items;
  }

  /**
   * Gets a RequestedShipment object for FedEx.
   *
   * @param \Drupal\commerce_shipping\Entity\ShipmentInterface $shipment
   *   The shipment.
   *
   * @return \NicholasCreativeMedia\FedExPHP\Structs\RequestedShipment
   *   The RequestedShipment object.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\TypedData\Exception\MissingDataException
   */
  protected function getFedExShipment(ShipmentInterface $shipment): RequestedShipment {
    $line_items = $this->getRequestedPackageLineItems($shipment);

    if ($this->configuration['options']['packaging'] === static::PACKAGE_CALCULATE) {
      $count = 0;
      foreach ($line_items as $line_item) {
        $count += $line_item->getGroupPackageCount();
      }
    }
    else {
      $count = count($line_items);
    }

    /** @var \Drupal\address\AddressInterface $recipient_address */
    $recipient_address = $shipment->getShippingProfile()->get('address')->first();
    $shipper_address = $shipment->getOrder()->getStore()->getAddress();

    $fedex_shipment = new RequestedShipment();

    $fedex_shipment
      ->setShipper($this->getAddressForFedEx($shipper_address))
      ->setRecipient($this->getAddressForFedEx($recipient_address))
      ->setRequestedPackageLineItems($line_items)
      ->setPackageCount($count)
      ->setPreferredCurrency($shipment->getOrder()->getStore()->getDefaultCurrencyCode())
      ->setDropoffType($this->configuration['options']['dropoff'])
      ->setRateRequestTypes([$this->configuration['options']['rate_request_type']]);

    if ($this->configuration['options']['insurance']) {
      $fedex_shipment->setTotalInsuredValue(new Money(
        $shipment->getTotalDeclaredValue()->getCurrencyCode(),
        $shipment->getTotalDeclaredValue()->getNumber()
      ));
    }

    return $fedex_shipment;
  }

  /**
   * Queries plugins to split shipments into FedEx packages.
   *
   * @param \Drupal\commerce_shipping\Entity\ShipmentInterface $shipment
   *   The shipment to split.
   *
   * @return array
   *   An array of arrays or shipment items.
   */
  protected function splitPackages(ShipmentInterface $shipment): array {
    $packages = [$shipment->getItems()];
    foreach ($this->fedExServiceManager->getDefinitions() as $plugin_id => $definition) {
      /** @var \Drupal\commerce_fedex\Plugin\Commerce\FedEx\FedExPluginInterface $plugin */
      $plugin = $this->plugins->get($plugin_id);
      $new_packages = [];
      foreach ($packages as $package) {
        $new_packages = array_merge($new_packages, $plugin->splitPackage($package, $shipment));
      }
      $packages = array_filter($new_packages);
    }
    return $packages;
  }

  /**
   * Query plugins for package adjustments.
   *
   * @param \NicholasCreativeMedia\FedExPHP\Structs\RequestedPackageLineItem $requested_package_line_item
   *   Te package line item to be adjusted.
   * @param array $shipment_items
   *   The shipment items in the package.
   * @param \Drupal\commerce_shipping\Entity\ShipmentInterface $shipment
   *   The full shipment.
   *
   * @return \NicholasCreativeMedia\FedExPHP\Structs\RequestedPackageLineItem
   *   The adjusted line item.
   */
  protected function adjustPackage(RequestedPackageLineItem $requested_package_line_item, array $shipment_items, ShipmentInterface $shipment): RequestedPackageLineItem {
    foreach ($this->fedExServiceManager->getDefinitions() as $plugin_id => $definition) {
      /** @var \Drupal\commerce_fedex\Plugin\Commerce\FedEx\FedExPluginInterface $plugin */
      $plugin = $this->plugins->get($plugin_id);
      $requested_package_line_item = $plugin->adjustPackage($requested_package_line_item, $shipment_items, $shipment);
    }
    return $requested_package_line_item;
  }

  /**
   * Gets a rate request object for FedEx.
   *
   * @param \NicholasCreativeMedia\FedExPHP\Services\RateService $rate_service
   *   The FedEx Rate Service.
   * @param \Drupal\commerce_shipping\Entity\ShipmentInterface $shipment
   *   The shipment.
   *
   * @return \NicholasCreativeMedia\FedExPHP\Structs\RateRequest
   *   The rate request object.
   *
   * @throws \Drupal\Core\TypedData\Exception\MissingDataException
   */
  protected function getRateRequest(RateService $rate_service, ShipmentInterface $shipment): RateRequest {
    $rate_request = $this->fedExRequest->getRateRequest($this->configuration);
    $rate_request->setRequestedShipment($this->getFedExShipment($shipment));
    $rate_request->setVersion($rate_service->version);

    // Allow other modules to alter the rate request before it's submitted.
    $rateRequestEvent = new RateRequestEvent($rate_request, $rate_service, $shipment);
    $this->eventDispatcher->dispatch($rateRequestEvent, CommerceFedExEvents::BEFORE_RATE_REQUEST);

    return $rate_request;
  }

  /**
   * Return the total volume of an array of shipment items in a given unit.
   *
   * @param array $shipment_items
   *   The array of shipment items.
   * @param string|null $volume_unit
   *   The volume unit.
   *
   * @return \Drupal\physical\Volume
   *   The total Volume.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\TypedData\Exception\MissingDataException
   */
  protected static function getPackageTotalVolume(array $shipment_items, ?string $volume_unit = NULL): Volume {
    if ($volume_unit === NULL) {
      $volume_unit = VolumeUnit::CUBIC_CENTIMETER;
    }
    switch ($volume_unit) {
      case VolumeUnit::CUBIC_CENTIMETER:
        $linear_unit = PhysicalLengthUnits::CENTIMETER;
        break;

      case VolumeUnit::CUBIC_INCH:
        $linear_unit = PhysicalLengthUnits::INCH;
        break;

      default:
        throw new \RuntimeException('Invalid Units');
    }
    $order_item_storage = \Drupal::entityTypeManager()->getStorage('commerce_order_item');
    $order_item_ids = [];
    foreach ($shipment_items as $shipment_item) {
      $order_item_ids[] = $shipment_item->getOrderItemId();
    }

    $order_items = $order_item_storage->loadMultiple($order_item_ids);

    $total_volume = 0;
    foreach ($order_items as $order_item) {
      /** @var \Drupal\commerce_order\Entity\OrderItem $order_item */
      /** @var \Drupal\commerce_product\Entity\ProductVariationInterface $purchased_entity */
      $purchased_entity = $order_item->getPurchasedEntity();

      if ($purchased_entity->hasField('dimensions') && !$purchased_entity->get('dimensions')->isEmpty()) {
        /** @var \Drupal\physical\Plugin\Field\FieldType\DimensionsItem $dimensions */
        $dimensions = $purchased_entity->get('dimensions')->first();

        $volume = $dimensions
          ->getHeight()
          ->convert($linear_unit)
          ->multiply($dimensions->getWidth()->convert($linear_unit)->getNumber())
          ->multiply($dimensions->getLength()->convert($linear_unit)->getNumber())
          ->getNumber();

        $total_volume += (float) $volume * $order_item->getQuantity();
      }
    }

    return new Volume((string) $total_volume, $volume_unit);
  }

  /**
   * Gets the shipment's total volume in the same units as the package type.
   *
   * @param \Drupal\commerce_shipping\Entity\ShipmentInterface $shipment
   *   The shipment.
   *
   * @return float
   *   The volume.
   *
   * @throws \Drupal\Core\TypedData\Exception\MissingDataException
   */
  protected function getShipmentTotalVolume(ShipmentInterface $shipment) {
    $order_item_ids = [];

    foreach ($shipment->getItems() as $shipment_item) {
      $order_item_ids[] = $shipment_item->getOrderItemId();
    }

    $order_items = $this->orderItemStorage->loadMultiple($order_item_ids);
    $unit = $shipment->getPackageType()->getHeight()->getUnit();

    $total_volume = 0;
    foreach ($order_items as $orderItem) {
      /** @var \Drupal\commerce_order\Entity\OrderItem $orderItem */
      $purchased_entity = $orderItem->getPurchasedEntity();

      if ($purchased_entity->hasField('dimensions') && !$purchased_entity->get('dimensions')->isEmpty()) {
        /** @var \Drupal\physical\Plugin\Field\FieldType\DimensionsItem $dimensions */
        $dimensions = $purchased_entity->get('dimensions')->first();

        $volume = $dimensions
          ->getHeight()
          ->convert($unit)
          ->multiply($dimensions->getWidth()->convert($unit)->getNumber())
          ->multiply($dimensions->getLength()->convert($unit)->getNumber())
          ->getNumber();

        $total_volume += (float) $volume * $orderItem->getQuantity();
      }
    }

    return $total_volume;
  }

  /**
   * Determine if we have the minimum information to connect to FedEx.
   *
   * @return bool
   *   TRUE if there is enough information to connect, FALSE otherwise.
   */
  protected function isConfigured(): bool {
    $api_information = $this->configuration['api_information'];

    return (
      !empty($api_information['api_key'])
      && !empty($api_information['api_password'])
      && !empty($api_information['account_number'])
      && !empty($api_information['meter_number'])
    );
  }

  /**
   * Convert physical weight to FedEx weight object.
   *
   * @param \Drupal\physical\Weight $weight
   *   The weight from Drupal.
   *
   * @return \NicholasCreativeMedia\FedExPHP\Structs\Weight
   *   The weight for FedEx.
   */
  public static function physicalWeightToFedex(PhysicalWeight $weight): FedexWeight {
    $number = $weight->getNumber();

    switch ($weight->getUnit()) {
      case PhysicalWeightUnits::GRAM:
        $number /= 1000;
        $unit = FedexWeightUnits::VALUE_KG;
        break;

      case PhysicalWeightUnits::KILOGRAM:
        $unit = FedexWeightUnits::VALUE_KG;
        break;

      case PhysicalWeightUnits::OUNCE:
        $number /= 16;
        $unit = FedexWeightUnits::VALUE_LB;
        break;

      case PhysicalWeightUnits::POUND:
        $unit = FedexWeightUnits::VALUE_LB;
        break;

      default:
        throw new \RuntimeException('Invalid Units for Weight');
    }

    return new FedexWeight($unit, $number);
  }

  /**
   * Get FedEx dimensions object for the given package.
   *
   * @param \Drupal\commerce_shipping\Plugin\Commerce\PackageType\PackageTypeInterface $package
   *   The package.
   *
   * @return \NicholasCreativeMedia\FedExPHP\Structs\Dimensions
   *   The dimensions.
   */
  public static function packageToFedexDimensions(PackageTypeInterface $package): Dimensions {
    $length = $package->getLength();
    $width = $package->getWidth();
    $height = $package->getHeight();

    if ($width->getUnit() !== $length->getUnit()) {
      $width = $width->convert($length->getUnit());
    }

    if ($height->getUnit() !== $length->getUnit()) {
      $height = $height->convert($length->getUnit());
    }

    switch ($length->getUnit()) {
      case PhysicalLengthUnits::MILLIMETER:
        $length = $length->divide(1000);
        $width = $width->divide(1000);
        $height = $height->divide(1000);
        $unit = FedexLengthUnits::VALUE_CM;
        break;

      case PhysicalLengthUnits::CENTIMETER:
        $unit = FedexLengthUnits::VALUE_CM;
        break;

      case PhysicalLengthUnits::METER;
        $length = $length->multiply(1000);
        $width = $width->multiply(1000);
        $height = $height->multiply(1000);
        $unit = FedexLengthUnits::VALUE_CM;
        break;

      case PhysicalLengthUnits::FOOT:
        $length = $length->multiply(12);
        $width = $width->multiply(12);
        $height = $height->multiply(12);
        $unit = FedexLengthUnits::VALUE_IN;
        break;

      case PhysicalLengthUnits::INCH:
        $unit = FedexLengthUnits::VALUE_IN;
        break;

      default:
        throw new \RuntimeException('Invalid Units for Length');
    }

    return new Dimensions($length->getNumber(), $width->getNumber(), $height->getNumber(), $unit);
  }

  /**
   * Returns a bool indicating if the shipment is domestic.
   *
   * @param \Drupal\commerce_shipping\Entity\ShipmentInterface $shipment
   *   The shipment.
   *
   * @return bool
   *   TRUE if the shipment is domestic, FALSE otherwise.
   *
   * @throws \Drupal\Core\TypedData\Exception\MissingDataException
   */
  public static function isDomestic(ShipmentInterface $shipment): bool {

    /** @var \Drupal\address\Plugin\Field\FieldType\AddressItem $shipping_address */
    $shipping_address = $shipment->getShippingProfile()->get('address')->first();

    $domestic = $shipping_address instanceof AddressItem && $shipment->getOrder()->getStore()->getAddress()->getCountryCode() === $shipping_address->getCountryCode();

    return $domestic;
  }

  /**
   * Gets the shipping type of the shipment.
   *
   * @param \Drupal\commerce_shipping\Entity\ShipmentInterface $shipment
   *   The Shipment.
   *
   * @return string
   *   A constant representing the shipment type
   *
   * @throws \Drupal\Core\TypedData\Exception\MissingDataException
   */
  public static function shippingType(ShipmentInterface $shipment): string {
    return static::isDomestic($shipment)
      ? self::SHIPPING_TYPE_DOMESTIC
      : self::SHIPPING_TYPE_INTERNATIONAL;
  }

  /**
   * Function packageTotalWeight.
   *
   * @param array $shipment_items
   *   An array of shipment items.
   * @param \Drupal\commerce_shipping\Plugin\Commerce\PackageType\PackageTypeInterface $package_type
   *   The commerce_shipping package type.
   * @param \Drupal\physical\Weight|null $adjustment
   *   Any weight adjustment to add or subtract.
   *
   * @return \NicholasCreativeMedia\FedExPHP\Structs\Weight
   *   The total weight of the package.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\TypedData\Exception\MissingDataException
   */
  public static function packageTotalWeight(array $shipment_items, PackageTypeInterface $package_type, PhysicalWeight $adjustment = NULL): FedexWeight {

    if (empty($shipment_items)) {
      return new FedexWeight('LB', 0);
    }

    $order_item_storage = \Drupal::entityTypeManager()->getStorage('commerce_order_item');
    $unit = $order_item_storage->load(reset($shipment_items)->getOrderItemId())->getPurchasedEntity()->get('weight')->first()->toMeasurement()->getUnit();
    $total_weight = new PhysicalWeight('0', $unit);
    /** @var \Drupal\commerce_shipping\ShipmentItem $shipment_item */
    foreach ($shipment_items as $shipment_item) {
      $total_weight = $total_weight->add($shipment_item->getWeight());
    }
    $total_weight = $total_weight->add($package_type->getWeight()->convert($unit));
    if ($adjustment) {
      $total_weight = $total_weight->add($adjustment->convert($unit));
    }
    return static::physicalWeightToFedex($total_weight);
  }

  /**
   * Determines a package count given package and item volumes.
   *
   * @param \NicholasCreativeMedia\FedExPHP\Structs\RequestedPackageLineItem $requested_package_line_item
   *   The package line item containing the FedEx package dimensions.
   * @param array $shipment_items
   *   The array of shipment items.
   *
   * @return bool|float
   *   The number of needed packages, or False on error.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\TypedData\Exception\MissingDataException
   */
  public static function calculatePackageCount(RequestedPackageLineItem $requested_package_line_item, array $shipment_items) {
    $package_volume = static::getFedexPackageVolume($requested_package_line_item->getDimensions());
    $items_volume = static::getPackageTotalVolume($shipment_items, $package_volume->getUnit());

    if ($package_volume->getNumber() != 0) {
      return ceil($items_volume->getNumber() / $package_volume->getNumber());
    }
    return FALSE;

  }

  /**
   * Determine the package volume from a FedEx package dimensions item.
   *
   * @param \NicholasCreativeMedia\FedExPHP\Structs\Dimensions $dimensions
   *   The FedEx package dimensions item.
   *
   * @return \Drupal\physical\Volume
   *   The total package volume.
   */
  public static function getFedexPackageVolume(Dimensions $dimensions): Volume {
    $volume_value = $dimensions->getHeight() * $dimensions->getLength() * $dimensions->getWidth();
    switch ($dimensions->getUnits()) {
      case LinearUnits::VALUE_CM:
        $volume_units = VolumeUnit::CUBIC_CENTIMETER;
        break;

      case LinearUnits::VALUE_IN:
        $volume_units = VolumeUnit::CUBIC_INCH;
        break;

      default:
        throw new \RuntimeException('Invalid Dimensions');
    }
    return new Volume((string) $volume_value, $volume_units);
  }

  /**
   * {@inheritDoc}
   */
  public function getTrackingUrl(ShipmentInterface $shipment) {
    $code = $shipment->getTrackingCode();
    if (!empty($code)) {
      // If the tracking code token exists, replace it with the code.
      if (stripos($this->configuration['options']['tracking_url'], '[tracking_code]') !== FALSE) {
        $url = str_replace('[tracking_code]', $code, $this->configuration['options']['tracking_url']);
      }
      else {
        // Otherwise, append the tracking code to the end of the URL.
        $url = $this->configuration['options']['tracking_url'] . $code;
      }

      return Url::fromUri($url);
    }
    return NULL;
  }

}
