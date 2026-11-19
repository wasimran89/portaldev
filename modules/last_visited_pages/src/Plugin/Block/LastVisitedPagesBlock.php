<?php

namespace Drupal\last_visited_pages\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Provides a Block to display last visited pages you visited on the website.
 *
 * @Block(
 *   id = "last_visited_pages_block",
 *   admin_label = @Translation("Last Visited Pages")
 * )
 */
class LastVisitedPagesBlock extends BlockBase {


    /**
     * {@inheritdoc}
     */
    public function defaultConfiguration() {
      $this->configuration['num_places'] = 5;
      return ['label_display' => FALSE];
    }

    /**
     * {@inheritdoc}
     */
    public function build() {
      $uid = \Drupal::currentUser()->id();

      // Grab the number of items to display
      $num_items = $this->configuration['num_places'] ?: 5;
      $date_format = $this->configuration['date_format'] ??'medium';

      // Get Recently visited links
      $result_query = \Drupal::database()->select('last_visited_pages', 'n')
      ->fields('n', array())
      ->condition('uid', $uid, '=')
      ->range(0, $num_items)
      ->orderBy('timestamp', 'DESC');

      $all_result = $result_query
      ->execute()
      ->fetchAllAssoc('id');

      $custom_fromat = false;

      if($date_format == 'custom') {
        $custom_fromat = true;
        $date_format = $this->configuration['custom_date_format'] ??'medium';

      }
        // Output the latest items as a list
        $last_visited_pages = ''; // Initializing output variable.

        if(!empty($all_result)) {
          foreach ($all_result as $key => $item) {
            $url = Url::fromUri('base:' . $item->path, array('absolute' => TRUE))->toString();

            if($item->title == '') {
              $item->title = 'Home';
            }

            if($custom_fromat) {
              $last_visited_pages .= '<li><a href="' . $url . '">' . $item->title . '</a> | ' . \Drupal::service('date.formatter')->format($item->timestamp,'custom', $date_format) . ' </li>
              ';
            } else {
              $last_visited_pages .= '<li><a href="' . $url . '">' . $item->title . '</a> | ' . \Drupal::service('date.formatter')->format($item->timestamp, $date_format) . ' </li>
              ';
            }
          }

        }

        if (isset($last_visited_pages)) {
          $output = '';

          if($this->configuration['label_display']) {
            $output .= $this->t('<h2>'.$this->label().'</h2>');
          }

          $output .= '<ul class="last-visited-pages">' . $last_visited_pages . '</ul>';
        }
        return array('#markup' => $output);
      }

    /**
     * {@inheritdoc}
     */
    public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
      $form = parent::buildConfigurationForm($form, $form_state);

      // Get the maximum allowed value from the configuration form.
      $max_places = \Drupal::config('last_visited_pages.settings')->get('max_places');
      $default_places = isset($this->configuration['num_places']) ? $this->configuration['num_places'] : 5;

      $form['label_display']['#default_value'] = $this->configuration['label_display']??true;

      // Add a select box of numbers form 1 to $max_to_display.
      $form['block_num_places_form'] = array(
        '#type' => 'select',
        '#title' => t('Number of items to show'),
        '#default_value' => $default_places,
        '#options' => array_combine(range(1,$max_places), range(1,$max_places)),
      );

      $date_formats = [];

      $formats = \Drupal::entityTypeManager()
      ->getStorage('date_format')
      ->loadMultiple();

      foreach ($formats as $machine_name => $value) {
        $date_formats[$machine_name] = $this->t('@name format: @date', ['@name' => $value->label(), '@date' => \Drupal::service('date.formatter')->format(REQUEST_TIME, $machine_name)]);
      }

      $date_formats['custom'] = $this->t('Custom');

      $form['date_format'] = [
        '#type' => 'select',
        '#title' => $this->t('Date format'),
        '#options' => $date_formats,
        '#default_value' =>  $this->configuration['date_format']??'medium'
      ];

      $form['custom_date_format'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Custom date format'),
        '#description' => $this->t('See <a href="https://www.php.net/manual/datetime.format.php#refsect1-datetime.format-parameters" target="_blank">the documentation for PHP date formats</a>.'),
        '#default_value' =>  $this->configuration['custom_date_format']??''
      ];

      $form['custom_date_format']['#states']['visible'][] = [
        ':input[name="settings[date_format]"]' => ['value' => 'custom'],
      ];

      return $form;
    }

    /**
     * {@inheritdoc}
     */
    public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
      $this->configuration['label_display'] = $form_state->getValue('label_display');
      $this->configuration['num_places'] = $form_state->getValue('block_num_places_form');
      $this->configuration['date_format'] = $form_state->getValue('date_format');
      $this->configuration['custom_date_format'] = $form_state->getValue('custom_date_format');
    }

    /**
     * {@inheritdoc}
     *
     * disable block cache to keep it the Last Visited Pages update.
     */
    public function getCacheMaxAge()
    {
      return 0;
    }
  }
