<?php

namespace Drupal\custom_apis\Plugin\rest\resource;

use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Drupal\Core\Database\Database;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * Provides a REST API for managing IMSI Management data.
 *
 * @RestResource(
 *   id = "imsi_management_rest_resource",
 *   label = @Translation("IMSI Management Rest Resource"),
 *   uri_paths = {
 *     "canonical" = "/api/imsi-management/{id}",
 *     "create" = "/api/imsi-management"
 *   }
 * )
 */
class imsiManagement extends ResourceBase {

  /**
   * GET method for retrieving custom data by ID.
   */
  public function get($id = NULL) {
    $connection = Database::getConnection('default', 'third');
    $query = $connection->select('imsi_range', 'ir')
      ->fields('ir')
      ->condition('imsi_range_id', $id);

    $result = $query->execute()->fetchAll(\PDO::FETCH_ASSOC);

    // Prepare the header with the execution status
    $header = [
        "header" => [
            "functionExecutionStatus" => [
                "status" => "Executed-Success"
            ]
        ]
    ];

    // Combine the header with the data
    $responseData = [
        "header" => $header["header"],
        "list" => $result
    ];

    // Create a ResourceResponse with the new structure
    $response = new ResourceResponse($responseData, Response::HTTP_OK);

    // Disable caching for the response
    $response->getCacheableMetadata()->setCacheMaxAge(0);

    // Return the response
    return $response;
  }

  /**
   * POST method for creating new custom data.
   */
  public function post($data) {
    $this->validateData($data);  // Validate data before inserting

    $connection = Database::getConnection('default', 'third');
    $user = \Drupal\user\Entity\User::load(\Drupal::currentUser()->id());
    $user_display_name = $user->getEmail();
    $queryuser = $connection->query("select sim_partner_id from drupal_user where drupal_user = '".$user_display_name."'");
    $resultuser = $queryuser->fetchAll();

    $ibn = $data['ibn'] ? "'".$data['ibn']."'" : 'NULL';
    $notes = $data['notes'] ? "'".$data['notes']."'" : 'NULL';
    try {
      $result = $connection->query("SELECT * FROM insert_update_imsi_range(null, 'insert', ".$resultuser[0]->sim_partner_id.", '".$data['esim_usim']."','".$data['imsi_range_name']."','".$data['mcc']."', '".$data['mnc']."','".$data['imsi_start']."', '".$data['imsi_count']."',".$ibn.", ".$notes.")");

      // Prepare the header with the execution status
      $header = [
          "header" => [
              "functionExecutionStatus" => [
                  "status" => "Executed-Success"
              ]
          ]
      ];

      // Combine the header with the data
      $responseData = [
          "header" => $header["header"],
          "message" => 'Data inserted successfully.'
      ];

      // Create a ResourceResponse with the new structure
      $response = new ResourceResponse($responseData, Response::HTTP_OK);

      // Disable caching for the response
      $response->getCacheableMetadata()->setCacheMaxAge(0);

      // Return the response
      return $response;
    } catch (\Exception $e) {
        \Drupal::logger('custom_apis')->error($e->getMessage());
        $responseresult = '{ "header": { "functionExecutionStatus": { "status": "Failed" } },"message": "'.$e->getMessage().'" }';
        $dataoutput = json_decode($responseresult, true);
        return (new ResourceResponse($dataoutput, Response::HTTP_FORBIDDEN))->addCacheableDependency(['max-age' => 0]);
    }
  }

