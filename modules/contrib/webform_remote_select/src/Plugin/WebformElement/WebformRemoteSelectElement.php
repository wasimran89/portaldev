<?php

namespace Drupal\webform_remote_select\Plugin\WebformElement;

use Drupal\Core\Form\FormStateInterface;
use Drupal\webform\WebformSubmissionInterface;
use Drupal\webform\Plugin\WebformElement\TextField;
use Drupal\Component\Utility\NestedArray;

/**
 * Provides a 'webform_remote_select_element' element.
 *
 * @WebformElement(
 *   id = "webform_remote_select_element",
 *   label = @Translation("Remote select element"),
 *   description = @Translation("Provides a select element prepopulated with values from a remote server."),
 *   category = @Translation("Advanced elements"),
 * )
 *
 * @see \Drupal\webform_remote_select\Element\WebformRemoteSelectElement
 * @see \Drupal\webform\Plugin\WebformElementBase
 * @see \Drupal\webform\Plugin\WebformElementInterface
 * @see \Drupal\webform\Annotation\WebformElement
 */
class WebformRemoteSelectElement extends TextField {

  /**
   * {@inheritdoc}
   */
  public function getDefaultProperties() {
    return [
        'endpoint' => '',
        'headers' => '',
        'response_key' => '',
        'data_key' => '',
        'data_value' => '',
        'allow_duplicated_values' => FALSE,
        'use_empty_option' => FALSE,
        'empty_option' => '',
        'use_select2' => FALSE
      ] + parent::getDefaultProperties();
  }

  /**
   * {@inheritdoc}
   */
  public function prepare(array &$element, WebformSubmissionInterface $webform_submission = null) {
    $webform_key = $element['#webform_key'];
    $element['#attributes']['data-id'] = $webform_key;
    $element['#attached']['drupalSettings']['webform']['remoteSelect'][$webform_key]['use_select2'] = isset($element['#use_select2']) ? $element['#use_select2'] : FALSE;
    $element['#attributes']['class'][] = 'js-webform-remote-select-element';

    // Set limit as data attributes for select2, choices or chosen.
    if (isset($element['#multiple']) && $element['#multiple'] > 1) {
      $element['#attributes']['data-limit'] = $element['#multiple'];
    }
    if (isset($element['#use_select2']) && !empty($element['#use_select2'])) {
      $element['#attributes']['class'][] = 'webform-select2';
      $element['#attributes']['class'][] = 'js-webform-select2';
      $element['#attached']['library'][] = 'webform_remote_select/select2_full';
      $element['#attached']['library'][] = 'webform_remote_select/webform_remote_select_element';
    }

    $this->setElementDefaultCallback($element, 'element_validate');
    if (!empty($element['#multiple']) && (!isset($element['#allow_duplicated_values']) || empty($element['#allow_duplicated_values']))) {
      $element['#element_validate'][] = [get_class($this), 'validateUniqueMultiple'];
    }

    if (!empty($element['#multiple'])) {
      $element['#element_validate'][] = [get_class($this), 'validateEmptyValues'];
    }

    $request = $this->executeRequest($element, $webform_submission);
    if (empty($request)) {
      parent::prepare($element, $webform_submission);
      return;
    }
    $element = $request;
    parent::prepare($element, $webform_submission);
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);
    $form['remote'] = [
        '#type' => 'fieldset',
        '#title' => $this->t('Remote settings'),
    ];

    $form['remote']['endpoint'] = [
        '#title' => $this->t('Endpoint'),
        '#type' => 'textfield',
        '#description' => $this->t('Endpoint URL. You can use tokens in this field.'),
        '#required' => TRUE,
    ];

    $form['remote']['headers'] = [
      '#title' => $this->t('Headers'),
      '#type' => 'textarea',
      '#description' => $this->t("Example: {'Content-Type': 'application/json'}"),
    ];

