<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Shippify Checkout class handles the Checkout page action and filter hooks.
 * @since   1.0.0
 * @version 1.0.0
 */

class WC_Shippify_Checkout {

	/**
	 * Adding Actions and Filters
	 */
    public function __construct() {

        add_filter( 'woocommerce_checkout_fields' , array( $this, 'customize_checkout_fields' ) );
        add_action( 'woocommerce_after_order_notes', array( $this, 'display_custom_checkout_fields' ) );
        add_action( 'woocommerce_checkout_update_order_meta', array( $this, 'save_custom_checkout_fields' ) );  

        //Enqueueing CSS and JS files
        wp_enqueue_script( 'wc-shippify-checkout', plugins_url( '../assets/js/shippify-checkout.js', __FILE__ ), array( 'jquery' ) ); 
        wp_enqueue_style( 'wc-shippify-map-css', plugins_url( '../assets/css/shippify-checkout.css', __FILE__ ) ); 
        wp_enqueue_script( 'wc-shippify-map-js', plugins_url( '../assets/js/shippify-map.js' , __FILE__ ) );

        add_action( 'woocommerce_after_checkout_form', array ( $this,'add_map' ) );
        add_action( 'woocommerce_checkout_process', array ( $this,'shippify_validate_order' ) , 10 );
		add_filter( 'woocommerce_cart_shipping_method_full_label', array( $this, 'change_shipping_label' ), 10, 2 );
		add_action( 'woocommerce_checkout_update_order_review', array( $this, 'action_woocommerce_checkout_update_order_review' ), 10, 2 );
		
    }		      

	/**
	 * Hooked to Action: woocommerce_checkout_update_order_review
	 * Everytime the order is updated, if Shippify is selected as shipping method, the calculate shipping method of the cart is called.
	 * @return NULL
	 */
	public function action_woocommerce_checkout_update_order_review( $array, $int ) {
		if ( in_array( "shippify", WC()->session->get( 'chosen_shipping_methods' ) ) ) {
			WC()->cart->calculate_shipping();
		}
	    return;
	}

	/**
	 * Hooked to Filter: woocommerce_cart_shipping_method_full_label
	 * Change Shippify label depending on the page the user is.
	 * @param string $method The shipping method id
	 * @param $full_label The current shipping method label
	 * @return string The label to show.
	 */
	public function change_shipping_label( $full_label, $method ) {
		if ( "shippify" == $method->id ){
			if ( is_cart() ) {
				$full_label = "Shippify: Same Day Delivery - Proceed to Checkout for fares";	
			} elseif ( is_checkout() ) {
				$full_label = $full_label . " - Same Day Delivery ";
			}	
		}
	    return $full_label;
	}

	/**
	 * Hooked to Action: woocommerce_after_checkout_form
	 * Insert our interactive map in checkout.
	 * @param array $after Every field after the checkout form.
	 */
    public function add_map( $after ) {
    	echo '<div id="shippify_map">';
    	echo '<h4>Delivery Position  </h4> <p> Click on the map to put a marker where you want your order to be delivered. </p>';
    	echo '<input id="pac-input" class="controls" type="text" placeholder="Search Box">';
    	echo '<div id="map"></div>';
    	wp_enqueue_script( 'wc-shippify-google-maps', 'https://maps.googleapis.com/maps/api/js?key=AIzaSyDEXSakl9V0EJ_K3qHnnrVy8IEy3Mmo5Hw&libraries=places&callback=initMap', $in_footer = true );
    	echo '</div>';
    }

	/**
	 * Hooked to Action: woocommerce_after_order_notes.
	 * Display in checkout our custom Shippify fields.
	 * @param array $checkout The checkout fields array
	 */
    public function display_custom_checkout_fields( $checkout ) {
		echo '<div id="shippify_checkout" class="col3-set"><h2>' . __('Shippify') . '</h2>';

	    foreach ( $checkout->checkout_fields['shippify'] as $key => $field ) : 
	            woocommerce_form_field( $key, $field, $checkout->get_value( $key ) );
	        endforeach;
	    echo '</div>';

	    // Set shipping price to $0 to not confuse the user.
   		setcookie( 'shippify_longitude', '', time() - 3600 );
   		setcookie( 'shippify_latitude', '', time() - 3600 );

	    WC()->cart->calculate_shipping();
    }


