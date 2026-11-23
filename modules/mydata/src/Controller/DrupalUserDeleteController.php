<?php
namespace Drupal\mydata\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\Response;
use Drupal\user\Entity\User;
use Drupal\Core\Database\Connection;
/**
 * Class DrupalUserDeleteController.
 *
 * @package Drupal\mydata\Controller
 */
class DrupalUserDeleteController extends ControllerBase {
  /**
   * The external database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $externalDatabase;

  /**
   * Constructs a new YourController object.
   *
   * @param \Drupal\Core\Database\Connection $externalConnection
   *   The external database connection.
   */
  public function __construct(Connection $externalConnection) {
    $this->externalDatabase = $externalConnection;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(\Symfony\Component\DependencyInjection\ContainerInterface $container) {
    $externalConnection = $container->get('mydata.external_database');
    return new static($externalConnection);
  }

  /**
   * Display.
   *
   * @return string
   *   Return Hello string.
   */
  public function display() {
    $config = \Drupal::config('xai.settings');
    $base_path = $config->get('domain');
    $ids = \Drupal::entityQuery('user')
          ->accessCheck(TRUE)
          ->condition('status', 1)
          ->execute();

    //application user
    $users = User::loadMultiple($ids);
    $drupaluserdata = [];
    foreach($users as $user){
      $drupaluserdata[] = $user->get('mail')->getString();
    }

    //external drupal_user table
    $query = $this->externalDatabase->select('drupal_user', 'u')
                                    ->fields('u', ['drupal_user']);
    $results = $query->execute()->fetchAll();                                
    
    $jciumuserdata = [];
    foreach($results as $result){      
      $jciumuserdata[] = $result->drupal_user;
    }
    $output = array_diff($jciumuserdata, $drupaluserdata);
 
    foreach($output as $d_user){
      try {
        $dquery = $this->externalDatabase->query("DELETE FROM drupal_user WHERE drupal_user='".$d_user."'");
        if($dquery){
          echo "<br/>Deleted user: ".$d_user;
        }
      } catch (\Exception $e) {
        \Drupal::messenger()->addError($e);
      }

    }
    return new Response('Success', Response::HTTP_OK);
  } //display function
} //DrupalUserDeleteController