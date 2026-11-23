<?php

/**
 * @file
 * Contains \Drupal\last_visited_pages\LastVisitedPagesSubscriber.
 */

namespace Drupal\last_visited_pages;

use Drupal\Console\Bootstrap\Drupal;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;

/**
 * Subscribes to the kernel request event to completely obliterate the default content.
 *
 * @param \Symfony\Component\HttpKernel\Event\GetResponseEvent $event
 *   The event to process.
 */
class LastVisitedPagesSubscriber implements EventSubscriberInterface {

  /**
   * Redirects the user when they're requesting our nearly blank page.
   *
   * @param \Symfony\Component\HttpKernel\Event\GetResponseEvent $event
   *   The response event.
   */
  public function checkForPlaces(RequestEvent $event) {
      // Grab the saved Last Visited Pages.
    $places = \Drupal::state()->get('recent.places') ?: array();
    $request = \Drupal::request();
    $route_match = \Drupal::routeMatch();
    $batchKeyword = "batch";
    $title = NULL;
    if (strpos($request, $batchKeyword) === false) {
      $title = \Drupal::service('title_resolver')->getTitle($request, $route_match->getRouteObject());
    }

    if(is_array($title)) {
      $title = array_key_exists('#markup', $title) ? $title['#markup'] : $title;
    } elseif (!is_null($title)) {
      $title = strip_tags($title);
    }

      // Save Last Visited Pages only for prominent destinations with titles.
    if(!is_null($title)) {
      $uid = \Drupal::currentUser()->id();
      $path = \Drupal::service('path.current')->getPath();
      $uid = \Drupal::currentUser()->id();
      $ip_address = $_SERVER['REMOTE_ADDR']; // Default to REMOTE_ADDR

      if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
          $ip_address = $_SERVER['HTTP_CLIENT_IP'];
      } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
          // If multiple IPs are in the HTTP_X_FORWARDED_FOR, take the first one.
          $ip_address = trim(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0]);
      }


      $file_insert_id = \Drupal::database()->insert('last_visited_pages')
      ->fields([
        'title',
        'path',
        'timestamp',
        'uid',
        'ip_address'
      ])
      ->values(array(
        $title,
        $path,
        \Drupal::time()->getRequestTime(),
        $uid,
        $ip_address
      ))
      ->execute();

      /*
      //Deleting old record for optimizing database storage.
      // Step 1: Count total records for the user
      $total_records = \Drupal::database()->select('last_visited_pages', 'n')
          ->condition('uid', $uid, '=')
          ->countQuery()
          ->execute()
          ->fetchField();

      // Step 2: If total records are 10,000 or more, delete the oldest 100 records
      if ($total_records >= 10000) {
          // Select the oldest 100 records based on timestamp (ascending order)
          $oldest_records = \Drupal::database()->select('last_visited_pages', 'n')
              ->fields('n', ['id'])
              ->condition('uid', $uid, '=')
              ->orderBy('timestamp', 'ASC') // ASC to get the oldest records
              ->range(0, 100) // Select the first 100 oldest records
              ->execute()
              ->fetchCol();

          // Delete the selected oldest records
          if (!empty($oldest_records)) {
              \Drupal::database()->delete('last_visited_pages')
                  ->condition('id', $oldest_records, 'IN')
                  ->execute();
          }
      }
      */
    }
  }

  /**
   * {@inheritdoc}
   */
  static function getSubscribedEvents(){
    $events[KernelEvents::REQUEST][] = array('checkForPlaces');
    return $events;
  }
}