<?php

namespace Drupal\mydata\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Database\Database;
use Drupal\encrypt\Entity\EncryptionProfile;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\OpenModalDialogCommand;
use Drupal\Core\Ajax\ReplaceCommand;
use Symfony\Component\HttpFoundation\Response;
use Drupal\Core\Database\Connection;

/**
 * Class TechAPI.
 *
 * @package Drupal\mydata\Form
 */
class TechAPI extends FormBase {
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
  public function __construct(Connection $externalconnection) {
    $this->externaldatabase = $externalconnection;
  }

  /**
   * Helper function to load the file path from the file entity by file ID.
   *
   * @param int $file_id
   *   The file ID of the file entity.
   *
   * @return string|null
   *   The real path of the file or NULL if the file is not found.
   */
  protected function loadFilePathById($file_id) {
      if ($file_id) {
          $file = \Drupal::entityTypeManager()->getStorage('file')->load($file_id);
          if ($file) {
              return \Drupal::service('file_system')->realpath($file->get('uri')->value);
          }
      }
      return null;
  }
  /**
   * Helper function to get ICCID data for the current user and related partners.
   *
   * @param string $iccid
   *   The ICCID to search for.
   * @param \Drupal\Core\Database\Connection $external_database
   *   The external database connection.
   *
   * @return array|null
   *   The query result with ICCID data or NULL if no result is found.
   */
  protected function getIccidForUserAndPartners($iccid, $external_database) {
      $user = \Drupal::currentUser();
      if ($user) {
          $user_display_name = $user->getEmail();
          // Query to get the sim_partner_id for the current user.
          $queryuser = $external_database->query("SELECT sim_partner_id FROM drupal_user WHERE drupal_user = :email", [':email' => $user_display_name]);
          $resultuser = $queryuser->fetchAll();

          if ($resultuser) {
              // Initialize an array to store all related partners.
              $all_related_partners = [];
              // Fetch all related partners recursively.
              $this->fetchRelatedPartners($resultuser[0]->sim_partner_id, $all_related_partners);
              $all_related_partners[] = $resultuser[0]->sim_partner_id;

              // Query the ICCID for the user and related partners.
              $query = $external_database->select('esim_10t_status_vw', 'esv')
                                        ->fields('esv', ['prof_iccid'])
                                        ->condition('esv.prof_iccid', $iccid)
                                        ->condition('esv.partner_id', $all_related_partners, 'IN');
              
              // Execute the query and fetch the results.
              return $query->execute()->fetchAll();
          }
      }
      return null;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(\Symfony\Component\DependencyInjection\ContainerInterface $container) {
    $externalconnection = $container->get('mydata.external_database');
    // Return the controller object with the injected database connections.
    return new static($externalconnection);
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'techiapi_form';
  }

  // Function to fetch related partners recursively
  public function fetchRelatedPartners($parentId, &$all_related_partners) {
    $user = \Drupal\user\Entity\User::load(\Drupal::currentUser()->id());  
    $roles = $user->getRoles();
    if (in_array('administrator', $roles)) {
      $query = $this->externaldatabase->select('partner', 'p');
      // Specify the field to retrieve (partner_id)
      $query->fields('p', ['partner_id']);
      // Execute the query and fetch all results
      $results = $query->execute()->fetchAll();
      // Loop through each result and add to the array
      foreach ($results as $partner) {
          $all_related_partners[] = $partner->partner_id;
      }
    } else {
      // Start building the query on the external database connection
      $query = $this->externaldatabase->select('partner', 'p');

      // Specify the field to retrieve (partner_id)
      $query->fields('p', ['partner_id']);

      // Add the OR condition for parent_partner_id and grand_parent_partner_id
      $orGroup = $query->orConditionGroup()
                        ->condition('p.parent_partner_id', $parentId, '=')
                        ->condition('p.grand_parent_partner_id', $parentId, '=');

      // Add the OR condition group to the main query
      $query->condition($orGroup);

      // Execute the query and fetch all results
      $results = $query->execute()->fetchAll();

      // Loop through each result and add to the array
      foreach ($results as $partner) {
          if (!in_array($partner->partner_id, $all_related_partners)) {
              $all_related_partners[] = $partner->partner_id;
              // Recursively call this function for further relationships
              $this->fetchRelatedPartners($partner->partner_id, $all_related_partners);
          }
      }
    }    
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['#prefix'] = '<div id="modal_example_form"><h1 class="textcenter">'.t('TECH API').'</h1>';
    $form['#suffix'] = '</div>';        
    $user = \Drupal::currentUser();    
    $user_display_name = $user->getEmail();
    $queryuser = $this->externaldatabase->select('drupal_user', 'u')
      ->fields('u', ['sim_partner_id'])
      ->condition('u.drupal_user', $user_display_name)
      ->range(0, 1); // We only need one result since usernames are unique.

    $resultuser = $queryuser->execute()->fetchAll();
    if ($resultuser[0]->sim_partner_id) {
      $form['iccid'] = [
        '#title' => t('ICCID'),
        '#type' => 'textfield',
        '#required' => TRUE,
        '#cache' => array('max-age' => 0),
        '#attributes' => [
                          'class' => ['custom-iccid-field'],
                          'size' => '30',
                        ],
      ];

      //Get Profile Status
      $form['profile_status'] = [
        '#type' => 'button',
        '#value' => t('Info'),
        '#attributes' => [
          'class' => [
            'use-ajax',
          ],
        ],
        '#ajax' => [
          'callback' => [$this, 'profilestatussubmitModalFormAjax'],
          'event' => 'click',
        ],
      ];

      //Final Cancel Order
      $form['final'] = [
        '#type' => 'button',
        '#value' => t('Final'),
        '#attributes' => [
          'class' => [
            'use-ajax',
          ],
        ],
        '#ajax' => [
          'callback' => [$this, 'finalsubmitModalFormAjax'],
          'event' => 'click',
        ],
      ];

      //Reset Cancel Order
      $form['reset'] = [
        '#type' => 'button',
        '#value' => t('Reset Error'),
        '#attributes' => [
          'class' => [
            'use-ajax',
          ],
        ],
        '#ajax' => [
          'callback' => [$this, 'resetsubmitModalFormAjax'],
          'event' => 'click',
        ],
      ];

      //Reset Cancel Order
      $form['list'] = [
        '#type' => 'button',
        '#value' => t('List'),
        '#attributes' => [
          'class' => [
            'use-ajax',
          ],
        ],
        '#ajax' => [
          'callback' => [$this, 'listsubmitModalFormAjax'],
          'event' => 'click',
        ],
        '#limit_validation_errors' => [],
      ];
    } else {
      $element = [
        '#markup' => '<p><b>Access Denied!!!</b></p>',
      ];
      return $element;
    }
    $form['#cache'] = ['max-age' => 0];
    // Attach the library for pop-up dialogs/modals.
    $form['#attached']['library'][] = 'core/drupal.dialog.ajax';
    return $form;
  }

  /**
   * GetProfileStatus AJAX callback handler that displays any errors or a success message.
   */
  public function profilestatussubmitModalFormAjax(array $form, FormStateInterface $form_state) {
    $current_user = \Drupal::currentUser();
    $uid = $current_user->id();
    $response11 = new AjaxResponse();
    $field = $form_state->getValues();    
    //configuration
    $config = \Drupal::config('xai.settings');
    $endpoint = $config->get('endpoint');
    $email = $config->get('email');
    $password = $config->get('password');
    $upload_pem = $config->get('upload_pem');
    $upload_crt = $config->get('upload_crt');
    $upload_key = $config->get('upload_key');

    // Load PEM file.
    $certificate = $this->loadFilePathById($upload_pem[0]);

    // Load CRT file.
    $cert = $this->loadFilePathById($upload_crt[0]);

    // Load KEY file.
    $key = $this->loadFilePathById($upload_key[0]);


    // If there are any form errors, AJAX replace the form.
    if ($form_state->hasAnyErrors()) {
      $response11->addCommand(new ReplaceCommand('#modal_example_form', $form));
    } else {
      try {
        $field = $form_state->getValues();
        if (isset($field['iccid'])) {          
          $iccid = $field['iccid'];
          $result = $this->getIccidForUserAndPartners($iccid, $this->externaldatabase);

          //if iccid found in table then continue
          if ($result[0]->prof_iccid) {
            $token = $this->executeCurlSignin($endpoint, $email, $password, $certificate, $key, $cert, $iccid);

            //getprofilehistory
            $curl = curl_init();
            if ($token) {
              curl_setopt_array($curl, array(
                CURLOPT_URL => $endpoint.'/gsma/rsp2/es2plus/getProfileStatus',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'POST',
                CURLOPT_CAINFO => $certificate,
                CURLOPT_SSLKEY => $key,
                CURLOPT_SSLCERT => $cert,
                CURLOPT_POSTFIELDS => '{
              "header": {
                  "functionRequesterIdentifier": "JCIUS",
                  "functionCallIdentifier": "JCIUS"
              },
              "iccidList": [{"iccid" :"'.$iccid.'"}]
              }',
                CURLOPT_HTTPHEADER => array(
                  "Authorization: Bearer $token",
                  'Content-Type: application/json'
                ),
              ));

              $response = curl_exec($curl);
              if (curl_errno($curl)) {
                $error_msg = curl_error($curl);
              }

              if (isset($error_msg)) {
                print_r($error_msg);
              }

              curl_close($curl);

              //getProfileStatus start
              $path = $endpoint.'/gsma/rsp2/es2plus/getProfileStatus';
              $activity = "Profile Status";
              \Drupal::database()->insert('10t_usage')->fields(['iccid', 'path', 'activity', 'uid', 'timestamp'])->values([$iccid, $path, $activity, $uid, \Drupal::time()->getRequestTime()])->execute();
              //getProfileStatus end

              $data = json_decode($response, true);
              $items = '<h3 class="iccid-header">' . $data['header']['functionExecutionStatus']['status'] . '</h3><ul class="logs-ul">';
              
                foreach ($data['profileStatusList'][0] as $keyv => $value) {
                  $item = '<li class="logs-li"><strong class="logs-strong">' . $keyv . ":</strong>" . $value . '</li>';
                  $items = $items . $item;
                }
                $items = $items . '<br/>';
              }
              $items = $items . '</ul>';

            $response11->addCommand(new OpenModalDialogCommand($iccid, $items, ['width' => 700]));
          } else { //if iccid not found in table
            $response11->addCommand(new OpenModalDialogCommand("Not Found", "".$iccid." Not Found", ['width' => 700]));
          }
          
        }        
      } catch (\Exception $e) {
        print_r($e);
      }
    }

    return $response11;
  }

