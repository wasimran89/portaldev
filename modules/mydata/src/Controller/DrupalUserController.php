<?php
namespace Drupal\mydata\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\Response;
use Drupal\encrypt\Entity\EncryptionProfile;
use Drupal\user\Entity\User;
use Drupal\Core\Database\Connection;
use Drupal\group\Entity\Group;
/**
 * Class DrupalUserController.
 *
 * @package Drupal\mydata\Controller
 */
class DrupalUserController extends ControllerBase {
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
    ->condition('status', 1)
    ->execute();

    $users = User::loadMultiple($ids);
    //Drupal application users
    foreach ($users as $user) {
        $drupaluserdata[] = $user->get('mail')->getString();
    }

    //PostgreSQL drupal_user users
    $query = $this->externalDatabase->select('drupal_user', 'du')
      ->fields('du', ['drupal_user']);
    $results = $query->execute()->fetchAll();

    $jciumuserdata = [];
    foreach ($results as $result) {
        $jciumuserdata[] = $result->drupal_user;
    }

    //check users in drupal application but not in postgressql then show diff
    $output = array_diff($drupaluserdata, $jciumuserdata);
    if (1) { //$output
      $groups = Group::loadMultiple();
      foreach ($users as $user) {
        $username = $user->get('mail')->getString();
        $name = $user->get('name')->getString();
        $group_type = $user->get('field_group_type')->target_id;
        $sim_partner_id = 0;
        foreach ($groups as $group) {
            $id = $group->id();            
            if ($group_type == $id) {
                $label = trim($group->label());
                $queryp = $this->externalDatabase->select('partner', 'p')
                            ->fields('p', ['partner_id'])
                            ->condition('p.partner_name', $label);
                $resultp = $queryp->execute()->fetchAll();
                //if ($resultp[0]->partner_id) {
                if (!empty($resultp) && isset($resultp[0]->partner_id)) {
                  $sim_partner_id = $resultp[0]->partner_id;
                  break;
                } else {
                  $sim_partner_id = 0;
                }
                
            }
        }
        try {
          $query = $this->externalDatabase->select('drupal_user', 'u');
          $query->fields('u');
          $query->condition('u.drupal_user', $username);
          $results = $query->execute()->fetchAll();

          if ($results) {
            echo "<br/>Existing User:".$username. " => Updated";
            //echo "<br/>update drupal_user set name='".$name."', sim_partner_id=" . $sim_partner_id . " where drupal_user='".$username."'";
            $query = $this->externalDatabase->query("update drupal_user set name='".$name."', sim_partner_id=" . $sim_partner_id . " where drupal_user='".$username."'");
          } else {
            echo "<br/>Newuser:".$username. " => Added";
            //echo "<br/>insert into drupal_user(drupal_user,name,sim_partner_id) values ('".$username."','".$name."',".$sim_partner_id.")";
            $query = $this->externalDatabase->query("insert into drupal_user(drupal_user,name,sim_partner_id) values ('".$username."','".$name."',".$sim_partner_id.")");

            if (0) {
              //mail sent
              $mailManager = \Drupal::service('plugin.manager.mail');
              $module = 'mydata';
              $key = 'general_mail';
              $to = 'drensby@jcius.com,support@contournetworks.com';
              $params['message'] = "<p>Please find below link to change/update user sim partner id for ".$username."</p><p>".$base_path."user-billing-entity-id</p>";
              $params['subject'] = "New User Register";
              $langcode = \Drupal::currentUser()->getPreferredLangcode();
              $send = true;
              $result = $mailManager->mail($module, $key, $to, $langcode, $params, NULL, $send);
              if ($result['result'] !== true) {
                echo "There was a problem sending your message and it was not sent.";
              }
              else {
                echo "Your message has been sent.";
              }
              //end mail sent        
            } //if
          }
        } catch (\Exception $e) {
          \Drupal::messenger()->addError($e);
        }
      } //forloop
    }
    return new Response('SUCCESS', Response::HTTP_OK);
  } //display function
} //DisplayTableController