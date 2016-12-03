<?php

/*
Plugin Name: DataCube Dashboard
Description: Plugin Enabling Monitoring Of Backups For DataCube
Version:     1.5
Author:      Elijah Mills
Author URI:  http://pressology.io
License:     GPL2
License URI: https://www.gnu.org/licenses/gpl-2.0.html
*/

define('plugin_url', plugin_dir_url( __FILE__ ));
add_action( 'admin_menu', 'datacube_dashboard_menu' );
add_action( 'wp_ajax_dc_build_view', 'dc_build_view' );
add_action( 'wp_ajax_dc_email_handler', 'dc_email_handler');
add_action( 'wp_ajax_dc_refresh_alerts', 'dc_refresh_alerts' );
add_action( 'wp_ajax_dc_file_upload' , 'dc_file_upload' );
add_action( 'wp_mail_failed', 'dc_mail_failed', 10, 1 );

function dc_email_handler () {
	
	//Let's determine what type of e-mail we're sending
	$command = $_POST['command'];
	$emailbase = "<p><b> This is an alert to inform you of ERRORS during your DataCube Backup.</b></p>
				  \r\n\r\n<p>You are receiving this message because one or more of your clients did not
				  backup properly on the last backup cycle. The DataCube team will be glad to help you
				  resolve these errors, simply respond to this e-mail or call us at 1-812-662-7996 to
				  schedule remote maintenance.</p> \r\n <p><b>Error Details Below</p></b>";
	$emailbody = '';
	$emailtitle = 'DataCube Backup Alert - Errors Detected During Backup';
	$headers = array(
		'From: DataCube Support <smidatacube@gmail.com>',
		'Content-type: text/html',
		'Cc: smidatacube@gmail.com'
	);

	//Now let's do something based on the command we've received, either BRAND or CLIENT
	if ( $command == 'brand' ) {

		$data = $_POST['data'];

		//Let's retrieve the brand object based on the $data

		$brand = get_term_by('name', $data, 'brand');
		$brandemail = array(get_field('main_email', $brand),
							get_field('secondary_email', $brand));

		//Now we retrieve all posts belonging to this brand

		wp_reset_query();
		$args = array(
			'post_type' => 'client',
			'numberposts' => -1,
			'tax_query' => array(
				array(
					'taxonomy' => 'brand',
					'field' => 'slug',
					'terms' => $brand
					)
				)
		);

		$clients = get_posts( $args );

		foreach ( $clients as $client ) {

			//Let's get the days_since_backup field for the client
			$daysout = get_post_meta($client->ID, 'days_since_backup', true);
			$exclude = get_post_meta($client->ID, 'exclude_from_alerts', true);

			// Check for clients in the brand with more than 6 days since last backup
			// And then build the e-mail string on a new line and append it to $emailbody
			if ( $daysout > 6 && $exclude == false ) {
				
				// If the client has an e-mail address in the e-mail override field, send it individually to that address.
				if ( !empty( get_field( 'email_override', $client->ID ) ) ) {

					$clientemail = get_field( 'email_override' , $client->ID );
					wp_mail(
					$clientemail,
					'DataCube Backup Alert for client ' . $client->post_title,
					'<p><b> This is an alert to inform you of ERRORS during your DataCube Backup.</b></p>
				  	<p>You are receiving this message because one or more of your clients did not
				  	backup properly on the last backup cycle. The DataCube team will be glad to help you
				  	resolve these errors, simply respond to this e-mail or call us at 1-812-662-7996 to
				 	schedule remote maintenance.</p><p><b>Error Details Below</b></p>
					<p> - <b>Client ' . $client->post_title . ' has not backed up for <mark>' . $daysout . ' days</mark>.</b> Please ensure
					this device is remaining powered on and connected to the internet. If it is remaining powered on and
					connected to the internet, please contact us so we can assist in resolving issues with the backup. </p>',
					$headers);
					}

				else {

				$emailbody .= "\r\n<p> - <b>Client " . $client->post_title . " has not backed up for <mark>" . $daysout . "</mark> days.</b> Please ensure
				this device is remaining powered on and connected to the internet. If it is remaining powered on and
				connected to the internet, please contact us so we can assist in resolving issues with the backup.</p>\r\n";
				
				}

			}

		}
		
		if ( empty( $emailbody ) && get_field('notify_of_successful_backup', $brand) == true ) {
			
			// If no alerts (i.e. $emailbody is empty), then we send an "All Good" e-mail to the brand
			// e-mail, but only IF the brand has "Alert on Success" checked.
			$emailtitle = "DataCube Backup - No Errors Detected";
			$emailbody .= "Your DataCube backup did not report any errors.";
			$emailbase  = "";
			
		}

		// Send the brand-wide e-mail alert now!
		if ( !empty( $emailbody ) ) {

			wp_mail(
			$brandemail,
			$emailtitle,
			$emailbase . $emailbody,
			$headers
			);
			
		}
		

		// Let's make sure we store the fact that we've sent this e-mail alert.
		update_field( 'alert_sent', '1', $brand );

	}

	else if ( $command == 'client' ) {
		//Do something
	}

	wp_die();

}

function dc_mail_failed() {

	$string = "<entry> Entry success \r\n";
	update_field( 'email_log', $string, 'DC000' );

}

function dc_refresh_alerts() {

	$brands = get_terms('brand');

	foreach ( $brands as $brand ) {
		update_field( 'alert_sent', '0', $brand );
	}

}

