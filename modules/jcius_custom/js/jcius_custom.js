/**
 * @file
 * Jcius custom behaviors.
 */

 (function ($, Drupal) {
    'use strict';
    //Deactivate popup start
    function confirmDialog(title, message, success) {
            var confirmdialog = jQuery('<div></div>').appendTo('body')
            .html('<div><h6>' + message + '</h6></div>')
            .dialog({
              modal: true,
              title: title,
              zIndex: 10000,
                autoOpen: false,
              width: '25%',
              resizable: false,
                    buttons: {
                Yes: function () {
                  success();
                  jQuery(this).dialog("close");
                },
                No: function () {
                  jQuery(this).dialog("close");
                }
              },
              close: function () {
                jQuery(this).remove();
              }
            });
            return confirmdialog.dialog("open");
          }
    //Deactivate popup end

    //Empty cart start
    jQuery(".empty-cart-button").on('click', function (e) {
          var form = this;
          e.preventDefault();
          confirmDialog('Confirm', 'Do you really want to remove product? If yes all product will be remove from shopping cart.', function () {
            jQuery(".quantity-edit-input").unbind('click').click();
          });
      })
    //Empty cart end

    //Quantity start
      function confirmDialog(title, message, success) {
        var confirmdialog = jQuery('<div></div>').appendTo('body')
          .html('<div><h6>' + message + '</h6></div>')
          .dialog({
            modal: true,
            title: title,
            zIndex: 10000,
            autoOpen: false,
            width: '25%',
            resizable: false,
            buttons: {
              Yes: function () {
                jQuery('.quantity-edit-input').val('0');
                jQuery("#edit-submit").trigger("click");
                //success();
                jQuery(this).dialog("close");
              },
              No: function () {
                jQuery(this).dialog("close");
              }
            },
            close: function () {
              jQuery(this).remove();
            }
          });
        return confirmdialog.dialog("open");
      }
      jQuery(".quantity-edit-input").on('click', function (e) {
        if (jQuery(this).val() == '0') {
          //alert(jQuery($this).attr("edit_quantity").val());
          jQuery(this).val('1');
          var form = this;
          e.preventDefault();
          confirmDialog('Confirm', 'Do you really want to remove product? If yes all product will be remove from shopping cart.', function () {
            jQuery(".quantity-edit-input").unbind('click').click();
          });
        }
      })
      //keyup
      jQuery(".quantity-edit-input").keyup(function (e) {
        if (jQuery(this).val() == '0') {
          //alert(jQuery($this).attr("edit_quantity").val());
          jQuery(this).val('1');
          var form = this;
          e.preventDefault();
          confirmDialog('Confirm', 'Do you really want to remove product? If yes all product will be remove from shopping cart.', function () {
            jQuery(".quantity-edit-input").unbind('click').click();
          });
        }
      })
    //Quantity end

    /**
     * Behavior description.
     */
    Drupal.behaviors.jciusMaster = {
      attach: function (context, settings) {
        //Portal_active_lines_vw
        jQuery('#continue-button').attr('disabled', 'disabled');
        jQuery('#agree').click(function() {
          if (jQuery(this).is(':checked')) {
            jQuery('#continue-button').removeAttr('disabled');
          }
        });

        //Add to cart
        jQuery('.button--add-to-cart').click(function (event) {
          //slot1
          var simprofile1 = jQuery("[name='sim-profile1'] option:selected").text();
          //slot2
          var simprofile2 = jQuery("[name='sim-profile2'] option:selected").text();
          //slot3
          var simprofile3 = jQuery("[name='sim-profile3'] option:selected").text();

          var imsi_range = jQuery("[name='imsi_range_custom'] option:selected").text();
          if (imsi_range) {
            jQuery("[name='field_imsi_range[0][value]']").val(imsi_range);
          }

          if (simprofile3) {            
            jQuery("[name='field_sim_profile[0][value]']").val(simprofile1 + " + "+ simprofile2+ " + " + simprofile3);
          } else if(simprofile2) {
            jQuery("[name='field_sim_profile[0][value]']").val(simprofile1 + " + " + simprofile2);
          } else {
            jQuery("[name='field_sim_profile[0][value]']").val(simprofile1);
          }

          //slot1
          document.cookie = "simprofile1=" + simprofile1;
          document.cookie = "flag1=" + 1;
          //slot2
          document.cookie = "simprofile2=" + simprofile2;
          document.cookie = "flag2=" + 1;

          //slot3
          document.cookie = "simprofile3=" + simprofile3;
          document.cookie = "flag3=" + 1;
          //console.log(value2);
        });

        //profile_type jQuery
        jQuery('select[name="profile_type"]').once('custom-redirect-dropdown').on('change', function () {
          var selectedValue = jQuery(this).val();
          if (selectedValue) {
            window.location.href = selectedValue;
          }
        });
        //profile_type jQuery end

        //partner_name jQuery
        jQuery('select[name="partner_name"]').once('custom-redirect-dropdown2').on('change', function () {
          var selectedValue = jQuery(this).val();
          if (selectedValue) {
            window.location.href = selectedValue;
          }
        });
        //partner_name jQuery end

        /*
        //slot1
        var simprofile1 = drupalSettings.jcius_custom.variable1;
        for (var i = 0; i < simprofile1.length; i++) {
          console.log("loop", simprofile1[i]);
          console.log("hello");
          jQuery('select[name="purchased_entity[0][attributes][attribute_sim_profile1]"]').find("option:contains('"+simprofile1[i]+"')").text(''+simprofile1[i]+'(Purchased)');
          jQuery('select[name="purchased_entity[0][attributes][attribute_sim_profile1]"]').find("option:contains('" + simprofile1[i] + "')").css('backgroundColor', 'green');
          jQuery('select[name="sim-profile1"]').find("option:contains('" + simprofile1[i] + "')").text('' + simprofile1[i] + '(Purchased)');
          jQuery('select[name="sim-profile1"]').find("option:contains('" + simprofile1[i] + "')").css('backgroundColor', 'green');
        }
        */

        //slot1
        var simprofile1 = drupalSettings.jcius_custom.variable1;
        for (var i = 0; i < simprofile1.length; i++) {
          var exactMatch = simprofile1[i];          
          jQuery('select[name="purchased_entity[0][attributes][attribute_sim_profile1]"] option').filter(function() {
              return jQuery.trim(jQuery(this).text()) === exactMatch;
          }).text(exactMatch + '(Purchased)').css('backgroundColor', 'green');

          jQuery('select[name="sim-profile1"] option').filter(function() {
              return jQuery.trim(jQuery(this).text()) === exactMatch;
          }).text(exactMatch + '(Purchased)').css('backgroundColor', 'green');
        }
        
        /*
        //slot2
        var simprofile2 = drupalSettings.jcius_custom.variable2;
        for (var i = 0; i < simprofile2.length; i++) {
          console.log("loop", simprofile2[i]);
          console.log("hello");
          jQuery('select[name="purchased_entity[0][attributes][attribute_sim_profile2]"]').find("option:contains('"+simprofile2[i]+"')").text(''+simprofile2[i]+'(Purchased)');
          jQuery('select[name="purchased_entity[0][attributes][attribute_sim_profile2]"]').find("option:contains('" + simprofile2[i] + "')").css('backgroundColor', 'green');
          jQuery('select[name="sim-profile2"]').find("option:contains('" + simprofile2[i] + "')").text('' + simprofile2[i] + '(Purchased)');
          jQuery('select[name="sim-profile2"]').find("option:contains('" + simprofile2[i] + "')").css('backgroundColor', 'green');
        }
        */

        //slot2
        var simprofile2 = drupalSettings.jcius_custom.variable2;
        for (var i = 0; i < simprofile2.length; i++) {
          var exactMatch = simprofile2[i];
          // Find the options and apply modifications
          jQuery('select[name="purchased_entity[0][attributes][attribute_sim_profile2]"] option').filter(function() {
              return jQuery.trim(jQuery(this).text()) === exactMatch;
          }).text(exactMatch + '(Purchased)').css('backgroundColor', 'green');

          jQuery('select[name="sim-profile2"] option').filter(function() {
              return jQuery.trim(jQuery(this).text()) === exactMatch;
          }).text(exactMatch + '(Purchased)').css('backgroundColor', 'green');
        }

        /*
        //slot3
        var simprofile3 = drupalSettings.jcius_custom.variable3;
        for (var i = 0; i < simprofile3.length; i++) {
          console.log("loop", simprofile3[i]);
          console.log("hello");
          jQuery('select[name="purchased_entity[0][attributes][attribute_sim_profile3]"]').find("option:contains('"+simprofile3[i]+"')").text(''+simprofile3[i]+'(Purchased)');
          jQuery('select[name="purchased_entity[0][attributes][attribute_sim_profile3]"]').find("option:contains('" + simprofile3[i] + "')").css('backgroundColor', 'green');
          jQuery('select[name="sim-profile3"]').find("option:contains('" + simprofile3[i] + "')").text('' + simprofile3[i] + '(Purchased)');
          jQuery('select[name="sim-profile3"]').find("option:contains('" + simprofile3[i] + "')").css('backgroundColor', 'green');
        }
        */

        //slot3
        var simprofile3 = drupalSettings.jcius_custom.variable3;
        var purchasedEntityOptions = jQuery('select[name="purchased_entity[0][attributes][attribute_sim_profile3]"] option');
        var simProfileOptions = jQuery('select[name="sim-profile3"] option');

        for (var i = 0; i < simprofile3.length; i++) {
          var exactMatch = simprofile3[i];
          // Find the options and apply modifications
          purchasedEntityOptions.filter(function() {
              return jQuery.trim(jQuery(this).text()) === exactMatch;
          }).text(exactMatch + '(Purchased)').css('backgroundColor', 'green');

          simProfileOptions.filter(function() {
              return jQuery.trim(jQuery(this).text()) === exactMatch;
          }).text(exactMatch + '(Purchased)').css('backgroundColor', 'green');
        }
      } //attach
    }; //beahaviours
}) (jQuery, Drupal, drupalSettings);