	/**
	 * Hooked to Action: woocommerce_checkout_update_order_meta.
	 * Save Shippify custom checkout fields to the order when checkout is processed.
	 * @param string $order_id The order id
	 */
	public function save_custom_checkout_fields( $order_id ) {
	    if( ! empty( $_POST['shippify_instructions'] ) ) {
	        update_post_meta( $order_id, 'Instructions', sanitize_text_field( $_POST['shippify_instructions'] ) );
	    }
	   	if( ! empty( $_POST['shippify_latitude'] ) ) {
	        update_post_meta( $order_id, 'Latitude', sanitize_text_field( $_POST['shippify_latitude'] ) );
	    }
	   	if( ! empty( $_POST['shippify_longitude'] ) ) {
	        update_post_meta( $order_id, 'Longitude', sanitize_text_field( $_POST['shippify_longitude'] ) );
	    }
		
	    update_post_meta( $order_id, 'pickup_latitude', sanitize_text_field( $_COOKIE["warehouse_latitude"] ) );
	    update_post_meta( $order_id, 'pickup_longitude', sanitize_text_field( $_COOKIE["warehouse_longitude"] ) );
	    update_post_meta( $order_id, 'pickup_address', sanitize_text_field( $_COOKIE["warehouse_address"] ) );
	    update_post_meta( $order_id, 'pickup_id', sanitize_text_field( $_COOKIE["warehouse_id"] ) );
	}

  
  	/**
	 * Hooked to Filter: woocommerce_checkout_fields.
	 * Add Shippify custom checkout fields to the checkout form.
	 * @param array $fields The checkout form fields
	 * @return array The checkout form fields
	 */
   	public function customize_checkout_fields( $fields ) {
   		
   		global $woocommerce;

   		$fields["shippify"] = array(
   			'shippify_instructions' => array(
				'type'          => 'text',
				'class'         => array( 'form-row form-row-wide' ),
				'label'         => __( 'Reference' , 'woocommerce-shippify' ),
				'placeholder'   => __( 'Reference to get to the delivery place.' ),
				'required'      => false
			),
   			'shippify_latitude' => array(
				'type'          => 'text',
				'class'         => array( 'form-row form-row-wide' ),
				'label'         => __( 'Latitude' ),
				'required'      => false,
				'class' 	    => array ('address-field', 'update_totals_on_change' )
			),
			'shippify_longitude' => array(
				'type'           => 'text',
				'class'          => array( 'form-row form-row-wide' ),
				'label'          => __( 'Longitude' ),
				'required'       => false,
				'class' 	     => array ( 'address-field', 'update_totals_on_change' )
			)
		);   

   		return $fields;

   	}

  	/**
	 * Hooked to Action: woocommerce_checkout_process.
	 * Validate the Shippify fields in checkout.
	 * The methods tries to obtain the shippify fare of the task the API would create if the order is placed just as it is right now.
	 * Warning messages appear and the order does not place if the request fails or any fields are empty.
	 */
	public function shippify_validate_order() {

		//wc_add_notice( __( 'Shippify: Please, write descriptive instructions.' ), 'error' );
		if ( in_array( 'shippify', $_POST["shipping_method"] ) ) {

			// No marker on Map
			if ( "" == $_POST['shippify_latitude'] || "" == $_POST['shippify_longitude'] ) {
				wc_add_notice( __( 'Shippify: Please, locate the marker of your address in the map.' ), 'error' );
			}
			if ( "" == $_POST['shippify_instructions'] || 10 > strlen( $_POST['shippify_instructions'] ) ) {
				wc_add_notice( __( 'Shippify: Please, write descriptive instructions.' ), 'error' );
			}

			// Getting pickup information based on shipping zone
			$pickup_warehouse = $_COOKIE["warehouse_id"];
			$pickup_latitude = $_COOKIE["warehouse_latitude"];
			$pickup_longitude = $_COOKIE["warehouse_longitude"];



			//wc_add_notice( __( 'Shippify: Please, write descriptive instructions.' . $pickup_latitude ), 'error' );

			// Get Delivery information (marker position)
			$delivery_latitude = $_POST["shippify_latitude"];
			$delivery_longitude = $_POST["shippify_longitude"];

			// Construct the request. 
			// Items array is just hardcoded. We just want to know if the task can be created.
            $data_value = '[{"pickup_location":{"lat":'. $pickup_latitude .',"lng":'. $pickup_longitude . '},"delivery_location":{"lat":' . $delivery_latitude . ',"lng":'. $delivery_longitude .'},"items":[{"id":"10234","name":"TV","qty":"2","size":"3","price":"0"}]}]';


			$task_endpoint = 'https://api.shippify.co/task/fare?data='. $data_value;
			$api_id = get_option( 'shippify_id' );
			$api_secret = get_option( 'shippify_secret' );

			// Basic Authorization
            $args = array(
                'headers' => array(
                    'Authorization' => 'Basic ' . base64_encode( $api_id . ':' . $api_secret )
                ),
                'method'  => 'GET'
            );                    

            $response = wp_remote_get( $task_endpoint, $args );
            $decoded = json_decode( $response['body'], true );
            $price = $decoded['price'];

            // Check if task could be created
			if ( ! isset($price) || "" == $price ) {
				wc_add_notice( __( 'Shippify: We are unable to make a route to your address. Verify the marker in the map is correctly positioned.' ), 'error' );
			}  
		}
	}
}

new WC_Shippify_Checkout();