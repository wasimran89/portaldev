<?php

namespace Drupal\mydata\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\OpenModalDialogCommand;
use Drupal\Core\Ajax\ReplaceCommand;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Url;

/**
 * SendToDestinationsForm class.
 */
class QRForm extends FormBase {
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
  public function __construct(RequestStack $request_stack) {
    $this->request = $request_stack->getCurrentRequest();
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $request = $container->get('request_stack');
    return new static($request);
  }
  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'qr_modal_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $options = NULL) {
    //ec2 start
    $iccid = $this->request->query->get('iccid');
    $current_user = \Drupal::currentUser();
    $uid = $current_user->id();
    $session = \Drupal::request()->getSession();
    $curl = curl_init();
    $config = \Drupal::config('xai.settings');
    $mode = $config->get('mode');
    if ($mode == 'production') {
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

    //sign start
    $path = $endpoint.'/gsma/rsp2/es2plus/user/signin';
    $activity = "SignIn";
    \Drupal::database()->insert('10t_usage')->fields(['iccid', 'path', 'activity', 'uid', 'timestamp'])->values([$iccid, $path, $activity, $uid, \Drupal::time()->getRequestTime()])->execute();
    //signin end

    $response = curl_exec($curl);

    if (curl_errno($curl)) {
      $error_msg = curl_error($curl);
    }
    curl_close($curl);

    if (isset($error_msg)) {
      print_r($error_msg);
    }

    $result = json_decode($response);

    //getQRCode
    $curl = curl_init();
    if ($result->token) {
      curl_setopt_array($curl, array(
        CURLOPT_URL => $endpoint.'/gsma/rsp2/es2plus/getQrCode',
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
                                "format": "png",
                                "size": 369
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

      //getQrCode start
      $path = $endpoint.'/gsma/rsp2/es2plus/getQrCode';
      $activity = "QR Code";
      \Drupal::database()->insert('10t_usage')->fields(['iccid', 'path', 'activity', 'uid', 'timestamp'])->values([$iccid, $path, $activity, $uid, \Drupal::time()->getRequestTime()])->execute();
      //getQrCode end

      $data = json_decode($response, true);
      $results = [];
      $status = [];
      foreach ($data['header'] as $logs) {
        $status[] = $logs["status"];
        if (isset($logs["statusCodeData"])) {
          $results[] = $logs["statusCodeData"];
        }
      }
      if (is_array($results) && isset($results[0]) && is_array($results[0])) {
        $items = '<h3 class="iccid-header">' . $logs['status'] . '</h3><ul class="logs-ul">';
        foreach ($results[0] as $keyv => $valuev) {
          $item = '<li class="logs-li"><strong class="logs-strong">' . $keyv . ":</strong>" . $valuev . '</li>';
          $items = $items . $item;
        }
        $items = $items . '<br/>';
        $items = $items . '</ul>';
      }
    }

    //getQrUri
    $curlnew = curl_init();
    if ($result->token) {
      curl_setopt_array($curlnew, array(
        CURLOPT_URL => $endpoint.'/gsma/rsp2/es2plus/getQrCode',
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
                                  "format": "text",
                                  "size": 369
                                }',
        CURLOPT_HTTPHEADER => array(
                                  "Authorization: Bearer $result->token",
                                  'Content-Type: application/json'
                                ),
                              ));

      $responsenew = curl_exec($curlnew);
      if (curl_errno($curlnew)) {
        $error_msg = curl_error($curlnew);
      }
      curl_close($curlnew);

      if (isset($error_msg)) {
        print_r($error_msg);
      }
      curl_close($curlnew);

      //getQrUri start
      $path = $endpoint.'/gsma/rsp2/es2plus/getQrCode';
      $activity = "QR URI";
      \Drupal::database()->insert('10t_usage')->fields(['iccid', 'path', 'activity', 'uid', 'timestamp'])->values([$iccid, $path, $activity, $uid, \Drupal::time()->getRequestTime()])->execute();
      //getQrUri end

      $datanew = json_decode($responsenew, true);
    }
    //getQrUri End

    if ($status[0] == 'Failed') {
      return [
        '#markup' => $items
      ];
    } else {
      //qr viewed start
      $session = \Drupal::request()->getSession();
      $session_key = 'qr_viewed_' . $iccid . '_' . $uid;

      if (!$session->get($session_key)) {
        $activity = 'QR code viewed';

        // Get the current user's UID.
        $current_user = \Drupal::currentUser();
        $uid = $current_user->id();
        $iccid = $this->request->query->get('iccid');

        $file_insert_id = \Drupal::database()->insert('qr_activity_log')
                          ->fields([
                            'iccid',
                            'activity',
                            'uid',
                            'timestamp'
                          ])
                          ->values(array(
                            $iccid,
                            $activity,
                            $uid,
                            \Drupal::time()->getRequestTime()
                          ))
                          ->execute();
        $session->set($session_key, TRUE);
      }
      //qr viewed end
      
      $datavar = 'data:image/png;base64,'.$data['data'].'';
      $form['#prefix'] = '<div class="row"><div id="modal_example_form"><img src='.$datavar.' alt="QR" width="300" height="300"></div>';
      $form['#suffix'] = '</div>';

      $form['qr_download_label'] = [
        '#type' => 'label',
        '#title' => $this->t('Download')
      ];

      $form['qr_download'] = [
        '#type' => 'submit',
        '#value' => $this->t('QR Download'),
        '#attributes' => [
          'class' => [
            'use-ajax',
          ],
        ],
        '#ajax' => [
          'callback' => [$this, 'qrdownloadModalFormAjax'],
          'event' => 'click',
        ],
        '#prefix' => '<div class="custom-wrapper1">'
      ];

      $form['email'] = array(
        '#type' => 'textfield',
        '#size' => 40,
        '#title' => $this->t('Email'),
        '#prefix' => '<div class="custom-wrapper2">'
      );

      $form['send'] = [
        '#type' => 'submit',
        '#value' => $this->t('Send Email'),
        '#attributes' => [
          'class' => [
            'use-ajax',
          ],
        ],
        '#ajax' => [
          'callback' => [$this, 'sendModalFormAjax'],
          'event' => 'click',
        ],
        '#suffix' => '</div>'
      ];      

      $form['qr_uri'] = [
        '#type' => 'label',
        '#value' => $datanew['data'],
        '#title' => $datanew['data'],
        '#attributes' => [
          'class' => ['custom-uri-label'],
        ],
        '#prefix' => '<div class="custom-wrapper3">',
        '#suffix' => '</div'    
      ];


      $form['copy'] = [
        '#type' => 'submit',
        '#value' => $this->t('Copy'),
        '#attributes' => [
          'class' => [
            'downloadlpastring'
          ],
        ],
        '#ajax' => [
          'callback' => [$this, 'copyModalFormAjax'],
          'event' => 'click',
        ],
        '#prefix' => '<div class="custom-wrapper4">',
        '#suffix' => '</div></div>'
      ];

      $form['qr_code'] = [
          '#type' => 'hidden',
          '#value' => $data['data'], 
      ];
      

      $form['qr_uri_value'] = [
          '#type' => 'hidden',
          '#value' => $datanew['data']
      ];
      
      $form['#attached']['library'][] = 'core/drupal.dialog.ajax';
      return $form;
    }
  }

  public function sendModalFormAjax(array $form, FormStateInterface $form_state) {
    $response = new AjaxResponse();

    // If there are any form errors, AJAX replace the form.
    if ($form_state->hasAnyErrors()) {
        $response->addCommand(new ReplaceCommand('#modal_example_form', $form));
    } else {
        $field = $form_state->getValues();
        // Mail details
        $to = $field['email'];

        // Validate the email address
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            $response->addCommand(new OpenModalDialogCommand("Error", 'Invalid email address provided.', ['width' => 700]));
            return $response; // Return early if the email is invalid
        }

        $from = \Drupal::config('system.site')->get('mail'); 
        $fromName = 'Webmaster'; 
        $subject = "QR Code"; 
        $qrCodeData = base64_decode($field['qr_code']); // Decode the base64 QR code
        $qrCodeFileName = 'qr_code.png';

        // MIME boundary
        $boundary = md5(time());

        // Email headers
        $headers = "MIME-Version: 1.0\r\n";
        $headers .= "From: $fromName <$from>\r\n";
        $headers .= "Content-Type: multipart/mixed; boundary=\"$boundary\"\r\n";

        // Email body
        $message = "--$boundary\r\n";
        $message .= "Content-Type: text/html; charset=UTF-8\r\n";
        $message .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
        $message .= "<html>
            <head>
                <title>QR</title>
            </head>
            <body>
                <p>Hi $to,</p>
                <p>Please find your QR code attached to this email. You can also view it directly below:</p>
                <p><img src='data:image/png;base64,{$field['qr_code']}' alt='QR' width='300' height='300'></p>
                <table cellspacing='0' style='border: 2px dashed #FB4314; width: 100%;'>
                    <tr>
                        <th>Download:</th>
                        <td><a href='data:image/png;base64,{$field['qr_code']}' target='_blank'>QR</a></td>
                    </tr>
                    <tr style='background-color: #e0e0e0;'>
                        <th>URI:</th>
                        <td><a href='{$field['qr_uri_value']}'>{$field['qr_uri_value']}</a></td>
                    </tr>
                </table>
            </body>
        </html>\r\n";

        // Attachment part
        $message .= "--$boundary\r\n";
        $message .= "Content-Type: image/png; name=\"$qrCodeFileName\"\r\n";
        $message .= "Content-Disposition: attachment; filename=\"$qrCodeFileName\"\r\n";
        $message .= "Content-Transfer-Encoding: base64\r\n\r\n";
        $message .= chunk_split(base64_encode($qrCodeData)) . "\r\n";
        $message .= "--$boundary--";
        // Send the email
        if (mail($to, $subject, $message, $headers)) {
            $response->addCommand(new OpenModalDialogCommand("Success", 'Email has been sent successfully with the attachment.', ['width' => 700]));

            $activity = 'QR code sent to : '.$to;

            // Get the current user's UID.
            $current_user = \Drupal::currentUser();
            $uid = $current_user->id();
            $iccid = $this->request->query->get('iccid');

            $file_insert_id = \Drupal::database()->insert('qr_activity_log')
                              ->fields([
                                'iccid',
                                'activity',
                                'uid',
                                'timestamp'
                              ])
                              ->values(array(
                                $iccid,
                                $activity,
                                $uid,
                                \Drupal::time()->getRequestTime()
                              ))
                              ->execute();

        } else {
            $response->addCommand(new OpenModalDialogCommand("Error", 'Error occurred while sending the email.', ['width' => 700]));
        }
    }

    return $response;
  }

