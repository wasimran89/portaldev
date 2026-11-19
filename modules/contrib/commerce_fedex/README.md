# Commerce FedEx

Commerce FedEx provides direct FedEx integration with your
Drupal Commerce 2.x e-commerce site on Drupal 8/9/10.

Supporting all FedEx shipping services and an increasing number
of FedEx's special services, such as Hazardous Materials and Dry Ice,
Commerce FedEx is designed to be your one-stop solution
for all of your FedEx shipping requirements.

## REQUIREMENTS

This module requires the following:

- Drupal 8/9/10
- [Commerce](https://drupal.org/project/commerce)
- [Commerce Shipping](https://drupal.org/project/commerce_shipping)
- A FedEx shipping account from [FedEx.com](http://www.fedex.com/)
- API credentials provided at <https://developer.fedex.com/>

The following patches are required for commerce core versions prior to 2.3.9:

- <https://www.drupal.org/project/commerce/issues/3021571>
  That resolves the
  issue: <https://www.drupal.org/project/commerce_fedex/issues/2971197> When
  updating the configuration form your password gets wiped

## INSTALLATION

Install as you would normally install a contributed Drupal module. Visit
https://www.drupal.org/docs/extending-drupal/installing-modules for
further information.

Preferably, use [Composer](https://getcomposer.org/) to get Commerce FedEx and
all of its dependencies installed on your Drupal site:

```
composer require drupal/commerce_fedex
```

Next, go to your site and enable the "Commerce FedEx" module.

## CONFIGURATION

Go to admin/commerce/shipping-methods to add a new FedEx shipping method.

## KNOWN ISSUES AND LIMITATIONS

[Issue Tracker](https://www.drupal.org/project/issues/commerce_fedex?version=8.x)
