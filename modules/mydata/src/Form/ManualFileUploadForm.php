<?php
namespace Drupal\mydata\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Database\Connection;
use Drupal\commerce_order\Entity\Order;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\OpenModalDialogCommand;
use Drupal\file\Entity\File;
use Drupal\Core\Session\AccountProxyInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Drupal\group\Entity\Group;
use Drupal\user\Entity\User;
use Drupal\commerce_product\Entity\ProductVariation;
use \Drupal\Core\Ajax\PrependCommand;
use Drupal\Core\Ajax\ReplaceCommand;
/**
 * Class ManualFileUploadForm.
 *
 * @package Drupal\mydata\Form
 */
class ManualFileUploadForm extends FormBase {
  protected $session;
  protected $currentUser;
  /**
   * The external database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $externaldatabase;

  /**
   *
   * @param \Drupal\Core\Database\Connection $externalconnection
   *   The external database connection.
   */
  public function __construct(AccountProxyInterface $current_user, Connection $externalconnection, SessionInterface $session) {
    $this->currentUser = $current_user;
    $this->externaldatabase = $externalconnection;
    $this->session = $session;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(\Symfony\Component\DependencyInjection\ContainerInterface $container) {
    $current_user = $container->get('current_user');
    $externalconnection = $container->get('mydata.external_database');
    $session = $container->get('session');
    return new static($current_user, $externalconnection, $session);
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'fileload_form';
  }

  /**
   * {@inheritdoc}
   */
  
  public function buildForm(array $form, FormStateInterface $form_state) {
    $queryp = $this->externaldatabase->query("select partner_id, partner_name from partner order by partner_name");
    $resultp = $queryp->fetchAll();
    $partner_id = [];
    foreach ($resultp as $keyp => $datap) {
    $partner_id[$datap->partner_id] = $datap->partner_name;
    }  

    $form['#prefix'] = '<div id="modal_example_form">';
    $form['#suffix'] = '</div>';

    $form['status_messages'] = [
      '#type' => 'status_messages',
      '#weight' => -10,
    ];

    $form['partner_id'] = [
      '#type' => 'select',
      '#title' => t('Partner Name'),
      '#options' => $partner_id,
      '#required' => TRUE,
    ];

    $form['upload_file'] = [
      '#type' => 'managed_file',
      '#title' => $this->t('Upload CSV File'),
      '#required' => TRUE,
      '#upload_location' => 'public://content/excel_files/',
      '#upload_validators' => [
        'file_validate_extensions' => $this->getAllowedFileExtensions(),
      ],
    ];

    $form['loadcore_submit'] = [
      '#type' => 'submit',
      '#name' => 'loadcore_submit',
      '#value' => $this->t('Submit'),
      '#attributes' => [
        'class' => [
          'use-ajax',
          ]
      ],
      '#ajax' => [
      'callback' => [$this, 'submitModalFormAjax'],
      'event' => 'click',
      ],
    ]; 

    $form['back'] = [
      '#type' => 'button',
      '#value' => 'Back',
      '#limit_validation_errors' => [],
      '#attributes' => [
        'class' => [
          'back-masquerade-button',
        ],
        'onclick' => "jQuery('.ui-dialog-titlebar-close').click()",
      ]
    ];

    $form['#attached']['library'][] = 'core/drupal.dialog.ajax'; 
    return $form;
  }

  /**
   * @return array
   */
  private function getAllowedFileExtensions() {
    return array('csv');
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

  }

  public function submitModalFormAjax(array $form, FormStateInterface $form_state) {    
    $response = new AjaxResponse();

    // If there are any form errors, AJAX replace the form.
    if ($form_state->hasAnyErrors()) {
      // Validate the form.
      $form_errors = $form_state->getErrors();
      // Form validation failed, return validation errors.
      foreach ($form_errors as $error) {
        $response->addCommand(new PrependCommand('#modal_example_form', '<div class="messages_error" style="color:red">' . $error . '</div>'));
      }
      $response->addCommand(new ReplaceCommand('#modal_example_form', $form));
    } else {
      try {
        $field = $form_state->getValues();
        //uploaded file path start
        $value = $form_state->cleanValues()->getValues();
        $file = \Drupal::entityTypeManager()->getStorage('file')
        ->load($form_state->getValue('upload_file')[0]);
        $full_path = $file->get('uri')->value;
        $file_name = basename($full_path);
        $inputFileName = \Drupal::service('file_system')->realpath('public://content/excel_files/' . $file_name);
        //uploaded file path end        

        $myfile = fopen($inputFileName, "r");
        $partner_id = $field['partner_id'];
        $rowCounter = 0;
        $item = [];
        while (!feof($myfile)) {
          $line = fgets($myfile);
          if ($line !== false) {  
            $lineA = explode(",", $line);
            //csv mapping

            $profile_type = $lineA[0];
            $partner_name = $lineA[1];
            $state = $lineA[2];
            $prov_iccid = $lineA[3];
            $imsi = $lineA[4];
            $msisdn = $lineA[5];
            $created_at = $lineA[6];

            if ($rowCounter > 0) {
              try {
                // Check if IMSI already exists
                $checkQuery = $this->externaldatabase->query("SELECT COUNT(*) AS count FROM preprod_esim_10t_status_vw WHERE imsi = '".$imsi."'");
                $result = $checkQuery->fetch();
                if ($result->count > 0) {
                  // If IMSI exists, update the record
                  $queryUpdate = $this->externaldatabase->query("UPDATE preprod_esim_10t_status_vw 
                      SET profile_type = '".$profile_type."', 
                          partner_name = '".$partner_name."', 
                          state = '".$state."', 
                          prov_iccid = '".$prov_iccid."', 
                          msisdn = '".$msisdn."', 
                          created_at = '".$created_at."', 
                          partner_id = ".$partner_id." 
                      WHERE imsi = '".$imsi."'");
                  $item[] = $imsi." IMSI updated successfully.";
                } else {
                  // If IMSI does not exist, insert a new record
                  $queryInsert = $this->externaldatabase->query("INSERT INTO preprod_esim_10t_status_vw 
                      (profile_type, partner_name, state, prov_iccid, imsi, msisdn, created_at, partner_id) 
                      VALUES 
                      ('".$profile_type."', '".$partner_name."', '".$state."', '".$prov_iccid."', '".$imsi."', '".$msisdn."', '".$created_at."', ".$partner_id.")");
                  $item[] = $imsi." IMSI inserted successfully.";
                }
              } catch (\Exception $e) {
                $item[] = $imsi . " IMSI failed: " . $e->getMessage();
              }
            }
            $rowCounter++;
          }          
        }
        fclose($myfile);

        $options = [
                'buttons' => [
                  'button1' => [
                    'text' => $this->t('Ok'),
                    'id' => 'ok-button',
                    'onclick' => 'window.location.reload()',
                  ]
                ],
              ];
             
        $title = 'Log';
        $content = implode("\n", $item);  
        $response->addCommand(new OpenModalDialogCommand($title, $content, $options));        
      } catch (\Exception $e) {
        print_r($e->getMessage());
      }
    }
    return $response;
  }
}
