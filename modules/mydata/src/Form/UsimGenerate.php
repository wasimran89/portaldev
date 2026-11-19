<?php

namespace Drupal\mydata\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Database\Database;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\OpenModalDialogCommand;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Ajax\ReplaceCommand;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Drupal\Core\Database\Connection;
/**
 * Class UsimGenerate.
 *
 * @package Drupal\mydata\Form
 */
class UsimGenerate extends FormBase {
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
    return 'usim_generate_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    //Remove session value which already set
    $session = \Drupal::request()->getSession();
    $session->remove('download_usim_genrate');
    $form['#prefix'] = '<div id="modal_example_form"><h1 class="txtcenter">USIM Generate</h1>';
    $form['#suffix'] = '</div>';

    // Build the select query using the select() and orderBy() methods.
    $query = $this->externalDatabase->select('partner', 'p')
                  ->fields('p', ['partner_id', 'partner_name'])
                  ->orderBy('partner_name');

    // Execute the query and fetch the result.
    $result = $query->execute()->fetchAll();

    $partner_id = [];
    foreach ($result as $key => $data) {
      $partner_id[$data->partner_id] = $data->partner_name;
    }

    $query2 = $this->externalDatabase->query("select input_file_name, input_file_id from input_file where input_file_type = 'USIM' order by input_file_name");
    $result2 = $query2->fetchAll();
    $filename = [];
    foreach ($result2 as $key2 => $data2) {
      $filename[$data2->input_file_id] = $data2->input_file_name;
    }

    $query3 = $this->externalDatabase->query("select iccid_prefix, description from iccid_prefix order by 1");
    $result3 = $query3->fetchAll();
    $iccid_prefix = [];
    foreach ($result3 as $key3 => $data3) {
      $iccid_prefix[$data3->iccid_prefix] = $data3->iccid_prefix." | ".$data3->description;
    }

    $form['partner_id'] = array(
      '#title' => t('Partner'),
      '#type' => 'select',
      '#required' => TRUE,
      '#options' => $partner_id,
      '#attributes' => [
        'class' => [
        'use-ajax',
        'display-usim-generate-partner-id'
        ],
      ],
      '#ajax' => [
        'callback' => [$this, 'FilenameFormAjax'],
        'event' => 'change',
      ],
    );
    
    $form['more'] = [
        '#type' => 'fieldset',
        "#prefix" => '<div id="form-example_entry">',
        '#collapsible' => FALSE,
    ];

    $form['more']['filename'] = [
      '#title' => t('Filename'),
      '#type' => 'select',
      '#required' => TRUE,
      '#options' => $filename,
      '#prefix' => '<div id="first">',
      '#suffix' => '</div>',
    ];

