<?php

namespace Drupal\webform_remote_select\Element;

use Drupal\Core\Render\Element\Select;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;

/**
 * Provides a 'webform_remote_select_element'.
 *
 * Webform elements are just wrappers around form elements, therefore every
 * webform element must have correspond FormElement.
 *
 * Below is the definition for a custom 'webform_remote_select_element'.
 *
 * @FormElement("webform_remote_select_element")
 *
 * @see \Drupal\Core\Render\Element\FormElement
 * @see https://api.drupal.org/api/drupal/core%21lib%21Drupal%21Core%21Render%21Element%21FormElement.php/class/FormElement
 * @see \Drupal\Core\Render\Element\RenderElement
 * @see https://api.drupal.org/api/drupal/namespace/Drupal%21Core%21Render%21Element
 * @see \Drupal\webform_remote_select\Element\WebformRemoteSelectElement
 */
class WebformRemoteSelectElement extends Select
{
    /**
   * {@inheritdoc}
   */
  public function getInfo() {
    $class = get_class($this);
    return parent::getInfo() + [
      '#input' => TRUE,
      '#multiple' => FALSE,
      '#sort_options' => FALSE,
      '#sort_start' => NULL,
      '#process' => [
        [$class, 'processSelect'],
        [$class, 'processAjaxForm'],
      ],
      '#pre_render' => [
        [$class, 'preRenderSelect'],
      ],
      '#theme' => 'select',
      '#theme_wrappers' => ['form_element'],
      '#options' => [],
    ];
  }

  /**
   * Processes a select list form element.
   *
   * This process callback is mandatory for select fields, since all user agents
   * automatically preselect the first available option of single (non-multiple)
   * select lists.
   *
   * @param array $element
   *   The form element to process.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param array $complete_form
   *   The complete form structure.
   *
   * @return array
   *   The processed element.
   *
   * @see _form_validate()
   */
  public static function processSelect(&$element, FormStateInterface $form_state, &$complete_form) {
    // #multiple select fields need a special #name.
    if ($element['#multiple']) {
      $element['#attributes']['multiple'] = 'multiple';
      $element['#attributes']['name'] = $element['#name'] . '[]';
    }
    // A non-#multiple select needs special handling to prevent user agents from
    // preselecting the first option without intention. #multiple select lists do
    // not get an empty option, as it would not make sense, user interface-wise.

    // If the element is set to #required through #states, override the
    // element's #required setting.

    if (!empty($element['#webform_key'])) {
      $required = isset($element['#states']['required']) ? TRUE : $complete_form['elements'][$element['#webform_key']]['#required'];
      $use_empty_option = isset($complete_form['elements'][$element['#webform_key']]['#use_empty_option']) ? 
        $complete_form['elements'][$element['#webform_key']]['#use_empty_option'] : $element['#use_empty_option'];
      $empty_option_text = isset($complete_form['elements'][$element['#webform_key']]['#empty_option']) ?
        $complete_form['elements'][$element['#webform_key']]['#empty_option'] : '';
    } else {
      if (!array_key_exists('#use_empty_option', $element)) {
        return $element;
      }
      $required = isset($element['#required']) ? $element['#required'] : FALSE;
      $use_empty_option = $element['#use_empty_option'];
      $empty_option_text = isset($element['#empty_option']) ? $element['#empty_option'] : '';
    }

    if (empty($use_empty_option)) {
      return $element;
    }
    if (empty($empty_option_text)) {
      $empty_option_text = $required ? t('- Select -') : t('- None -');
    }

    // If the element is required and there is no #default_value, then add an
    // empty option that will fail validation, so that the user is required to
    // make a choice. Also, if there's a value for #empty_value or
    // #empty_option, then add an option that represents emptiness.
    $element += [
      '#empty_value' => '',
      '#empty_option' => $empty_option_text,
    ];

    // The empty option is prepended to #options and purposively not merged
    // to prevent another option in #options mistakenly using the same value
    // as #empty_value.
    $empty_option = [$element['#empty_value'] => $element['#empty_option']];
    $element['#options'] = $empty_option + $element['#options'];

    // Provide the correct default value for #sort_start.
    $element['#sort_start'] = $element['#sort_start'] ??
      (isset($element['#empty_value']) ? 1 : 0);
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public static function valueCallback(&$element, $input, FormStateInterface $form_state) {
    if ($input !== FALSE) {
      if (isset($element['#multiple']) && $element['#multiple']) {
        // If an enabled multi-select submits NULL, it means all items are
        // unselected. A disabled multi-select always submits NULL, and the
        // default value should be used.
        if (empty($element['#disabled'])) {
          return (is_array($input)) ? array_combine($input, $input) : [];
        }
        else {
          return (isset($element['#default_value']) && is_array($element['#default_value'])) ? $element['#default_value'] : [];
        }
      }
      // Non-multiple select elements may have an empty option prepended to them
      // (see \Drupal\Core\Render\Element\Select::processSelect()). When this
      // occurs, usually #empty_value is an empty string, but some forms set
      // #empty_value to integer 0 or some other non-string constant. PHP
      // receives all submitted form input as strings, but if the empty option
      // is selected, set the value to match the empty value exactly.
      elseif (isset($element['#empty_value']) && $input === (string) $element['#empty_value']) {
        return $element['#empty_value'];
      }
      else {
        return $input;
      }
    }
  }

  /**
   * Prepares a select render element.
   */
  public static function preRenderSelect($element) {
    Element::setAttributes($element, ['id', 'name', 'size']);
    static::setAttributes($element, ['form-select']);
    return $element;
  }
}
