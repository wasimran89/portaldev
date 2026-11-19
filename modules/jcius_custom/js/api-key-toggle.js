(function ($, Drupal) {
  Drupal.behaviors.apiKeyToggle = {
    attach: function (context) {

      jQuery('#api-key-toggle', context).once('api-key-toggle').click(function () {

        // ALWAYS select the input, with or without the mask class
        var input = jQuery('input[name="field_api_key[0][value]"]');
        var group = jQuery('[name="label[0][value]"]');

        if (input.hasClass('api-key-mask')) {
          // Remove mask
          input.removeClass('api-key-mask');
          jQuery(this).text('Hide Key');
        } else {
          // Add mask back
          input.addClass('api-key-mask');
          jQuery(this).text('Show Key');
        }

        // AJAX GET log
        jQuery.ajax({
          //url: '/log-copy-api-key?api_key=' + encodeURIComponent(input.val())
          url: '/log-copy-api-key', // this is the route
          type: 'POST',
          data: { api_key: input.val(), group_name: group.val(), activity: "View"},
        });

      });


      // COPY KEY BUTTON
      jQuery('#api-key-copy', context).once('api-key-copy').click(function () {
        var input = jQuery('[name="field_api_key[0][value]"]');
        var group = jQuery('[name="label[0][value]"]');
        var btn = jQuery(this);

        if (input.length) {
          navigator.clipboard.writeText(input.val()).then(function () {
            btn.text('Copied!');
            setTimeout(function () {
              btn.text('Copy Key');
            }, 1500);

            // AJAX GET log
            jQuery.ajax({
              //url: '/log-copy-api-key?api_key=' + encodeURIComponent(input.val())
              url: '/log-copy-api-key', // this is the route
              type: 'POST',
              data: { api_key: input.val(), group_name: group.val(), activity: "Copy"},
            });

          }).catch(function () {
            alert('Failed to copy.');
          });
        }
      });

    }
  };
})(jQuery, Drupal);
