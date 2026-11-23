<?php

namespace Drupal\custom_apis\Plugin\rest\resource;

use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Drupal\Core\Database\Database;
use Drupal\Core\Entity\EntityStorageException;
use Symfony\Component\HttpFoundation\Response;
use Drupal\group\Entity\Group;
/**
 * Provides a Demo Resource
 *
 * @RestResource(
 *   id = "release",
 *   label = @Translation("Release"),
 *   uri_paths = {
 *     "create" = "api/release"
 *   }
 * )
 */
class Release extends ResourceBase
{
  /**
   * Post api
   */
  public function post($data){
    try {
      // *******************************
      // DISABLE PAGE CACHE (KILL SWITCH)
      // *******************************
      \Drupal::service('page_cache_kill_switch')->trigger();

      if (isset($data['iccid'])) {
        //Get icccid through postman body
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
            $id = $groupRows[0]->id;
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
        $user = \Drupal\user\Entity\User::load(\Drupal::currentUser()->id());
        $uid = $user->id();
        $roles = $user->getRoles();

        if ($user || $id) {
          if (!empty($id)) {
            $group_id = $id;
          } else {
            $cgroup = $user->field_group_type->getValue(); //current user group load
            $group_id = $cgroup[0]['target_id']; //current user group id
          }
          
          $group = Group::load($group_id);

          if ($group) {
            $field_credit_reset = $group->get('field_credit_reset')->value;
            $field_credit_release = $group->get('field_credit_release')->value;
          } else {
            //$data = '{ "header": { "functionExecutionStatus": { "status": "Executed-Success" } }, "iccid": "'.$data['iccid'].'", "message": "Group not found." }';
            //$dataoutput = json_decode($data, true);
            //return new ResourceResponse($dataoutput);
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

        if ($field_credit_release >= 1) {          
          if (in_array('administrator', $roles)) {
            //Show all for administrator user
            $querya = $conn->select('esim_10t_status_vw', 'esv')
                          ->fields('esv', ['prof_iccid'])
                          ->condition('esv.prof_iccid', $iccid);
            $result = $querya->execute()->fetchAll();
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
          }
          //if iccid found in table then continue
          if ($result[0]->prof_iccid) {
            $curl = curl_init();
            //$certificate = "/var/www/html/usim-webportal/10T_API_Sample/10T_API_Sample_Prod/ca-prod.pem";
            //$key = "/var/www/html/usim-webportal/10T_API_Sample/10T_API_Sample_Prod/client.key";
            //$cert = "/var/www/html/usim-webportal/10T_API_Sample/10T_API_Sample_Prod/client.crt";

            //Configuration
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

            $res = json_decode($response);
            if ($res->token) {
              //getProfileStatus start
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
                  "Authorization: Bearer $res->token",
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
              //getProfileStatus end
              if ($data['profileStatusList'][0]['state'] == 'AVAILABLE') {
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

                  $downloadOrderResponse = json_decode($response, true);
                  $download_status = $downloadOrderResponse['header']['functionExecutionStatus']['status'];
                } catch (\Exception $e) {
                    $downloadOrderResponse = $e->getMessage();
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
                      "functionRequesterIdentifier": "JCIUS",
                      "functionCallIdentifier": "JCIUS"
                  },
                  "iccid": "'.$iccid.'",
                  "releaseFlag": true
                  }',
                    CURLOPT_HTTPHEADER => array(
                      "Authorization: Bearer $res->token",
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

                  $confirmOrderResponse = json_decode($response, true);
                  $confirm_status = $confirmOrderResponse['header']['functionExecutionStatus']['status'];

                } catch (\Exception $e) {
                    $confirmOrderResponse = $e->getMessage();
                }
                //confirmOrder end      

                //Credit logic start
                if(strtolower($download_status) == strtolower('Executed-Success') && strtolower($confirm_status) == strtolower('Executed-Success')) {
                  // First, check the current state of the iccid
                  $select_query = $conn->query("SELECT state FROM esim_profiles WHERE iccid = '".$iccid."'");
                  $current_state = $select_query->fetchField();

                  if ($current_state !== 'RELEASED') {
                    // Execute the update query
                    $query = $conn->query("UPDATE esim_profiles SET state = 'RELEASED' WHERE iccid = '".$iccid."'");
                    $group_reset_value = $group->get('field_credit_reset')->value;
                    $field_credit_reset_value = $group_reset_value - 0;        

                    $group_release_value = $group->get('field_credit_release')->value;
                    $field_credit_release_value = $group_release_value - 1;

                    $group->set('field_credit_reset', $field_credit_reset_value);
                    $group->set('field_credit_release', $field_credit_release_value);
                    $group->save();
                    $message = 'Executed-Success';
                    
                    //credit allotment history start
                    $schema = \Drupal::database()->schema();
                    if ($schema->tableExists('credit_allotment_history')) {
                      $querycah = \Drupal::database()->insert('credit_allotment_history')
                      ->fields([
                      'from_partner_id',
                      'to_partner_id',
                      'operation_type',
                      'credit_amount',
                      'remarks',
                      'uid',
                      'timestamp'
                      ])
                      ->values(array(
                      $group_id,
                      $group_id,
                      'Release credit used via API call for ICCID:'.$iccid,
                      1,
                      'RELEASE CREDIT USED',
                      $user->id(),
                      \Drupal::time()->getRequestTime()
                      ))
                      ->execute();
                    }
                    //credit allotment history end
                  } else {
                    $message = $iccid . ' is already in RELEASED state.';
                  }
                } else {
                  $message = 'Failed'; 
                }
                /*
                $items = [
                  'iccid' => $iccid,
                  'message' => $message,
                  //'credit_reset' => $group->get('field_credit_reset')->value,
                  'credit_release' => $group->get('field_credit_release')->value,
                  'downloadOrder' => $downloadOrderResponse,
                  'confirmOrder' => $confirmOrderResponse,              
                ];
              
                return new ResourceResponse($items);
                */
                $data = '{ "header": { "functionExecutionStatus": { "status": "Executed-Success" } }, "iccid": "'.$iccid.'", "message": "'.$message.'", "credit_release":"'.$group->get('field_credit_release')->value.'", "downloadOrder":"'.$downloadOrderResponse.'", "confirmOrder":"'.$confirmOrderResponse.'" }';
                $dataoutput = json_decode($data, true);
                return new ResourceResponse($dataoutput);
                //Credit logic end
              } else {
                $data = '{ "header": { "functionExecutionStatus": { "status": "Executed-Success" } }, "iccid": "'.$iccid.'", "message": "STATE value must be AVAILABLE in order to perform the RELEASE operation." }';
                $dataoutput = json_decode($data, true);
                return new ResourceResponse($dataoutput);
              }
            } else {
              $data = '{ "header": { "functionExecutionStatus": { "status": "Executed-Success" } }, "iccid": "'.$iccid.'", "message": "Token not received." }';
              $dataoutput = json_decode($data, true);
              return new ResourceResponse($dataoutput);
            }
          } else {
            $data = '{ "header": { "functionExecutionStatus": { "status": "Failed" } }, "iccid": "'.$iccid.'", "message": "Invalid / Incorrect ICCID" }';
            $dataoutput = json_decode($data, true);
            return (new ResourceResponse($dataoutput, Response::HTTP_FORBIDDEN))->addCacheableDependency(['max-age' => 0]);
          }
        } else {
          $data = '{ "header": { "functionExecutionStatus": { "status": "Executed-Success" } }, "iccid": "'.$iccid.'", "message": "Low credit: Please purchase credit from the place order page." }';
          $dataoutput = json_decode($data, true);
          return new ResourceResponse($dataoutput);
        }            
      } else {
        //Get icccid through postman body
        $iccid = $data['iccid'];
        $data = '{ "header": { "functionExecutionStatus": { "status": "Executed-Success" } }, "iccid": "'.$iccid.'", "message": "ICCID parameter is missing." }';
        $dataoutput = json_decode($data, true);
        return new ResourceResponse($dataoutput);
      }

     //return new ResourceResponse($dataoutput);
    } catch (EntityStorageException $e) {
      \Drupal::logger('custom-rest')->error($e->getMessage());
      $data = '{ "header": { "functionExecutionStatus": { "status": "Failed" } }, "iccid": "'.$data['iccid'].'", "message": "'.$e->getMessage().'" }';
      $dataoutput = json_decode($data, true);
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