  /**
   * PUT method for updating custom data.
   */
  public function put($id) {
    try {
      $request = \Drupal::request();
      $connection = Database::getConnection('default', 'third');
      $content = $request->getContent(); // Get the raw JSON content
      $data = json_decode($content, TRUE); // Convert JSON input to an associative array

      $user = \Drupal\user\Entity\User::load(\Drupal::currentUser()->id());
      $user_display_name = $user->getEmail();
      $queryuser = $connection->query("select sim_partner_id from drupal_user where drupal_user = '".$user_display_name."'");
      $resultuser = $queryuser->fetchAll();


      $this->validateData($data);  // Validate data before updating
      
      $ibn = $data['ibn'] ? "'".$data['ibn']."'" : 'NULL';
      $notes = $data['notes'] ? "'".$data['notes']."'" : 'NULL';
    
      $result = $connection->query("SELECT * FROM insert_update_imsi_range($id, 'update', ".$resultuser[0]->sim_partner_id.", '".$data['esim_usim']."','".$data['imsi_range_name']."','".$data['mcc']."', '".$data['mnc']."','".$data['imsi_start']."', '".$data['imsi_count']."',".$ibn.", ".$notes.")");

      // Prepare the header with the execution status
      $header = [
          "header" => [
              "functionExecutionStatus" => [
                  "status" => "Executed-Success"
              ]
          ]
      ];

      // Combine the header with the data
      $responseData = [
          "header" => $header["header"],
          "message" => 'Data updated successfully.'
      ];

      // Create a ResourceResponse with the new structure
      $response = new ResourceResponse($responseData, Response::HTTP_OK);

      // Disable caching for the response
      $response->getCacheableMetadata()->setCacheMaxAge(0);

      // Return the response
      return $response;
    } catch (\Exception $e) {
      \Drupal::logger('custom_apis')->error($e->getMessage());
      $responseresult = '{ "header": { "functionExecutionStatus": { "status": "Failed" } },"message": "'.$e->getMessage().'" }';
      $dataoutput = json_decode($responseresult, true);
      return (new ResourceResponse($dataoutput, Response::HTTP_FORBIDDEN))->addCacheableDependency(['max-age' => 0]);
    }
  }

  /**
   * DELETE method for removing custom data by ID.
   */
  public function delete($id) {
    $connection = Database::getConnection('default', 'third');
    
    try {
        // Perform the delete query
        $result = $connection->delete('imsi_range')
            ->condition('imsi_range_id', $id)
            ->execute();
        if ($result) {
          $responseresult = '{ "header": { "functionExecutionStatus": { "status": "Executed-Success" } },"message": "Data deleted successfully." }';
          print_r($responseresult);
          exit;
        }
        else {
          $responseresult = '{ "header": { "functionExecutionStatus": { "status": "Failed" } },"message": "id not found." }';
          print_r($responseresult);
          exit;
        }
    } catch (\Exception $e) {
        // Log error message for debugging (optional)
        \Drupal::logger('custom_api')->error($e->getMessage());

        // Return error response in JSON format
        return new ResourceResponse(['error' => 'Failed to delete entry.'], 500, ['Content-Type' => 'application/json']);
    }
  }


  /**
   * Validates the input data.
   */
  protected function validateData(array $data) {
    $errors = [];

    // Validate 'imsi_range_name' (example: required)
    if (empty($data['imsi_range_name'])) {
      $errors[] = 'The IMSI range name is required.';
    }

    // Validate 'mcc' (example: must be numeric and length 3)
    if (empty($data['mcc']) || !is_numeric($data['mcc']) || strlen($data['mcc']) != 3) {
      $errors[] = 'The MCC must be a numeric value with a length of 3.';
    }

    // Validate 'mnc' (example: must be numeric and length 2 or 3)
    if (empty($data['mnc']) || !is_numeric($data['mnc']) || (strlen($data['mnc']) < 2 || strlen($data['mnc']) > 3)) {
      $errors[] = 'The MNC must be a numeric value with a length of 2 or 3.';
    }

    if (empty($data['ibn']) || !is_numeric($data['ibn']) || (strlen($data['ibn']) !=4)) {
      $errors[] = 'The ibn must be a numeric value with a length of 4.';
    }

    // Validate other fields similarly...

    // If there are errors, throw an exception
    if (!empty($errors)) {
      throw new UnprocessableEntityHttpException(implode(' ', $errors));
    }
  }
}
