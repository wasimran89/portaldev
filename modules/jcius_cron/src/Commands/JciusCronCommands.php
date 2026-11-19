<?php
namespace Drupal\jcius_cron\Commands;
use Drush\Commands\DrushCommands;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Database\Connection;
use Drupal\taxonomy\Entity\Term;
use Drupal\node\Entity\Node;
use Drupal\Core\Cache\CacheBackendInterface;
/**
 * A Drush commandfile.
 *
 * In addition to this file, you need a drush.services.yml
 * in root of your module, and a composer.json file that provides the name
 * of the services file to use.
 */
class JciusCronCommands extends DrushCommands
{
    /**
     * The entity type manager.
     *
     * @var \Drupal\Core\Entity\EntityTypeManagerInterface
     */
    protected $entityTypeManager;

    /**
     * The database connection.
     *
     * @var \Drupal\Core\Database\Connection
     */
    protected $connection;

    /**
     * The cache.
     *
     * @var \Drupal\Core\Cache\CacheBackendInterface
     */
    protected $cache;

    /**
     * Constructs a NodeController object.
     *
     * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
     *   The entity type manager.
     * @param \Drupal\Core\Database\Connection $connection
     *   The database connection.
     * @param \Drupal\Core\Cache\CacheBackendInterface $cache
     *   The cache object.
     */
    public function __construct(
        EntityTypeManagerInterface $entity_type_manager,
        Connection $connection,
        CacheBackendInterface $cache
    ) {
        $this->entityTypeManager = $entity_type_manager;
        $this->connection = $connection;
        $this->cache = $cache;
    }
    /**
     * Echos back cron with the argument provided.
     *
     *   Argument provided to the drush command.
     *
     * @command jcius_cron:cron
     * @aliases jcius-cron
     * @options arr An option that takes multiple values.
     * @options msg Whether or not an extra message should be displayed to the user.
     * @usage jcius_cron:cron
     *   Display 'Cron successfully run!!!' message.
     */
    public function cron()
    {
        //Get lineviewid from custom_lineview_operations whose status = 0
        try {
            $query = $this->connection->select(
                "custom_lineview_operations",
                "m"
            );
            $query->fields("m", ["lineviewid"]);
            $query->condition("m.status", "0", "=");
            $result = $query->execute()->fetchAll();
            $key = [];
            foreach ($result as $row) {
                //Get lineviewid from lineview whose status = 0 from above query
                $query2 = $this->connection->select("lineview", "m");
                $query2->fields("m", []);
                $query2->condition("m.lineviewid", $row->lineviewid, "=");
                $result2 = $query2->execute()->fetchAll();
                foreach ($result2 as $value) {
                    $nodes = \Drupal::entityTypeManager()
                        ->getStorage("node")
                        ->loadByProperties([
                            "field_master_id" => $value->masterid, //change
                            "field_imsi" => $value->imsi,
                        ]);
                    if ($nodes) {
                        //Already existing node getting updated
                        foreach ($nodes as $key => $node) {
                            $nid = $key;
                            $node = Node::load($nid);
                            $node->set("title", $value->imsi);
                            //$node->set('type', 'line_detail');
                            //$node->set('langcode', 'en');
                            $node->set("uid", "1");
                            $node->set("status", $value->isactive);
                            $node->set("field_master_id", $value->masterid);
                            $node->set("field_iccid", $value->iccid);
                            $node->set("field_imsi", $value->imsi);
                            //$node->set('field_hss_profile', $value->profile);
                            if ($value->profile == "TAMU_SEC_HIGH") {
                                $profile = "1";
                            }
                            if ($value->profile == "TAMU_SEC_MED") {
                                $profile = "2";
                            }
                            if ($value->profile == "TAMU_TEST_HIGH") {
                                $profile = "3";
                            }
                            if ($value->profile == "TAMU_TEST_LOW") {
                                $profile = "4";
                            }
                            if ($value->profile == "TAMU_TEST_MED") {
                                $profile = "5";
                            }
                            $node->set("field_hss_profile", $profile);
                            $node->set("field_location_id", $value->locationid);
                            $node->set(
                                "field_location_address",
                                $value->locationaddress
                            );
                            $node->set("field_sim_type", $value->simtype); //change
                            $node->set("field_order_id", $value->orderid);
                            $node->set("field_usage_total", $value->usagetotal);
                            $node->set("field_usage_in", $value->usagein);
                            $node->set("field_usage_out", $value->usageout);
                            $node->set(
                                "field_interface_type",
                                $value->interfacetype
                            );
                            $node->set("field_ki", $value->ki);
                            $node->set("field_opc", $value->opc);
                            $node->set("field_is_suspend", $value->issuspend);
                            $node->set("field_is_active", $value->isactive);
                            $node->save();
                            $this->output()->writeln(
                                $value->imsi . ":node updated"
                            );
                        }
                    } else {
                        //for new node create
                        $node = Node::create([
                            "type" => "line_detail",
                            "title" => $value->imsi,
                            "langcode" => "en",
                            "uid" => "1",
                            "status" => $value->isactive,
                            "field_master_id" => $value->masterid,
                            "field_iccid" => $value->iccid,
                            "field_imsi" => $value->imsi,
                            //'field_hss_profile' => $value->profile,
                            "field_location_id" => $value->locationid,
                            "field_location_address" => $value->locationaddress,
                            "field_sim_type" => $value->simtype,
                            "field_order_id" => $value->orderid,
                            "field_usage_total" => $value->usagetotal,
                            "field_usage_in" => $value->usagein,
                            "field_usage_out" => $value->usageout,
                            "field_interfacetype" => $value->interfacetype,
                            "field_ki" => $value->ki,
                            "field_opc" => $value->opc,
                            "field_is_suspend" => $value->issuspend,
                            "field_is_active" => $value->isactive,
                        ]);
                        $node->save();
                        $this->output()->writeln(
                            $value->imsi . ":node created"
                        );
                    }
                    //Update the custom_lineview_operations after node inserting
                    $query3 = $this->connection->update(
                        "custom_lineview_operations"
                    );
                    $query3->fields([
                        "status" => 1,
                    ]);
                    $query3->condition("lineviewid", $row->lineviewid, "=");
                    $query3->execute();
                } //result2 for loop
            } //result for loop
        } catch (Exception $e) {            
            // Log the exception to watchdog.
            watchdog_exception("cron", $e);
        } //catch
    } //cron
} //JciusCronCommands