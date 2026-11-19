<?php

namespace Drupal\redirect_after_logout\EventSubscriber;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Url;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * RedirectAfterLogoutSubscriber event subscriber.
 *
 * @package Drupal\redirect_after_logout\EventSubscriber
 */
class RedirectAfterLogoutSubscriber implements EventSubscriberInterface {

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactory
   */
  protected $configFactory;

  /**
   * {@inheritdoc}
   */
  public function __construct(ConfigFactory $configFactory) {
    $this->configFactory = $configFactory;
  }

  /**
   * Check redirection.
   *
   * @param \Symfony\Component\HttpKernel\Event\ResponseEvent $event
   *   Event.
   */
  public function checkRedirection(FilterResponseEvent $event) {
    $destination = &drupal_static('redirect_after_logout_user_logout');
    $response = $event->getResponse();
    if (!$response instanceof RedirectResponse || ! (bool) $destination) {
      return;
    }

    if ($destination == '<front>') {
      $destination = Url::fromRoute($destination);
    }
    elseif (UrlHelper::isExternal($destination)) {
      $destination = Url::fromUri($destination);
    }
    else {
      $destination = Url::fromUri('internal:' . $destination);
    }

    $config = $this->configFactory->get('redirect_after_logout.settings');
    $logout_message = $config->get('message');
    if ($logout_message === '' || $destination->isExternal()) {
      $destination = $destination->toString();
    }
    else {
      $destination = $destination
        ->setOption('query', ['logout-message' => 1])
        ->toString();
    }

    $response = new RedirectResponse($destination);
    $response->send();
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events = [];
    $events[KernelEvents::RESPONSE][] = ['checkRedirection'];
    return $events;
  }

}