    $form['remote']['response_key'] = [
      '#title' => $this->t('Response data key'),
      '#type' => 'textfield',
      '#description' => $this->t("Key used to get data items in response data. Leave it empty if it is not necessary. Example: {'contentResponse' : [{'key' : 'key', 'value' : 'value'}, {'key' : 'key2', 'value' : 'value2'}] -> In this case, you should use <b>contentResponse</b>. To use response key in child levels of JSON array, you should use '.' to navigate to child levels (Example: contentResponse.data.items)"),
    ];

    $form['remote']['data_key'] = [
        '#title' => $this->t('Response items key'),
        '#type' => 'textfield',
        '#description' => $this->t("Key used in select items. Leave it empty if expected response data is not associative array. Example with associative array: if response data is [{'key' : 'key', 'value' : 'value'}, {'key' : 'key2', 'value' : 'value2'}], key should be <b>key</b> and value should be <b>value</b>."),
    ];

    $form['remote']['data_value'] = [
      '#title' => $this->t('Response items value'),
      '#type' => 'textfield',
      '#description' => $this->t("Value used in select items. Leave it empty if expected response data is not associative array. Example with associative array: if response data is [{'key' : 'key', 'value' : 'value'}, {'key' : 'key2', 'value' : 'value2'}], key should be <b>key</b> and value should be <b>value</b>."),
    ];

    $form['remote']['allow_duplicated_values'] = [
      '#title' => $this->t('Allow duplicated values'),
      '#type' => 'checkbox',
      '#description' => $this->t("If this configuration is checked and the field is multiple, users are allowed to choose the same value more than once."),
    ];

    $form['remote']['use_empty_option'] = [
      '#title' => $this->t('Use empty option'),
      '#type' => 'checkbox',
      '#description' => $this->t("Use empty option used as default option in select."),
    ];

    $form['remote']['empty_option'] = [
      '#title' => $this->t('Empty option label'),
      '#type' => 'textfield',
      '#states' => ['visible' => [':input[name="properties[use_empty_option]"]' => ['checked' => TRUE]]],
    ];

    $form['remote']['use_select2'] = [
      '#title' => $this->t('Use select2'),
      '#type' => 'checkbox',
    ];

