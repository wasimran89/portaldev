/**
 * @file
 * Jcius custom behaviors.
 */
(function ($, Drupal, drupalSettings) {
  Drupal.behaviors.jciusCustom = {
    attach: function (context, settings) {
      // get color_body value with "drupalSettings.mymodule.color_body"
      //var simprofile = drupalSettings.jcius_simprofile.variable;
      //alert(simprofile);
    }
  };
})(jQuery, Drupal, drupalSettings);
