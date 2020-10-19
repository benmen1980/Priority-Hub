<?php
class Konimbo extends \Priority_Hub {
    function get_service_name(){
        return 'Konimbo';
    }
	function get_orders_by_user() {
		// this function return the orders as array, if error return null
		// the funciton heandles the error internally
		//echo 'Getting orders from  konimbo...<br>';
        $user = $this->get_user();
		$token = get_user_meta( $user->ID, 'konimbo_token', true );
		switch($this->document){
            case 'order':
                $last_sync_time = get_user_meta( $user->ID, 'konimbo_orders_last_sync_time', true );
                break;
            case 'receipt':
                $last_sync_time = get_user_meta( $user->ID, 'konimbo_receipts_last_sync_time', true );
                break;
        }
		$konimbo_base_url = 'https://api.konimbo.co.il/v1/orders/?token=';
		$order_id         = '';
		$new_sync_time = date( "c" );
        $filter_status = '';
            switch($this->document){
                case 'order':
                    update_user_meta( $user->ID, 'konimbo_orders_last_sync_time', $new_sync_time );
                    $filter_status = '&payment_status=אשראי - מלא';
                    break;
                case 'receipt':
                    update_user_meta( $user->ID, 'konimbo_receipts_last_sync_time', $new_sync_time );
                    $filter_status = '&payment_status=שולם';
                    break;
            }
        $daysback = 0;
		if($daysback>0){
            $last_sync_time = date(DATE_ATOM, mktime(0, 0, 0, date("m") , date("d")-$daysback,date("Y")));
        }
        $orders_limit  = '&created_at_min=' . $last_sync_time;
        //$orders_limit     = '&created_at_min=2020-10-06T00:00:00Z&created_at_max=2020-10-07T00:00:00Z';
        $orders_limit     = '&created_at_min=2020-10-06T00:00:00Z';
        $konimbo_url   = $konimbo_base_url . $order_id . $token . $orders_limit . $filter_status;
		// debug url
		if ($this->debug||$this->order) {
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
	}
	function post_order_to_priority( $order) {
        $user = $this->get_user();
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

        // this  should be config file or in the user meta as json in WP
        $username = $user->user_login;

        switch ($username) {
            case 'jojo':
                $konimbo_cards_dictionary   = array(
                    1 => '5',  // Isracard
                    2 => '4',  // Visa
                    3 => '11',  // Diners
                    4 => '5',  // Amex
                    5 => '17',  // JCB
                    6 => '14'   // Leumi Card
                );
                $konimbo_number_of_payments_dictionary = array(
                    1 => '01',
                    2 => '02',
                    3 => '03',
                    4 => '04',
                    5 => '05',
                    6 => '06',
                    7 => '07',
                    8 => '08',
                    9 => '09'
                );
            break;
            case 'other...':
            break;
            default:
        }


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
