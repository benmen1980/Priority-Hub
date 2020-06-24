<?php
/*
Plugin Name: Simply Konimbo
*/

class Konimbo extends \PriorityAPI\API{
	private static $instance; // api instance
	public static function instance()
	{
		if (is_null(static::$instance)) {
			static::$instance = new static();
		}

		return static::$instance;
	}
	public function __construct() {
		parent::__construct();
		add_action( 'admin_menu',array( $this,'add_menu_items'));
		add_action( 'init', array($this,'custom_post_type'), 0 );
		add_action('init', array($this,'register_tag'));
	}
	public function run()
	{
		return is_admin() ? $this->backend(): $this->frontend();
	}
	// Konimbo API
	function simply_post_order_to_priority( $order,$user ) {

		$cust_number = get_user_meta( $user->ID, 'walk_in_customer_number' ,true);
		$data        = [
			'CUSTNAME' => $cust_number,
			'CDES'     => $order->name,
			//'CURDATE'  => date('Y-m-d', strtotime($order->get_date_created())),
			'BOOKNUM'  => $order->id,
			//'DCODE' => $priority_dep_number, // this is the site in Priority
			'DETAILS'  => $order->note,
		];

		// order comments
		/*
		$order_comment_array = explode("\n", $order->get_customer_note());
		foreach($order_comment_array as $comment){
			$data['ORDERSTEXT_SUBFORM'][] = [
				'TEXT' =>preg_replace('/(\v|\s)+/', ' ',$comment),
			];
		}
		*/

		// billing customer details
		$customer_data                = [

			'PHONE' => $order->phone,
			'EMAIL' => $order->email,
			'ADRS'  => $order->address,
		];
		$data['ORDERSCONT_SUBFORM'][] = $customer_data;

		// shipping
		$shipping_data           = [
			'NAME'      => $order->name,
			'CUSTDES'   => $order->name,
			'PHONENUM'  => $order->phone,
			'EMAIL'     => $order->email,
			'CELLPHONE' => $order->phone,
			'ADDRESS'   => $order->address,
		];
		$data['SHIPTO2_SUBFORM'] = $shipping_data;

		// get ordered items
		foreach ( $order->items as $item ) {
			$partname = $item->code;
			// debug
			//$partname                     = '000';
			$data['ORDERITEMS_SUBFORM'][] = [
				'PARTNAME' => $partname,
				'TQUANT'   => (int) $item->quantity,
				'VATPRICE' => (float) $item->unit_price * (int) $item->quantity,
				//  if you are working without tax prices you need to modify this line Roy 7.10.18
				'REMARK1'  => $item->second_code,
				//'DUEDATE' => date('Y-m-d', strtotime($campaign_duedate)),
			];
		}

		// get discounts as items
		$discount_partname = '000';
		foreach ( $order->discounts as $item ) {
			$data['ORDERITEMS_SUBFORM'][] = [
				'PARTNAME' => $discount_partname,
				'TQUANT'   => - 1,
				'VATPRICE' => (float) $item->price * - 1.0,
				'PDES'     => $item->title,
				//'DUEDATE' => date('Y-m-d', strtotime($campaign_duedate)),
			];
		}
		// shipping rate
		/*
		if( $order->get_shipping_method()) {
			$data['ORDERITEMS_SUBFORM'][] = [
				// 'PARTNAME' => $this->option('shipping_' . $shipping_method_id, $order->get_shipping_method()),
				'PARTNAME' => $this->option( 'shipping_' . $shipping_method_id . '_'.$shipping_method['instance_id'], $order->get_shipping_method() ),
				'TQUANT'   => 1,
				'VATPRICE' => floatval( $order->get_shipping_total() ),
				"REMARK1"  => "",
			];
		}
		*/
		// payment info
		$payment_code               = '5'; // need to fetch code from dictionary
		$payment                    = $order->payments;
		$credit_cart_payments       = $order->credit_card_details;
		$data['PAYMENTDEF_SUBFORM'] = [
			'PAYMENTCODE' => $payment_code,
			'QPRICE'      => (float) $payment->single_payment,
			// Konimbo can handle multi paymnets so this might be modified
			//'PAYACCOUNT'  => '',
			//'PAYCODE'     => '',
			'PAYACCOUNT'  => $credit_cart_payments->last_4d,
			'VALIDMONTH'  => $credit_cart_payments->card_expiration,
			'CCUID'       => $credit_cart_payments->credit_cart_token,
			'CONFNUM'     => $credit_cart_payments->order_confirmation_id,
			//'ROYY_NUMBEROFPAY' => $order_payments,
			//'FIRSTPAY' => $order_first_payment,
			//'ROYY_SECONDPAYMENT' => $order_periodical_payment

		];

		// make request
		//PriorityAPI\API::instance()->run();
		// make request
		//echo json_encode($data);
		$response = $this->makeRequest( 'POST', 'ORDERS', [ 'body' => json_encode( $data ) ], $user );
		if ( $response['code'] <= 201 ) {
			$body_array = json_decode( $response["body"], true );
			// Create post object
			$my_post = array(
				'post_type'     => 'konimbo_order',
				'post_title'    => $order->name.' '.$order->id,
				'post_content'  => json_encode($data),
				'post_status'   => 'publish',
				'post_author'   => 1,
				'tags_input' => array( $body_array["ORDNAME"])
			);

			// Insert the post into the database
			wp_insert_post( $my_post );
			// update Konimbo status and Priority sales order number
		}
		$subject = 'Priority API error '.$order->id;
		$emails = [];
		if ( $response['code'] >= 400 ) {
			$body_array = json_decode( $response["body"], true );
			$error = $response["body"];
			$this->sendEmailError($emails, $subject , $error );
		}
		if ( ! $response['status'] || $response['code'] >= 400 ) {
			$error = $response["body"];
			$this->sendEmailError($emails, $subject , $error );
		}


		// add timestamp
		return $response;
	}
	function simply_konimbo_process_orders( $orders,$user ) {
		$index = 0;
		foreach ( $orders as $order ) {
			echo '<br> Starting process order ' . $order->id . '<br>';
			$response = $this->simply_post_order_to_priority( $order,$user );
			echo $response['message'] . '<br>';
			$index ++;
		}
		echo 'Complete to sync ' . $index . ' orders';
	}
	function simply_konimbo($user) {
		echo '<br><br>Starting Konimbo<br>';
		$token          = get_user_meta( $user->ID, 'token' ,true);
		$last_sync_time = get_user_meta( $user->ID, 'last_sync_time',true );
		if(empty($token)){
			$token            = '53aa2baff634333547b7cf50dcabbebaa471365241f77340da068b71bfc22d93';
		}
		$konimbo_base_url = 'https://api.konimbo.co.il/v1/orders/?token=';
		$order_id         = '2586980';
		$order_id         = '';
        //$orders_limit     = '&created_at_min=2020-06-15T00:00:00Z';
		$orders_limit     = '&created_at_min='.$last_sync_time;
		$new_sync_time  = date( "c" );
		update_user_meta($user->ID,'last_sync_time',$new_sync_time);
		$filter_status    = '&payment_status=שולם';
		$konimbo_url      = $konimbo_base_url . $order_id . $token . $orders_limit . $filter_status;
		// debug url
		$konimbo_url = 'https://api.konimbo.co.il/v1/orders/1679803?token=53aa2baff634333547b7cf50dcabbebaa471365241f77340da068b71bfc22d93';
		$method = 'GET';
		$args   = [
			'headers' => [],
			'timeout' => 45,
			'method'  => strtoupper( $method ),
			//'sslverify' => $this->option('sslverify', false)
		];


		if ( ! empty( $options ) ) {
			$args = array_merge( $args, $options );
		}

		$response = wp_remote_request( $konimbo_url, $args );

		$emails = [$user->user_email];
		$subject = 'Konimbo Error for user '. get_user_meta($user->ID,'nickname',true);

		if ( is_wp_error( $response ) ) {
			echo 'internal server error<br>';
			echo $response->get_error_message();


			$error = $response->get_error_message();
			$this->sendEmailError($emails, $subject , $error );
		} else {
			$respone_code    = (int) wp_remote_retrieve_response_code( $response );
			$respone_message = $response['body'];
			If ( $respone_code <= 200 ) {
				echo 'Konimbo ok!!!<br>';
				$orders = [json_decode( $response['body'])];
				$this->simply_konimbo_process_orders( $orders,$user );
			} elseif ( $respone_code >= 400 && $respone_code <= 499 ) {
				echo $respone_code . ' error occures <br>';
				echo $respone_message . '<br>';
				echo $konimbo_url .'<br>';
				if($respone_code != 404){
					$error = $respone_message.'<br>'.$konimbo_url;
					PriorityAPI\API::instance()->sendEmailError($emails, $subject , $error );
				}

			}
		}

	}
	function konimbo_process_all_users(){
		echo 'Starting to post Konimbo orders to Priority<br> ';
		// WP_User_Query arguments
		$args = array(
			//'role'           => 'shop_manager',
			'order'   => 'DESC',
			'orderby' => 'user_registered',
			//'include'        => $users
		);
		// The User Query
		$user_query = new WP_User_Query( $args );
		// The User Loop
		if ( ! empty( $user_query->results ) ) {
			foreach ( $user_query->results as $user ) {
				$activate_sync = get_user_meta( $user->ID, 'activate_sync' )[0];
				if ( $activate_sync ) {
					echo '<br>Start sync  '.get_user_meta($user->ID,'nickname',true).'<br>';
					ini_set('MAX_EXECUTION_TIME', 0);
					$this->simply_konimbo($user);
				}

			}
		} else {
			// no shop_manager found
		}


		//var_dump(get_user_meta(1));
	}
	public function makeRequest($method, $url_addition = null,$options = [], $user)
	{
		$args = [
			'headers' => [
				'Authorization' => 'Basic ' . base64_encode($this->option('username') . ':' . $this->option('password')),
				'Content-Type'  => 'application/json',
				'X-App-Id' => get_user_meta( $user->ID, 'x-app-id' ,true),
				'X-App-Key' => get_user_meta( $user->ID, 'x-app-key' ,true)
			],
			'timeout'   => 45,
			'method'    => strtoupper($method),
			'sslverify' => get_user_meta( $user->ID, 'ssl_verify' ,true)
		];


		if ( ! empty($options)) {
			$args = array_merge($args, $options);
		}

		$url = sprintf('https://%s/odata/Priority/%s/%s/%s',
			get_user_meta( $user->ID, 'url' ,true),
			get_user_meta( $user->ID, 'application' ,true),
			get_user_meta( $user->ID, 'environment_name' ,true),
			is_null($url_addition) ? '' : stripslashes($url_addition)
		);

		$response = wp_remote_request($url, $args);

		$response_code    = wp_remote_retrieve_response_code($response);
		$response_message = wp_remote_retrieve_response_message($response);
		$response_body    = wp_remote_retrieve_body($response);

		if ($response_code >= 400) {
			$response_body = strip_tags($response_body);
		}

		// decode hebrew
		$response_body_decoded = $this->decodeHebrew($response_body);


		return [
			'url'      => $url,
			'args'     => $args,
			'method'   => strtoupper($method),
			'body'     => $response_body_decoded,
			'body_raw' => $response_body,
			'code'     => $response_code,
			'status'   => ($response_code >= 200 && $response_code < 300) ? 1 : 0,
			'message'  => ($response_message ? $response_message : $response->get_error_message())
		];
	}
	// frontend
	function frontend(){


	}

