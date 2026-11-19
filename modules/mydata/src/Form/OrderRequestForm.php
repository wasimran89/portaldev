<?php

namespace Drupal\mydata\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\OpenModalDialogCommand;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Database\Connection;


/**
 * Class OrderRequestForm.
 *
 * @package Drupal\mydata\Form
 */
class OrderRequestForm extends FormBase {
  /**
   * The external database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $externalDatabase;
  /**
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

    // Return the controller object with the injected database connections.
    return new static($externalConnection);
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'order_request_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $user = \Drupal::currentUser();
    $user_display_name = $user->getEmail();
    $queryuser = $this->externalDatabase->query("select sim_partner_id from drupal_user where drupal_user = '".$user_display_name."'");
    $resultuser = $queryuser->fetchAll();   

    $form['#prefix'] = '<div id="modal_example_form"><h1 class="txtcenter">Order Request Form</h1>';
    $form['#suffix'] = '</div>';
    $query = $this->externalDatabase->query("select partner_id, partner_name from partner order by partner_name");
    $result = $query->fetchAll();
    $partner_id = [];
    foreach ($result as $key => $data) {
      $partner_id[$data->partner_id] = $data->partner_name;
    }
    
    $form['partner_id'] = [
      '#type' => 'hidden',
      '#default_value' => $resultuser[0]->sim_partner_id,
    ];

    $form['sim_type'] = [
      '#title' => t('Type of SIM'),
      '#type' => 'select',
      '#required' => TRUE,
      '#options' => ['ESIM'=> 'ESIM','USIM'=> 'USIM'],
      '#attributes' => [
        'class' => [
        'use-ajax',
        'display-esim-generate-partner-id'
        ],
      ],
      '#ajax' => [
        'callback' => [$this, 'FilenameFormAjax'],
        'event' => 'change',
      ],
    ];

    $form['date_created'] = [
      '#type' => 'hidden',
      '#default_value' => date('Y-m-d')
    ];

    $form['more'] = [
        '#type' => 'fieldset',
        '#title' => t('Customer Contact Information'),
        '#collapsible' => FALSE,
    ];

    $form['more']['name'] = [
      '#title' => t('Name'),
      '#type' => 'textfield',
      '#required' => TRUE
    ];

    $form['more']['email'] = [
      '#title' => t('Email'),
      '#type' => 'email',
      '#required' => TRUE
    ];

    $form['more']['phone'] = [
      '#title' => t('Phone'),
      '#type' => 'tel',
      '#attributes' => array('onkeyup' => 'javascript: this.value = this.value.replace(/[^0-9-]/g, "")', 'pattern' => array('[0-9]{10}'),'maxlength'=>"10"),
      '#required' => TRUE
    ];

    $form['operator_name'] = [
      '#type' => 'textfield',
      '#title' => t('Operator Name'),
      '#default_value' => '',
      '#required' => true
    ];

    $form['profile_name'] = [
      '#type' => 'select',
      '#title' => t('New SIM profile name or Existing SIM profile name?'),
      '#options' => ['CREATE NEW'=> 'CREATE NEW'],
      '#default_value' => 'CREATE NEW',
      '#required' => true,
      '#validated' => TRUE,
      '#prefix' => '<div id="first">',
      '#suffix' => '</div>',
    ];

    $form['display_elements'] = [
        '#type' => 'button',
        '#value' => t('Display Elements'),
        '#prefix' => '<div id="display-element">',
        '#suffix' => '</div>',
        '#limit_validation_errors' => array(),
      '#attributes' => [
        'class' => [
        'use-ajax',
        'display-esim-generate'
        ],
      ],
      '#ajax' => [
        'callback' => [$this, 'DisplayModalFormAjax'],
        'event' => 'click',
      ],
    ];

    $form['mcc_mnc'] = [
      '#type' => 'radios',
      '#title' => t('MCC-MNC(if new SIM profie id)'),
      '#default_value' => '0',
      '#options' => ['5'=>t('5 digit'),'6'=>t('6 digit')],
      '#attributes' => [
                         'style' => 'display:inline'
                       ],
      '#ajax' => [
                    'callback' => '::radioCallback',
                    'wrapper' => 'mcc_mnc_container'
                  ],
    ];
    

    $form['mcc_mnc_container'] = [
        '#type' => 'container',
        '#attributes' => [
          'id' => 'mcc_mnc_container',
          'style' => 'display:inline-flex'
        ],
    ];

    if ($form_state->getValue('mcc_mnc', NULL) === "5") {
      $form['mcc_mnc_container']['5digit1'] = [
        '#type' => 'number',
        '#default_value' => '',
        '#size' => 3,
        '#maxlength' => 3,
        '#element_validate' => [
          [$this, 'validate5mccField'],
        ],
        '#attributes' => array('oninput'=>'javascript: if (this.value.length > this.maxLength) this.value = this.value.slice(0, this.maxLength)','pattern' => array('[0-9]{3}'),'maxlength'=>"3"),
        '#description'=>' MCC' 
      ];

      $form['mcc_mnc_container']['5digit2'] = [
        '#type' => 'number',
        '#title' => t('-'),
        '#default_value' => '',
        '#size' => 2,
        '#maxlength' => 2,
        '#element_validate' => [
          [$this, 'validate5mncField'],
        ],
        '#attributes' => array('oninput'=>'javascript: if (this.value.length > this.maxLength) this.value = this.value.slice(0, this.maxLength)','pattern' => array('[0-9]{2}'),'maxlength'=>"2"),
        '#description'=>' MNC'
      ];
    }

    if ($form_state->getValue('mcc_mnc', NULL) === "6") {
      $form['mcc_mnc_container']['6digit1'] = [
        '#type' => 'number',
        '#default_value' => '',
        '#size' => 3,
        '#maxlength' => 3,
        '#element_validate' => [
          [$this, 'validate6mccField'],
        ],
        '#attributes' => array('oninput'=>'javascript: if (this.value.length > this.maxLength) this.value = this.value.slice(0, this.maxLength)','pattern' => array('[0-9]{3}'),'maxlength'=>"3"),
        '#description'=>' MCC'
      ];

      $form['mcc_mnc_container']['6digit2'] = [
        '#type' => 'number',
        '#title' => t('-'),
        '#default_value' => '',
        '#size' => 3,
        '#maxlength' => 3,
        '#element_validate' => [
          [$this, 'validate6mncField'],
        ],
        '#attributes' => array('oninput'=>'javascript: if (this.value.length > this.maxLength) this.value = this.value.slice(0, this.maxLength)','pattern' => array('[0-9]{3}'),'maxlength'=>"3"),
        '#description'=>' MNC'
      ];
    }

    $form['imsi_start'] = [
      '#type' => 'textfield',
      '#title' => t('IMSI Start'),
      '#default_value' => '',
      '#required' => true,
      '#attributes' => array('pattern' => array('[0-9]{15}')),
      '#description' => 'The entered value must be numeric and length 15',
    ];

    $form['imsi_start'] = [
      '#type' => 'textfield',
      '#title' => t('IMSI Start'),
      '#default_value' => '',
      '#required' => true,
      '#attributes' => array('pattern' => array('[0-9]{15}')),
      '#description' => 'The entered value must be numeric and length 15',
    ];

    $form['imsi_end'] = [
      '#type' => 'textfield',
      '#title' => t('IMSI End'),
      '#default_value' => '',
      '#required' => true,
      '#attributes' => array('pattern' => array('[0-9]{15}')),
      '#description' => 'The entered value must be numeric and length 15',
    ];

    $form['spn'] = [
      '#type' => 'textfield',
      '#title' => t('SPN'),
      '#default_value' => '',
      '#description' => 'The entered value may be alphanumeric with max length 16',
    ];

    $form['sim_parameter'] = [
      '#type' => 'textfield',
      '#title' => t('Any Other SIM Parameter'),
      '#default_value' => '',
      '#cache' => array('max-age' => 0)
    ];
    $form['newop'] = [
      '#type' => 'checkbox',
      '#title' => t('Add New OP'),
      '#default_value' => '',
      '#description' => 'We have an existing OP on record. If you wish to use alternate, please select “Add New” and we will coordinate secure transfer.'
    ];
    
    $form['ki_opc'] = [
      '#type' => 'fieldset',
      '#title' => t('KI & OPC'),
      '#collapsible' => FALSE,
      '#description' => 'KI generated randomly. OPC calculated. All secrets are encrypted.'
    ];

    $form['more1'] = [
        '#type' => 'fieldset',
        '#collapsible' => FALSE
    ];    
    $form['more1']['shipping_speed'] = [
      '#type' => 'select',
      '#title' => t('Shipping Speed'),
      '#options' => ['Standard'=> 'Standard','3 Day'=> '3 Day','2 Day'=> '2 Day', 'Overnight'=> 'Overnight'],
      '#default_value' => 'Standard'
    ];

    $form['more1']['ship_to'] = [
      '#title' => t('Ship-To'),
      '#type' => 'textfield',
      '#required' => TRUE
    ];

    $form['more1']['ship_email'] = [
      '#title' => t('Email'),
      '#type' => 'email',
      '#required' => TRUE
    ];
    
    $form['more1']['ship_phone'] = [
      '#title' => t('Phone'),
      '#type' => 'tel',
      '#attributes' => array('onkeyup' => 'javascript: this.value = this.value.replace(/[^0-9-]/g, "")', 'pattern' => array('[0-9]{10}'),'maxlength'=>"10"),
      '#required' => TRUE
    ];

    /*
    $form['my_captcha_element'] = [
      '#type' => 'captcha',
    ];
    */

