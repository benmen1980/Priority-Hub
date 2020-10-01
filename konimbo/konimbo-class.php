<?php
class Konimbo extends \Priority_Hub {
	public static $instance;
	public  $debug;
	public $document;
	public static function instance()
	{
		if (is_null(static::$instance)) {
			static::$instance = new static();
		}

		return static::$instance;
	}
	public function __construct() {
		add_action( 'init', array($this,'custom_post_type'), 0 );
		add_action('init', array($this,'register_tag'));
		add_action( 'admin_post_sync_konimbo', array($this,'process_all_users'));
	}
	public function run()
	{
		//return is_admin() ? $this->backend(): $this->frontend();
	}
	function get_orders_all_users() {
		$args = array(
			'order'   => 'DESC',
			'orderby' => 'user_registered',
			'meta_key' => 'konimbo_activate_sync',
			'meta_value' => true
		);
		// The User Query
		$user_query = new WP_User_Query( $args );
		// The User Loop
		if ( ! empty( $user_query->results ) ) {
			foreach ( $user_query->results as $user ) {
				$activate_sync = get_user_meta( $user->ID, 'konimbo_activate_sync',true );
				if ( $activate_sync ) {
					//echo 'Start sync  ' . get_user_meta( $user->ID, 'nickname', true ) . '<br>';
					ini_set( 'MAX_EXECUTION_TIME', 0 );
                    switch(Konimbo::instance()->document){
                        case 'order':
                            $responses[$user->ID] = $this->get_orders_by_user( $user );
                            break;
                        case 'receipt':
                            $responses[$user->ID] = $this->get_receipts_by_user( $user );
                            break;
                    }
				}
			}
		} else {
			// no shop_manager found
		}
		return $responses;
	} // return array user/orders
	function get_orders_by_user( $user ) {
		// this function return the orders as array, if error return null
		// the funciton heandles the error internally
		//echo 'Getting orders from  konimbo...<br>';
		$token = get_user_meta( $user->ID, 'konimbo_token', true );
		/*if(empty($token)){
			$token            = '53aa2baff634333547b7cf50dcabbebaa471365241f77340da068b71bfc22d93';
		}*/
        switch($this->document){
            case 'order':
                $last_sync_time = get_user_meta( $user->ID, 'konimbo_orders_last_sync_time', true );
                break;
            case 'receipt':
                $last_sync_time = get_user_meta( $user->ID, 'konimbo_receipts_last_sync_time', true );
                break;
        }
		if ( empty( $token ) ) {
			$token = '53aa2baff634333547b7cf50dcabbebaa471365241f77340da068b71bfc22d93';
		}
		$konimbo_base_url = 'https://api.konimbo.co.il/v1/orders/?token=';
		$order_id         = '';
		//$orders_limit     = '&created_at_min=2020-06-15T00:00:00Z';
		$orders_limit  = '&created_at_min=' . $last_sync_time;
		$new_sync_time = date( "c" );
		if ( !$this->debug ) {
            switch($this->document){
                case 'order':
                    update_user_meta( $user->ID, 'konimbo_orders_last_sync_time', $new_sync_time );
                    break;
                case 'receipt':
                    update_user_meta( $user->ID, 'konimbo_receipts_last_sync_time', $new_sync_time );
                    break;
            }

		}
		$filter_status = '&payment_status=אשראי - מלא';
        $filter_status = '';
		$konimbo_url   = $konimbo_base_url . $order_id . $token . $orders_limit . $filter_status;
		// debug url
		if ($this->debug) {
			$order = $this->order;
			$konimbo_url = 'https://api.konimbo.co.il/v1/orders/'.$order.'?token='.$token;
		}
		$method = 'GET';
		$args   = [
			'headers' => [],
			'timeout' => 450,
			'method'  => strtoupper( $method ),
			//'sslverify' => $this->option('sslverify', false)
		];


		if ( ! empty( $options ) ) {
			$args = array_merge( $args, $options );
		}
		$response = wp_remote_request( $konimbo_url, $args );
		$subject = 'konimbo Error for user ' . get_user_meta( $user->ID, 'nickname', true );

		if ( is_wp_error( $response ) ) {
			//echo 'internal server error<br>';
			//echo 'konimbo error: '.$response->get_error_message();
			$this->sendEmailError($subject, $response->get_error_message() );
		} else {
			$respone_code    = (int) wp_remote_retrieve_response_code( $response );
			$respone_message = $response['body'];
			If ( $respone_code <= 201 ) {
				//echo 'konimbo ok!!!<br>';
				$orders = json_decode( $response['body'] );
				if ( $this->debug ) {
					$orders = [ json_decode( $response['body'] ) ];
				}
				return $orders;
			}
			if ( $respone_code >= 400 && $respone_code <= 499 &&  $respone_code != 404 ) {
				$error = $respone_message . '<br>' . $konimbo_url;
				$this->sendEmailError( $subject, $error );
				return null;
			}
			if($respone_code == 404 ){
				return null;
			}
		}
	} // return array of konimbo orders
    function get_receipts_by_user( $user ) {
        // this function return the orders as array, if error return null
        // the function handles the error internally
        //echo 'Getting orders from  konimbo...<br>';
        $token = get_user_meta( $user->ID, 'konimbo_token', true );
        $last_sync_time = get_user_meta( $user->ID, 'konimbo_receipts_last_sync_time', true );
        $daysback = 3;
        $stamp = mktime(0 - $daysback * 24, 0, 0);
        $last_sync_time = date(DATE_ATOM,$stamp);
        $konimbo_base_url = 'https://api.konimbo.co.il/v1/orders/?token=';
        $order_id         = '';
        //$orders_limit     = '&created_at_min=2020-06-15T00:00:00Z';
        $orders_limit  = '&created_at_min=' . $last_sync_time;
        $new_sync_time = date( "c" );
        if ( !$this->debug ) {
            update_user_meta( $user->ID, 'konimbo_receipts_last_sync_time', $new_sync_time );
        }
        $filter_status = '&payment_status=שולם';
        $konimbo_url   = $konimbo_base_url . $order_id . $token . $orders_limit . $filter_status;
        // debug url
        if ($this->debug) {
            $order = $this->receipt;
            $konimbo_url = 'https://api.konimbo.co.il/v1/orders/'.$order.'?token='.$token;
        }
        $method = 'GET';
        $args   = [
            'headers' => [],
            'timeout' => 450,
            'method'  => strtoupper( $method ),
            //'sslverify' => $this->option('sslverify', false)
        ];


        if ( ! empty( $options ) ) {
            $args = array_merge( $args, $options );
        }
        $response = wp_remote_request( $konimbo_url, $args );
        $subject = 'konimbo Error for user ' . get_user_meta( $user->ID, 'nickname', true );

        if ( is_wp_error( $response ) ) {
            //echo 'internal server error<br>';
            //echo 'konimbo error: '.$response->get_error_message();
            $this->sendEmailError($subject, $response->get_error_message() );
        } else {
            $respone_code    = (int) wp_remote_retrieve_response_code( $response );
            $respone_message = $response['body'];
            If ( $respone_code <= 201 ) {
                //echo 'konimbo ok!!!<br>';
                $orders = json_decode( $response['body'] );
                if ( $this->debug ) {
                    $orders = [ json_decode( $response['body'] ) ];
                }
                return $orders;
            }
            if ( $respone_code >= 400 && $respone_code <= 499 &&  $respone_code != 404 ) {
                $error = $respone_message . '<br>' . $konimbo_url;
                $this->sendEmailError( $subject, $error );
                return null;
            }
            if($respone_code == 404 ){
                return null;
            }
        }
    } // return array of konimbo orders
	function process_orders( $orders, $user ) {
		// this function return array of all responses one per order
		$index = 0;
		$error = '';
		$responses = [];
		if(empty($orders)){
			return ;
		}
		foreach ( $orders as $order ) {
			//	echo 'Starting process order ' . $order->id . '<br>';
			$response = $this->post_order_to_priority( $order, $user );
			$responses[$order->id]= $response;
			$response_body = json_decode($response['body']);
			if ( $response['code'] <= 201 && $response['code'] >= 200 ) {
				$body_array = json_decode( $response["body"], true );
				// Create post object
				$my_post = array(
					'post_type'    => 'konimbo_order',
					'post_title'   => $order->name . ' ' . $order->id,
					'post_content' => json_encode( $response["body"] ),
					'post_status'  => 'publish',
					'post_author'  => $user->ID,
					'tags_input'   => array( $body_array["ORDNAME"] )
				);
				// Insert the post into the database
				$id = wp_insert_post( $my_post );
				update_post_meta($id,'konimbo_order_number',$order->id);
				// update konimbo status and Priority sales order number
				$this->update_status( 'Priority ERP', $body_array["ORDNAME"], $order->id, $user->ID );
			}
			if ( ! $response['status'] || $response['code'] >= 400 ) {
				$error .= '*********************************<br>Error on order: ' . $order->id . '<br>';
				$interface_errors =  $response_body->FORM->InterfaceErrors;
				if(isset($interface_errors)){
					foreach ($interface_errors as $err_line){
						if(is_object($err_line)){
							$error .=  $err_line->text.'<br>';
						}else{
							$error .=  $interface_errors->text.'<br>';
						}
					}
				}
			}
			//echo $response['message'] . '<br>';
			$index ++;
			if('' == $response['code']){
				break;
			}
		}
		return $responses;
	} // return array of Priority responses by user
    function process_receipts( $receipts, $user ) {
        // this function return array of all responses one per order
        $index = 0;
        $error = '';
        $responses = [];
        if(empty($receipts)){
            return ;
        }
        foreach ( $receipts as $receipt ) {
            // check if receipt already been posted, and continue
            $args = array(
                'post_type' => 'konimbo_order',
                'meta_query' => array(
                    array(
                        'key' => 'konimbo_order_number',
                        'value' => $receipt->id,
                        'compare' => '=',
                    )
                )
            );
            // The Query
            $the_query = new WP_Query( $args );
            // The Loop
            if ( $the_query->have_posts() ) {
                $the_query->the_post();
                $order_post_id = get_the_ID();
                $is_receipt = get_post_meta($order_post_id,'konimbo_is_receipts_posted',true);
                $ordernumber = get_post_meta($order_post_id,'konimbo_order_number',true);
                if($is_receipt) {
                continue;
                }
                update_post_meta($order_post_id,'konimbo_is_receipts_posted',true);
            }
            $response = $this->post_receipt_to_priority( $receipt, $user );
            $responses[$receipt->id]= $response;
            $response_body = json_decode($response['body']);
            if ( $response['code'] <= 201 && $response['code'] >= 200 ) {
                $body_array = json_decode( $response["body"], true );
                // Create post object
                $my_post = array(
                    'post_type'    => 'konimbo_order',
                    'post_title'   => $receipt->name . ' ' . $receipt->id,
                    'post_content' => json_encode( $response["body"] ),
                    'post_status'  => 'publish',
                    'post_author'  => $user->ID,
                    'tags_input'   => array( $body_array["IVNUM"] )
                );
                // Insert the post into the database
                wp_insert_post( $my_post );
                // update konimbo status and Priority sales order number
                //$this->update_status( 'Priority ERP', $body_array["IVNUM"], $order->id, $user->ID );
            }
            if ( ! $response['status'] || $response['code'] >= 400 ) {
                $error .= '*********************************<br>Error on order: ' . $receipt->id . '<br>';
                $interface_errors =  $response_body->FORM->InterfaceErrors;
                if(isset($interface_errors)){
                    foreach ($interface_errors as $err_line){
                        if(is_object($err_line)){
                            $error .=  $err_line->text.'<br>';
                        }else{
                            $error .=  $interface_errors->text.'<br>';
                        }
                    }
                }
            }
            //echo $response['message'] . '<br>';
            $index ++;
            if('' == $response['code']){
                break;
            }
        }
        return $responses;
    } // return array of Priority responses by user
	function post_order_to_priority( $order, $user ) {

		$cust_number = get_user_meta( $user->ID, 'walk_in_customer_number', true );
		$data        = [
			'CUSTNAME' => $cust_number,
			'CDES'     => $order->name,
			//'CURDATE'  => date('Y-m-d', strtotime($order->get_date_created())),
			'BOOKNUM'  => $order->id,
			'DETAILS'  => trim(preg_replace('/\s+/', ' ', $order->note))
		];
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
			//	'EMAIL'     => $order->email,
			//	'CELLPHONE' => $order->phone,
			'ADDRESS'   => $order->address,
		];
		$data['SHIPTO2_SUBFORM'] = $shipping_data;

