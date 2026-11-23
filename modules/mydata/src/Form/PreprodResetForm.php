<?php

namespace Drupal\mydata\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\OpenModalDialogCommand;
use Drupal\Core\Ajax\ReplaceCommand;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\DependencyInjection\ContainerInterface;
//credit
use Drupal\group\Entity\Group;
use Drupal\Core\Database\Connection;

/**
 * SendToDestinationsForm class.
 */
class PreprodResetForm extends FormBase {
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
  protected $externaldatabase;
  /**
   *
   * @param \Drupal\Core\Database\Connection $externalconnection
   *   The external database connection.
   */
  public function __construct(RequestStack $request_stack, Connection $externalconnection) {
    $this->request = $request_stack->getCurrentRequest();
    $this->externaldatabase = $externalconnection;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $request = $container->get('request_stack');
    $externalconnection = $container->get('mydata.external_database');
    return new static($request, $externalconnection);
  }
  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'preprod_reset_modal_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $options = NULL) {
    $user = \Drupal\user\Entity\User::load(\Drupal::currentUser()->id());
    $uid = $user->id();
    if ($user) {      
      $cgroup = $user->field_group_type->getValue(); //current user group load
      $type = $cgroup[0]['target_id']; //current user group id
      $group_id = $cgroup[0]['target_id']; //current user group id
      $group = Group::load($group_id);
      if ($group) {
        //$field_credit_reset = $group->get('field_credit_reset')->value;
        //$field_credit_release = $group->get('field_credit_release')->value;
      } else {
        $items1 = '<h3 class="iccid-header">Group Not Found.</h3><ul class="logs-ul">';
      }
    }
    if (1) { //$field_credit_reset > 1
      //ec2 start
      $iccid = $this->request->query->get('iccid');
      $curl = curl_init();
      $config = \Drupal::config('xai.settings');
      $mode = $config->get('mode');
      if (0) { //always preprod
        //Production configuration Fetched
        $endpoint = $config->get('endpoint');
        $email = $config->get('email');
        $password = $config->get('password');
        $upload_pem = $config->get('upload_pem');
        $upload_crt = $config->get('upload_crt');
        $upload_key = $config->get('upload_key');
      } else {
        //Pre Production configuration Fetched
        $endpoint = $config->get('pre_endpoint');
        $email = $config->get('pre_email');
        $password = $config->get('pre_password');
        $upload_pem = $config->get('pre_upload_pem');
        $upload_crt = $config->get('pre_upload_crt');
        $upload_key = $config->get('pre_upload_key');
      }          

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
      
      if ($result->token) {
        //releaseProfile start
        try {
          $curl = curl_init();
          curl_setopt_array($curl, array(
            CURLOPT_URL => $endpoint.'/gsma/rsp2/es2plus/releaseProfile',
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
          curl_close($curl);

          if (isset($error_msg)) {
            print_r($error_msg);
          }
          curl_close($curl);

          //releaseProfile start
          $path = $endpoint.'/gsma/rsp2/es2plus/releaseProfile';
          $activity = "Release Profile";
          \Drupal::database()->insert('10t_usage')->fields(['iccid', 'path', 'activity', 'uid', 'timestamp'])->values([$iccid, $path, $activity, $uid, \Drupal::time()->getRequestTime()])->execute();
          //releaseProfile end

          $data = json_decode($response, true);
          $items = '<h3 class="iccid-header">' . $iccid . '</h3><ul class="logs-ul">';
          $release_items = '<li class="logs-li"><strong class="logs-strong">Release Profile</strong>'.$data['header']['functionExecutionStatus']['status'].'</li>';
          //$release_items = $data['header']['functionExecutionStatus']['status'];
          //echo "Status: " . $status; //Executed-Success
        } catch (\Exception $e) {
            //$release_items = $e->getMessage();
            $release_items = '<li class="logs-li"><strong class="logs-strong">Release Profile</strong>'.$e->getMessage().'</li>';
        }
        //releaseProfile end

        //cancelOrder Start
        try {
          $curl = curl_init();
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
              "functionRequesterIdentifier": "10T",
              "functionCallIdentifier": "10T"
          },
          "iccid": "'.$iccid.'",
          "finalProfileStatusIndicator": "Available"
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

          //cancelOrder start
          $path = $endpoint.'/gsma/rsp2/es2plus/cancelOrder';
          $activity = "Cancel Order";
          \Drupal::database()->insert('10t_usage')->fields(['iccid', 'path', 'activity', 'uid', 'timestamp'])->values([$iccid, $path, $activity, $uid, \Drupal::time()->getRequestTime()])->execute();
          //cancelOrder end

          $data = json_decode($response, true);
          $cancel_items = '<li class="logs-li"><strong class="logs-strong">Cancel Order</strong>'.$data['header']['functionExecutionStatus']['status'].'</li>';

        } catch (\Exception $e) {
            //$cancel_items = $e->getMessage();
            $cancel_items = '<li class="logs-li"><strong class="logs-strong">Cancel Order</strong>'.$e->getMessage().'</li>';
        }
        //cancelOrder End

        //downloadOrder start
        try {
          $curl = curl_init();
          curl_setopt_array($curl, array(
            CURLOPT_URL => $endpoint.'/gsma/rsp2/es2plus/downloadOrder',
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

          //downloadOrder start
          $path = $endpoint.'/gsma/rsp2/es2plus/downloadOrder';
          $activity = "Download Order";
          \Drupal::database()->insert('10t_usage')->fields(['iccid', 'path', 'activity', 'uid', 'timestamp'])->values([$iccid, $path, $activity, $uid, \Drupal::time()->getRequestTime()])->execute();
          //downloadOrder end

          $data = json_decode($response, true);

          $download_items = '<li class="logs-li"><strong class="logs-strong">Download Profile</strong>'.$data['header']['functionExecutionStatus']['status'].'</li>';

        } catch (\Exception $e) {
            //$download_items = $e->getMessage();
            $download_items = '<li class="logs-li"><strong class="logs-strong">Download Profile</strong>'.$e->getMessage().'</li>';
        }
        //downloadOrder end

        //confirmOrder start
        try {
          $curl = curl_init();
          curl_setopt_array($curl, array(
            CURLOPT_URL => $endpoint.'/gsma/rsp2/es2plus/confirmOrder',
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
          "iccid": "'.$iccid.'",
          "releaseFlag": true
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

          //confirmOrder start
          $path = $endpoint.'/gsma/rsp2/es2plus/confirmOrder';
          $activity = "Confirm Order";
          \Drupal::database()->insert('10t_usage')->fields(['iccid', 'path', 'activity', 'uid', 'timestamp'])->values([$iccid, $path, $activity, $uid, \Drupal::time()->getRequestTime()])->execute();
          //confirmOrder end

          $data = json_decode($response, true);
          $status = $data['header']['functionExecutionStatus']['status'];
          $confirm_items = '<li class="logs-li"><strong class="logs-strong">Confirm Order</strong>'.$data['header']['functionExecutionStatus']['status'].'</li></ul>';

        } catch (\Exception $e) {
            //$confirm_items = $e->getMessage();
            $confirm_items = '<li class="logs-li"><strong class="logs-strong">Confirm Order</strong>'.$e->getMessage().'</li></ul>';
        }
        //confirmOrder end
 

        //Credit logic start
        if(strtolower($status) == strtolower('Executed-Success')) {
          // Execute the update query
          $query = $this->externaldatabase->query("UPDATE preprod_esim_10t_status_vw SET state = 'RELEASED' WHERE prov_iccid = '".$iccid."'");
          $items = $items. $release_items . $cancel_items . $download_items . $confirm_items;
          $items = $items . '<br/>';
          $items = $items . '</ul>';

        } else {
          $items = $data['message']; //invalid headers
        }
        //Credit logic end
      } //token end
    } else {
      $items = '<h3 class="iccid-header">Low Credit: Please puchase credit from place order page.</h3><ul class="logs-ul">';
    }
    return [
        '#markup' => $items1. $items
      ];
  } //build form end

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
    return ['config.preprod_reset_modal_form'];
  }
}