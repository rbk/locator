<?php

/*
Plugin Name: GuRuStu Food Bank Locator
Plugin URI: 
Description: 
Version: 1
Author: Richard Keller
Author URI: 
*/
/*

Notes:

- Help making this work:
	https://developers.google.com/maps/articles/phpsqlsearch_v3
	http://www.movable-type.co.uk/scripts/latlong.html


- Algorithm for actual locator:
	1. get lat/lng of zip code entered, validate
	2. get chosen distance from user
	3. query db with harvesines formula

		SELECT id, ( 3959 * acos( cos( radians(37) ) * cos( radians( lat ) ) * cos( radians( lng ) - radians(-122) ) + sin( radians(37) ) * sin( radians( lat ) ) ) ) 
		AS distance FROM markers HAVING distance < 25 ORDER BY distance LIMIT 0 , 20;


	4. loop through results of query to create markers
	5. show map
	6. display locations found as a list below map

- Need to make editing locations possible
- Should make this object oriented 



*/

global $wpdb;

if( ! defined('GURU_LOCATOR_TABLE') )
	define('GURU_LOCATOR_TABLE', $wpdb->prefix . 'gurustu_locations' );

add_action( 'admin_menu', 'init_gurustu_locator_plugin' );

register_activation_hook( __FILE__,  'gurustu_install_locator_plugin' );

function init_gurustu_locator_plugin(){
	global $locator;
	$locator = add_menu_page( 'Locator', 'Locator', 'manage_options', 'locator', 'render_admin_page', '', 100 );
}

// Create table on activation
function gurustu_install_locator_plugin(){
	
	global $wpdb;

	// if( ! defined('GURU_LOCATOR_TABLE') )
	// 	define('GURU_LOCATOR_TABLE', $wpdb->prefix . 'gurustu_locations' );

	$table_name = GURU_LOCATOR_TABLE;

	if($wpdb->get_var("show tables like '$table_name'") != $table_name) {

		// Remeber to edit data fields array when adding to column to table
		$query = "create table $table_name (
				id INT NOT NULL AUTO_INCREMENT,
				name VARCHAR(60) NOT NULL,
				address VARCHAR(80) NOT NULL,
				city VARCHAR(60) NOT NULL,
				state VARCHAR(60) NOT NULL,
				zip NUMERIC(10),
				phone VARCHAR(20),
				lat FLOAT(10,6) NOT NULL,
				lng FLOAT(10,6) NOT NULL,
				PRIMARY KEY id (id)
			)";
		$wpdb->query( $query );

	}

	require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

	dbDelta($query);
}

// Gotta have our javascript
function gurustu_load_ajax_scripts( $hook ){

	global $locator;

	if( $hook != $locator )
		return;
	
	wp_enqueue_script( 'data-tables', plugin_dir_url(__FILE__) . 'js/jquery.dataTables.min.js', array('jquery') );

	wp_enqueue_script( 'guru_locator_google_maps_api_v3_backend', 'https://maps.googleapis.com/maps/api/js?key=AIzaSyAIKWLF2zm-hukmc6Z9eTDhL6Xfg1iXz_0&sensor=true', array('jquery') );
	wp_enqueue_script( 'backend_locator_scripts', plugin_dir_url(__FILE__) . 'js/admin_locator_ajax.js', array('jquery') );
	wp_localize_script( 'backend_locator_scripts', 'gurustu_vars', array('guru_nounce' => wp_create_nonce( 'guru_nounce' ) ) );

}
add_action( 'admin_enqueue_scripts', 'gurustu_load_ajax_scripts' );

// Add ajax url to frontend
function gurustu_add_ajax_url_to_frontend() {

	wp_enqueue_script( 'guru_locator_google_maps_api_v3', 'https://maps.googleapis.com/maps/api/js?key=AIzaSyAIKWLF2zm-hukmc6Z9eTDhL6Xfg1iXz_0&sensor=true', array('jquery') );
	wp_enqueue_script( 'frontend_locator_scripts', plugin_dir_url(__FILE__) . 'js/frontend_ajax.js', array('jquery') );
	wp_localize_script( 'frontend_locator_scripts', 'guru_ajaxurl', array('ajaxurl' => admin_url( 'admin-ajax.php' ), 'nonce' => wp_create_nonce( 'guru_nounce' ) ) );

}
add_action( 'wp_enqueue_scripts', 'gurustu_add_ajax_url_to_frontend' );