		// get ordered items
		foreach ( $order->items as $item ) {
			$partname = $item->code;
			// check variation
			$variations = $order->upgrades;
			foreach ( $variations as $variation ) {
				if(isset($item->line_item_id) && isset($variation->inventory_code)){
					if ( $item->line_item_id == $variation->line_item_id ) {
						$partname = $variation->inventory_code;
					}
				}
			}
			// debug
			if (!empty($this->generalpart)) {
				$partname = '000';
			}
			$second_code = isset($item->second_code) ? $item->second_code : '';
			$unit_price = isset($item->unit_price) ? (float) $item->unit_price : 0.0;
			$quantity = isset($item->quantity) ? (int)$item->quantity : 0;
			$data['ORDERITEMS_SUBFORM'][] = [
				'PARTNAME' => $partname,
				'TQUANT'   => (int) $item->quantity,
				'VATPRICE' => $unit_price * $quantity,
				//  if you are working without tax prices you need to modify this line Roy 7.10.18
				'REMARK1'  =>$second_code,
				//'DUEDATE' => date('Y-m-d', strtotime($campaign_duedate)),
			];
		}

		// get discounts as items
		$discount_partname = '000';
		foreach ( $order->discounts as $item ) {
			$data['ORDERITEMS_SUBFORM'][] = [
				'PARTNAME' => $discount_partname,
				'TQUANT'   => -1 * (int)$item->quantity,
				'VATPRICE' => (float) $item->price * - 1.0 * (int)$item->quantity,
				'PDES'     => $item->title,
				//'DUEDATE' => date('Y-m-d', strtotime($campaign_duedate)),
			];
		}
		// shipping rate

