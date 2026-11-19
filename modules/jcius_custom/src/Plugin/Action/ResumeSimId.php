<?php
namespace Drupal\jcius_custom\Plugin\Action;
use Drupal\Core\Action\ActionBase;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a a Resume SIM ID action.
 *
 * @Action(
 *   id = "resume_sim_id",
 *   label = @Translation("Resume"),
 *   type = "node",
 *   category = @Translation("Custom")
 * )
 *
 * @DCG
 * For a simple updating entity fields consider extending FieldUpdateActionBase.
 */
class ResumeSimId extends ActionBase implements ContainerFactoryPluginInterface {

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $connection;

  /**
   * Constructs an EntityActionBase object.
   *
   * @param mixed[] $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Database\Connection $connection
   *   The database connection.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, Connection $connection) {
    $this->connection = $connection;
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('database')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function access($node, AccountInterface $account = NULL, $return_as_object = FALSE) {
    /** @var \Drupal\node\NodeInterface $node */
    $access = $node->access('update', $account, TRUE)
      ->andIf($node->title->access('edit', $account, TRUE));
    return $return_as_object ? $access : $access->isAllowed();
  }

  /**
   * {@inheritdoc}
   */
  public function execute($node = NULL) {

    if ($node) {
        /** @var \Drupal\node\NodeInterface $node */
        if (!$node->hasField('field_is_suspend') && !$node->hasField('field_is_active')) {
          throw new \RuntimeException("field_is_suspend or field_is_active not found on node.");
        }
        $node->set('field_is_suspend', FALSE);
        $node->save();
        $masterid = $node->field_master_id->getValue();
        $imsi = $node->field_imsi->getValue();
        $query = $this->connection->update('lineview')
                   ->fields(['issuspend' => 0])
                   ->condition('masterid', $masterid[0]['value'], '=')
                   ->condition('imsi', $imsi[0]['value'], '=')
                   ->execute();
        if($query) {
          \Drupal::messenger()->addStatus($this->t('Lineview database updated successfully!!!!'));
        }
    }
  }

}