	// backend
	function backend(){

	}
	// post types
	// Register Custom Post Type
	function custom_post_type() {

		$labels = array(
			'name'                  => _x( 'Konimbo Orders', 'Post Type General Name', 'text_domain' ),
			'singular_name'         => _x( 'Konimbo Order', 'Post Type Singular Name', 'text_domain' ),
			'menu_name'             => __( 'Konimbo Orders', 'text_domain' ),
			'name_admin_bar'        => __( 'Konimbo Order', 'text_domain' ),
			'archives'              => __( 'Item Archives', 'text_domain' ),
			'attributes'            => __( 'Item Attributes', 'text_domain' ),
			'parent_item_colon'     => __( 'Parent Item:', 'text_domain' ),
			'all_items'             => __( 'All Items', 'text_domain' ),
			'add_new_item'          => __( 'Add New Item', 'text_domain' ),
			'add_new'               => __( 'Add New', 'text_domain' ),
			'new_item'              => __( 'New Item', 'text_domain' ),
			'edit_item'             => __( 'Edit Item', 'text_domain' ),
			'update_item'           => __( 'Update Item', 'text_domain' ),
			'view_item'             => __( 'View Item', 'text_domain' ),
			'view_items'            => __( 'View Items', 'text_domain' ),
			'search_items'          => __( 'Search Item', 'text_domain' ),
			'not_found'             => __( 'Not found', 'text_domain' ),
			'not_found_in_trash'    => __( 'Not found in Trash', 'text_domain' ),
			'featured_image'        => __( 'Featured Image', 'text_domain' ),
			'set_featured_image'    => __( 'Set featured image', 'text_domain' ),
			'remove_featured_image' => __( 'Remove featured image', 'text_domain' ),
			'use_featured_image'    => __( 'Use as featured image', 'text_domain' ),
			'insert_into_item'      => __( 'Insert into item', 'text_domain' ),
			'uploaded_to_this_item' => __( 'Uploaded to this item', 'text_domain' ),
			'items_list'            => __( 'Items list', 'text_domain' ),
			'items_list_navigation' => __( 'Items list navigation', 'text_domain' ),
			'filter_items_list'     => __( 'Filter items list', 'text_domain' ),
		);
		$args = array(
			'label'                 => __( 'Konimbo Order', 'text_domain' ),
			'description'           => __( 'Konimbo order log', 'text_domain' ),
			'labels'                => $labels,
			'supports'              => array( 'title', 'editor' ),
			'taxonomies'            => array( 'PriorityOrder', 'OrderID', 'CustomerName' ),
			'hierarchical'          => false,
			'public'                => true,
			'show_ui'               => true,
			'show_in_menu'          => true,
			'menu_position'         => 23,
			'show_in_admin_bar'     => true,
			'show_in_nav_menus'     => true,
			'can_export'            => true,
			'has_archive'           => true,
			'exclude_from_search'   => false,
			'publicly_queryable'    => true,
			'capability_type'       => 'post',
		);
		register_post_type( 'konimbo_order', $args );
		register_taxonomy_for_object_type( 'post_tag', 'portfolio' );


	}
	function register_tag(){
		register_taxonomy_for_object_type('post_tag', 'konimbo_order');

	}
	// admin pages
	function konimbo_options() {
		echo( '<br><br>This plugin is Konimbo<br>' );
		if ( isset( $_GET['post_all'] ) ) {
			$this->konimbo_process_all_users();

		}
	}
	// admin menu
	function add_menu_items(){
	 $hook = add_menu_page( 'KonimBo', 'Konimbo', 'activate_plugins', 'simply_konimbo', array($this,'konimbo_options'));
	 add_action( "load-$hook", 'add_options' );

	}
}

add_action('plugins_loaded', function(){
	Konimbo::instance()->run();
	});












