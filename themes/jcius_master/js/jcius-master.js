/**
 * @file
 * Jcius Master behaviors.
 */

(function ($, Drupal, drupalSettings) {

  'use strict';
  //simlineview toggle button
  jQuery('.simlineview-link').click(function() {
    if(jQuery(this).hasClass('fa-toggle-on')) {       
        jQuery(this).removeClass('fa-toggle-on').addClass('fa-toggle-off');
    }
    else{
        jQuery(this).removeClass('fa-toggle-off').addClass('fa-toggle-on');
    }
  });

  //esim-generate-checkbox
  jQuery('.js-form-item-custom-file-name').css("display", "none");
  jQuery('.custom-default-generated').on('click', function () {
    jQuery('input:checkbox[name=default_generated]').each(function() 
    {    
        if (jQuery(this).is(':checked')) {        
          jQuery('.js-form-item-custom-file-name').css("display", "block");
          jQuery('.custom-file-name').css("display", "block");
          jQuery('.custom-file-name').attr('required', true);     
        } else {
          jQuery('.custom-file-name').attr('required', false);            
          jQuery('.js-form-item-custom-file-name').css("display", "none");
          
        }
    });
  });

  //oss portal search module
  document.cookie = "field=" + null;
  document.cookie = "column=" + null;

  //user-billing-entity-id
  jQuery('.js-form-item-billing-entity').css("display", "none");
  jQuery('.js-form-item-sim-partner').css("display", "none");
  jQuery('.multi_service_field_wrapper').css("display", "none");
  jQuery('#custom-multiservice-field').css("display", "none");
  //simlineview
  jQuery('.path-sim-line-view .js-form-item-partner-id').css("display", "none");
  jQuery('#custom-partnerid-colmn').css("display", "none");
  //profiles audit
  jQuery('.path-profiles-audit .js-form-item-partner-name').css("display", "none");
  jQuery('.js-form-item-group-type').css("display", "none");
  //profiles audit end
  jQuery('#custom-header-colmn').on('click', function () {
    jQuery('.multi_service_field_wrapper').css("display", "none");
    jQuery('#custom-multiservice-field').css("display", "none");
    jQuery('.custom-search-wrapper').css("display", "block");
    var columnvalue = jQuery("#custom-header-colmn option:selected").text();
    if(columnvalue == 'Username'){
      jQuery('.js-form-item-text').css("display", "block");
      jQuery('.js-form-item-billing-entity').css("display", "none");
      jQuery('.js-form-item-sim-partner').css("display", "none");
      jQuery('.js-form-item-partner_id').css("display", "none");
      jQuery('#custom-billingentity-colmn').prop('selectedIndex',0);
      jQuery('#custom-simpartner-colmn').prop('selectedIndex',0);
    }
    if(columnvalue == 'Billing Entity'){
      jQuery('.js-form-item-text').css("display", "none");
      jQuery('.js-form-item-billing-entity').css("display", "block");
      jQuery('.js-form-item-sim-partner').css("display", "none");
      jQuery('#custom-simpartner-colmn').prop('selectedIndex',0);
    }
    if(columnvalue == 'Sim Partner'){
      jQuery('.js-form-item-text').css("display", "none");
      jQuery('.js-form-item-billing-entity').css("display", "none");
      jQuery('.js-form-item-sim-partner').css("display", "block");
     jQuery('#custom-billingentity-colmn').prop('selectedIndex',0);
    }
    //sim line view
    if(columnvalue == 'partner_id'){
      jQuery('.js-form-item-text').css("display", "none");
      jQuery('.path-sim-line-view .js-form-item-partner-id').css("display", "block");
      jQuery('#custom-partnerid-colmn').css("display", "block");
    }

    if(columnvalue == 'iccid' || columnvalue == 'imsi' || columnvalue == 'masterid' || columnvalue == 'profile' || columnvalue == 'orderid' || columnvalue == 'locationid' || columnvalue == 'simtype' || columnvalue == 'isactive' || columnvalue == 'issuspend'){
      jQuery('.js-form-item-text').css("display", "block");
      jQuery('.path-sim-line-view .js-form-item-partner-id').css("display", "none");
      jQuery('#custom-partnerid-colmn').css("display", "none");
    }

    if(columnvalue == '- None -'){
      jQuery('.js-form-item-text').css("display", "block");
      jQuery('.js-form-item-billing-entity').css("display", "none");
      jQuery('.js-form-item-sim-partner').css("display", "none");
      jQuery('#custom-billingentity-colmn').prop('selectedIndex',0);
      jQuery('#custom-simpartner-colmn').prop('selectedIndex',0);
    }
    //portal lines vw 
    if(columnvalue == 'multi_service'){
      jQuery('.custom-search-wrapper').css("display", "none");
      jQuery('.multi_service_field_wrapper').css("display", "block");
      jQuery('#custom-multiservice-field').css("display", "block");
      jQuery('.custom-search').attr('required', false);
    }
    //profiles audit
    if (columnvalue == 'Username') {
      jQuery('.js-form-item-text').css("display", "block");
      jQuery('.path-profiles-audit .js-form-item-partner-name').css("display", "none");
      jQuery('.js-form-item-group-type').css("display", "none");
      jQuery('#custom-grouppartner-colmn').prop('selectedIndex',0);
      jQuery('#custom-simpartner-colmn').prop('selectedIndex',0);
    }
    if (columnvalue == 'Sim Profile Name') {
      jQuery('.js-form-item-text').css("display", "block");
      jQuery('.path-profiles-audit .js-form-item-partner-name').css("display", "none");
      jQuery('.js-form-item-group-type').css("display", "none");
      jQuery('#custom-grouppartner-colmn').prop('selectedIndex',0);
      jQuery('#custom-simpartner-colmn').prop('selectedIndex',0);
    }    
    if (columnvalue == 'Group Type') {
      jQuery('.js-form-item-text').css("display", "none");
      jQuery('.path-profiles-audit .js-form-item-partner-name').css("display", "none");
      jQuery('.js-form-item-group-type').css("display", "block");
      jQuery('#custom-simpartner-colmn').prop('selectedIndex',0);
    }
    if (columnvalue == 'Partner Name') {
      jQuery('.js-form-item-text').css("display", "none");
      jQuery('.path-profiles-audit .js-form-item-partner-name').css("display", "block");
      jQuery('.js-form-item-group-type').css("display", "none");
      jQuery('#custom-grouppartner-colmn').prop('selectedIndex',0);
    }
    if (columnvalue == 'Created') {
      jQuery('.js-form-item-text').css("display", "block");
      jQuery('.path-profiles-audit .js-form-item-partner-name').css("display", "none");
      jQuery('.js-form-item-group-type').css("display", "none");
      jQuery('#custom-grouppartner-colmn').prop('selectedIndex',0);
      jQuery('#custom-simpartner-colmn').prop('selectedIndex',0);
    }
    //profiles audit end
  });

  //onload default column value show
  jQuery(document).ready(function() { 
      var columnvalue = jQuery("#custom-header-colmn option:selected").text();
      if(columnvalue == 'Username'){
        jQuery('.js-form-item-text').css("display", "block");
        jQuery('.js-form-item-billing-entity').css("display", "none");
        jQuery('.js-form-item-sim-partner').css("display", "none");
        jQuery('#custom-billingentity-colmn').prop('selectedIndex',0);
        jQuery('#custom-simpartner-colmn').prop('selectedIndex',0);
      }
      if(columnvalue == 'Billing Entity'){
        jQuery('.js-form-item-text').css("display", "none");
        jQuery('.js-form-item-billing-entity').css("display", "block");
        jQuery('.js-form-item-sim-partner').css("display", "none");
        jQuery('#custom-simpartner-colmn').prop('selectedIndex',0);
      }
      if(columnvalue == 'Sim Partner'){
        jQuery('.js-form-item-text').css("display", "none");
        jQuery('.js-form-item-billing-entity').css("display", "none");
        jQuery('.js-form-item-sim-partner').css("display", "block");
       jQuery('#custom-billingentity-colmn').prop('selectedIndex',0);
      }
      if(columnvalue == '- None -'){
        jQuery('.js-form-item-text').css("display", "block");
        jQuery('.js-form-item-billing-entity').css("display", "none");
        jQuery('.js-form-item-sim-partner').css("display", "none");
        jQuery('#custom-billingentity-colmn').prop('selectedIndex',0);
        jQuery('#custom-simpartner-colmn').prop('selectedIndex',0);
      }
      //portal lines vw 
      if(columnvalue == 'multi_service'){
        jQuery('.custom-search-wrapper').css("display", "none");
        jQuery('.multi_service_field_wrapper').css("display", "block");
        jQuery('#custom-multiservice-field').css("display", "block");
        jQuery('.custom-search').attr('required', false);
      }
  });

  //user-mgmt
  jQuery('.path-user-mgmt .js-form-item-group').css("display", "none");
  jQuery('.path-user-mgmt .js-form-item-status').css("display", "none");

  jQuery('#user-mgmt-header-colmn').on('click', function () {   
    var columnvalue = jQuery("#user-mgmt-header-colmn option:selected").text();
    if(columnvalue == 'Username'){
      jQuery('.js-form-item-text').css("display", "block");
      jQuery('.path-user-mgmt .js-form-item-group').css("display", "none");
      jQuery('.path-user-mgmt .js-form-item-status').css("display", "none");
      jQuery('#custom-group-colmn').prop('selectedIndex',0);
      jQuery('#custom-status-colmn').prop('selectedIndex',0);
    }
    if(columnvalue == 'Name'){
      jQuery('.js-form-item-text').css("display", "block");
      jQuery('.path-user-mgmt .js-form-item-group').css("display", "none");
      jQuery('.path-user-mgmt. js-form-item-status').css("display", "none");
      jQuery('#custom-group-colmn').prop('selectedIndex',0);
      jQuery('#custom-status-colmn').prop('selectedIndex',0);
    }
    if(columnvalue == 'Group'){
      jQuery('.js-form-item-text').css("display", "none");
      jQuery('.path-user-mgmt .js-form-item-group').css("display", "block");
      jQuery('.path-user-mgmt .js-form-item-status').css("display", "none");
      jQuery('#custom-status-colmn').prop('selectedIndex',0);
    }
    if(columnvalue == 'Status'){
      jQuery('.js-form-item-text').css("display", "none");
      jQuery('.path-user-mgmt .js-form-item-group').css("display", "none");
      jQuery('.path-user-mgmt .js-form-item-status').css("display", "block");
      jQuery('#custom-status-colmn').prop('selectedIndex',0);
    }
  });

  //onload default column value show
  jQuery(document).ready(function() { 
      var columnvalue = jQuery("#user-mgmt-header-colmn option:selected").text();
      if(columnvalue == 'Username'){
        jQuery('.js-form-item-text').css("display", "block");
        jQuery('.path-user-mgmt .js-form-item-group').css("display", "none");
        jQuery('.path-user-mgmt .js-form-item-status').css("display", "none");
        jQuery('#custom-group-colmn').prop('selectedIndex',0);
        jQuery('#custom-status-colmn').prop('selectedIndex',0);
      }
      if(columnvalue == 'Name'){
        jQuery('.js-form-item-text').css("display", "block");
        jQuery('.path-user-mgmt .js-form-item-group').css("display", "none");
        jQuery('.path-user-mgmt .js-form-item-status').css("display", "none");
        jQuery('#custom-group-colmn').prop('selectedIndex',0);
        jQuery('#custom-status-colmn').prop('selectedIndex',0);
      }
      if(columnvalue == 'Group'){
        jQuery('.js-form-item-text').css("display", "none");
        jQuery('.path-user-mgmt .js-form-item-group').css("display", "block");
        jQuery('.path-user-mgmt .js-form-item-status').css("display", "none");
        jQuery('#custom-status-colmn').prop('selectedIndex',0);
      }
      if(columnvalue == 'Status'){
        jQuery('.js-form-item-text').css("display", "none");
        jQuery('.path-user-mgmt .js-form-item-group').css("display", "none");
        jQuery('.path-user-mgmt .js-form-item-status').css("display", "block");
        jQuery('#custom-status-colmn').prop('selectedIndex',0);
      }
  });
  //end user-mgmt

  //search colum button
  jQuery('.custom-filter-btn').on('click', function () {
    var oldURL = window.location.href;
    //alert(oldURL);
    var fieldvalue = jQuery('.custom-search').val();
    var columnvalue = jQuery("#custom-header-colmn option:selected").text();
    if(columnvalue == 'multi_service'){
      var fieldvalue = jQuery("#custom-multiservice-field option:selected").text();
    }
    //for esim query parameter getting refreshed
    document.cookie = "field=" + fieldvalue;
    document.cookie = "column=" + columnvalue;
    //end for esim query parameter getting refreshed
    if (window.location.href.indexOf("field") > -1) {
      //
      var currentUrl = window.location.href;
      var url = new URL(currentUrl);
      url.searchParams.set("field", fieldvalue); // setting your param
      url.searchParams.set("column", columnvalue); // setting your param
      url.searchParams.set("q", ''); // setting your param
      if (history.pushState) {
        var newUrl = url.href;
        window.history.pushState({ path: newUrl }, '', newUrl);
      }
      return true;
      //
    } else if (history.pushState) {
      var newUrl = oldURL + "&field=" + fieldvalue + "&column=" + columnvalue + "&q=" + '';
      //alert(newUrl)
      window.history.pushState({ path: newUrl }, '', newUrl);
    }
    return true;
  });

  //search column button end
  //export button
  jQuery('.export-btn').on('click', function () {
    jQuery('.custom-search').attr('required', false); 
    var oldURL = window.location.href;
    //alert(oldURL);
    
    var columnvalue = jQuery("#export-csv option:selected").val();
    if(columnvalue=='export_csv'){
      var fieldvalue = 'csv';
      var exportv = "&export=1";
    }
    if(columnvalue=='export_all_csv'){
      var fieldvalue = 'csv';
      var exportv = "&exportall=1";
    }
    if(columnvalue=='export_xls'){
      var fieldvalue = 'xls';
      var exportv = "&export=1";
    }
    if(columnvalue=='export_all_xls'){
      var fieldvalue = 'xls';
      var exportv = "&exportall=1";
    }

    //for esim query parameter getting refreshed
    //document.cookie = "field=" + fieldvalue;
    //document.cookie = "column=" + columnvalue;
    //end for esim query parameter getting refreshed
    //if (window.location.href.indexOf("type") > -1) {
    if (0) {
      var currentUrl = window.location.href;
      var url = new URL(currentUrl);
      //url.searchParams.set("ext", fieldvalue); // setting your param
      url.searchParams.set("type", fieldvalue); // setting your param
      url.searchParams.set("export", '1'); // setting your param
      if (history.pushState) {
        var newUrl = url.href;
        //window.history.pushState({ path: newUrl }, '', newUrl);
    window.open(newUrl, '_blank');
      }
      return true;
      //
    } else if (history.pushState) {
      var newUrl = oldURL + "&type=" + fieldvalue + exportv;
      //alert(newUrl)
      //window.history.pushState({ path: newUrl }, '', newUrl);
      window.open(newUrl, '_blank');
    }
    return true;
  });
  //export button end

  //esim preprod
  jQuery('.custom-preprod-btn').on('click', function () {
    jQuery('.custom-search').attr('required', false); 
  });

  //oss portal search reset module
  jQuery('.custom-reset-btn').on('click', function () {
    jQuery('.custom-search').attr('required', false); 
    //for esim status view page is getting refreshed that why added
    document.cookie = "field=" + null;
    document.cookie = "column=" + null;
    document.cookie = "q=" + null;
    //end for esim status view page is getting refreshed that why added  
    var oldURL = window.location.href;
    var fieldvalue = '';
    var columnvalue = '';
    var q = '';
    if (window.location.href.indexOf("field") > -1) {
      //
      var currentUrl = window.location.href;
      var url = new URL(currentUrl);
      url.searchParams.set("field", fieldvalue); // setting your param
      url.searchParams.set("column", columnvalue); // setting your param
      url.searchParams.set("q", q); // setting your param
      if (history.pushState) {
        var newUrl = url.href;
        window.history.pushState({ path: newUrl }, '', newUrl);
      }
      return true;
      //
    } else if (history.pushState) {
      var newUrl = oldURL + "&field=" + fieldvalue + "&column=" + columnvalue + "&q=" + q;
      //alert(newUrl)
      window.history.pushState({ path: newUrl }, '', newUrl);
    }
    return true;
  });

  //esim portal export
  //document.cookie = "export=" + 0;
  jQuery('.custom-export-btn').on('click', function () {
  jQuery('.custom-search').attr('required', false); 
  //document.cookie = "export=" + 1;    
  var oldURL = window.location.href;
  if (window.location.href.indexOf("field") > -1) {
    var currentUrl = window.location.href;
    var url = new URL(currentUrl);
    url.searchParams.set("export", "1"); // setting your param
    if (history.pushState) {
      var newUrl = url.href;
      //window.history.pushState({ path: newUrl }, '', newUrl);
      window.open(newUrl, '_blank');
    }
    return true;
  } else if (history.pushState) {
    var newUrl = oldURL + "&export=1";
    //window.history.pushState({ path: newUrl }, '', newUrl);
    window.open(newUrl, '_blank');
  }
  return true;    
  });
  //esim portal export end

  //upload file esim textfield hide
  jQuery('.custom-input-type').change(function(){ 
      var value = jQuery(this).val();
    if (value == 1) {
      jQuery(".js-form-item-upload-file2").hide();
      jQuery('.edit-upload-file2').attr('required', false); 
    } else {
      jQuery(".js-form-item-upload-file2").show();
    }
  });

  jQuery(".sm menu-base-theme li ul li a:not(.is-active)").click(function () {
    
      $(".has-submenu").addClass("highlighted");
  });

  /**
   * Behavior description.
   */
  Drupal.behaviors.jciusMaster = {
    attach: function (context, settings) {
      //simlineview search start
      jQuery('#simlineview-header-colmn', context).once('jciusMasterBehavior').on('change', function () {
        // Get the selected value
        var columnvalue = jQuery(this).find("option:selected").text();
        // Handle the display logic
        if (columnvalue === 'partner_id') {
          jQuery('.js-form-item-text', context).hide();
        } else if (columnvalue === 'isactive') {
          jQuery('.js-form-item-text', context).hide();
        } else if (columnvalue === 'enabled') {
          jQuery('.js-form-item-text', context).hide();
        } else {
          jQuery('.js-form-item-text', context).show();
          jQuery('#simlineview-partner-colmn', context).prop('selectedIndex', 0);
          jQuery('#sim_line_view_isactive', context).prop('selectedIndex', 0);
          jQuery('#sim_line_view_enabled', context).prop('selectedIndex', 0);
        }
      });
      //simlineview search end

      //export button
      document.cookie = "export=" + 0;
      jQuery('.custom-export-btn').on('click', function () {
        jQuery('.custom-search').attr('required', false); 
        document.cookie = "export=" + 1;
      });
      //esim-generate-checkbox
      jQuery('.js-form-item-custom-file-name').css("display", "none");
      jQuery('.custom-default-generated').on('click', function () {
        jQuery('input:checkbox[name=default_generated]').each(function() 
        {    
            if(jQuery(this).is(':checked')){
              //alert(jQuery(this).val());          
              jQuery('.js-form-item-custom-file-name').css("display", "block");
              jQuery('.custom-file-name').css("display", "block");
              jQuery('.custom-file-name').attr('required', true);     
            }
            else{
              jQuery('.custom-file-name').attr('required', false);            
              jQuery('.js-form-item-custom-file-name').css("display", "none");
              
            }
        });
      });
    }
  };
}) (jQuery, Drupal, drupalSettings);
