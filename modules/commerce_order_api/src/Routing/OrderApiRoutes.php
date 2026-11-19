<?php

namespace Drupal\commerce_order_api\Routing;

use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

class OrderApiRoutes {

  public static function routes() {
    $routes = new RouteCollection();

    $routes->add('commerce_order_api.create', new Route(
      '/api/place-order',
      [
        '_controller' => 'Drupal\commerce_order_api\Controller\OrderApiController::placeOrder',
        '_title' => 'Place Order'
      ],
      [
        '_permission' => 'create commerce order'
      ],
      [
        'methods' => ['POST']
      ]
    ));

    return $routes;
  }

}
