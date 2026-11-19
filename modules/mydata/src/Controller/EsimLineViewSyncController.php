<?php
namespace Drupal\mydata\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\Response;
use Drupal\encrypt\Entity\EncryptionProfile;
use Drupal\user\Entity\User;
use Drupal\Core\Database\Connection;
/**
 * Class EsimLineViewSyncController.
 *
 * @package Drupal\mydata\Controller
 */
class EsimLineViewSyncController extends ControllerBase {
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
    $query = $this->externalDatabase->select('esim_10t_status_vw', 'esv')
      ->fields('esv', ['prov_iccid']);
    $results = $query->execute()->fetchAll();
    $certificate = "/var/www/html/usim-webportal/10T_API_Sample/10T_API_Sample_Prod/ca-prod.pem";
    $key = "/var/www/html/usim-webportal/10T_API_Sample/10T_API_Sample_Prod/client.key";
    $cert = "/var/www/html/usim-webportal/10T_API_Sample/10T_API_Sample_Prod/client.crt";

    $curl = curl_init();
    curl_setopt_array($curl, array(
              CURLOPT_URL => 'https://es2plus-prod.10ttech.com/gsma/rsp2/es2plus/user/signin',
              CURLOPT_RETURNTRANSFER => true,
              CURLOPT_ENCODING => '',
              CURLOPT_MAXREDIRS => 10,
              CURLOPT_TIMEOUT => 0,
              CURLOPT_FOLLOWLOCATION => true,
              CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
              CURLOPT_CUSTOMREQUEST => 'POST',
              CURLOPT_POSTFIELDS => 'email=JCIUS_CBRS@jcius.com&password=Jc!uS09876',
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
    curl_close($curl);

    if (isset($error_msg)) {
      print_r($error_msg);
    }
    $resulttoken = json_decode($response);
    if ($resulttoken->token) {
      $counter = 0;
      foreach ($results as $result) {
        $iccid = $result->prov_iccid;
        //$iccid ='89105631504000001049';
        if ($iccid) {
          $curl = curl_init();
          curl_setopt_array($curl, array(
                CURLOPT_URL => 'https://es2plus-prod.10ttech.com/gsma/rsp2/es2plus/getProfileStatus',
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
                  "Authorization: Bearer $resulttoken->token",
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
          $data = json_decode($response, true);
          if ($data['header']['functionExecutionStatus']['status']== 'Executed-Success') {
            // Check current state of the iccid
            $query_check = $this->externalDatabase->query("SELECT state FROM esim_profiles WHERE iccid = '".$iccid."'");
            $output = $query_check->fetchAll();
            if ($output) {
                $current_state = $output[0]->state;
                
                // Check if the current state is not already 'RELEASED'
                if ($current_state != $data['profileStatusList'][0]['state']) {
                    // Execute the update query
                    $query_update = $this->externalDatabase->query("UPDATE esim_profiles SET state = '".$data['profileStatusList'][0]['state']."' WHERE iccid = '".$iccid."'");
                    
                    if ($query_update) {
                        // Update successful
                        echo "<br/>ICCID ".$iccid." state updated to ".$data['profileStatusList'][0]['state'];
                    } else {
                        // Update failed
                        echo "<br/>Failed to update ICCID ".$iccid." state.";
                    }
                } else {
                    // ICCID is already in updated state
                    echo "<br/>ICCID ".$iccid." is already in ".$data['profileStatusList'][0]['state']." state.";
                }
            } else {
                // ICCID not found in the database
                echo "<br/>ICCID ".$iccid." not found in the database.";
            }
          }          
        }
        if ($counter >= 20) {
          exit;
        }//if
        $counter++;
      } //foreach
    }    

    $item = "Success";
    return ['#markup' => $item];
  } //display function
} //EsimLineViewSyncController