		$shipping = $order->shipping;

			$data['ORDERITEMS_SUBFORM'][] = [
				// 'PARTNAME' => $this->option('shipping_' . $shipping_method_id, $order->get_shipping_method()),
				'PARTNAME' => '000',
				'PDES'     => $shipping->title,
				'TQUANT'   => (int)$shipping->quantity,
				'VATPRICE' => (float)$shipping->price
			];



		// payment info
		$payment              = $order->payments;
		$credit_cart_payments = $order->credit_card_details;

		$konimbo_cards_dictionary   = array(
			1 => '1',  // Isracard
			2 => '5',  // Visa
			3 => '3',  // Diners
			4 => '4',  // Amex
			5 => '5',  // JCB
			6 => '6'   // Leumi Card
		);
		$payment_code               = $konimbo_cards_dictionary[ $credit_cart_payments->issued_company_number ];
		$data['PAYMENTDEF_SUBFORM'] = [
			'PAYMENTCODE' => $payment_code,
			'QPRICE'      => (float) $payment->single_payment,
			// konimbo can handle multi paymnets so this might be modified
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

		return $response;
	}
    function post_receipt_to_priority( $order, $user ) {
        $cust_number = get_user_meta( $user->ID, 'walk_in_customer_number', true );
        $data        = [
            'ACCNAME' => $cust_number,
            'CDES'     => $order->name,
            'IVDATE'  => date('Y-m-d', strtotime($order->created_at)),
            'BOOKNUM'  => 'KNB-'.$order->id,
            //'DETAILS'  => trim(preg_replace('/\s+/', ' ', $order->note))
        ];
        // billing customer details
        $customer_data                = [
            'PHONE' => $order->phone,
            //'EMAIL' => $order->email,
            'ADRS'  => $order->address,
        ];
        $data['TINVOICESCONT_SUBFORM'][] = $customer_data;

        // payment info
        $payment              = $order->payments;
        $credit_cart_payments = $order->credit_card_details;

        $konimbo_cards_dictionary   = array(
            1 => '1',  // Isracard
            2 => '5',  // Visa
            3 => '3',  // Diners
            4 => '4',  // Amex
            5 => '5',  // JCB
            6 => '6'   // Leumi Card
        );
        $payment_code               = $konimbo_cards_dictionary[ $credit_cart_payments->issued_company_number ];
        $data['TPAYMENT2_SUBFORM'][] = [
            'PAYMENTCODE' => $payment_code,
            'QPRICE'      => (float) $payment->single_payment,
            'PAYDATE'     => date('Y-m-d', strtotime($order->created_at)),
            // konimbo can handle multi paymnets so this might be modified
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
        $response = $this->makeRequest( 'POST', 'TINVOICES', [ 'body' => json_encode( $data ) ], $user );
        return $response;
    }
	public function processResponse($responses){
		$response3 = null;
		if(empty($responses)){
			return ;
		}
		$message = '';
		$is_error = false;
		foreach ( $responses as $user_id => $responses2 ) {
			$user = get_user_by( 'ID', $user_id );
			$message .= 'Starting process user ' . $user->nickname . '<br>';
			if(empty($responses2)){
				continue;
			}
			foreach($responses2 as $order => $response){
				if(isset($response['code'])){
					$response_code = (int) $response['code'];
					$response_body = json_decode( $response['body'] );
					if ( $response_code >= 200 & $response_code <= 201 ) {
						$message .=  'New Priority  ' . $response_body->IVNUM.' places successfully for konimbo order '.$response_body->BOOKNUM.'<br>';
					}
					if ( $response_code >= 400 && $response_code < 500 ) {
						$is_error = true;
						$message .= 'Error while posting order ' . $order . '<br>';
						$interface_errors = $response_body->FORM->InterfaceErrors;
						if ( is_array( $interface_errors ) ) {
							foreach ( $interface_errors as $err_line ) {
								if ( is_object( $err_line ) ) {
									$message .=  $err_line->text . '<br>';
								}
							}
						}else {
							$message .= $interface_errors->text . '<br>';
						}
					}elseif(500 == $response_code || 0 == $response_code){
						$message .= 'Priority message: '.$response['message'].'<br>';
					}
				}elseif(isset($response['response']['code'])){
					$message .= $response['body'].'<br>';
				}
			}
			$response3[$user_id] = array("message" => $message,"is_error" => $is_error);
		}
		return $response3;
	}
	function update_status( $title, $comment, $order, $user_id ) {
		$user                        = get_user_by( 'ID', $user_id );
		$data                        = [];
		$token                       = get_user_meta( $user->ID, 'konimbo_token', true );
		$data['token']               = $token;
		$statuses                    = [
			'status_option_title' => $title,
			'username'            => 'Priority ERP',
			'comment'             => $comment
		];
		$data['order']['statuses'][] = $statuses;
		// request
		$konimbo_base_url = 'https://api.konimbo.co.il/v1/orders/';
		$konimbo_url      = $konimbo_base_url . $order;
		$method           = 'PUT';
		$args             = [
			'headers' => array(
				'Content-Type' => 'application/json',
			),
			'timeout' => 45,
			'method'  => strtoupper( $method ),
			'body'    => json_encode( $data )
			//'sslverify' => $this->option('sslverify', false)
		];


		if ( ! empty( $options ) ) {
			$args = array_merge( $args, $options );
		}
		$response = wp_remote_request( $konimbo_url, $args );

		$emails  = [ $user->user_email ];
		$subject = 'konimbo Error for user ' . get_user_meta( $user->ID, 'nickname', true );

		if ( is_wp_error( $response ) ) {
			echo 'internal server error<br>';
			echo $response->get_error_message();


			$error = $response->get_error_message();
			$this->sendEmailError($subject, $error);
		} else {
			$respone_code    = (int) wp_remote_retrieve_response_code( $response );
			$respone_message = $response['body'];
			If ( $respone_code <= 201 ) {
				//echo 'konimbo ok!!!<br>';
				if ($this->debug) {
					$orders = [ json_decode( $response['body'] ) ];
				}
			} elseif ( $respone_code >= 400 && $respone_code <= 499 ) {
				echo $respone_code . ' error occures <br>';
				echo $respone_message . '<br>';
				echo $konimbo_url . '<br>';
				if ( $respone_code != 404 ) {
					$error = $respone_message . '<br>' . $konimbo_url;
					$this->sendEmailError( $subject, $error );
				}

			}
		}


	}
	// post type konimbo order
	function custom_post_type() {

		$labels = array(
			'name'                  => _x( 'konimbo Orders', 'Post Type General Name', 'text_domain' ),
			'singular_name'         => _x( 'konimbo Order', 'Post Type Singular Name', 'text_domain' ),
			'menu_name'             => __( 'konimbo Orders', 'text_domain' ),
			'name_admin_bar'        => __( 'konimbo Order', 'text_domain' ),
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
			//'insert_into_item'      => __( 'Insert into item', 'text_domain' ),
			'uploaded_to_this_item' => __( 'Uploaded to this item', 'text_domain' ),
			'items_list'            => __( 'Items list', 'text_domain' ),
			'items_list_navigation' => __( 'Items list navigation', 'text_domain' ),
			'filter_items_list'     => __( 'Filter items list', 'text_domain' ),
		);
		$args   = array(
			'label'               => __( 'konimbo Order', 'text_domain' ),
			'description'         => __( 'konimbo order log', 'text_domain' ),
			'labels'              => $labels,
			'supports'            => array( 'title', 'editor' ),
			'taxonomies'          => array( 'PriorityOrder', 'OrderID', 'CustomerName' ),
			'hierarchical'        => false,
			'public'              => true,
			'show_ui'             => true,
			'show_in_menu'        => true,
			'menu_position'       => 23,
			'show_in_admin_bar'   => true,
			'show_in_nav_menus'   => true,
			'can_export'          => true,
			'has_archive'         => true,
			'exclude_from_search' => false,
			'publicly_queryable'  => true,
			'capability_type'     => 'post',
		);
		register_post_type( 'konimbo_order', $args );
	//	register_taxonomy_for_object_type( 'post_tag', 'portfolio' );


	}
	function register_tag() {
	    register_taxonomy_for_object_type( 'post_tag', 'konimbo_order' );

	}
	public function sendEmailError($subject = '', $error = '')
	{
		$user = wp_get_current_user();
        $emails  = [$user->user_email,get_bloginfo('admin_email')];
		if (!$emails) return;

		if ($emails && !is_array($emails)) {
			$pattern ="/[\._a-zA-Z0-9-]+@[\._a-zA-Z0-9-]+/i";
			preg_match_all($pattern, $emails, $result);
			$emails = $result[0];
		}
		$to = array_unique($emails);
		$headers = [
			'content-type: text/html'
		];

		wp_mail( $to,get_bloginfo('name').' '. $subject, $error, $headers );
	}
}