    $form['submit_request'] = [
        '#type' => 'submit',
        '#value' => $this->t('Submit')
    ];

    $form['reset'] = [
      '#type' => 'submit',
      '#submit' => [[$this, 'ResetButton']],
      '#value' => t('Reset'),
      '#weight' => 9,
      '#limit_validation_errors' => [], //no validation for back button
    ];


    $form['#attached']['library'][] = 'core/drupal.dialog.ajax';
    return $form;
  }

  /**
   * Custom validation callback for the custom number field.
   */
  public function validate5mccField(array &$element, FormStateInterface $form_state, array &$complete_form) {
    $value = $element['#value'];
    if (!is_numeric($value) || strlen($value) !== 3) {
      $form_state->setError($element, $this->t('The MCC value must be a numeric value of 3 characters.'));
    }
  }
  public function validate5mncField(array &$element, FormStateInterface $form_state, array &$complete_form) {
    $value = $element['#value'];
    if (!is_numeric($value) || strlen($value) !== 2) {
      $form_state->setError($element, $this->t('The MNC value must be a numeric value of 2 characters.'));
    }
  }
  public function validate6mccField(array &$element, FormStateInterface $form_state, array &$complete_form) {
    $value = $element['#value'];
    if (!is_numeric($value) || strlen($value) !== 3) {
      $form_state->setError($element, $this->t('The MCC value must be a numeric value of 3 characters.'));
    }
  }
  public function validate6mncField(array &$element, FormStateInterface $form_state, array &$complete_form) {
    $value = $element['#value'];
    if (!is_numeric($value) || strlen($value) !== 3) {
      $form_state->setError($element, $this->t('The MNC value must be a numeric value of 3 characters.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    // Validate the Customer Email field.
    $customer_email = $form_state->getValue('email');
    if (!filter_var($customer_email, FILTER_VALIDATE_EMAIL)) {
      $form_state->setErrorByName('customer_email', $this->t('The email address is not valid.'));
    }
    // Validate the Ship Email field.
    $ship_email = $form_state->getValue('ship_email');
    if (!filter_var($ship_email, FILTER_VALIDATE_EMAIL)) {
      $form_state->setErrorByName('ship_email', $this->t('The shipping email address is not valid.'));
    }
    // Validate the Customer Phone field.
    $customer_phone = $form_state->getValue('phone');
    if (!is_numeric($customer_phone) || strlen($customer_phone) !== 10) {
      $form_state->setErrorByName('phone', $this->t('Customer Phone must be a numeric value of 10 characters.'));
    }

    // Validate the Ship Phone field.
    $ship_phone = $form_state->getValue('ship_phone');
    if (!is_numeric($ship_phone) || strlen($ship_phone) !== 10) {
      $form_state->setErrorByName('ship_phone', $this->t('Ship Phone must be a numeric value of 10 characters.'));
    }
  }
  public function radioCallback($form, FormStateInterface $form_state)
  {
    return $form['mcc_mnc_container'];
  }

  public function DisplayModalFormAjax(array $form, FormStateInterface $form_state) {
    $field = $form_state->getValues();
    $query = $this->externalDatabase->query("select input_file_id from input_file where input_file_type = '".$field['sim_type']."' and input_file_id = '".$field['profile_name']."'");
    $result = $query->fetchAll();
    $response = new AjaxResponse();
    $header_column = ['position', 'input_element','static','static_value'];
    $query = $this->externalDatabase->query("select position, input_element, case when static then 'TRUE' else 'FALSE' end as static, coalesce(static_value, 'NULL') as static_value from input_element where input_file_id = ".$result[0]->input_file_id." order by position");        
    $result = $query->fetchAll();
    $rows = [];
    foreach ($result as $key => $value) {       
    $rows[] = (array) $value;
    }

    $form2['table'] = [
      '#type' => 'table',
      '#header' => $header_column,
      '#rows' => $rows,
      '#empty' => t('No Records Found'),
      '#cache' => array('max-age' => 0),
      '#attributes' => array('class' => array('custom-table'))
    ];

      $field = $form_state->getValues();
      $options1 = [
          'buttons' => [
            'button1' => [
              'text' => $this->t('Ok'),
              'id' => 'ok-button',
              'onclick' => "jQuery('.ui-dialog-titlebar-close').click()",
              'width' => '800',
            ],
          ],
        ];
      $title = 'Input Elements';
      $content = $form2;
      $options = array(
             'dialogClass' => 'popup-dialog-class',
             'width' => '1000',
             //'height' => '300',
        );
      $response->addCommand(new OpenModalDialogCommand($title, $content, $options));


    return $response;
  }
  /**
   * submit AJAX callback handler that displays any errors or a success message.
   */
  public function submitModalFormAjax(array $form, FormStateInterface $form_state) {
    $response = new AjaxResponse();

    // If there are any form errors, AJAX replace the form.
    if ($form_state->hasAnyErrors()) {
      $response->addCommand(new ReplaceCommand('#modal_example_form', $form));
    } else {
      $field = $form_state->getValues();
      $options = [
          'buttons' => [
            'button1' => [
              'text' => $this->t('Ok'),
              'id' => 'ok-button',
              'onclick' => "jQuery('.ui-dialog-titlebar-close').click()",
            ],
          ],
        ];

      $title = 'Message';
      $content = 'Hello';

      $options = [
        'dialogClass' => 'popup-dialog-class',
        'width' => '800',
        'buttons' => [
          'button1' => [
            'text' => $this->t('Ok'),
            'id' => 'ok-button',
            'onclick' => "jQuery('.ui-dialog-titlebar-close').click()",
          ],
          'button2' => [
            'text' => $this->t('Download'),
            'id' => 'download-button',
            'onclick' => "jQuery('.esim-download').click()",
          ],
        ],
      ];           

     $response->addCommand(new OpenModalDialogCommand($title, $content, $options));

    }

    return $response;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {  
    $field = $form_state->getValues();
   
    $session = \Drupal::request()->getSession();
    if($field['mcc_mnc']){
      if($field['mcc_mnc']=="5"){
        $mccmnc = $field['5digit1']."-".$field['5digit2'];
      }else{
        $mccmnc = $field['6digit1']."-".$field['6digit2'];
      }
    }

    if($field['profile_name'] == 'CREATE NEW'){
      $field['profile_name'] = 'NULL';        
    }

    if($field['newop'] == 1){
      $field['newop'] = 'true';        
    }else{
      $field['newop'] = 'false';
    }

    try {
        $queryif = $this->externalDatabase->query("select input_file_name from input_file where input_file_type = '".$field['sim_type']."' and input_file_id = '".$field['profile_name']."'");
        $resultif = $queryif->fetchAll();
        $profile_name = $resultif[0]->input_file_name;
        $query = $this->externalDatabase->query("INSERT INTO order_request VALUES(nextval('order_request_order_request_id_seq'), ".$field['partner_id'].", '".$field['sim_type']."', '".$field['name']."', '".$field['email']."', '".$field['phone']."', '".$field['operator_name']."', ".$field['profile_name'].", '".$mccmnc."', '".$field['imsi_start']."', '".$field['imsi_end']."', '".$field['spn']."', '".$field['sim_parameter']."', ".$field['newop'].", '".$field['shipping_speed']."', '".$field['ship_to']."', '".$field['ship_email']."', '".$field['ship_phone']."')");
        //\Drupal::messenger()->addMessage($this->t('Succesfully Saved'));       
        
    } catch (\Exception $e) {
      \Drupal::messenger()->addError($e);
    }


    if($query){
      //mail sent      
      $mailManager = \Drupal::service('plugin.manager.mail');
      $module = 'mydata';
      $key = 'general_mail';
      $to = 'wsayyad@jcius.com,sims@jcius.com';
      $params['message'] = "<div><p><b>Customer: </b> ".$session->get('current_partner_name')."</p><p><b>SIM Type: </b> ".$field['sim_type']."</p><p><b>Date: </b> ".$field['date_created']."</p><p><b>Customer Contact Information</b></p><p><b>Name: </b> ".$field['name']."</p><p><b>Email: </b> ".$field['email']."</p><p><b>Phone: </b> ".$field['phone']."</p><p><b>Operator Name: </b> ".$field['operator_name']."</p><p><b>Profile Name: </b> ".$profile_name."</p><p><b>MCC-MNC(if new SIM profie id): </b> ".$field['mcc_mnc']." digit: ".$mccmnc."</p><p><b>IMSI Start: </b> ".$field['imsi_start']."</p><p><b>EMSI End: </b> ".$field['imsi_end']."</p><p><b>SPN: </b> ".$field['spn']."</p><p><b>SIM Parameter: </b> ".$field['sim_parameter']."</p><p><b>Add new OP: </b> ".$field['newop']."</p><p><b>KI & OPC: KI generated randomly. OPC calculated. All secrets are encrypted.</b></p><p><b>Shipping Information</b></p><p><b>Shipping Speed: </b> ".$field['shipping_speed']."</p><p><b>Ship To: </b> ".$field['ship_to']."</p><p><b>Email: </b> ".$field['ship_email']."</p><p><b>Phone: </b> ".$field['ship_phone']."</p><div>";
      $params['subject'] = "New Customer Order Request Form";
      $langcode = \Drupal::currentUser()->getPreferredLangcode();
      $send = true;
      $result = $mailManager->mail($module, $key, $to, $langcode, $params, NULL, $send);
      if ($result['result'] !== true) {
        \Drupal::messenger()->addMessage($this->t('There was a problem sending your message and it was not sent.'));
      }
      else {
        \Drupal::messenger()->addMessage($this->t('Thank you for contacting us, we will reach back to you in a short time.'));
      }
      //end mail sent
    }
    
  }

  /**
   * {@inheritdoc}
   */
  public function ResetButton(array &$form, FormStateInterface $form_state) {  

  }

  public function FilenameFormAjax(array $form, FormStateInterface $form_state){
      $response = new AjaxResponse();
      $field = $form_state->getValues(); 

      if($field['sim_type']=='ESIM'){
        $query2 = $this->externalDatabase->query("select input_file_name, input_file_id from input_file where partner_id = ".$field['partner_id']." and input_file_type = 'ESIM' order by input_file_name");
      }
      else{
        $query2 = $this->externalDatabase->query("select input_file_name, input_file_id from input_file where partner_id = ".$field['partner_id']." and input_file_type = 'USIM' order by input_file_name");
      }      
      $result2 = $query2->fetchAll();
      $filename = [];
      foreach ($result2 as $key2 => $data2) {
        $filename['CREATE NEW'] = "CREATE NEW";
        $filename[$data2->input_file_id] = $data2->input_file_name;
      }
      $form['profile_name']['#options'] = $filename;
      $response->addCommand(new ReplaceCommand('#first', $form['profile_name']));

      return $response;
  }
}