function datacube_dashboard_menu() {
    add_menu_page( 'DataCube Dashboard', 'DataCube', 'manage_options', 'datacube-dashboard', 'datacube_build_page' );
}

function load_dc_scripts() {
	wp_register_script('dc-dash-js', plugin_dir_url( __FILE__ ) . 'datacube-dashboard.js', array('jquery'));
	wp_enqueue_script('dc-dash-js');
	wp_enqueue_style( "dcdash-style", plugin_dir_url( __FILE__ ) . "dcdash-style.css" );
}

function datacube_build_page() {
	load_dc_scripts();
	?>
	<div id="wrapper">
		<div id="dc_header_wrapper">
		<div id="dc_title">
		<h1> Datacube Dashboard </h1>
		</div>
		<div id="dc_upload">
		<form id="dc_upload_form">
		<input id="dc_client_import" type="file" name="client_import">
		<input type="submit" id="dc_submit_btn">
		</div>
		</div>
		</div>
		<div id="dc_dash_nav">
		<a class="dc_dash_nav_btn" id="client_btn">Client</a>
		<a class="dc_dash_nav_btn" id="brands_btn">Brands</a>
		<a class="dc_dash_nav_btn" id="goto_dash" href="https://dash.backupops.com/xfer/" target="_blank">Go To Dash</a>
		</div>
		<div id="all-the-content">
		Welcome to the DataCube Dashboard.
		</div>
	</div>
	<?php
	
}

function dc_build_view() {
	
	if($_POST['view'] == 'brand') {
		?>
	
		<h2>Brand View</h2>
		<div class='horizontal-line'></div>
		<div id='brand-list'>
			<table class="wp-list-table widefat fixed striped posts" id="brand-list">
				<thead>
					<th scope="col" id="brand">Brand</th>
					<th scope="col" id="clients">Clients</th>
					<th scope="col" id="errors">Errors</th>
					<th scope="col" id="alert">Alert</th>
				</thead>
				<tbody id="the-list">
				<?php
				$brands = get_terms('brand');

				foreach( $brands as $brand ) {
					wp_reset_query();
					$args = array(
						'post_type' => 'client',
						'numberposts' => -1,
						'tax_query' => array(
							array(
								'taxonomy' => 'brand',
								'field' => 'slug',
								'terms' => $brand
							)
						)
					);

					$clients = get_posts( $args );
					$totalclients = 0;
					$errclients = 0;
					$alertsent = get_field( 'alert_sent', $brand );
					$alertmsg = 'null';
					$rowcss = '';

					foreach( $clients as $client ) {
						
						$totalclients++;
						
						$clienterr = get_post_meta( $client->ID, 'days_since_backup', true );
						$exclude = get_post_meta( $client->ID, 'exclude_from_alerts', true);
						
						if( $clienterr >= 7 && $exclude == false ) {

							$errclients++;

						}

					}

					if ( $errclients != 0 ) {
						$rowcss = 'dc_dash_err_row';
					}

					if ( $errclients > 0 ) {
						if ( $alertsent == true ) {
							$alertmsg = 'Alert sent.';
							$rowcss = 'dc_dash_semi_row';
						}
					
						else if ( $alertsent == false ) {
							$alertmsg = '<button class="send-alert" name="brand" value="' . $brand->name . '">Send Alert</button>';
						}
					}

					else {
						$alertmsg = 'No alerts needed.';
					}

					?>
						<tr id="brand" class="<?php echo $rowcss; ?>">
							<td><?php echo $brand->name; ?></td>
							<td><?php echo $totalclients; ?></td>
							<td><?php echo $errclients; ?></td>
							<td><?php echo $alertmsg; ?></td>
						</tr>
					<?php
	
				}

				?>
				</tbody>
			</table>
		</div>
		
		<?php
	}
	
	else if($_POST['view'] == 'client') {
		?>
		
		<h2>Client View</h2>
		<div class='horizontal-line'></div>
				<table class="wp-list-table widefat fixed striped posts" id="brand-list">
				<thead>
					<th scope="col" id="client">Client</th>
					<th scope="col" id="errors">Errors</th>
					<th scope="col" id="brand">Brand</th>
					<th scope="col" id="alert">Alert</th>
				</thead>
				<tbody id="the-list">
					<?php

					$args = array(
						'post_type' => 'client',
						'numberposts' => -1,
						'orderby' => 'title',
						'order' => 'ASC'
					);
	
					$clients = get_posts ( $args );
	
					foreach ( $clients as $client ) {

						$daysout = get_post_meta( $client->ID, 'days_since_backup', true);
						$exclude = get_post_meta( $client->ID, 'exclude_from_alerts', true);

						$terms = get_the_terms( $client->ID, 'brand' );
						$term = array_pop( $terms );
						$brand = $term->name ;
						$alertstatus = get_field( 'alert_sent', $term );
						$name = $client->post_title;

						if ( $daysout >= 7 && $exclude == false ) {

						?>
					
						<tr id="client" >
						<td><?php echo $name; ?></td>
						<td><?php echo $daysout . ' days since backup.'; ?></td>
						<td><?php echo $brand; ?></td>
						<td><?php if ( $alertstatus == true ) { echo 'Alert sent.'; } else { echo 'Alert NOT sent.'; } ?></td>
						</tr>

						<?php

						}

					}

					?>
				</tbody>
			</table>
		
		<?php
	}
	
	wp_die();
}

function dc_file_upload() {

	//echo $_FILES['userfile']['name'];
	echo 'Hello.';
	wp_die();

}