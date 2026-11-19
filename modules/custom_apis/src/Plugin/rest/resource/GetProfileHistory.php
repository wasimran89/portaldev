<?php

namespace Drupal\custom_apis\Plugin\rest\resource;

use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Drupal\Core\Database\Database;
use Symfony\Component\HttpFoundation\Response;

/**
 * Provides a GetProfileHistory Resource
 *
 * @RestResource(
 *   id = "getprofilestatus",
 *   label = @Translation("Get Profile History"),
 *   uri_paths = {
 *     "create" = "api/profilehistory"
 *   }
 * )
 */
class GetProfileHistory extends ResourceBase
{
  /**
   * Post api
   */
  public function post($data) {
    try {
      // *******************************
      // DISABLE PAGE CACHE (KILL SWITCH)
      // *******************************
      \Drupal::service('page_cache_kill_switch')->trigger();

      //GetProfileHistory
      if (isset($data['iccid'])) {           
        $iccid = $data['iccid'];

        //apikey start
        $request = \Drupal::request();
        $apiKey = trim((string) $request->headers->get('x-api-key', ''));

        $label = NULL;

        // API Key lookup
        if ($apiKey !== '') {
          $connection = \Drupal::database();
          $query = $connection->select('groups_field_data', 'g');
          $query->fields('g', ['id', 'label']);
          $query->join('group__field_api_key', 'k', 'g.id = k.entity_id');
          $query->condition('k.field_api_key_value', $apiKey);
          $groupRows = $query->execute()->fetchAll();

          if (!empty($groupRows)) {
            $label = (string) $groupRows[0]->label;
          }

          // **********************
          // INVALID API KEY BLOCK
          // **********************
          if (empty($label)) {
            $error = [
              "header" => [
                "functionExecutionStatus" => ["status" => "Failed"]
              ],
              "message" => "Authentication required"
            ];

            $response = new ResourceResponse($error, Response::HTTP_UNAUTHORIZED);
            $response->getCacheableMetadata()->setCacheMaxAge(0);
            return $response;
          }
        }

        //api key end

        $conn = \Drupal\Core\Database\Database::getConnection('default', 'third');
        $user = \Drupal::currentUser();
        $uid = $user->id();
        $roles = $user->getRoles();
        $result = []; // Initialize an empty array for the result.
        if (in_array('administrator', $roles)) {
          //Show all for administrator user
          $query = $conn->select('esim_10t_status_vw', 'esv')
                        ->fields('esv', ['prof_iccid'])
                        ->condition('esv.prof_iccid', $iccid);
          $result = $query->execute()->fetchAll();
        } else {
          //Non admin filter data as per partner
          if (!empty($label)) { 
            $sim_partner_id = $conn->select('partner', 'p')
            ->fields('p', ['partner_id'])
            ->condition('p.partner_name', $label)
            ->execute()
            ->fetchField();
          } else {
            $user_display_name = $user->getEmail();
            if (empty($user_display_name)) {
              $error = [
                "header" => [
                  "functionExecutionStatus" => ["status" => "Failed"]
                ],
                "message" => "Authentication required"
              ];

              $response = new ResourceResponse($error, Response::HTTP_UNAUTHORIZED);
              $response->getCacheableMetadata()->setCacheMaxAge(0);
              return $response;
            }
            $sim_partner_id = $conn->select('drupal_user', 'du')
                          ->fields('du', ['sim_partner_id'])
                          ->condition('du.drupal_user', $user_display_name)
                          ->execute()
                          ->fetchField();
          }

                      
          if ($sim_partner_id) {
              $query = $conn->select('esim_10t_status_vw', 'esv')
                                ->fields('esv', ['prof_iccid'])
                                ->condition('esv.prof_iccid', $iccid);

              //child partner start
              $all_related_partners = [];
              $this->fetchRelatedPartners($sim_partner_id, $all_related_partners);
              $all_related_partners[] = $sim_partner_id;
              $query->condition('esv.partner_id', $all_related_partners, 'IN');        
              //child partner end

              // Execute the query and fetch all the results.
              $result = $query->execute()->fetchAll();
          }
        } //non admin end

        //if iccid found in table then continue
        if ($result[0]->prof_iccid) {
          $curl = curl_init();
          //Direct file path
          //$certificate = "/var/www/html/usim-webportal/10T_API_Sample/10T_API_Sample_Prod/ca-prod.pem";
          //$key = "/var/www/html/usim-webportal/10T_API_Sample/10T_API_Sample_Prod/client.key";
          //$cert = "/var/www/html/usim-webportal/10T_API_Sample/10T_API_Sample_Prod/client.crt";
          //$endpoint = "https://es2plus-prod.10ttech.com/gsma/rsp2/es2plus/user/signin";

          //Upload file path
          //configuration
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
          \Drupal::database()->insert('10t_usage')->fields(['iccid', 'path', 'activity', 'uid', 'timestamp'])->values([$iccid, $path, $activity, $uid, REQUEST_TIME])->execute();
          //signin end

          $res = json_decode($response);

          //getprofilehistory
          $curl = curl_init();
          if ($res->token) {
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
                  "functionRequesterIdentifier": "JCIUS",
                  "functionCallIdentifier": "JCIUS"
              },
              "iccid": "'.$iccid.'"
              }',
              CURLOPT_HTTPHEADER => array(
                "Authorization: Bearer $res->token",
                'Content-Type: application/json'
              ),
            ));

            $responseresult = curl_exec($curl);
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
            \Drupal::database()->insert('10t_usage')->fields(['iccid', 'path', 'activity', 'uid', 'timestamp'])->values([$iccid, $path, $activity, $uid, REQUEST_TIME])->execute();
            //getProfileHistory end

            $dataoutput = json_decode($responseresult, true);

            // Disable caching for the response
            $response = new ResourceResponse($dataoutput, Response::HTTP_OK);
            $response->addCacheableDependency(['max-age' => 0]);

            return $response;
          } else {
            $responseresult = '{ "header": { "functionExecutionStatus": { "status": "Failed" } }, "iccid": "'.$iccid.'", "message": "Token is missing." }';            
            $dataoutput = json_decode($responseresult, true);
            return (new ResourceResponse($dataoutput, Response::HTTP_FORBIDDEN))->addCacheableDependency(['max-age' => 0]);
          }          
        } else { //if iccid not found in table          
          $responseresult = '{ "header": { "functionExecutionStatus": { "status": "Failed" } }, "iccid": "'.$iccid.'", "message": "Invalid / Incorrect ICCID" }';
          $dataoutput = json_decode($responseresult, true);
          return (new ResourceResponse($dataoutput, Response::HTTP_FORBIDDEN))->addCacheableDependency(['max-age' => 0]);
        }
            
      } else {
        $iccid = $data['iccid'];        
        $responseresult = '{ "header": { "functionExecutionStatus": { "status": "Failed" } }, "iccid": "'.$iccid.'", "message": "ICCID parameter is missing." }';
        $dataoutput = json_decode($responseresult, true);
        return (new ResourceResponse($dataoutput, Response::HTTP_FORBIDDEN))->addCacheableDependency(['max-age' => 0]);
      }
      //GetProfileHistory

     return new ResourceResponse($dataoutput);
    } catch (EntityStorageException $e) {
      $iccid = $data['iccid'];
      \Drupal::logger('custom-rest')->error($e->getMessage());      
      $responseresult = '{ "header": { "functionExecutionStatus": { "status": "Failed" } }, "iccid": "'.$iccid.'", "message": "'.$e->getMessage().'" }';
      $dataoutput = json_decode($responseresult, true);
      return (new ResourceResponse($dataoutput, Response::HTTP_FORBIDDEN))->addCacheableDependency(['max-age' => 0]);
    }
  }

  /**
   * Fetch related partners recursively.
   *
   * @param int $parentId
   *   The parent partner ID.
   * @param array $all_related_partners
   *   The array to store all related partner IDs.
   */
  public function fetchRelatedPartners($parentId, &$all_related_partners) {
    $conn = \Drupal\Core\Database\Database::getConnection('default', 'third');
    // Start building the query on the external database connection
    $query = $conn->select('partner', 'p');

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