    return $form;
  }

  public static function validateEmptyValues(array &$element, FormStateInterface $form_state) {
    $element_value = $form_state->getValue($element['#webform_key']);
    if (empty($element_value) || !is_array($element_value)) {
      return;
    }
    $first_value = isset($element_value['items']) && isset($element_value['items'][0]) && isset($element_value['items'][0]['_item_']) ? $element_value['items'][0]['_item_'] : '';
    foreach ($element_value['items'] as $k => $v) {
      if (!is_numeric($k) || $k === 0) {
        continue;
      }
      if (!is_array($v) || !isset($v['_item_'])) {
        continue;
      }
      if (empty($v['_item_']) || empty($first_value)) {
        $form_state->setError($element, t('Empty values are not allowed in multiple elements.'));
      }
    }
  }

  /**
   * Form API callback. Check if only different values are checked in multiple elements.
   */
  public static function validateUniqueMultiple(array &$element, FormStateInterface $form_state) {
    $element_value = $form_state->getValue($element['#webform_key']);
    if (is_array($element_value)) {
      $values = array_values($element_value);
      if (!empty($values)) {
        $filtered = array_filter($values, "self::check_valid_value");
        $count_arr = array_count_values($filtered);
        foreach ($count_arr as $k => $v) {
          if ($v > 1) {
            $form_state->setError($element, t('Not allowed to insert the same value more than once.'));
          }
        }
      }
    }

    if (!isset($element['#multiple'])) {
      return;
    }
    $values = NestedArray::getValue($form_state->getValues(), $element['#parents']);

    // Skip empty values or values that are not an array.
    if (empty($values) || !is_array($values)) {
      return;
    }

    if (count($values) > $element['#multiple']) {
      if (isset($element['#multiple_error'])) {
        $form_state->setError($element, $element['#multiple_error']);
      }
      elseif (isset($element['#title'])) {
        $t_args = [
          '%name' => empty($element['#title']) ? $element['#parents'][0] : $element['#title'],
          '@count' => $element['#multiple'],
        ];
        $form_state->setError($element, t('%name: this element cannot hold more than @count values.', $t_args));
      }
      else {
        $form_state->setError($element);
      }
    }
  }

  private static function isAssoc(array $arr) {
    if (array() === $arr) {
      return false;
    }
    return array_keys($arr) !== range(0, count($arr) - 1);
  }

  private static function check_valid_value($var) {
    return is_string($var) || is_int($var);
  }

  /**
   * Gets value from JSON array.
   *
   * @param array $retval
   *   Original array from which the value is obtained.
   * @param string $string
   *   Path to value.
   *
   * @return string
   *   Result value from specific key/path inside the JSON array.
   */
  private function getJsonValue(array $retval, $string) {
    $parts = explode('.', $string);
    foreach ($parts as $part) {
      $retval = (array_key_exists($part, $retval) ? $retval[$part] : $retval);
    }
    return $retval;
  }

  /**
   * {@inheritdoc}
   */
  protected function formatHtmlItem(array $element, WebformSubmissionInterface $webform_submission = null, array $options = []) {
    $build = parent::formatHtmlItem($element, $webform_submission, $options);

    $request = $this->executeRequest($element, $webform_submission);
    if (empty($request)) {
      return $build;
    }
    $element = $request;
    if (empty($build) || !is_array($build) || !array_key_exists('#plain_text', $build)) {
      return $build;
    }
    $value = $build['#plain_text'];
    return $element['#options'][$value];
  }

  /**
   * Execute request to get options from endpoint.
   */
  protected function executeRequest($element, WebformSubmissionInterface $webform_submission = null) {
    $client = \Drupal::httpClient();
    $data_key = isset($element['#data_key']) ? $element['#data_key'] : '';
    $data_value = isset($element['#data_value']) ? $element['#data_value'] : '';

    $headers = isset($element['#headers']) ? json_decode(str_replace(["\r", "\n"], '', $element['#headers']), TRUE) : '';

    // Replace tokens in endpoint.
    $endpoint = $element['#endpoint'];
    $token_options = ['clear' => TRUE, 'callback' => '_webform_remote_select_token_cleaner'];
    if (empty($webform_submission)) {
      $token_data = [];
    } else {
      $token_data = [
        'webform' => $webform_submission->getWebform(),
        'webform_submission' => $webform_submission,
      ];
    }
    $endpoint = \Drupal::token()->replace($endpoint, $token_data, $token_options);

    try {
      $response = $client->get($endpoint, [
        'headers' => !empty($headers) ? $headers : [],
      ]);

      $body = $response->getBody();
      if (empty($body)) {
        return FALSE;
      }

      $data = $body->getContents();
      if (empty($data)) {
        return FALSE;
      } else {
        $data = json_decode($data, TRUE);
        if (isset($element['#response_key']) && !empty($element['#response_key'])) {
          $data = $this->getJsonValue($data, $element['#response_key']);
        }
        if (!self::isAssoc($data)) {
          if (!is_array($data[0])) {
            foreach ($data as $item) {
              $element['#options'][$item] = $item;
            }
          } else {
            if (self::isAssoc($data[0]) && !empty($data_key) && !empty($data_value)) {
              foreach ($data as $item) {
                $element['#options'][$item[$data_key]] = $item[$data_value];
              }
            } else {
              return FALSE;
            }
          }
        } else {
          foreach ($data as $k => $item) {
            if (is_array($item)) {
              $value = $item[$data_value];
            } else {
              $value = $item;
            }
            $element['#options'][$item[$data_key]] = $value;
          }
        }
      }
      return $element;
    }
    catch (\Exception $e) {
      return FALSE;
    }
  }

}