  // Helper function to perform the sign-in API call.
  public function executeCurlSignin($endpoint, $email, $password, $certificate, $key, $cert, $iccid) {
    $current_user = \Drupal::currentUser();
    $uid = $current_user->id();
    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => $endpoint.'/gsma/rsp2/es2plus/user/signin',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POSTFIELDS => 'email='.$email.'&password='.$password,
        CURLOPT_CAINFO => $certificate,
        CURLOPT_SSLKEY => $key,
        CURLOPT_SSLCERT => $cert,
        CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded']
    ]);

    $response = curl_exec($curl);
    if (curl_errno($curl)) {
        \Drupal::logger('xai')->error(curl_error($curl));
    }

    //signin start
    $path = $endpoint.'/gsma/rsp2/es2plus/user/signin';
    $activity = "SignIn";
    \Drupal::database()->insert('10t_usage')->fields(['iccid', 'path', 'activity', 'uid', 'timestamp'])->values([$iccid, $path, $activity, $uid, \Drupal::time()->getRequestTime()])->execute();
    //signin end

    return json_decode($response)->token ?? null;
  }

  /**
   * final AJAX callback handler that displays any errors or a success message.
   */
  public function finalsubmitModalFormAjax(array $form, FormStateInterface $form_state) {
    $current_user = \Drupal::currentUser();
    $uid = $current_user->id();
    $response11 = new AjaxResponse();
    $field = $form_state->getValues();    
    //configuration
    $config = \Drupal::config('xai.settings');
    $endpoint = $config->get('endpoint');
    $email = $config->get('email');
    $password = $config->get('password');
    $upload_pem = $config->get('upload_pem');
    $upload_crt = $config->get('upload_crt');
    $upload_key = $config->get('upload_key');

    // Load PEM file.
    $certificate = $this->loadFilePathById($upload_pem[0]);

    // Load CRT file.
    $cert = $this->loadFilePathById($upload_crt[0]);

    // Load KEY file.
    $key = $this->loadFilePathById($upload_key[0]);

    // If there are any form errors, AJAX replace the form.
    if ($form_state->hasAnyErrors()) {
      $response11->addCommand(new ReplaceCommand('#modal_example_form', $form));
    } else {
      try {
        $field = $form_state->getValues();
        if (isset($field['iccid'])) {          
          $iccid = $field['iccid'];
          $result = $this->getIccidForUserAndPartners($iccid, $this->externaldatabase);
          //if iccid found in table then continue
          if ($result[0]->prof_iccid) {
            $token = $this->executeCurlSignin($endpoint, $email, $password, $certificate, $key, $cert, $iccid);

            //getprofilehistory
            $curl = curl_init();
            if ($token) {
              curl_setopt_array($curl, array(
                CURLOPT_URL => $endpoint.'/gsma/rsp2/es2plus/cancelOrder',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'POST',
                CURLOPT_CAINFO => $certificate,
                CURLOPT_SSLKEY => $key,
                CURLOPT_SSLCERT => $cert,
                CURLOPT_POSTFIELDS => '{
              "header": {
                  "functionRequesterIdentifier": "JCIUS",
                  "functionCallIdentifier": "JCIUS"
              },
              "iccid": "'.$iccid.'",
              "finalProfileStatusIndicator": "Unavailable"
              }',
                CURLOPT_HTTPHEADER => array(
                  "Authorization: Bearer $token",
                  'Content-Type: application/json'
                ),
              ));

              $response = curl_exec($curl);
              if (curl_errno($curl)) {
                $error_msg = curl_error($curl);
              }

              if (isset($error_msg)) {
                print_r($error_msg);
              }
              curl_close($curl);

              //cancelOrder start
              $path = $endpoint.'/gsma/rsp2/es2plus/cancelOrder';
              $activity = "Final";
              \Drupal::database()->insert('10t_usage')->fields(['iccid', 'path', 'activity', 'uid', 'timestamp'])->values([$iccid, $path, $activity, $uid, \Drupal::time()->getRequestTime()])->execute();
              //cancelOrder end

              $data = json_decode($response, true);
              $results = [];
              foreach ($data['header'] as $logs) {
                $results[] = $logs["statusCodeData"];
              }

              $items = '<h3 class="iccid-header">' . $logs['status'] . '</h3><ul class="logs-ul">';
              
                foreach ($results[0] as $keyv => $value) {
                  $item = '<li class="logs-li"><strong class="logs-strong">' . $keyv . ":</strong>" . $value . '</li>';
                  $items = $items . $item;
                }
                $items = $items . '<br/>';
              }
              $items = $items . '</ul>';

            $response11->addCommand(new OpenModalDialogCommand($iccid, $items, ['width' => 700]));
          } else { //if iccid not found in table
            $response11->addCommand(new OpenModalDialogCommand("Not Found", "".$iccid." Not Found", ['width' => 700]));
          }          
        }        
      } catch (\Exception $e) {
        print_r($e->getMessage());
      }
    }
    return $response11;
  }

  /**
   * Reset AJAX callback handler that displays any errors or a success message.
   */
  public function resetsubmitModalFormAjax(array $form, FormStateInterface $form_state) {
    $current_user = \Drupal::currentUser();
    $uid = $current_user->id();
    $response11 = new AjaxResponse();
    $field = $form_state->getValues();    
    //configuration
    $config = \Drupal::config('xai.settings');
    $endpoint = $config->get('endpoint');
    $email = $config->get('email');
    $password = $config->get('password');
    $upload_pem = $config->get('upload_pem');
    $upload_crt = $config->get('upload_crt');
    $upload_key = $config->get('upload_key');

    // Load PEM file.
    $certificate = $this->loadFilePathById($upload_pem[0]);

    // Load CRT file.
    $cert = $this->loadFilePathById($upload_crt[0]);

    // Load KEY file.
    $key = $this->loadFilePathById($upload_key[0]);

    // If there are any form errors, AJAX replace the form.
    if ($form_state->hasAnyErrors()) {
      $response11->addCommand(new ReplaceCommand('#modal_example_form', $form));
    } else {
      try {
        $field = $form_state->getValues();
        if (isset($field['iccid'])) {          
          $iccid = $field['iccid'];
          $user = \Drupal::currentUser();
          $result = $this->getIccidForUserAndPartners($iccid, $this->externaldatabase);
          //if iccid found in table then continue
          if ($result[0]->prof_iccid) {
            $token = $this->executeCurlSignin($endpoint, $email, $password, $certificate, $key, $cert, $iccid);

            //getprofilehistory
            $curl = curl_init();
            if ($token) {
              curl_setopt_array($curl, array(
                CURLOPT_URL => $endpoint.'/gsma/rsp2/es2plus/cancelOrder',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'POST',
                CURLOPT_CAINFO => $certificate,
                CURLOPT_SSLKEY => $key,
                CURLOPT_SSLCERT => $cert,
                CURLOPT_POSTFIELDS => '{
              "header": {
                  "functionRequesterIdentifier": "JCIUS",
                  "functionCallIdentifier": "JCIUS"
              },
              "iccid": "'.$iccid.'",
              "finalProfileStatusIndicator": "Available"
              }',
                CURLOPT_HTTPHEADER => array(
                  "Authorization: Bearer $token",
                  'Content-Type: application/json'
                ),
              ));

              $response = curl_exec($curl);
              if (curl_errno($curl)) {
                $error_msg = curl_error($curl);
              }

              if (isset($error_msg)) {
                print_r($error_msg);
              }
              curl_close($curl);

              //cancelOrder start
              $path = $endpoint.'/gsma/rsp2/es2plus/cancelOrder';
              $activity = "Reset Error";
              \Drupal::database()->insert('10t_usage')->fields(['iccid', 'path', 'activity', 'uid', 'timestamp'])->values([$iccid, $path, $activity, $uid, \Drupal::time()->getRequestTime()])->execute();
              //cancelOrder end

              $data = json_decode($response, true);
              $results = [];
              foreach ($data['header'] as $logs) {
                $results[] = $logs["statusCodeData"];
              }
              $items = '<h3 class="iccid-header">' . $logs['status'] . '</h3><ul class="logs-ul">';
              
                foreach ($results[0] as $keyv => $value) {
                  $item = '<li class="logs-li"><strong class="logs-strong">' . $keyv . ":</strong>" . $value . '</li>';
                  $items = $items . $item;
                }
                $items = $items . '<br/>';
              }
              $items = $items . '</ul>';

            $response11->addCommand(new OpenModalDialogCommand($iccid, $items, ['width' => 700]));
          } else { //if iccid not found in table
            $response11->addCommand(new OpenModalDialogCommand("Not Found", "".$iccid." Not Found", ['width' => 700]));
          }          
        }        
      } catch (\Exception $e) {
        print_r($e->getMessage());
      }
    }
    return $response11;
  }
  /**
   * List AJAX callback handler that displays any errors or a success message.
   */
  public function listsubmitModalFormAjax(array $form, FormStateInterface $form_state) {
    $response11 = new AjaxResponse();
    // If there are any form errors, AJAX replace the form.
    if ($form_state->hasAnyErrors()) {
      $response11->addCommand(new ReplaceCommand('#modal_example_form', $form));
    } else {
      $user = \Drupal::currentUser();
      if ($user) {
            $user_display_name = $user->getEmail();
            $queryuser = $this->externaldatabase->query("select sim_partner_id from drupal_user where drupal_user = '".$user_display_name."'");
            $resultuser = $queryuser->fetchAll();            
            if ($resultuser) {
                //child partner start
                $all_related_partners = [];
                $this->fetchRelatedPartners($resultuser[0]->sim_partner_id, $all_related_partners);
                $all_related_partners[] = $resultuser[0]->sim_partner_id;           
                $query = $this->externaldatabase->select('esim_10t_status_vw', 'esv')
                                  ->fields('esv');
                              
                $query->condition('esv.partner_id', $all_related_partners, 'IN');
                // Execute the query and fetch all the results.
                $result = $query->execute()->fetchAll();

                //child partner end    
            }
          } //user end

      $dataoutput = json_encode($result, JSON_PRETTY_PRINT);
      $optionss = [
          'width' => 700
        ];
      $response11->addCommand(new OpenModalDialogCommand("List", "".$dataoutput."", $optionss));
    }

    return $response11;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

  }
}
