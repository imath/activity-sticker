jQuery(document).ready(function($){
	
	if( $('#prepend-sticky').length ) {
		$('#bp-activities-form table.activities').prepend( $('#prepend-sticky').html());
		$('#prepend-sticky').remove();
	}
	
	if( $('.sticky-activity ul#activity-sticker').length ) {

		$('.sticky-activity ul#activity-sticker').bind("ajaxComplete", function(){
			
   			if( !$.cookie( "bp-activity-scope") || $.cookie( "bp-activity-scope") == 'all' )
   				$(this).parent().removeClass('sticky-hide');
   			else
   				$(this).parent().addClass('sticky-hide');
 		});
	}

	
});