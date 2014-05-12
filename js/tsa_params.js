/**
 * <p>ThrowsSpamAway</p> JavaScript
 * WordPress's Plugin
 * @author Takeshi Satoh@GTI Inc. 2014
 * @since version2.6
 *
 * -- updated --
 * 2014/05/10 debug for IE8
*/

jQuery(function($){
    $('.tsa_param_field_tsa_2').hide();	// hide
    $('.tsa_param_field_tsa_2 input#tsa_param_field_tsa_3').val( $('.tsa_param_field_tsa_ input').val() );	// copy

    var date = new Date();
    var iso = null;
    if ( typeof date.toISOString != 'undefined' ) {
    	iso = date.toISOString().match(/(\d{4}\-\d{2}\-\d{2})T(\d{2}:\d{2}:\d{2})/);
        current_date = iso[1] + ' ' + iso[2];
        if ( $('#comments form input#tsa_param_field_tsa_3').length == 0 ) {
            $('#comments form').append('<input type="hidden" name="tsa_param_field_tsa_3" id="tsa_param_field_tsa_3" value="'+current_date+'" />'); // add to comment form
        }

        if ( $('#respond form input#tsa_param_field_tsa_3').length == 0 ) {
            $('#respond form').append('<input type="hidden" name="tsa_param_field_tsa_3" id="tsa_param_field_tsa_3" value="'+current_date+'" />'); // add to comment form
        }

        if ( $('form#commentform input#tsa_param_field_tsa_3').length == 0 ) {
            $('form#commentform').append('<input type="hidden" name="tsa_param_field_tsa_3" id="tsa_param_field_tsa_3" value="'+current_date+'" />'); // add to comment form
        }
    }

});