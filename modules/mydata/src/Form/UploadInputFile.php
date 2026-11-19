<?php
namespace Drupal\mydata\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Database\Database;
use Drupal\Core\Database\Connection;

/**
 * Class MydataForm.
 *
 * @package Drupal\mydata\Form
 */
class UploadInputFile extends FormBase {
  /**
   * The external database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $externaldatabase;

  /**
   * Constructs a new YourController object.
   *
   * @param \Drupal\Core\Database\Connection $externalconnection
   *   The external database connection.
   */
  public function __construct(Connection $externalconnection) {
    $this->externaldatabase = $externalconnection;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(\Symfony\Component\DependencyInjection\ContainerInterface $container) {
    $externalconnection = $container->get('mydata.external_database');
    return new static($externalconnection);
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'upload_input_file';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['#prefix'] = '<h1>'.$this->t('Upload Input File').'</h1><div id="modal_example_form">';
    $form['#suffix'] = '</div>';    
    $form['type'] = array(
      '#title' => t('Type'),
      '#type' => 'select',
      '#required' => TRUE,
      '#options' => array(t('ESIM'), t('USIM')),
            '#attributes' => [
        'class' => [
          'custom-input-type',
        ],
      ]
    );

    $query = $this->externaldatabase->query("select partner_name, partner_id from partner order by partner_name");
    $result = $query->fetchAll();

    $partner = [];
    foreach ($result as $key => $data) {
      $partner[$data->partner_id] = $data->partner_name;
    }

    $form['partner'] = array(
      '#title' => t('Select Partner'),
      '#type' => 'select',
      '#required' => TRUE,
      '#options' => $partner,
    );

    $form['upload_file'] = [
      '#type' => 'managed_file',
      '#title' => $this->t('Upload Input File'),
      '#required' => TRUE,
      '#upload_location' => 'public://content/excel_files/',
      '#upload_validators' => [
        'file_validate_extensions' => $this->getAllowedFileExtensions(),
      ],
    ];    

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => t('Save'),
      '#attributes' => [
        'class' => [
          'custom-input-element',
        ],
      ]
    ];

    // Get the data:
    if (isset($_SESSION['mydata']['input_file_id'])) {
        $somedata = $_SESSION['mydata']['input_file_id'];
    } else {
        $somedata = 0; // Or handle the missing data case appropriately.
    }

    if ($somedata != 0) {
      $query = $this->externaldatabase->query("select * from input_element where input_file_id = '".$somedata."' order by position");
      $result = $query->fetchAll();
      $rows = array();
      foreach ($result as $row => $content) {        
        $rows[] = array(
          'data' => array($content->input_file_id, $content->input_element, $content->position, $content->static, $content->static_value, $content->date_created));
        //true and flase
        if ($content->static == 1) {
          $rows[$row]['data']['3'] = 'TRUE'; 
        } else {
          $rows[$row]['data']['3'] = 'FALSE'; 
        }
      }

      $header_column = ['input_file_id','input_element','position','static','static_value','date_created'];

      $form['table'] = [
        '#type' => 'table',
        '#header' => $header_column,
        '#rows' => $rows,
        '#empty' => t('No data found'),
        '#cache' => array('max-age' => 0),
        '#attributes' => array('class' => array('custom-table'))
      ];

      // Finally add the pager.
      $form['pager'] = [
        '#type' => 'pager'
      ];      
      $form['#cache'] = ['max-age' => 0];
      $form['#attached']['library'][] = 'core/drupal.dialog.ajax';      
    }
    $_SESSION['mydata']['input_file_id'] = 0;
    return $form;    
  }

