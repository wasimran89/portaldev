<?php
//oss second setting used
namespace Drupal\mydata\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\user\Entity\User;
use Drupal\Core\Database\Connection;
/**
 * Class MigrateTableController.
 *
 * @package Drupal\mydata\Controller
 */
class MigrateTableController extends ControllerBase {
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
    $form['form'] = [
          '#type'  => 'form',
        ];        

    try{

      $query = $this->externalDatabase->query("SELECT login, pswd, name, email, active, billing_entity_id FROM sec_users WHERE billing_entity_id in (select distinct billing_entity_id from portal_active_lines_vw) AND billing_entity_id <> 112 ORDER BY login");
      $result = $query->fetchAll();
      foreach ($result as $key => $value) {
        $ids = \Drupal::entityQuery('user')
              ->condition('mail', $value->email)
              ->execute();
        if (!empty($ids)) {
          \Drupal::messenger()->addError($this->t($value->email.'Already Exist!!!'));
        } else {
          $user = User::create();
          $user->setUsername($value->name);
          $user->setPassword("Jcius@123");
          $user->enforceIsNew();
          $user->setEmail($value->email);
          //$user->addRole('authenticated');
          $user->activate();
          // Save user account.
          //Inactive this functionality by commenting below line
          //$output = $user->save();
          if($output){
            \Drupal::messenger()->addMessage($this->t($value->email.'Account Created'));
          }
        }
      }
    } catch (\Exception $e) {
       \Drupal::messenger()->addError($e);
    }
    return $form;   

  }//display function
} //DisplayTableController
