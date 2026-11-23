<?php

namespace Drupal\mydata\Controller;
use Drupal\Core\Render\Markup;
use Drupal\Core\Database\Connection;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;

/**
 * Class MydataController.
 *
 * @package Drupal\mydata\Controller
 */
class MydataController extends ControllerBase {

  /**
   * Display.
   *
   * @return string
   *   Return oss string.
   */
  public function display() {
    $base_path = Url::fromRoute('<front>', [], ['absolute' => TRUE])->toString();
    $rows = [
        [Markup::create('<a target="_blank" href="'.$base_path.'/mydata/oss/table2?table=address">address</a>')],
        [Markup::create('<a target="_blank" href="'.$base_path.'/mydata/oss/table2?table=address_type">address_type</a>')],
        [Markup::create('<a target="_blank" href="'.$base_path.'/mydata/oss/table2?table=api_client">api_client</a>')],
        [Markup::create('<a target="_blank" href="'.$base_path.'/mydata/oss/table2?table=country">country</a>')],
        [Markup::create('<a target="_blank" href="'.$base_path.'/mydata/oss/table2?table=drupal_user">drupal_user</a>')],
        [Markup::create('<a target="_blank" href="'.$base_path.'/mydata/oss/table2?table=esim_api_token">esim_api_token</a>')],
        [Markup::create('<a target="_blank" href="'.$base_path.'/mydata/oss/table2?table=esim_inventory">esim_inventory</a>')],
        [Markup::create('<a target="_blank" href="'.$base_path.'/mydata/oss/table2?table=esim_profile_type">esim_profile_type</a>')],
        [Markup::create('<a target="_blank" href="'.$base_path.'/mydata/oss/table2?table=esim_profiles">esim_profiles</a>')],
        [Markup::create('<a target="_blank" href="'.$base_path.'/mydata/oss/table2?table=esim_provision">esim_provision</a>')],
        [Markup::create('<a target="_blank" href="'.$base_path.'/mydata/oss/table2?table=iccid_pool">iccid_pool</a>')],
        [Markup::create('<a target="_blank" href="'.$base_path.'/mydata/oss/table2?table=iccid_prefix">iccid_prefix</a>')],
        [Markup::create('<a target="_blank" href="'.$base_path.'/mydata/oss/table2?table=imsi_range">imsi_range</a>')],
        [Markup::create('<a target="_blank" href="'.$base_path.'/mydata/oss/table2?table=input_element">input_element</a>')],
        [Markup::create('<a target="_blank" href="'.$base_path.'/mydata/oss/table2?table=input_element_type">input_element_type</a>')],
        [Markup::create('<a target="_blank" href="'.$base_path.'/mydata/oss/table2?table=input_file_detail">input_file_detail</a>')],
        [Markup::create('<a target="_blank" href="'.$base_path.'/mydata/oss/table2?table=input_file_esim_header">input_file_esim_header</a>')],
        [Markup::create('<a target="_blank" href="'.$base_path.'/mydata/oss/table2?table=item">item</a>')],
        [Markup::create('<a target="_blank" href="'.$base_path.'/mydata/oss/table2?table=item_group">item_group</a>')],
        [Markup::create('<a target="_blank" href="'.$base_path.'/mydata/oss/table2?table=item_identifier">item_identifier</a>')],
        [Markup::create('<a target="_blank" href="'.$base_path.'/mydata/oss/table2?table=item_identifier_enc">item_identifier_enc</a>')],
        [Markup::create('<a target="_blank" href="'.$base_path.'/mydata/oss/table2?table=item_identifier_key">item_identifier_key</a>')],
        [Markup::create('<a target="_blank" href="'.$base_path.'/mydata/oss/table2?table=item_location">item_location</a>')],
        [Markup::create('<a target="_blank" href="'.$base_path.'/mydata/oss/table2?table=item_type">item_type</a>')],
        [Markup::create('<a target="_blank" href="'.$base_path.'/mydata/oss/table2?table=microsoft_json_batch">microsoft_json_batch</a>')],
        [Markup::create('<a target="_blank" href="'.$base_path.'/mydata/oss/table2?table=order">order</a>')],
        [Markup::create('<a target="_blank" href="'.$base_path.'/mydata/oss/table2?table=order_detail">order_detail</a>')],
        [Markup::create('<a target="_blank" href="'.$base_path.'/mydata/oss/table2?table=order_detail_sim_profile_template">order_detail_sim_profile_template</a>')],
        [Markup::create('<a target="_blank" href="'.$base_path.'/mydata/oss/table2?table=order_status">order_status</a>')],
        [Markup::create('<a target="_blank" href="'.$base_path.'/mydata/oss/table2?table=order_status_type">order_status_type</a>')],
        [Markup::create('<a target="_blank" href="'.$base_path.'/mydata/oss/table2?table=package">package</a>')],
        [Markup::create('<a target="_blank" href="'.$base_path.'/mydata/oss/table2?table=partner">partner</a>')],
        [Markup::create('<a target="_blank" href="'.$base_path.'/mydata/oss/table2?table=preload_sim_info">preload_sim_info</a>')],
        [Markup::create('<a target="_blank" href="'.$base_path.'/mydata/oss/table2?table=product">product</a>')],
        [Markup::create('<a target="_blank" href="'.$base_path.'/mydata/oss/table2?table=product_category">product_category</a>')],
        [Markup::create('<a target="_blank" href="'.$base_path.'/mydata/oss/table2?table=product_pricing">product_pricing</a>')],
        [Markup::create('<a target="_blank" href="'.$base_path.'/mydata/oss/table2?table=provision_history">provision_history</a>')],
        [Markup::create('<a target="_blank" href="'.$base_path.'/mydata/oss/table2?table=role">role</a>')],
        [Markup::create('<a target="_blank" href="'.$base_path.'/mydata/oss/table2?table=sim_batch">sim_batch</a>')],
        [Markup::create('<a target="_blank" href="'.$base_path.'/mydata/oss/table2?table=sim_profile">sim_profile</a>')],
        [Markup::create('<a target="_blank" href="'.$base_path.'/mydata/oss/table2?table=sim_profile_group">sim_profile_group</a>')],
        [Markup::create('<a target="_blank" href="'.$base_path.'/mydata/oss/table2?table=sim_profile_queue">sim_profile_queue</a>')],
        [Markup::create('<a target="_blank" href="'.$base_path.'/mydata/oss/table2?table=sim_profile_queue_table">sim_profile_queue_table</a>')],
        [Markup::create('<a target="_blank" href="'.$base_path.'/mydata/oss/table2?table=sim_profile_status">sim_profile_status</a>')],
        [Markup::create('<a target="_blank" href="'.$base_path.'/mydata/oss/table2?table=sim_profile_template">sim_profile_template</a>')],
        [Markup::create('<a target="_blank" href="'.$base_path.'/mydata/oss/table2?table=sim_profile_template_attributes">sim_profile_template_attributes</a>')],
        [Markup::create('<a target="_blank" href="'.$base_path.'/mydata/oss/table2?table=supplier">supplier</a>')],
        [Markup::create('<a target="_blank" href="'.$base_path.'/mydata/oss/table2?table=update_conditions">update_conditions</a>')],
        [Markup::create('<a target="_blank" href="'.$base_path.'/mydata/oss/table2?table=user">user</a>')],
        [Markup::create('<a target="_blank" href="'.$base_path.'/mydata/oss/table2?table=user_address">user_address</a>')],
        [Markup::create('<a target="_blank" href="'.$base_path.'/mydata/oss/table2?table=user_group">user_group</a>')],
        [Markup::create('<a target="_blank" href="'.$base_path.'/mydata/oss/table2?table=users_groups">users_groups</a>')],
        [Markup::create('<a target="_blank" href="'.$base_path.'/mydata/oss/table2?table=webui_user_level_permissions">webui_user_level_permissions</a>')],
        [Markup::create('<a target="_blank" href="'.$base_path.'/mydata/oss/table2?table=webui_user_levels">webui_user_levels</a>')],
        [Markup::create('<a target="_blank" href="'.$base_path.'/mydata/oss/table2?table=webui_users">webui_users</a>')]
    ];
    $header = [
      'title' => t('Title')
    ];
    $build['table'] = [
      '#type' => 'table',
      '#header' => $header,
      '#rows' => $rows,
      '#empty' => t('No content has been found.'),
      '#cache' => array('max-age' => 0)
    ];

    $renderer = \Drupal::service('renderer');

    return [
      '#type' => '#markup',
      '#markup' => $renderer->render($build)
    ];
  }

}