  /**
   * @return array
   */
  private function getAllowedFileExtensions() {
    return array('jpg jpeg gif png txt doc docx zip xls xlsx pdf ppt pps odt ods odp csv inp dat');
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $value = $form_state->cleanValues()->getValues();
    $file = \Drupal::entityTypeManager()->getStorage('file')
    ->load($form_state->getValue('upload_file')[0]);
    $full_path = $file->get('uri')->value;
    $file_name = basename($full_path);
    $inputFileName = \Drupal::service('file_system')->realpath('public://content/excel_files/' . $file_name);
    $myfile = fopen($inputFileName, "r");
    $myfilecheck = fopen($inputFileName, "r");
    $mynewfile = fopen($inputFileName, "r");
    $mynewfilecheck = fopen($inputFileName, "r");
    $partner_id= $value['partner'];
    $type= $value['type'];
    if ($type == 0) {
      //if upload file HEADER DESCRIPTION found then it would be esim file else give error
      while (!feof($mynewfilecheck)) {
        $esim_flag = 0;
        $line = fgets($mynewfilecheck);
        $needle   = 'HEADER DESCRIPTION';
        if (strpos($line, $needle) !== FALSE) {
          $esim_flag = 1;
          break;
        }
      }

      //esim_flag start
      if ($esim_flag == 1) {
        $type= 'ESIM';
        $query = $this->externaldatabase->query("select * from nextval('input_file_input_file_id_seq')");
        $result = $query->fetchAll();
        $input_file_id = $result[0]->nextval;
        //Save the data in session:
        $_SESSION['mydata']['input_file_id'] = $input_file_id;
        //insert query esim
        try {
          $query2 = $this->externaldatabase->query("INSERT INTO input_file(input_file_id, partner_id, input_file_name, rpad_offset, input_file_type, environment)VALUES ('".$input_file_id."', '".$partner_id."', '".$file_name."', 14, '".$type."', 'PROD')");
        } catch (\Exception $e) {
          \Drupal::messenger()->addWarning($e->getMessage());
        }     

        //$data = [];
        $var_position = 1;
        while (!feof($myfile)) {
            $line = fgets($myfile);
            //Read file till var_In then break the query
            $needle   = 'var_In:';
            if (strpos($line, $needle) !== FALSE) {
              break;
            }            
            $lineA = explode(":", $line);
            if (($lineA[1]) && ($lineA[0] != 'var_In')) {
              try {     
                  $column1 = str_replace(' ', '_', trim($lineA[0])); 
                  $column_new = str_replace('.', '_', $column1);
                  //input_element_type
                  $querycheck = $this->externaldatabase->query("SELECT count(*) from input_element_type where input_element = '".$column_new."'");
                  $resultcheck = $querycheck->fetchAll();
                  $count = $resultcheck[0]->count;
                  if ($count == 0) {
                    $queryinsert = $this->externaldatabase->query("INSERT INTO input_element_type values ('".$column_new."', 'ESIM', true)");
                  }
                  $query2 = $this->externaldatabase->query("SELECT * FROM insert_input_element('".$input_file_id."', '".$column_new."', '".$var_position."', '".trim($lineA[1])."')");
                  $var_position = $var_position + 1;
              } catch (\Exception $e) {
                \Drupal::messenger()->addWarning($e->getMessage());
              }              
            }

            //FORMAT
            if ((!$lineA[1]) && ($lineA[0] != 'var_In')) {
              try {
              $query2 = $this->externaldatabase->query("INSERT INTO input_element (input_file_id, static, position, input_element, static_value) values (".$input_file_id.", true, ".$var_position.", 'FORMAT', '".trim($lineA[0])."')");
                $var_position = $var_position + 1;
              } catch (\Exception $e) {
                \Drupal::messenger()->addWarning($e->getMessage());
              } 
            }
        } //end while

        //update input_element
        try {
          $string = "update input_element set date_created = '".date('Y-m-d')."' where input_file_id = '".$input_file_id."'";
          $query2 = $this->externaldatabase->query("update input_element set date_created = '".date('Y-m-d')."' where input_file_id = '".$input_file_id."'");
          $result2 = $query2->execute();
        } catch (\Exception $e) {
          \Drupal::messenger()->addWarning($e->getMessage());
        }
        //end update input_element

        //update esim_provision
        try {
          $query2 = $this->externaldatabase->query("update esim_provision set esim_file_name = '".$file_name."' where esim_file_name = 'UPDATE'");
          $result2 = $query2->execute();
          if ($result2) {
             \Drupal::messenger()->addMessage("Successfully Uploaded ESIM Input File ".$file_name);
          }
        } catch (\Exception $e) {
          \Drupal::messenger()->addWarning($e->getMessage());
        }
        //end update esim_provision
      } else {
        \Drupal::messenger()->addError("ESIM Input File ".$file_name." is not the correct format. Input File Upload canceled.");
      } //esim_flag end
    } //end esim///////////////////////////////////
    else {
      if ($myfilecheck) {
        $usim_flag = 0;
        $line = fgets($myfilecheck);
        $needle   = 'T1 ';
        if (strpos($line, $needle) !== FALSE) {
          $usim_flag = 1;
        }
      }
      if ($usim_flag ==1) {
        $type= 'USIM';
        $query = $this->externaldatabase->query("select * from nextval('input_file_input_file_id_seq')");
        $result = $query->fetchAll();
        $input_file_id = $result[0]->nextval;
        // Save the data:
        $_SESSION['mydata']['input_file_id'] = $input_file_id;
        //insert input_file query usim
        try {
          $query2 = $this->externaldatabase->query("INSERT INTO input_file(input_file_id, partner_id, input_file_name, rpad_offset, input_file_type, environment)VALUES ('".$input_file_id."', '".$partner_id."', '".$file_name."', 13, '".$type."', 'PROD')");
        } catch (\Exception $e) {
          \Drupal::messenger()->addWarning($e->getMessage());
        }

        //$data = [];
        $var_position = 1;
        while (!feof($myfile)) {
          $line = fgets($myfile);
            $lineB = explode(" ", $line, 2);
            if ($lineB[1]) {
              //$data[trim($lineB[0])] = trim($lineB[1]); //key value pair
              try {
                $query2 = $this->externaldatabase->query("SELECT * FROM insert_input_element('".$input_file_id."', '".strtoupper(trim($lineB[0]))."', '".$var_position."', '".trim($lineB[1])."')");
                $var_position = $var_position + 1;
              } catch (\Exception $e) {
                \Drupal::messenger()->addWarning($e->getMessage());
              }
            }
        }

        //update input_element
        try {
          $query2 = $this->externaldatabase->query("update input_element set date_created = '".date('Y-m-d')."' where input_file_id = '".$input_file_id."'");
          $result2 = $query2->execute();
        } catch (\Exception $e) {
          \Drupal::messenger()->addWarning($e->getMessage());
        }
        //end update input_element
        //global message for usim
        \Drupal::messenger()->addMessage("Successfully Uploaded USIM Input File ".$file_name);
      } else {
        \Drupal::messenger()->addError("USIM Input File ".$file_name." is not the correct format. Input File Upload canceled.");
      }
    } //end usim ///////////////////////////////////////////////////
    fclose($myfile);    
  }
}
