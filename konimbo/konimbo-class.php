<?php
class Konimbo extends \Priority_Hub {
    public  $generalpart;
    function get_service_name(){
        return 'Konimbo';
    }
	function get_orders_by_user() {
        $user = $this->get_user();
        $order_status = $this->get_user_api_config('order_status') ?? 'שולם';
		$token = get_user_meta( $user->ID, 'konimbo_token', true );
		$last_sync_time = $this->get_last_sync_time();
		$konimbo_base_url = 'https://api.konimbo.co.il/v1/orders/?token=';
		$order_id         = '';
        $filter_status = '';
            switch($this->document){
                case 'order':
               //     $filter_status = '&payment_status=אשראי - מלא';
                    $filter_status = '&payment_status='.$order_status;
                    break;
                case 'receipt':
                    $filter_status = '&payment_status=שולם';
                    break;
            }
        $daysback = 0;
		if($daysback>0){
            $last_sync_time = date(DATE_ATOM, mktime(0, 0, 0, date("m") , date("d")-$daysback,date("Y")));
        }
		// debug url
		if ($this->debug||$this->order) {
			$order = $this->order;
			$konimbo_url = 'https://api.konimbo.co.il/v1/orders/'.$order.'?token='.$token;

		}else{
            $this->set_last_sync_time();
            $orders_limit       = '&created_at_min=' . $last_sync_time;
            //$orders_limit     = '&created_at_min=2020-10-06T00:00:00Z&created_at_max=2020-10-07T00:00:00Z';
            //$orders_limit       = '&created_at_min=2020-10-06T00:00:00Z';
            $konimbo_url        = $konimbo_base_url . $order_id . $token . $orders_limit . $filter_status;
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
			$this->sendEmailError($subject, $response->get_error_message() );
		} else {
			$respone_code    = (int) wp_remote_retrieve_response_code( $response );
			$respone_message = $response['body'];
			If ( $respone_code <= 201 ) {
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
	}
	function post_order_to_priority ($order) {
        $user = $this->get_user();
		$cust_number = get_user_meta( $user->ID, 'walk_in_customer_number', true );
		$data        = [
			'CUSTNAME' => $cust_number,
			'CDES'     => $order->name,
			//'CURDATE'  => date('Y-m-d', strtotime($order->get_date_created())),
			'BOOKNUM'  => 'KNB-'.$order->id,
			'DETAILS'  => trim(preg_replace('/\s+/', ' ', $order->note))
		];
		// billing customer details
		$customer_data                = [
			'PHONE' => $order->phone,
			'EMAIL' => $order->email,
			//'ADRS'  => $order->address,
		];
		$data['ORDERSCONT_SUBFORM'][] = $customer_data;
		// shipping
		$shipping_data           = [
			'NAME'      => $order->name,
			'CUSTDES'   => $order->name,
			'PHONENUM'  => $order->phone ?? '',
			//	'EMAIL'     => $order->email,
			//	'CELLPHONE' => $order->phone,
            'STATE'     => $order->address->city ?? '',
			'ADDRESS'   => $order->address->street ?? '',
            'ADDRESS2'  => $order->address->street_number ?? '',
            'ADDRESS3'  => $order->address->apartment ?? '',
            'ZIP'       => $order->address->zip_code ?? '',
            'ADDRESSA'  => $order->address->post_office_box ?? ''

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

			$partname_config = $this->get_user_api_config('if_code_empty') ;
			$partname = (($partname) ? $partname : $partname_config);

			$overwrite_partname = $this->get_user_api_config('overwrite_all_codes');
			if(!empty($overwrite_partname) ){
				$partname = $overwrite_partname;
			}

			// debug
			if ($this->generalpart) {
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
				'PDES'     => preg_replace( "/\r|\n/", "",$item->title),
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
		$pay_pal_details = $order->paypal_details;
        /*
		$konimbo_cards_dictionary   = array(
			1 => '1',  // Isracard
			2 => '5',  // Visa
			3 => '3',  // Diners
			4 => '4',  // Amex
			5 => '5',  // JCB
			6 => '6'   // Leumi Card
		);
		*/
        $konimbo_cards_dictionary = json_decode(get_user_meta($user->ID,'konimbo_credit_cards_conversion_table',true),true);
        $konimbo_number_of_payments_dictionary = json_decode(get_user_meta($user->ID,'konimbo_number_payments_conversion_table',true),true);
        if(!empty(get_object_vars($credit_cart_payments))){
        $payment_code = $konimbo_cards_dictionary[$credit_cart_payments->issued_company_number] ;
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
        }

		// make request
		//PriorityAPI\API::instance()->run();
		// make request
		//echo json_encode($data);
		$response = $this->makeRequest( 'POST', 'ORDERS', [ 'body' => json_encode( $data ) ], $user );

		return $response;
	}
	function post_ainvoice_to_priority( $order) {
        $user = $this->get_user();
		$cust_number = get_user_meta( $user->ID, 'walk_in_customer_number', true );
		$data        = [
			'CUSTNAME' => $cust_number,
			'CDES'     => $order->name,
			'IVDATE'  => date('Y-m-d', strtotime($order->get_date_created())),
			'BOOKNUM'  => 'KNB-'.$order->id,
			'DETAILS'  => trim(preg_replace('/\s+/', ' ', $order->note))
		];
		// billing customer details
		$customer_data                = [
			'PHONE' => $order->phone ?? '',
			'EMAIL' => $order->email ?? '',
			//'ADRS'  => $order->address,
		];
		$data['AINVOICESCONT_SUBFORM'][] = $customer_data;


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
			if ($this->generalpart) {
				$partname = '000';
			}
			$second_code = isset($item->second_code) ? $item->second_code : '';
			$unit_price = isset($item->unit_price) ? (float) $item->unit_price : 0.0;
			$quantity = isset($item->quantity) ? (int)$item->quantity : 0;
			$data['AINVOICEITEMS_SUBFORM'][] = [
				'PARTNAME' => $partname,
				//'PARTNAME' => '000',
				'TQUANT'   => (int) $item->quantity,
				//'VATPRICE' => $unit_price * $quantity,
				'VPRICE' => $unit_price,
				//  if you are working without tax prices you need to modify this line Roy 7.10.18
				//'REMARK1'  =>$second_code,
				//'DUEDATE' => date('Y-m-d', strtotime($campaign_duedate)),
			];
		}

		// get discounts as items
		$discount_partname = '000';
		foreach ( $order->discounts as $item ) {
			$data['AINVOICEITEMS_SUBFORM'][] = [
				'PARTNAME' => $discount_partname,
				'TQUANT'   => -1 * (int)$item->quantity,
				//'VATPRICE' => (float) $item->price * - 1.0 * (int)$item->quantity,
				'VPRICE' => (float) $item->price,
				'PDES'     => preg_replace( "/\r|\n/", "",$item->title),
				//'DUEDATE' => date('Y-m-d', strtotime($campaign_duedate)),
			];
		}
		// shipping rate

		$shipping = $order->shipping;

		$data['AINVOICEITEMS_SUBFORM'][] = [
			// 'PARTNAME' => $this->option('shipping_' . $shipping_method_id, $order->get_shipping_method()),
			'PARTNAME' => '000',
			'PDES'     => $shipping->title,
			'TQUANT'   => (int)$shipping->quantity,
			//'VATPRICE' => (float)$shipping->price
		];


		// make request
		//PriorityAPI\API::instance()->run();
		// make request
		//echo json_encode($data);
		$response = $this->makeRequest( 'POST', 'AINVOICES', [ 'body' => json_encode( $data ) ], $user );

		return $response;
	}
	function post_items_to_priority($sku = null){
        $message['message'] = 'Nothing happen yet...';
        // get the token from config
        //$productToken = 'f0a511b7aec60c6629cd6749502e5c6e08d468df818bcf368f2016e17e57e183';
        $productToken = $this->get_user_api_config('konimbo_product_token');
        // get last sync time or days back
        $start_date = '2018-01-01';
        $start_date = ($this->get_user_api_config('konimbo_product_start_date') ?? $start_date);
        $now = date('Y-m-d H:i:s');
        $data = [];
        while($start_date < $now){
            $end_date = date('Y-m-d', strtotime("+1 months", strtotime($start_date)));
            // do stuff
            $baseUrl =  'https://api.konimbo.co.il/v1/items?token=';
            $url = $baseUrl.$productToken.'&created_at_min='.$start_date.'&created_at_max='.$end_date;
            // if debug change base url
            if(null != $sku){
                $url =  'https://api.konimbo.co.il/v1/items/'.$sku.'?token='.$productToken;
                // disable the while
                $start_date = $now;
            }
            // get items from Konimbo
            $response = wp_remote_get($url);
            if(is_wp_error($response) ){
                $message['message'] = 'wp error: '.$response->get_error_message();
                return $message;
            }
            if($response['response']['code']<=201){
                $res_data = json_decode($response['body']);
                $data = array_merge($data,$res_data);
            }elseif($response['response']['code']==404){
                // do nothing
            }else{
            $subject = 'Konimbo Error for user ' . get_user_meta( $this->get_user()->ID, 'nickname', true );
            $this->sendEmailError($subject,'');
            $message['message'] =  'We got error here... '.$response['body'];
            return $message;
            }
            $start_date = $end_date;
        }
            $message['message'] = 'Starting process products...<br>';
            foreach($data as $item) {
                $is_variation = false;
                if (!empty($item->inventory)) {
                    $variations = $item->inventory;
                    foreach ($variations as $variation) {
                        if (!($variation->title == 'כמות' || $variation->code == 'Inventory')) {
                            if (empty($variation->code)) {
                                continue;
                            }
                            $is_variation = true;
                            $pri_data = [
                                'PARTNAME' => $variation->code,
                                'PARTDES' => $item->title . ' ' . $variation->title,
                                'VATPRICE' => (float)$variation->price,
                                'SPEC1' => $item->code
                            ];
                        }
                    }
                }
                if (false == $is_variation) {
                    if (empty($item->code)) {
                        continue;
                    }
                    $pri_data = [
                        'PARTNAME' => $item->code,
                        'PARTDES' => $item->title,
                        'VATPRICE' => (float)$item->price
                    ];
                }
                $pri_data['PRICE']                    = (float)$item->cost;
                $pri_data['SPEC1']                    = $item->store_category_title;
                $pri_data['SPEC2']                    = $item->store_category_title_with_parent->parent_title;
                $pri_data['SPEC3']                    = $item->store_category_title_with_parent->child_title;
                $pri_data['SPEC4']                    = $item->brand;
                $pri_data['SPEC5']                    = $item->warranty;
               // $pri_data['SPEC6']                    = $item->note;
                // add description
                $pri_data['PARTTEXT_SUBFORM']['TEXT'] = $item->note;  //$item->spec_text;
                // make request
                $pri_response = $this->makeRequest('POST', 'LOGPART', ['body' => json_encode($pri_data)], $this->get_user());
                if ($pri_response['code'] > 201) {
                    if ($pri_response['code'] == 409) {
                        $msg = 'Product ' . $item->code . ' already exists in Priority<br>';
                        $this->write_custom_log($msg, $this->get_user()->user_login);
                        $message['message'] .= $msg;
                        $pri_response = $this->makeRequest('PATCH', 'LOGPART(\''.$pri_data['PARTNAME'].'\')', ['body' => json_encode($pri_data)], $this->get_user());
                        if($pri_response['code'] <= 201){
                            $msg = 'Product ' . $item->code . ' update succesfuly<br>';
                            $message['message'] .= $msg;
                        }else{
                            $message['message'] .= 'Error code: ' . $pri_response['code'] . ' Message: ' . $pri_response['message'];
                        }
                        continue;
                    }
                    if ($pri_response['code'] != 409) {
                        $message['message'] = 'Error code: ' . $pri_response['code'] . ' Message: ' . $pri_response['message'];
                        $msg = 'Konimbo Error for user ' . get_user_meta($this->get_user()->ID, 'nickname', true);
                        $this->write_custom_log($msg, $this->get_user()->username);
                        $subject = $msg;
                        $this->sendEmailError($subject, $message['message']);
                        return $message;
                    }
                } else {
                    $message['message'] .= 'Product ' . $item->code . ' posted to Priority<br>';
                    // use WEBSDK to upload the images
                    $imgIndex = 1;
                    foreach ($item->images as $image) {
                        if ($imgIndex == 1) {
                            // upload main image
                        } else {
                            // upload sub form images
                        }
                        $imgIndex++;
                    }
                }
            }
        // upload image
        // process response
        return $message;
    }
    function post_receipt_to_priority( $order) {
        $user = $this->get_user();
        $cust_number = get_user_meta( $user->ID, 'walk_in_customer_number', true );
        $credit_cart_payments = $order->credit_card_details;
        $receipt_date = date('Y-m-d', strtotime($credit_cart_payments->response_date));
        $data        = [
            'ACCNAME' => $cust_number,
            'CDES'     => $order->name,
            'IVDATE'   => !empty($receipt_date) ? $receipt_date : date('Y-m-d', strtotime($order->created_at)),
            'BOOKNUM'  => 'KNB-'.$order->id,
            'DETAILS'  => 'KNB-'.$order->id
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
        /*
        $konimbo_cards_dictionary   = array(
            1 => '5',  // Isracard
            2 => '4',  // Visa
            3 => '11',  // Diners
            4 => '5',  // Amex
            5 => '17',  // JCB
            6 => '14'   // Leumi Card
        );
        $konimbo_number_of_payments_dictionary = array(
            1 => '1',
            2 => '2',
            3 => '3',
            4 => '4',
            5 => '5'
        );
        */
        $konimbo_cards_dictionary = json_decode(get_user_meta($user->ID,'konimbo_credit_cards_conversion_table',true),true);
        $konimbo_number_of_payments_dictionary = json_decode(get_user_meta($user->ID,'konimbo_number_payments_conversion_table',true),true);
        $payment_code               = $konimbo_cards_dictionary[ $credit_cart_payments->issued_company_number ];
        $data['TPAYMENT2_SUBFORM'][] = [
            'PAYMENTCODE'    => $payment_code,
            'QPRICE'         => (float)$order->total_price,
            'PAYCODE'        => $konimbo_number_of_payments_dictionary[$payment->number_of_payments],
            'FIRSTPAY'       => (float) $payment->special_first_payment,
            //'OTHERPAYMENTS'  => (float)$payment->single_payment,
            'PAYDATE'        => date('Y-m-d', strtotime($order->created_at)),
            'PAYACCOUNT'     => $credit_cart_payments->last_4d,
            'VALIDMONTH'     => $credit_cart_payments->card_expiration,
            'CCUID'          => $credit_cart_payments->credit_cart_token,
            'CONFNUM'        => $credit_cart_payments->order_confirmation_id,
            'CARDNUM'        => $credit_cart_payments->shovar_number
        ];

        // make request
        $response = $this->makeRequest( 'POST', 'TINVOICES', [ 'body' => json_encode( $data ) ], $user );
        return $response;
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
}
