<?php

namespace Drupal\mydata\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\OpenModalDialogCommand;
use Drupal\Core\Ajax\ReplaceCommand;
use Symfony\Component\HttpFoundation\RequestStack;
use Drupal\Core\Database\Connection;

/**
 * SendToDestinationsForm class.
 */
class PreprodModalForm extends FormBase {
  /**
   * The current request object.
   *
   * @var \Symfony\Component\HttpFoundation\Request
   */
  protected $request;

  /**
   *
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack service.
   */
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
  public function __construct(RequestStack $request_stack, Connection $externalConnection) {
    $this->request = $request_stack->getCurrentRequest();
    $this->externalDatabase = $externalConnection;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(\Symfony\Component\DependencyInjection\ContainerInterface $container) {
    $request = $container->get('request_stack');
    $externalConnection = $container->get('mydata.external_database');
    return new static($request, $externalConnection);
  }
  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'preprod_modal_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $options = NULL) {
    $iccid = $this->request->query->get('iccid');
    $current_user = \Drupal::currentUser();
    $uid = $current_user->id();
    $curl = curl_init();

    //configuration
    $config = \Drupal::config('xai.settings');
    //Pre Production configuration Fetched
    $endpoint = $config->get('pre_endpoint');
    $email = $config->get('pre_email');
    $password = $config->get('pre_password');
    $upload_pem = $config->get('pre_upload_pem');
    $upload_crt = $config->get('pre_upload_crt');
    $upload_key = $config->get('pre_upload_key');          

    //pem
    $filepem = \Drupal::entityTypeManager()->getStorage('file')
    ->load($upload_pem[0]);
    $certificate = \Drupal::service('file_system')->realpath($filepem->get('uri')->value);

    //crt
    $filecrt = \Drupal::entityTypeManager()->getStorage('file')
    ->load($upload_crt[0]);
    $cert = \Drupal::service('file_system')->realpath($filecrt->get('uri')->value);

    //key
    $filekey = \Drupal::entityTypeManager()->getStorage('file')
    ->load($upload_key[0]);
    $key = \Drupal::service('file_system')->realpath($filekey->get('uri')->value);

    curl_setopt_array($curl, array(
      CURLOPT_URL => $endpoint.'/gsma/rsp2/es2plus/user/signin',
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_ENCODING => '',
      CURLOPT_MAXREDIRS => 10,
      CURLOPT_TIMEOUT => 0,
      CURLOPT_FOLLOWLOCATION => true,
      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
      CURLOPT_CUSTOMREQUEST => 'POST',
      CURLOPT_POSTFIELDS => 'email='.$email.'&password='.$password,
      CURLOPT_CAINFO => $certificate,
      CURLOPT_SSLKEY => $key,
      CURLOPT_SSLCERT => $cert,
      CURLOPT_HTTPHEADER => array(
        'Content-Type: application/x-www-form-urlencoded'
      ),
    ));

    $response = curl_exec($curl);

    //curl_close($curl);
    if (curl_errno($curl)) {
      $error_msg = curl_error($curl);
    }

    if (isset($error_msg)) {
      print_r($error_msg);
    }
    curl_close($curl);

    //sign start
    $path = $endpoint.'/gsma/rsp2/es2plus/user/signin';
    $activity = "SignIn";
    \Drupal::database()->insert('10t_usage')->fields(['iccid', 'path', 'activity', 'uid', 'timestamp'])->values([$iccid, $path, $activity, $uid, \Drupal::time()->getRequestTime()])->execute();
    //signin end

    $result = json_decode($response);

    //getprofilehistory
    $curl = curl_init();
    if ($result->token) {
      curl_setopt_array($curl, array(
        CURLOPT_URL => $endpoint.'/gsma/rsp2/es2plus/getProfileHistory',
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
          "functionRequesterIdentifier": "10T",
          "functionCallIdentifier": "10T"
      },
      "iccid": "'.$iccid.'"
      }',
        CURLOPT_HTTPHEADER => array(
          "Authorization: Bearer $result->token",
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

      //getProfileHistory start
      $path = $endpoint.'/gsma/rsp2/es2plus/getProfileHistory';
      $activity = "Profile History";
      \Drupal::database()->insert('10t_usage')->fields(['iccid', 'path', 'activity', 'uid', 'timestamp'])->values([$iccid, $path, $activity, $uid, \Drupal::time()->getRequestTime()])->execute();
      //getProfileHistory end

      $data = json_decode($response, true);
      $results = [];
      foreach ($data['logs'] as $logs) {
        //print_r($logs["data"]);
        $results[] = $logs["data"];
      }
      //
      $items = '<h3 class="iccid-header">' . $data["iccid"] . '</h3><ul class="logs-ul">';
      //if condition added because showing invalid headers
      if ($data['message']) {
        $items = $data['message'];
      }      
      foreach ($data['logs'] as $logs) {
        if($logs["data"]){
          foreach ($logs["data"] as $keyv => $value) {
            $item = '<li class="logs-li"><strong class="logs-strong">' . $keyv . ":</strong>" . $value . '</li>';
            $items = $items . $item;
          }
          $items = $items . '<br/>';
        }
      }
      $items = $items . '</ul>';

      //profilestatus update start
      //get value from getProfileStatus and update in esim_profiles table
      if ($iccid) {  //$iccid remove intentionally because api not working as exptected.      
        $curl = curl_init();
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
                "functionRequesterIdentifier": "10T",
                "functionCallIdentifier": "10T"
            },
            "iccidList": [{"iccid" :"'.$iccid.'"}]
            }',
              CURLOPT_HTTPHEADER => array(
                "Authorization: Bearer $result->token",
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
        
        $dataps = json_decode($response, true);
        if ($dataps['header']['functionExecutionStatus']['status']== 'Executed-Success') {
          // Check current state of the iccid
          $query_check = $this->externalDatabase->query("SELECT state FROM preprod_esim_10t_status_vw WHERE prov_iccid = '".$iccid."'");
          $output = $query_check->fetchAll();
          if ($output) {
              $current_state = $output[0]->state;              
              // Check if the current state is not already 'RELEASED'
              if ($current_state != $dataps['profileStatusList'][0]['state']) {
                  // Execute the update query
                  $query_update = $this->externalDatabase->query("UPDATE preprod_esim_10t_status_vw SET state = '".$dataps['profileStatusList'][0]['state']."' WHERE prov_iccid = '".$iccid."'");
              } else {
                  // ICCID is already in updated state
                  //echo "<br/>ICCID ".$iccid." is already in ".$dataps['profileStatusList'][0]['state']." state.";
              }
          }
        }          
      }
      //profilestatus update end

    } //token end
    return [
      '#markup' => $items
    ];


  }

  /**
   * AJAX callback handler that displays any errors or a success message.
   */
  public function submitModalFormAjax(array $form, FormStateInterface $form_state) {
    $response = new AjaxResponse();

    // If there are any form errors, AJAX replace the form.
    if ($form_state->hasAnyErrors()) {
      $response->addCommand(new ReplaceCommand('#modal_example_form', $form));
    } else {
      $response->addCommand(new OpenModalDialogCommand("Success!", 'The modal form has been submitted.', ['width' => 700]));
    }

    return $response;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
  }

  /**
   * Gets the configuration names that will be editable.
   *
   * @return array
   *   An array of configuration object names that are editable if called in
   *   conjunction with the trait's config() method.
   */
  protected function getEditableConfigNames() {
    return ['config.preprod_modal_form'];
  }
}