    $form['more']['display_elements'] = [
        '#type' => 'button',
        '#value' => t('Display Elements'),
        "#suffix" => '</div>',
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

    $form['master_id_start'] = array(
      '#title' => t('Master ID Start'),
      '#type' => 'textfield',
      '#required' => TRUE,
      '#attributes' => array('pattern' => array('[0-9]{20}')),
      '#description' => 'The entered value must be numeric and length 20'
    );

    $form['more1'] = [
        '#type' => 'fieldset',
        "#prefix" => '<div id="form-imsi-range">',
        '#collapsible' => FALSE,
    ];    

    $form['more1']['next_imsi_range'] = [
        '#type' => 'button',
        '#value' => t('Next IMSI In Range'),
        "#suffix" => '</div>',
        '#limit_validation_errors' => array(),
      '#attributes' => [
        'class' => [
        'use-ajax',
        'display-next-imsi-range'
        ],
      ],
      '#ajax' => [
        'callback' => [$this, 'NextImsiRangeModalFormAjax'],
        'event' => 'click',
      ],
    ];

    $form['more1']['partial_imsi'] = [
      '#description' => t('Enter Leading Partial IMSI Max Length 14'),
      '#type' => 'textfield',
      "#size" => 30
    ];

    $form['count'] = array(
      '#title' => t('USIM Records to Generate'),
      '#description' => t('Enter USIM Records to Generate Next IMSI Range'),
      '#type' => 'number',
      '#required' => TRUE,
      '#attributes' => array('pattern' => array('[0-9]+'),'min' => '0','max' => '100000')
    );

    $form['imsi_start'] = array(
      '#type' => 'textfield',
      '#description' => array(
                        '#markup' => '<div class="imsi_start_desc">The entered value must be numeric and length 15</div>',
                      ),
      '#required' => TRUE,
      '#attributes' => array('pattern' => array('[0-9]{15}'), 'class' => ['imsi_start']),
      '#ajax' => array(
        // Function to call when event on form element triggered.
        'callback' => [$this, 'ImsiStartValidateCallback'],
        // Effect when replacing content. Options: 'none' (default), 'slide', 'fade'.
        'effect' => 'fade',
        // Javascript event to trigger Ajax. Currently for: 'onchange'.
        'event' => 'change',
        'progress' => array(
          // Graphic shown to indicate ajax. Options: 'throbber' (default), 'bar'.
          'type' => 'throbber',
          // Message to show along progress graphic. Default: 'Please wait...'.
          'message' => NULL,
        ),
      ),
    );

    $form['iccid_prefix'] = array(
      '#title' => t('ICCID Prefix'),
      '#type' => 'select',
      '#required' => TRUE,
      '#options' => $iccid_prefix,
    );    

    $form['default_generated'] = array(
        '#type' => 'checkbox',
        '#title' => t('Custom File Name (Default is Partner_Name_PLMNID_YYYYMMDD_Count.zip)'),
        '#default_value' => 0,
        '#attributes' => array('class' => array('custom-default-generated'))
    );

    $form['custom_file_name'] = array(
        '#title' => t('Custom File Name'),
        '#type' => 'textfield',
        '#attributes' => array('pattern' => array('[a-zA-Z0-9_]+'),'class' => array('custom-file-name'))
    );
    
    $form['testing_exit'] = array(
        '#type' => 'checkbox',
        '#title' => t('Validate Only'),
        '#default_value' => 0,
        '#attributes' => array('class' => array('custom-testing-exit'))
    );

    /** future enhancement
    $form['json_secrets'] = array(
        '#type' => 'checkbox',
        '#title' => t('Json Secrets'),
        '#default_value' => 0
    );
    **/


    $form['submit_usim'] = [
        '#type' => 'button',
        '#value' => $this->t('Submit'),
        '#attributes' => [
          'class' => [
          'use-ajax',
          'custom-usim-generate'
          ],
        ],
        '#ajax' => [
          'callback' => [$this, 'submitModalFormAjax'],
          'event' => 'click',
        ],
    ];

    $form['reset'] = [
      '#type' => 'submit',
      '#submit' => [[$this, 'ResetButton']],
      '#value' => t('Reset'),
      '#weight' => 9,
      '#limit_validation_errors' => [], //no validation for back button
    ];

    $form['download'] = [
      '#type' => 'submit',
      '#submit' => [[$this, 'downloadZip']],
      '#value' => t('Download '),
      '#weight' => 10,
      '#attributes' => [
        'class' => [
          'usim-download',
        ],
        'style' =>["visibility:hidden"],
      ],
      '#limit_validation_errors' => [], //no validation for back button
    ];

    $form['download-directory'] = [
      '#type' => 'button',
      '#value' => $this->t('Download'),
      '#limit_validation_errors' => [],
      '#attributes' => [
        'class' => [
        'use-ajax',
        'custom-download-directory'
        ],
      ],
      '#ajax' => [
        'callback' => [$this, 'downloadZipDirectory'],
        'event' => 'click',
      ],
    ];

    $form['#attached']['library'][] = 'core/drupal.dialog.ajax';

    return $form;
  }

  public function validateForm(array &$form, FormStateInterface $form_state) {
    $selected_option_value = $form_state->getValue('imsi_start');
    if (!is_numeric($selected_option_value) || strlen($selected_option_value) !== 15) {
      $form_state->setErrorByName('imsi_start', $this->t('The entered value must be numeric and length 15'));
    }  
  }