  public function qrdownloadModalFormAjax(array $form, FormStateInterface $form_state) {
    $response = new AjaxResponse();

    // If there are any form errors, AJAX replace the form.
    if ($form_state->hasAnyErrors()) {
        $response->addCommand(new ReplaceCommand('#modal_example_form', $form));
    } else {
        $field = $form_state->getValues();
        $datavar = 'data:image/png;base64,' . $field['qr_code'];
        $activity = 'QR code downloaded';

        // Get the current user's UID.
        $current_user = \Drupal::currentUser();
        $uid = $current_user->id();
        $iccid = $this->request->query->get('iccid');

        $file_insert_id = \Drupal::database()->insert('qr_activity_log')
                          ->fields([
                            'iccid',
                            'activity',
                            'uid',
                            'timestamp'
                          ])
                          ->values(array(
                            $iccid,
                            $activity,
                            $uid,
                            \Drupal::time()->getRequestTime()
                          ))
                          ->execute();
        $encoded_content = base64_encode($field['qr_uri_value']);
        $lpastring = 'data:text/plain;charset=utf-8;base64,' . $encoded_content;

        $qrimage = 'data:image/png;base64,' . $field['qr_code'];
        
        
        $download_links = '
        <a style="display:none" class="add-new downloadlpastring" href="' . $lpastring . '" download="qrcode.txt">Download LPA String</a><br>
        <a style="display:none"class="add-new downloadqrcode" href="' . $qrimage . '" download="qrcode.png">Download QR Code</a>
        ';
        $options = [
            'buttons' => [
              'button1' => [
                'text' => $this->t('Close & Download'),
                'id' => 'ok-button',
                'onclick' => "jQuery('.downloadqrcode')[0].click();jQuery('.downloadlpastring')[0].click();jQuery('.ui-dialog-titlebar-close').click();"
              ],
            ],
            'width'=>600
          ];
        $response->addCommand(new OpenModalDialogCommand("Success", $this->t('Your download is ready. Please click below close & download button.').$download_links, $options));
    }
    return $response;
  }

  public function copyModalFormAjax(array $form, FormStateInterface $form_state) {
    $response = new AjaxResponse();

    // If there are any form errors, AJAX replace the form.
    if ($form_state->hasAnyErrors()) {
        $response->addCommand(new ReplaceCommand('#modal_example_form', $form));
    } else {
        $field = $form_state->getValues();
        // Get the current user's UID.
        $current_user = \Drupal::currentUser();
        $uid = $current_user->id();
        $iccid = $this->request->query->get('iccid');
        $activity = "LPA string copied";
        $file_insert_id = \Drupal::database()->insert('qr_activity_log')
                          ->fields([
                            'iccid',
                            'activity',
                            'uid',
                            'timestamp'
                          ])
                          ->values(array(
                            $iccid,
                            $activity,
                            $uid,
                            \Drupal::time()->getRequestTime()
                          ))
                          ->execute();
        

        // Open modal dialog with the QR code image and download link
        $response->addCommand(new OpenModalDialogCommand("Copied", $field['qr_uri_value'], ['width' => 600]));
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
    return ['config.qr_modal_form'];
  }
}
