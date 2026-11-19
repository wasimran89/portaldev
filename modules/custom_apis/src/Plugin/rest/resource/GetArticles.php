<?php

namespace Drupal\custom_apis\Plugin\rest\resource;

use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Drupal\node\Entity\Node;
use Drupal\taxonomy\Entity\Term;
use Drupal\Core\Database\Database;
use Drupal\Core\Url;
use Drupal\Core\Link;

use Symfony\Component\HttpFoundation\Response;

/**
 * Provides a Demo Resource
 *
 * @RestResource(
 *   id = "articles",
 *   label = @Translation("Articles listing"),
 *   uri_paths = {
 *     "canonical" = "/get/articles",
 *     "create" = "add/articles"
 *   }
 * )
 */
class GetArticles extends ResourceBase
{
  /**
   * Responds to entity GET requests.
   * @return \Drupal\rest\ResourceResponse
   */
  public function get()
  {
    echo "Test";
    exit;
    try {
      $nids = \Drupal::entityQuery('node')->condition('type', 'article')->execute();
      $nodes =  \Drupal\node\Entity\Node::loadMultiple($nids);
      $response = $this->processNodes($nodes);
    return new ResourceResponse($response);
    } catch (EntityStorageException $e) {
      \Drupal::logger('custom-rest')->error($e->getMessage());
    }
  }
  /**
   * Get articles
   */
  private function processNodes($nodes)
  {
    $output = [];
    foreach ($nodes as $key => $node) {
      $output[$key]['title'] = $node->get('title')->getValue();
      $output[$key]['node_id'] = $node->get('nid')->getValue();
      $output[$key]['description'] = $node->get('body')->getValue();
    }
    return $output;
  }

  /**
   * Post api
   */
 public function post($data){
   try {
      //chu
    echo "post";
    exit;
      if (isset($data['iccid'])) {             
            $iccid = $data['iccid'];
            $user = \Drupal::currentUser();
            $user_display_name = $user->getDisplayName();
            \Drupal\Core\Database\Database::setActiveConnection();
            $connuser = \Drupal\Core\Database\Database::getConnection('default', 'third');
            $queryuser = $connuser->query("select sim_partner_id from drupal_user where drupal_user = 'Test'");
            $resultuser = $queryuser->fetchAll();
            //
            \Drupal\Core\Database\Database::setActiveConnection();
            $conn = \Drupal\Core\Database\Database::getConnection('default', 'third');
            $query = $conn->query("SELECT prof_iccid FROM esim_10t_status_vw where partner_id= ".$resultuser[0]->sim_partner_id." and prof_iccid='".$iccid."'");
            $result = $query->fetchAll();
            //if iccid found in table then continue
            if($result[0]->prof_iccid){
              //wasim
              //$iccid = "89105600000001746275";
              $curl = curl_init();
              $certificate = "/var/www/html/usim-webportal/10T_API_Sample/10T_API_Sample_Prod/ca-prod.pem";
              $key = "/var/www/html/usim-webportal/10T_API_Sample/10T_API_Sample_Prod/client.key";
              $cert = "/var/www/html/usim-webportal/10T_API_Sample/10T_API_Sample_Prod/client.crt";

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
              $result = json_decode($response);

              //getprofilehistory
              $curl = curl_init();
              if ($result->token) {
                curl_setopt_array($curl, array(
                  CURLOPT_URL => 'https://es2plus-prod.10ttech.com/gsma/rsp2/es2plus/releaseProfile',
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
                $dataoutput = json_decode($response, true);
              }              
            } else { //if iccid not found in table
             return new ResourceResponse('No '. $data['iccid'].' Found!!!');
            }            
      }

     return new ResourceResponse($dataoutput);
   } catch (EntityStorageException $e) {
     \Drupal::logger('custom-rest')->error($e->getMessage());
   }
 }
  /**
   * Patch api
   */
  public function patch($data){
    try {

      $term = Term::load($data['tid']);
      $term->setName($data['name']);
      $term->field_custom_title->setValue($data['sub_title']);

      $term->save();

      return new ResourceResponse('Term updated successfully');
    } catch (EntityStorageException $e) {
      \Drupal::logger('custom-rest')->error($e->getMessage());
    }
  }
}
