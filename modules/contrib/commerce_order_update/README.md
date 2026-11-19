## Commerce Order Status Update

Provides functionality for updating the status of a Commerce order via
Views Bulk Operations action.

### Requirements

Requires Commerce and Commerce Order modules.

https://www.drupal.org/project/commerce

Requires Commerce Order Label module:

https://www.drupal.org/project/commerce_order_label

### Installation

Install the module like any other Drupal module.

### Usage
This module includes an action which you should enable on the order listing
view. View config is included with module:

**/config/optional/views.view.commerce_orders.yml**

* Once view is updated to use "Views Bulk Operations" global field, visit
**/admin/commerce/orders**.
* Select orders you wish to update.
* Once selected, click on "Change Order Status" button.
* On next screen, select which "Order State Status" you wish to update selected
orders to.
* Confirm and execute action.
* Selected orders should have an updated state now.

### Maintainers

* George Anderson (geoanders) - https://www.drupal.org/u/geoanders
