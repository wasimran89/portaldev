<?php
//oss second setting used
namespace Drupal\mydata\Controller;

use Drupal\Core\Controller\ControllerBase;
use  \Drupal\user\Entity\User;
/**
 * Class DisplayTableController.
 *
 * @package Drupal\mydata\Controller
 */
class RemoveUserController extends ControllerBase {


  public function getContent() {
    // First we'll tell the user what's going on. This content can be found
    // in the twig template file: templates/description.html.twig.
    // @todo: Set up links to create nodes and point to devel module.
    $build = [
      'description' => [
        '#theme' => 'mydata_description',
        '#description' => 'foo',
        '#attributes' => [],
      ],
    ];
    return $build;
  }

  /**
   * Display.
   *
   * @return string
   *   Return Hello string.
   */
  public function display() {
  $ids = \Drupal::entityQuery('user')->execute();
    $users = User::loadMultiple($ids);
    $arrfields = explode("\n", file_get_contents('/var/www/html/usim-webportal/remove_user_emails.txt'));
    //echo "<pre>";
    //print_r($arrfields);
    //exit;   
    $useremail = [];
    foreach($users as $user) {        
      $useremail[] = $user->getEmail();
    }
    //echo "<pre>";
    //print_r($useremail);
    //exit;
    $result = array_intersect($arrfields,$useremail);
    //echo "<pre>";
    //print_r($result);
    //exit;
    foreach ($result as $key => $value) {
      foreach($users as $user) {        
        if($user->getEmail()==$value){
          $user->delete();
        }
      }
    }
  }//display function
} //DisplayTableController
