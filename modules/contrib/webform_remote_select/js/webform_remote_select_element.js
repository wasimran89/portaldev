(function ($, Drupal) {
  Drupal.behaviors.webformRemoteSelectElement = {
    attach: function (context, settings) {
      $.each($('.js-webform-remote-select-element'), function () {
        var itemId = $(this).data().id;
        if (typeof settings.webform.remoteSelect[itemId] !== 'undefined' && settings.webform.remoteSelect[itemId].use_select2) {
          $('[data-id="' + itemId + '"]').select2({});
        }
      });
    }
  };
}(jQuery, Drupal));
