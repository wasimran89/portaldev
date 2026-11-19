/**
 * @file
 * Jcius custom behaviors.
 */

 (function ($, Drupal) {
    'use strict';
    var urlParams = new URLSearchParams(window.location.search);
    //console.log(urlParams.get('table'));
    if(urlParams.get('table') == 'esim_provision'){
      jQuery("th:first-child").css("display","none");
      jQuery("td:first-child").css("display","none");
    }
    if(urlParams.get('table') == 'provision_history'){
      jQuery("th:first-child").css("display","none");
      jQuery("td:first-child").css("display","none");
    }
    $(document).ready(function () {
      //custom group color js
      var colorset = jQuery('input[name="field_color_set"]:checked').val();
      if(colorset == 1){
          jQuery('#edit-field-color-scheme-wrapper').hide();
          jQuery('#edit-field-header-background-top-wrapper').show();
      jQuery('#edit-field-header-background-top-wrapper').show();
          jQuery('#edit-field-primary-color-wrapper').show();
          jQuery('#edit-field-menu-hover-background-wrapper').show();
          jQuery('#edit-field-title-and-slogan-wrapper').show();
          jQuery('#edit-field-text-color-wrapper').show();
          jQuery('#edit-field-link-color-wrapper').show();
          jQuery('#edit-field-link-hover-color-wrapper').show();
          jQuery('#edit-field-footer-background-wrapper').show();
      }else{
          jQuery('#edit-field-color-scheme-wrapper').show();
      jQuery('#edit-field-header-background-top-wrapper').hide();
          jQuery('#edit-field-primary-color-wrapper').hide();
          jQuery('#edit-field-menu-hover-background-wrapper').hide();
          jQuery('#edit-field-title-and-slogan-wrapper').hide();
          jQuery('#edit-field-text-color-wrapper').hide();
          jQuery('#edit-field-link-color-wrapper').hide();
          jQuery('#edit-field-link-hover-color-wrapper').hide();
          jQuery('#edit-field-footer-background-wrapper').hide();
      }
      jQuery('input[name="field_color_set').on('click', function () {
        var colorset = jQuery('input[name="field_color_set"]:checked').val();
        if(colorset == 1){
            jQuery('#edit-field-color-scheme-wrapper').hide();
            jQuery('#edit-field-header-background-top-wrapper').show();
            jQuery('#edit-field-header-background-top-wrapper').show();
            jQuery('#edit-field-primary-color-wrapper').show();
            jQuery('#edit-field-menu-hover-background-wrapper').show();
            jQuery('#edit-field-title-and-slogan-wrapper').show();
            jQuery('#edit-field-text-color-wrapper').show();
            jQuery('#edit-field-link-color-wrapper').show();
            jQuery('#edit-field-link-hover-color-wrapper').show();
            jQuery('#edit-field-footer-background-wrapper').show();
        }else{
            jQuery('#edit-field-color-scheme-wrapper').show();
            jQuery('#edit-field-header-background-top-wrapper').hide();
            jQuery('#edit-field-primary-color-wrapper').hide();
            jQuery('#edit-field-menu-hover-background-wrapper').hide();
            jQuery('#edit-field-title-and-slogan-wrapper').hide();
            jQuery('#edit-field-text-color-wrapper').hide();
            jQuery('#edit-field-link-color-wrapper').hide();
            jQuery('#edit-field-link-hover-color-wrapper').hide();
            jQuery('#edit-field-footer-background-wrapper').hide();
        }
        });
    });
    /**
     * Behavior description.
     */
    Drupal.behaviors.mydata = {
      attach: function (context, settings) {
        // Ensure this runs only once per element.
        jQuery(".downloadlpastring", context).once("copyQrLabelBehavior").click(function (event) {
            event.preventDefault(); // Prevent page refresh or default behavior.

            // Fetch the text from the label.
            const labelText = jQuery('.custom-uri-label').text();
            // Copy the label text to the clipboard.
            navigator.clipboard.writeText(labelText).then(() => {
                // Change the button value to "Copied"
                jQuery(this).val('Copied URI');
            }).catch((error) => {
                console.error("Failed to copy text: ", error);
            });
        });

        // Define a global function to copy text to the clipboard.
        window.customCopyToClipboard = function (text) {
            navigator.clipboard.writeText(text).then(function () {
              console.log('Copied to clipboard:', text);
            }).catch(function (err) {
              console.error('Error copying to clipboard:', err);
            });
        };
             
        jQuery("[name='input_file_type']").change(function(){
            if(jQuery(this).val() == 'ESIM'){
            jQuery("[name='rpad_offset']").val('21');
          } else if(jQuery(this).val() == 'USIM'){
            jQuery("[name='rpad_offset']").val('13');
          }
        });
        //imsi_range table imsi_start calculation
        jQuery("input[name=mcc]").keyup(function() {
            updateTotal();
        });

        jQuery("input[name=mnc]").keyup(function() {
            updateTotal();
        });
        jQuery(document).ready(function() {
            updateTotal();
        });
        var updateTotal = function () {
          var input1 = jQuery("input[name=mcc]").val();
          var input2 = jQuery("input[name=mnc]").val();
          var input3 = input1 + "" + input2;
          //console.log(input1);
          jQuery("input[name=imsi_start1]").val(input3);
        };

        //new pie start
        if (drupalSettings.mydata) {
            var piedata = drupalSettings.mydata.piechartdata;
            var esimdata = [];
            for (var i = 0; i < piedata.length; i++) {
              //console.log("loop", piedata[i]);
              esimdata.push(piedata[i]);
            }
            //console.log(esimdata);
            // Data for the pie chart
            var data = {
                labels: ['Allocated', 'Available', 'Deleted', 'Enabled', 'Error', 'Installed','Released'],
                datasets: [{
                    //data: ['2', '10', '20', '0', '0', '126'],
                    data: esimdata,
                    backgroundColor: [
                        'rgba(255, 99, 132, 0.7)',
                        'rgba(25, 99, 132, 0.7)',
                        'rgba(54, 162, 235, 0.7)',
                        'rgba(255, 206, 86, 0.7)',
                        'rgba(75, 192, 192, 0.7)',
                        'rgba(153, 102, 255, 0.7)',
                        'rgba(255, 159, 64, 0.7)'
                    ],
                }],
            };

            // Options for the pie chart
            var options = {
                responsive: true,
                maintainAspectRatio: false, // Disable aspect ratio maintenance
                aspectRatio: 1, // Set the desired aspect ratio (1 for a square chart)
            };

            // Get the canvas element
            var ctx = jQuery('#partnerPieChart', context);
            //ctx.css('width', '200px'); // Set the width to 200 pixels
            //ctx.css('height', '200px'); // Set the height to 200 pixels

            // Create the pie chart
            var myPieChart = new Chart(ctx, {
                type: 'pie',
                data: data,
                options: options
            });            
        }
        //new pie end
      }
    };

}) (jQuery, Drupal, drupalSettings);
