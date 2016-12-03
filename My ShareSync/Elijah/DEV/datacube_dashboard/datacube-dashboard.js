
jQuery.noConflict();

jQuery(document).ready(function($) {
	
	//DASHBOARD OVERVIEW
	
	//Individual "Send E-mail" Buttons on Dashboard Overview
	//These are written to allow the on click binding to apply to dynamically created buttons as well		
    $("#all-the-content").on('click', '.send-alert',function() {

		var button = $(this);

	    $.post(
			ajaxurl,
				{
					'action':'dc_email_handler',
					'command':$(this).attr('name'),
					'data':$(this).attr('value')
				}
			).done( function( data ) {

			//alert( data );
			$(button).parent().html('Alert sent.');

			});

	});
	
	//NAV and Page Building. NOT dynamically bound.
	$("#brands_btn").click( function() {
		
		$.ajax({
			type: "POST",
			url: ajaxurl,
			data: {
				action: "dc_build_view",
				view: "brand"
			}
		}).done( function( data ) {
			//alert( "Return = " + data);
			$("#all-the-content").html(data);
		})
		
	});
	
	$("#client_btn").click( function() {
		
		$.ajax({
			type: "POST",
			url: ajaxurl,
			data: {
				action: "dc_build_view",
				view: "client"
			}
		}).done( function( data ) {
			//alert( "Return = " + data);
			$("#all-the-content").html(data);
		})
		
	});

	$("#refresh_btn").click( function() {

		$.ajax({
			type: "POST",
			url: ajaxurl,
			data: {
				action: "dc_refresh_alerts",
			}
		}).done( function( data ) {
			alert( "All brands alert status reset." );
		})

	});

	$("#dc_upload_form").submit( function( event ) {
		
		event.preventDefault();

		var file_data = $('#dc_client_import').prop('files')[0];
		var form_data = new FormData();
		form_data.append('file', file_data);

		alert(form_data);

		$.ajax({
			url: ajaxurl,
			dataType: "text",
			cache: false,
			contentType: false,
			processData: false,
			data: form_data,
			type: "POST"
		}).success( function( data ) {
			alert( data );
		});

	});
			
});