// Render the options page
function render_admin_page(){
	
	// Must change database tables to add new fields
	$fields = array( 
		'name' => 'Location Name',
		'address' => 'Address',
		'city' => 'City',
		'state' => 'State',
		'zip' => 'Zip Code',
		'phone' => 'Phone number',
		'lng' => 'longitude',
		'lat' => 'latitude'
		// 'hours' => 'Hours',
		// 'phone' => 'Phone Number',
		// 'url' => 'Website',
		// 'fax' => 'Fax',
		// 'director' => 'Director',
		// 'description' => 'Description'
	);

?>
<link rel="stylesheet" type="text/css" href="<?php echo plugin_dir_url(__FILE__) ?>/css/app.css">
<script>

	// Tabs yay
	jQuery(document).ready(function($){
		var tabs = $('.tab');
		$('.tab').click(function(e){
			e.preventDefault();
			$('#message').html('').hide();
			if( ! $(this).hasClass('current') ) {
				tabs.each(function(){
					$(this).removeClass('current');
				})
				$(this).addClass('current');

				$('div.current').hide().removeClass('current');
				var page = $(this).attr('href');
				$('#' + page).fadeIn().addClass('current');

				// Refresh list because we are using ajax
				// if( $(this).attr('href') == 'locations_list' )
					// location.reload();
			}
		});

	});

</script>
<div class="wrap">
	<div id="icon-edit-pages" class="icon32 icon32-posts-page"><br></div>
		
		<h2>Locator</h2>
		<nav id="locations-nav">
			<a href="locations_list" class="tab current">Locations</a>
			<a href="add_location" class="tab">Add Location</a>
			<!-- <a href="import_csv" class="tab">Import</a> -->
		</nav>
		<div id="message" style="display:none;" class="updated below-h2"></div>
		<!-- template -->
		<div id="add_location" class="" style="display:none;">
			<div class="col-1">
				<form action="" method="post" name="location_form" id="location_form">
					<?php 

						foreach( $fields as $label => $field ){ ?>
							<label for="<?php echo $label ?>"><?php echo $field ?></label>
							<input name="<?php echo $label ?>" type="text" id="<?php echo $label ?>" class="" /><br/>
							<?php
						}

					?>
					<input type="hidden" name="lat" id="lat" value="" />
					<input type="hidden" name="lng" id="lng" value="" />

					<input type="submit" id="submit" class="button-primary" value="Add A Location"/>
					<a id="map_preview_button" class="button-primary">Preview Map</a>
					<img id="ajax-loader" src="<?php echo admin_url();?>/images/wpspin_light.gif" style="display:none;"/>
				</form>
				
			</div>
			<div class="col-2">
				<div id="map_preview">Map Preview</div>
			</div>
		</div>
		
		<!-- template -->
		<div id="locations_list" class="current">

			<div id="message" class="updated">Please delete and re-enter locations with red highlight on coordinates</div>

			<table id="print_locations" class="widefat fixed" cellspacing="0">
				<thead>
					<tr>
						<th>Name</th>
						<th>Address</th>
						<th>City</th>
						<th>State</th>
						<th>Zip</th>
						<th>Coordinates</th>
						<th></th>
					</tr>
				</thead>
				<tbody class="the-list">
					<?php 

					$fields = gurustu_get_locations();

					if( $fields ) :

						foreach( $fields as $field ) : ?>
							<tr href="<?php echo $field['id'] ?>" id="<?php echo $field['id'] ?>">
								<td><?php echo $field['name'] ?></td>
								<td><?php echo $field['address'] ?></td>
								<td><?php echo $field['city'] ?></td>
								<td><?php echo $field['state'] ?></td>
								<td><?php echo $field['zip'] ?></td>
								<?php if( $field['lng'] == 0 || $field['lat'] == 0 ) : ?>
									<td class="red"><?php echo $field['lng'] . ',' . $field['lat']; ?></td>
								<?php else : ?>
									<td><?php echo $field['lat'] . ',' . $field['lng']; ?></td>
								<?php endif; ?>
							
								<td><a href="<?php echo $field['id'] ?>" class="edit button-primary">Edit</a>&nbsp;&nbsp;&nbsp;&nbsp;
								<a href="<?php echo $field['id'] ?>" class="delete button-primary">Delete</a></td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</div><!-- end list template -->

		<div id="import_csv" class="" style="display:none">

			<form id="import_csv" action="" method="post" enctype="multipart/form-data">
				<input type="file" name="csv-file" id="csv-file"/>
				<input type="submit" class="button-primary" value="Import CSV"/>
				<img id="ajax-loader" src="<?php echo admin_url();?>/images/wpspin_light.gif" style="display:none;"/>
			</form>

		</div>


</div><!-- end wrap -->

<?php
} // end render plugin page



	function gurustu_save_location(){

		global $wpdb;

		if( !isset( $_POST['guru_nounce'] ) || ! wp_verify_nonce( $_POST['guru_nounce'], 'guru_nounce' ) )
			die('Permissions Denied');

		$table_name = GURU_LOCATOR_TABLE;
		$form = $_POST['form'];
		$save_form = array();

		for( $i = 0; $i < count( $form ); $i = $i + 1 ){
				$save_form[$form[$i]['name']] = $form[$i]['value'];
		}

		if( isset( $_POST['id'] ) ) {

			$where = array( 'id' => $_POST['id'] );
			$wpdb->update( $table_name, $save_form, $where, $format = null, $where_format = null );

		} else {

			$wpdb->insert( 
				$table_name, 
				$save_form
			);

		}

		die();
	}
	add_action('wp_ajax_gurustu_save_location', 'gurustu_save_location');



	// Deleted location from database
	function gurustu_delete_location(){
		global $wpdb;
		$table_name = GURU_LOCATOR_TABLE;
		$wpdb->query("DELETE FROM $table_name WHERE id =" . $_POST['id'] );
		die();
	}
	add_action('wp_ajax_gurustu_delete_location', 'gurustu_delete_location');

	// Retrieves all locations from database
	function gurustu_get_locations(){

		global $wpdb;
		$table_name = GURU_LOCATOR_TABLE;
		$all_locations = $wpdb->get_results("SELECT * FROM $table_name",ARRAY_A);
		
		if( $all_locations ) {
			return $all_locations;
		} else {
			return false;
		}

	}

	/*
	 *
	 * Gets single location from db to edit
	 *
	*/
	function gurustu_single_location(){

		global $wpdb;
		$id = $_POST['id'];
		$table_name = GURU_LOCATOR_TABLE;
		$fields = $wpdb->get_row("SELECT * FROM $table_name WHERE id = $id", ARRAY_A);

		?>
		<div class="edit-location">
			<span class="x">X</span>
			<h2>Edit</h2>
			<form id="edit-location" method="" action="">
		<?php foreach( $fields as $field => $value ) : ?>
				
				<?php if( $field == 'id' ) : ?>
					<input type="hidden" id="<?php echo $field; ?>" name="<?php echo $field; ?>" value="<?php echo $value; ?>" />
				<?php else : ?>
					<div>
						<label><?php echo $field; ?></label>
						<input type="text" id="<?php echo $field; ?>" name="<?php echo $field; ?>" value="<?php echo $value; ?>" />
					</div>

				<?php endif; ?>

		<?php endforeach; ?>
				<input type="button" id="remove" class="button cancel" value="Cancel"/>
				<input type="submit" id="edit-save" class="button" value="SAVE"/>
			</form>
		</div>
		<?php die();

	}
	add_action('wp_ajax_gurustu_single_location', 'gurustu_single_location');


	function gurustu_import_csv(){

		global $wpdb;
		$table_name = GURU_LOCATOR_TABLE;
		$filename = plugin_dir_url(__FILE__) . 'Get-Help-List.csv';
		$file = file( $filename );
		foreach( $file as $line ){
			$field = explode( ',', $line );

			$address = $field[2] . ',' . $field[3] . ',' . $field[4];
			$address = urlencode($address);
			$loc = geocoder::getLocation($address);
			error_log( $address );
			$wpdb->query( $wpdb->prepare( 
				"
					INSERT INTO $table_name
					( name, address, city, state, zip, phone, lat, lng )
					VALUES ( %s, %s, %s, %s, %d, %s, %F, %F )
				", 
			    $field[1],$field[2],$field[3],$field[4],$field[5],$field[6],$loc['lng'],$loc['lat']
			) );
			
			

		}
		die();
	}
	add_action('wp_ajax_gurustu_import_csv', 'gurustu_import_csv');


	// this pulls locations for the front end
	function gurustu_find_location(){

		global $wpdb;
		$table_name = GURU_LOCATOR_TABLE;

		$lat = $_POST['lat'];
		$lng = $_POST['lng'];
		$radius = $_POST['radius'];
		$radius = $radius / 2;


		$query = "SELECT id, name, address, city, state, zip, phone, lat, lng, 
		( 3959 * acos( cos( radians( $lng ) ) * cos( radians( lat ) ) * cos( radians( lng ) - radians( $lat ) ) + sin( radians( $lng ) ) * sin( radians( lat ) ) ) ) 
		AS distance FROM $table_name HAVING distance < $radius ORDER BY distance LIMIT 0 , 10";
		
		// $query = "SELECT id, name, address, city, state, zip, lat, lng FROM $table_name";

		$result = $wpdb->get_results( $query, OBJECT );

		echo json_encode($result);
		// echo $result;

		die();
	}
	add_action('wp_ajax_gurustu_find_location', 'gurustu_find_location');
	add_action('wp_ajax_nopriv_gurustu_find_location', 'gurustu_find_location');
	
	


	//[the_locator]
	function show_gurustu_locator( $atts ){

		extract( shortcode_atts( array(
      		'height' => '400px',
      		'id' => 'locator_map_canvas'
      	), $atts ) );
		?>
		<style type="text/css">
			#locator_map_canvas { height: <?php echo esc_attr($height); ?>;}
			#location_search_form input, #location_search_form select {
				float: left;
				margin-right: 5px;
			}
			#location_search_form input[type=text]{font-size:18px;font-size: 1.8rem;}
			#locator_map_canvas {
				width: 100%;
				
			}
			#locator_map_canvas img {	
				max-width: none;
			}
			#locator_map_canvas {
				width: 64%;
				float:left;
			}

		</style>

		<form id="location_search_form" action="<?php echo get_permalink( $post->ID ) ?>" method="GET">
			<select id="radiusSelect">
				<option value="1" selected>1 mile</option>
				<option value="5">5 miles</option>
				<option value="10">10 miles</option>
				<option value="20">20 miles</option>
			</select>	
			<label for="locator_zip"></label>
			<input type="text" name="locator_zip" id="locator_zip" placeholder="Address/Zipcode" value="" />
			<input type="submit" id="gurustu_address_submit" value="Find a Food Pantry" />
		</form>
		<div id="locator_message_box">Enter your address or zip code to find a food pantry.</div>
		<div id="locator_module">
			<div id="locator_map_canvas"></div>
			<div id="locations_list"></div>
		</div>
    
    <script type="text/javascript">
    jQuery(document).ready(function($){
    	  $('#locator_map_canvas').googleMap({
    	     'address' : 'United States',
    	     'zoom' : 3 
    	   });

			$('#radiusSelect').val(5);
			$('#locator_zip').val(74120);
			$('#gurustu_address_submit').trigger('click');


  	});
    </script>

		<?php
	}
	add_shortcode( 'the_locator', 'show_gurustu_locator' );
	

	// Slightly different shortcode for the widget
	function show_gurustu_locator_widget( $atts ){

		extract( shortcode_atts( array(
      		'height' => '400px'
      	), $atts ) );
		?>
		<style type="text/css">
			#locator_map_canvas_widget { 
				height: <?php echo esc_attr($height); ?>;
			}
			.location_search_form_widget input[type=text]{
				font-size:18px;
				font-size: 1.8rem;
			}
			#locator_map_canvas_widget {
				width: 100%;
			}
			#locator_map_canvas_widget div div.gmnoprint div span, #locator_map_canvas_widget div div.gmnoprint div span a {
				font-size: 10px !important;
			}
			#locator_map_canvas_widget img {	
				max-width: none;
			}
			#locator_map_canvas_widget .gmnoprint {
				display: none;
			}
			.location_search_form_widget input {
				width: 100%;
				margin-top: 5px;
			}
		</style>

		<div id="locator_map_canvas_widget"></div>
		<div class="location_search_form_widget">
			<form id="location_search_form_widget" action="" method="GET">
				<label for="locator_zip" style="display:none;"></label>
				<input type="text" name="locator_zip_widget" id="locator_zip_widget" placeholder="Enter Zip/Postal Code" value="" />
				<input type="submit" id="gurustu_address_submit_widget" value="Find a Food Pantry" />
			</form>
		</div>
		<br/>
	    <script type="text/javascript">
	    		jQuery(document).ready(function($){
			      $('#locator_map_canvas_widget').googleMap({
			         'address' : 'Tulsa, OK',
			         'zoom' : 10 
			       });
	    		});
	    </script>
		<?php
	}
	add_shortcode( 'the_locator_widget', 'show_gurustu_locator_widget' );


// Helper class thanks to Sergiy Dzysyak, geocoding on the server side

// http://erlycoder.com/45/php-server-side-geocoding-with-google-maps-api-v3
class geocoder{
    static private $url = "http://maps.google.com/maps/api/geocode/json?sensor=false&address=";

    static public function getLocation($address){
        $url = self::$url.$address;
        // error_log($url);

        $resp_json = file_get_contents($url);
        // $resp_json = self::curl_file_get_contents($url);
        $resp = json_decode($resp_json, true);

        print_r( $resp['results'] );
        error_log( $resp['results'][0]['location'] );

        if($resp['status']='OK'){
            return $resp['results'][0]['geometry']['location'];
        }else{
            return false;
            error_log( 'Location not found' );
        }
    }


    static private function curl_file_get_contents($URL){
        $c = curl_init();
        curl_setopt($c, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($c, CURLOPT_URL, $URL);
        $contents = curl_exec($c);
        curl_close($c);

        if ($contents) return $contents;
            else return FALSE;
    }
}
?>