  public function ImsiStartValidateCallback(array &$form, FormStateInterface $form_state) {

    if (preg_match('~^\d{15}$~', $form_state->getValue('imsi_start')))
    {
        $css = ['border' => '1px solid green'];
        $message = ('The entered value is numeric and length 15');
        $css1 = ['color'=> 'green'];
    }
    else
    {
       $css = ['border' => '1px solid red'];
       $css1 = ['color'=> 'red'];
       $class= ['required'=>'required'];
        $message = ('The entered value is not numeric or 15 digit!!!');
    }

    $response = new \Drupal\Core\Ajax\AjaxResponse();
    $response->addCommand(new \Drupal\Core\Ajax\CssCommand('.imsi_start', $css));
    $response->addCommand(new \Drupal\Core\Ajax\CssCommand('.imsi_start_desc', $css1));
    $response->addCommand(new \Drupal\Core\Ajax\HtmlCommand('.imsi_start_desc', $message));
    $response->addCommand(new \Drupal\Core\Ajax\HtmlCommand('.result_message', '<div class="my_top_message">errror</div>')
    );
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
              'onclick' => "jQuery('.ui-dialog-titlebar-close').click()"
            ],
          ],
        ];
      
      if ($field['testing_exit'] == 1) {
        $testing_exit = "-t";
      } 
      if ($field['json_secrets'] == 1) {
        $json_secrets = "-j";
      }
      if ($field['default_generated'] == 1) {
        $default_generated = "-o ".$field['custom_file_name'];
      }

      $process = new Process("sudo -u clarion /home/clarion/oss_scripts/x-usim_gen.sh -p ".$field['partner_id']." -f ".$field['filename']." -s ".$field['master_id_start']." -m ".$field['imsi_start']." -i ".$field['iccid_prefix']." -c ".$field['count']." ".$testing_exit." ".$json_secrets." ".$default_generated."");      

      $process->run();

      // executes after the command finishes
      if (!$process->isSuccessful()) {
          //throw new ProcessFailedException($process);
          $output = $process->getErrorOutput();
      }
      else {
          $output = $process->getOutput();
      }

      $title = 'Message';
      $content = $output;
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
            'onclick' => "jQuery('.usim-download').click()",
          ],
        ],
      ];

      $optiond = [
        'dialogClass' => 'popup-dialog-class',
        'width' => '800',
        'buttons' => [
          'button1' => [
            'text' => $this->t('Ok'),
            'id' => 'ok-button',
            'onclick' => "jQuery('.ui-dialog-titlebar-close').click()",
          ]
        ],
      ];

      // Build the select query using the select() and condition() methods.
      $query = $this->externalDatabase->select('partner', 'p')
                        ->fields('p', ['partner_name'])
                        ->condition('partner_id', $field['partner_id']);

      // Execute the query and fetch the result.
      $result = $query->execute()->fetchAll();

      $partner_name = str_replace(' ', '_', $result[0]->partner_name);
      $imsi_start = substr($field['imsi_start'] ,0 ,6);
      $count = $field['count'];
      //$date = date("mdY");// format changed by duane 13jan23
      $date = date("Ymd");
      if ($field['custom_file_name']) {
        $zip = $field['custom_file_name'].".zip.gpg"; //changed .gpg encrypted
      } else {
        $zip = $partner_name."_".$imsi_start."_".$date."_".$count.".zip.gpg"; //.gpg encrypted
      }      
      $session = \Drupal::request()->getSession();
      $session->set('download_usim_genrate', $zip);

      if ($field['testing_exit'] == 0) {
        $response->addCommand(new OpenModalDialogCommand($title, $content, $options));
      } else {
        $response->addCommand(new OpenModalDialogCommand($title, $content, $optiond));
      }
      $response->addCommand(new ReplaceCommand('#modal_example_form', $form));

    }

    return $response;
  }
  /**
   * Display FilenameFormAjax callback handler that displays any errors or a success message.
   */
  public function FilenameFormAjax(array $form, FormStateInterface $form_state) {
      $response = new AjaxResponse();
      $field = $form_state->getValues();

      // Build the select query using the select(), condition(), and orderBy() methods.
      $query2 = $this->externalDatabase->select('input_file', 'i')
                         ->fields('i', ['input_file_name', 'input_file_id'])
                         ->condition('input_file_type', 'USIM')
                         ->condition('partner_id', $field['partner_id'])
                         ->orderBy('input_file_name');

      // Execute the query and fetch the result.
      $result2 = $query2->execute()->fetchAll();

      $filename = [];
      foreach ($result2 as $key2 => $data2) {
        $filename[$data2->input_file_id] = $data2->input_file_name;
      }
      $form['more']['filename']['#options'] = $filename;
      $response->addCommand(new ReplaceCommand('#first', $form['more']['filename']));

      return $response;
  }
  /**
   * Display AJAX callback handler that displays any errors or a success message.
   */
  public function DisplayModalFormAjax(array $form, FormStateInterface $form_state) {
    $field = $form_state->getValues();
    $response = new AjaxResponse();

    $header_column = ['position', 'input_element','static','static_value'];
    $query = $this->externalDatabase->query("select position, input_element, case when static then 'TRUE' else 'FALSE' end as static, coalesce(static_value, 'NULL') as static_value from input_element where input_file_id = ".$field['filename']." order by position");        
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
              'width' => '800'
            ],
          ],
        ];
      $title = 'Input Elements';
      $content = $form2;
      $options = array(
             'dialogClass' => 'popup-dialog-class',
             'width' => '800',
             //'height' => '300',
        );
      $response->addCommand(new OpenModalDialogCommand($title, $content, $options));


    return $response;
  }

  /**
   * Display NextImsiRangeModalFormAjax AJAX callback handler that displays any errors or a success message.
   */
  public function NextImsiRangeModalFormAjax(array $form, FormStateInterface $form_state) {
    $field = $form_state->getValues();
    $response = new AjaxResponse();
    if (($form_state->getValue('partial_imsi')) && ($form_state->getValue('count')))
    {        
        try {
          $query = $this->externalDatabase->query("SELECT * FROM fetch_next_imsi('".$field['partial_imsi']."', 'USIM', ".$field['count'].")");
          $result = $query->fetchAll(); 
          $output = $result[0]->fetch_next_imsi;
          $response->addCommand(new \Drupal\Core\Ajax\CssCommand('#edit-partial-imsi', ['border' => '1px solid green']));
          $response->addCommand(new \Drupal\Core\Ajax\CssCommand('#edit-partial-imsi--description', ['color' => 'green']));
          $response->addCommand(new \Drupal\Core\Ajax\CssCommand('#edit-count', ['border' => '1px solid green']));
          $response->addCommand(new \Drupal\Core\Ajax\CssCommand('#edit-count--description', ['color' => 'green']));
          //$response->addCommand(new \Drupal\Core\Ajax\HtmlCommand('#edit-partial-imsi--description', "Enter Leading Partial IMSI Max Length 14"));   
        } catch (\Exception $e) {
          $output = "No provisioned IMSI records exist for partial IMSI ".$field['partial_imsi'];
          $response->addCommand(new \Drupal\Core\Ajax\CssCommand('#edit-partial-imsi', ['border' => '1px solid green']));
          $response->addCommand(new \Drupal\Core\Ajax\CssCommand('#edit-partial-imsi--description', ['color' => 'green']));
          $response->addCommand(new \Drupal\Core\Ajax\HtmlCommand('#edit-partial-imsi--description', "Enter Leading Partial IMSI Max Length 14"));
        }   
    } elseif ($form_state->getValue('count')) {
        $output = "Please enter partial IMSI range.";
        $response->addCommand(new \Drupal\Core\Ajax\CssCommand('#edit-partial-imsi', ['border' => '1px solid red']));
        $response->addCommand(new \Drupal\Core\Ajax\CssCommand('#edit-partial-imsi--description', ['color' => 'red']));
        $response->addCommand(new \Drupal\Core\Ajax\CssCommand('#edit-count', ['border' => '1px solid green']));
        $response->addCommand(new \Drupal\Core\Ajax\CssCommand('#edit-count-imsi--description', ['color' => 'green']));
        $response->addCommand(new \Drupal\Core\Ajax\HtmlCommand('#edit-partial-imsi--description', "Please enter Leading Partial IMSI Max Length 14
        "));
    } elseif ($form_state->getValue('partial_imsi')) {
        $output = "Please enter USIM Records to Generate.";
        $response->addCommand(new \Drupal\Core\Ajax\CssCommand('#edit-count', ['border' => '1px solid red']));
        $response->addCommand(new \Drupal\Core\Ajax\CssCommand('#edit-count--description', ['color' => 'red']));
        $response->addCommand(new \Drupal\Core\Ajax\CssCommand('#edit-partial-imsi', ['border' => '1px solid green']));
        $response->addCommand(new \Drupal\Core\Ajax\CssCommand('#edit-partial-imsi--description', ['color' => 'green']));
        $response->addCommand(new \Drupal\Core\Ajax\HtmlCommand('#edit-count--description', "Please enter USIM Records to Generate.
        "));
    } else {
        $output = "Please enter partial IMSI range & USIM Records to Generate.";
        $response->addCommand(new \Drupal\Core\Ajax\CssCommand('#edit-partial-imsi', ['border' => '1px solid red']));
        $response->addCommand(new \Drupal\Core\Ajax\CssCommand('#edit-partial-imsi--description', ['color' => 'red']));
        $response->addCommand(new \Drupal\Core\Ajax\HtmlCommand('#edit-partial-imsi--description', "Please enter Leading Partial IMSI Max Length 14
        "));
        $response->addCommand(new \Drupal\Core\Ajax\CssCommand('#edit-count', ['border' => '1px solid red']));
        $response->addCommand(new \Drupal\Core\Ajax\CssCommand('#edit-count--description', ['color' => 'red']));
        $response->addCommand(new \Drupal\Core\Ajax\HtmlCommand('#edit-count--description', "Please enter USIM Records to Generate.
        "));
    }
    
    $field = $form_state->getValues();
    $options1 = [
        'buttons' => [
          'button1' => [
            'text' => $this->t('Ok'),
            'id' => 'ok-button',
           // 'onclick' => 'window.open("mydata/oss/table3?table=portal_active_lines_vw")',
            'onclick' => "jQuery('.ui-dialog-titlebar-close').click()",
            'width' => '800',
           // 'disabled' => 'disabled'
          ],
          /*'button2' => [
            'text' => $this->t('Back'),
            'id' => 'close-button',
            'onclick' => "jQuery('.ui-dialog-titlebar-close').click()",
          ],*/
        ],
      ];
    $title = 'Next IMSI In Range';
    $content = $output;
    $options = array(
           'dialogClass' => 'popup-dialog-class',
           'width' => '300',
           //'height' => '300',
      );

      $response->addCommand(new OpenModalDialogCommand($title, $content, $options));    


    return $response;
  }


  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {  

  }
  public function ResetButton(array &$form, FormStateInterface $form_state) {  

  }
    /**
   * {@inheritdoc}
   */
  public function downloadZip(array &$form, FormStateInterface $form_state) { 
    try{
      $field = $form_state->getValues();
      $response = new AjaxResponse();
      $session = \Drupal::request()->getSession();  
      $filename = '/home/clarion/oss_scripts/data/usim/'.$session->get('download_usim_genrate');
      $response = new BinaryFileResponse($filename);
      //for file name
      $response->setContentDisposition(
          ResponseHeaderBag::DISPOSITION_ATTACHMENT,
          $session->get('download_usim_genrate')
      );
      $form_state->setResponse($response);
    } catch (\Exception $e) {
      \Drupal::messenger()->addError('Zip Not Exist');
    }  

  }
  //directory
  public function downloadZipDirectory(array $form, FormStateInterface $form_state) {
      $response = new AjaxResponse();
      $field = $form_state->getValues(); 

      $title = 'Download';
      $content = [];
      $options = array(
             'dialogClass' => 'popup-dialog-class',
             'width' => '1200',
        );
      $directory = '/home/clarion/oss_scripts/data/usim/';
      // Get all files and directories inside the given directory
      // Get the current timestamp
      $currentTimestamp = time();

      // Calculate the timestamp for 30 days ago
      $thirtyDaysAgoTimestamp = strtotime('-30 days', $currentTimestamp);
      // Use glob to list .gpg files in the directory
      $files = glob($directory . '*.gpg');
      // Filter and sort the files
      $filteredFiles = [];
      foreach ($files as $file) {
          // Get the file's last modified timestamp
          $fileTimestamp = filemtime($file);
          // Check if the file is modified within the last 30 days
          if ($fileTimestamp >= $thirtyDaysAgoTimestamp) {
              // Add the file to the list
              $filteredFiles[] = $file;
          }
      }

      // Sort the filtered files by filename
      //natsort($filteredFiles);

      // Output the file list
      foreach ($filteredFiles as $file) {
          $filename = basename($file);
          $timestamp = date("Y-m-d H:i:s", filemtime($file));
          $content[] = "<span>$timestamp - </span><a href='/download?type=usim&filename=" . urlencode($filename) . "'>" . urlencode($filename) . "</a><br>";
      }
      arsort($content);
      /*
      $files = scandir($directory);
      // Exclude "." and ".." directories
      $files = array_diff($files, array('.', '..'));
      // Output the file list
      foreach ($files as $file) {
        $extension = pathinfo($file, PATHINFO_EXTENSION);
        // Check if the extension is ".gpg"
        if ($extension === 'gpg') {
        // Get the file's timestamp (last modified time)
        $timestamp = date("Y-m-d H:i:s", filemtime($directory . $file));
        $content[] = "<a href='/download?type=esim&filename=" . urlencode($file) . "'>" . urlencode($file) . "</a> - $timestamp<br>";
        }
      }
      */

      $response->addCommand(new OpenModalDialogCommand($title, implode(" ",$content), $options));
      return $response;
  }
}
