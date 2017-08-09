/**
 * Admin interface enhacments
 */
jQuery(document).ready(function($) {
	/* currently not used
    jQuery('.hide-year.datepicker').datepicker({
        dateFormat : 'mm/dd',
        beforeShow: function(input, inst) {
        	inst.dpDiv.addClass('hide-year')
        }
    });
    */
	
	// prepare the form for file upload
	var form = $("form[action='options.php']");
	form.attr('enctype', 'multipart/form-data');
    
	
    $('.scd-er-hide-control').on('change', function() {
    	var $this = $(this);
    	var value = $this.val();
    	var table = $this.closest('table');
    	table.find('.scd-gc-hide').hide();
    	switch(value) {
    	case 'api_key' : 
    		table.find('.scd-gcal-general').show();
    		table.find('.scd-gcal-api-key').show();
    		table.find('.scd-gcal-link-title').show();
    		
    		break;
    	case 'oauth' :
    		table.find('.scd-gcal-general').show();
    		table.find('.scd-gcal-auth-email').show();
    		table.find('.scd-gcal-api-key-file').show();
    		table.find('.scd-gcal-colors').show();
    	
    		break;
    	default :
    		// disabled
    	};
    });
    $('.scd-er-hide-control').trigger('change');
    
    $('.refresh-p12-file').on('click', function(ev) {
    	var btn = $(ev.target);
    	btn.siblings('.upload-p12-file').show();
    	btn.hide();
    });
    
});