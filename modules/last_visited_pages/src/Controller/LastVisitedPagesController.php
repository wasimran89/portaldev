<?php

namespace Drupal\last_visited_pages\Controller;

use Drupal\comment\CommentStatisticsInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Query\PagerSelectExtender;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\Core\Link;
use Drupal\user\Entity\User;

/**
 * Controller for tracker pages.
 */
class LastVisitedPagesController extends ControllerBase {

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * The database replica connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $databaseReplica;

  /**
   * The comment statistics.
   *
   * @var \Drupal\comment\CommentStatisticsInterface
   */
  protected $commentStatistics;

  /**
   * The date formatter.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * The node storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $nodeStorage;

  /**
   * Constructs a TrackerController object.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   * @param \Drupal\Core\Database\Connection $databaseReplica
   *   The database replica connection.
   * @param \Drupal\comment\CommentStatisticsInterface $commentStatistics
   *   The comment statistics.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $dateFormatter
   *   The date formatter.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   */
  public function __construct(Connection $database, Connection $databaseReplica, CommentStatisticsInterface $commentStatistics, DateFormatterInterface $dateFormatter, EntityTypeManagerInterface $entityTypeManager) {
    $this->database = $database;
    $this->databaseReplica = $databaseReplica;
    $this->commentStatistics = $commentStatistics;
    $this->dateFormatter = $dateFormatter;
    $this->entityTypeManager = $entityTypeManager;
    $this->nodeStorage = $entityTypeManager->getStorage('node');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('database'),
      $container->get('database.replica'),
      $container->get('comment.statistics'),
      $container->get('date.formatter'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * Title callback for the tracker.user_tab route.
   *
   * @param \Drupal\user\UserInterface $user
   *   The user.
   *
   * @return string
   *   The title.
   */
  public function getTitle(UserInterface $user) {
    return $user->getDisplayName();
  }

  /**
   * Checks access for the users recent content tracker page.
   *
   * @param \Drupal\user\UserInterface $user
   *   The user being viewed.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The account viewing the page.
   *
   * @return \Drupal\Core\Access\AccessResult
   *   The access result.
   */
  public function checkAccess(UserInterface $user, AccountInterface $account) {
    return AccessResult::allowedIf($account->isAuthenticated() && $user->id() == $account->id())
      ->cachePerUser();
  }

  /**
   * Builds content for the tracker controllers.
   *
   * @param \Drupal\user\UserInterface|null $user
   *   (optional) The user account.
   *
   * @return array
   *   The render array.
   */

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    $this->configuration['num_places'] = 50;
    return ['label_display' => FALSE];
  }

  public function buildContent(UserInterface $user = NULL) {
    $uid = $user->id();
    $user = User::load($uid);

    // Grab the number of items to display
    $num_items = 50;
    $date_format = 'medium';

    // Add cache metadata to disable caching for this page.
    $cache_metadata = new CacheableMetadata();
    $cache_metadata->setCacheMaxAge(0);
    $cache_metadata->setCacheContexts(['user']);

    // Get Recently visited links
    $result_query = \Drupal::database()->select('last_visited_pages', 'n')
      ->fields('n', array())
      ->condition('uid', $uid, '=')
      ->range(0, $num_items)
      ->orderBy('timestamp', 'DESC');

    $all_result = $result_query
      ->execute()
      ->fetchAllAssoc('id');

    $custom_format = false;

    if ($date_format == 'custom') {
      $custom_format = true;
      $date_format = 'medium';
    }

    // Output the latest items as a list
    $last_visited_pages = '';

    if (!empty($all_result)) {
      $rows = [];
      foreach ($all_result as $key => $item) {        
        $url = Url::fromUri('base:' . $item->path, array('absolute' => TRUE))->toString();

        if ($item->title == '') {
          $item->title = 'Home';
        }
        $config = \Drupal::config('xai.settings');
        $base_path = $config->get('domain');
        $url2 = Url::fromUri($url);
        $link_options = array(
          'attributes' => array(
            'class' => array(
              'last_visited_pages'
            ),
          ),
        );
        $rows[$key]['title'] = Link::fromTextAndUrl($item->title, $url2->setOptions($link_options))->toString();

        $rows[$key]['ip_address'] = $item->ip_address;

        //$rows[$key]['title'] = Link::fromTextAndUrl($item->title, $url2->setOptions($link_options))->toString();
        $rows[$key]['last visited'] = \Drupal::service('date.formatter')->format($item->timestamp, $date_format);
        if ($custom_format) {
          $last_visited_pages .= '<li><a href="' . $url . '">' . $item->title . '</a> | ' . \Drupal::service('date.formatter')->format($item->timestamp,'custom', $date_format) . ' </li>';
        } else {
          $last_visited_pages .= '<li><a href="' . $url . '">' . $item->title . '</a> | ' . \Drupal::service('date.formatter')->format($item->timestamp, $date_format) . ' </li>';
        }
      }
    }

    if (isset($last_visited_pages)) {
      $output = '';

      if ($this->configuration['label_display']) {
        $output .= $this->t('<h2>' . $this->label() . '</h2>');
      }

      $output .= '<ul class="last-visited-pages">' . $last_visited_pages . '</ul>';

      // Attach cache metadata to the render array.
      $output = [
        '#markup' => $output,
        '#cache' => [
          'max-age' => 0,
          'contexts' => ['user'],
        ],
      ];
      $header_column = ["Title"=>"Title", "IP Address"=>"IP Address", "Last Visited"=>"Last Visited"];

      $form['last_visited_pages'] = [
            '#type' => 'table',
            '#header' => $header_column,
            '#rows' => $rows,
            '#empty' => t('No data found'),
            '#attributes' => array('class' => array('custom-table')),
            '#prefix' => "<h1 class='uppar-case'>".$user->getEmail()."</h1>"
          ];

      $form['pager'] = [
            '#type' => 'pager',
      ];

      $cache_metadata->applyTo($form);
    }

    return $form;
  }
}
