<?php
class Istore extends \Priority_Hub {
    function get_service_name(){
        return 'Istore';
    }
    function update_products_to_service(){
        $user = $this->get_user();
        $istore_base_url = 'https://my.istores.co.il/gateway/products';
        $method = 'POST';
        $YOUR_TOKEN = get_user_meta( $user->ID, 'istore_token', true );
        $products = $this->get_products_from_priority();
        foreach ($products as $product){
            $data['model'] = $product['PARTDES'];
            $data['sku'] = $product['PARTNAME'];
            $data['price'] = $product['BASEPLPRICE'];
            $data['product_description']['3'] = [
                'name' => $product['PARTDES']
            ];

            // retrieve prioriry image path
            $priority_image_path = $product['EXTFILENAME'];
            $url = get_user_meta($user->ID, 'url', true);
            $images_url = 'https://'. $url.'/primail';
            $product_full_url = str_replace( '../../system/mail', $images_url, $priority_image_path );

            $data['images'] = $product_full_url;
            $args   = [
                'headers' => array(
                    'Content-Type' => 'application/json',
                    'x-token' => $YOUR_TOKEN,
                    'accept' => 'application/json'
                ),
                'timeout' => 450,
                'method'  => strtoupper( $method ),
                //'sslverify' => $this->option('sslverify', false)
                'body'    => json_encode($data)
            ];
            if ( ! empty( $options ) ) {
                $args = array_merge( $args, $options );
            }
            $responses[] = wp_remote_request( $istore_base_url, $args );
        }
        return $responses;
    }
    function get_orders_by_user(  ) {
        $user = $this->get_user();
        //$last_sync_time = get_user_meta( $user->ID, 'istore_last_sync_time_order', true );
        $last_sync_time = $this->get_last_sync_time();
        $d=strtotime("now");
        $date_now = date("Y-m-d h:i:s", $d);

        $order_id         = '';

        $istore_base_url = 'https://my.istores.co.il/gateway/orders';

        $this->set_last_sync_time();
        
        $method = 'POST';
        $YOUR_TOKEN = get_user_meta( $user->ID, 'istore_token', true );
        $FROM_DATE = $last_sync_time;
        //debug
        //$FROM_DATE = "2020-02-11T11:02:13+00:00";
        //$TO_DATE = "2021-02-11T11:02:13+00:00";
        $TO_DATE = $date_now;
        $LIMIT = 100;
        //$PAGE = 0;
        $data =[
            "date_from" => $FROM_DATE,
            "date_to"   => $TO_DATE,
            "limit"     => $LIMIT
        ]; 
        $args             = [
			'headers' => array(
                'Content-Type' => 'application/json',
                'x-token' => $YOUR_TOKEN,
                'accept' => 'application/json'
			),
			'timeout' => 45,
			'method'  => strtoupper( $method ),
			'body'    => json_encode( $data )
			//'sslverify' => $this->option('sslverify', false)
		];
        if ( ! empty( $options ) ) {
            $args = array_merge( $args, $options );
        }
        $response = wp_remote_request( $istore_base_url, $args );
        $subject = 'Istore Error for user ' . get_user_meta( $user->ID, 'nickname', true );
        if ( is_wp_error( $response ) ) {
            $this->sendEmailError($subject, $response->get_error_message() );
        } else {
            $respone_code    = (int) wp_remote_retrieve_response_code( $response );
            $respone_message = $response['body'];
            If ( $respone_code <= 201 ) {
                $orders = json_decode( $response['body'])->response;
                if ( $this->debug ) {
                }
                return $orders;
            }
            if ( $respone_code >= 400 && $respone_code <= 499 &&  $respone_code != 404 ) {
                $error = $respone_message . '<br>' . $istore_base_url;
                $this->sendEmailError( $subject, $error );
                return null;
            }
            if($respone_code == 404 ){
                return null;
            }
        }
    }

    function process_documents($documents)
    {
        $user = $this->get_user();
        // this function return array of all responses one per order
        $index = 0;
        $error = '';
        $responses = [];
        if (empty($documents) && !$this->debug) {
            return;
        }
        foreach ($documents as $doc) {

            // check if receipt already been posted, and continue
            $args = array(
                'post_type' => $this->get_post_type(),
                'meta_query' => array(
                    array(
                        'key' => 'order_number',
                        'value' => $doc->order_id,
                        'compare' => '=',
                    )
                )
            );
            // The Query
            $the_query = new WP_Query($args);
            // The Loop
            if ($the_query->have_posts() && !$this->debug) {
                continue;
            }
            switch ($this->get_doctype()) {
                case 'order':
                    $response = $this->post_order_to_priority($doc);
                    break;
                case 'otc':
                    $response = $this->post_otc_to_priority($doc);
                    break;
                case 'invoice':
                    $response = $this->post_invoice_to_priority($doc);
                    break;
                case 'receipt':
                    $response = $this->post_receipt_to_priority($doc);
                    break;
                case 'orderreceipt':
                    $response = $this->post_order_and_receipt_to_priority($doc);
                    break;
                case 'shipment':
                    $response = $this->post_shipment_to_priority($doc);
                    break;
            }
            $responses[$doc->order_id] = $response;
            $response_body = json_decode($response['body']);
            $error_prefix = '';
            if ($response['code'] <= 201 && $response['code'] >= 200) {

            }
            if (!$response['status'] || $response['code'] >= 400) {
                $error_prefix = 'Error ';
            }
            $body_array = json_decode($response["body"], true);
            // Create post object
            $ret_doc_name = $this->get_doctype() == 'order' ? 'ORDNAME' : 'IVNUM';
            $my_post = array(
                'post_type' => $this->get_service_name().'_'.$this->get_doctype(),
                'post_title' => $error_prefix . $doc->name . ' ' . $doc->order_id,
                'post_content' => json_encode($response),
                'post_status' => 'publish',
                'post_author' => $user->ID,
                'tags_input' => array($body_array[$ret_doc_name])
            );
            // Insert the post into the database
            $post_id = wp_insert_post($my_post);
            update_post_meta($post_id, 'order_number', $doc->order_id);
            $index++;
            if ('' == $response['code']) {
                break;
            }
        }
        return $responses;
    }

    function get_orders_details_by_id($order){
        $order_id = $order->order_id;
        $user = $this->get_user();
        $istore_base_url = 'https://my.istores.co.il/gateway/order/'.$order_id;
        $method = 'GET';
        $YOUR_TOKEN = get_user_meta( $user->ID, 'istore_token', true );
        
        $args             = [
            'headers' => array(
                'Content-Type' => 'application/json',
                'x-token' => $YOUR_TOKEN,
                'accept' => 'application/json'
            ),
            'timeout' => 45,
            'method'  => strtoupper( $method ),
        ];
        $response = wp_remote_request( $istore_base_url, $args );
        $subject = 'Istore Error for user ' . get_user_meta( $user->ID, 'nickname', true );
        if ( is_wp_error( $response ) ) {
            $this->sendEmailError($subject, $response->get_error_message() );
        } else {
            $respone_code    = (int) wp_remote_retrieve_response_code( $response );
            $respone_message = $response['body'];
            if ( $respone_code <= 201 ) {
                $order_details = json_decode( $response['body'])->response;
                if ( $this->debug ) {
                }
                return $order_details;
            }
            if ( $respone_code >= 400 && $respone_code <= 499 &&  $respone_code != 404 ) {
                $error = $respone_message . '<br>' . $istore_base_url;
                $this->sendEmailError( $subject, $error );
                return null;
            }
            if($respone_code == 404 ){
                return null;
            }
        }
    }

    // return array of Priority responses by user
    function post_order_to_priority( $order ) {
        $order_detail = $this->get_orders_details_by_id($order);
        $user = $this->get_user();
        $cust_number = get_user_meta( $user->ID, 'walk_in_customer_number', true );

        $date = $order_detail->date_added; // date_modified?
        $createDate = new DateTime($date);
        $stripDate = $createDate->format('Y-m-d');

        $data        = [
        'CUSTNAME' => $cust_number,
        'CDES'     => $order_detail->payment_firstname. ' '.$order_detail->payment_lastname,
        'CURDATE'  => $stripDate,
        'BOOKNUM'  => 'ISTORE'.$order_detail->order_id, //is it right?
        //'CURDATE'  => date('Y-m-d', strtotime($order->get_date_created())),
        //'DETAILS'  => trim(preg_replace('/\s+/', ' ', $order->note))
        ];
        // billing customer details
        $customer_data                = [
        'PHONE' => $order_detail->telephone,
        'EMAIL' => $order_detail->email,
        'ADRS'  => $order_detail->payment_address_1,
        ];
        $data['ORDERSCONT_SUBFORM'][] = $customer_data;
        // shipping
        $shipping_data           = [
        'NAME'      => $order_detail->shipping_firstname. ' '.$order_detail->shipping_lastname,
        //'CUSTDES'   => $order->shipping_address->first_name.' '.$order->shipping_address->last_name, ??
        //'PHONENUM'  => $order->shipping_address->phone, 
        'ADDRESS'   => $order_detail->shipping_address_1,
        'STATE'      => $order_detail->shipping_city
        ];
        $data['SHIPTO2_SUBFORM'] = $shipping_data;
        // get ordered items
        foreach ( $order_detail->products as $item ) {
        $partname = $item->sku;
        // debug
        if (!empty($this->generalpart)) {
        $partname = '000';
        }
        $data['ORDERITEMS_SUBFORM'][] = [
        //'PARTNAME' => $partname,
        //debug
        'PARTNAME' => '000',
        //'PDES' => $item->name,
        'TQUANT'   => (int) $item->quantity,
        'PRICE' => (float) $item->price,
        'QPRICE' => (int) $item->quantity * (float) $item->price,
        
        //  if you are working without tax prices you need to modify this line Roy 7.10.18
        //'REMARK1'  =>$second_code,
        //'DUEDATE' => date('Y-m-d', strtotime($campaign_duedate)),
        ];
        }
        // get discounts as items
            //$data['ORDERITEMS_SUBFORM'][] = $this->get_payment_details($order);
        // shipping rate
        $discount_price = 0;
        foreach ( $order_detail->totals as $total ) {
            if($total->code == "shipping"){
               $shipping_price =  $total->value;
            }
            if($total->code == "coupon"){
                $discount_price =  $total->value;
             }
            
        }
        $data['ORDERITEMS_SUBFORM'][] = [
        'PARTNAME' => '000',
        'PDES'     => '',
        'TQUANT'   => (int)1,
        'PRICE' => (float) $shipping_price, //shipping cost add
        'DISPRICE' =>  $discount_price ? (float) $discount_price : 0 // discount price
        ];
        $data['PAYMENTDEF_SUBFORM'] = $this->get_payment_details($order);
        // make request
        //echo json_encode($data);
        $response = $this->makeRequest( 'POST', 'ORDERS', [ 'body' => json_encode( $data ) ], $user );
        return $response;
    }

    function post_receipt_to_priority( $order) {
        $order_detail = $this->get_orders_details_by_id($order);
        $user = $this->get_user();
        $cust_number = get_user_meta( $user->ID, 'walk_in_customer_number', true );
        $date = $order_detail->date_added; // date_modified?
        $createDate = new DateTime($date);
        $stripDate = $createDate->format('Y-m-d');
        $receipt_date =  $stripDate;
        $data        = [
            'ACCNAME' => $cust_number,
            'CDES'     => $order_detail->payment_firstname. ' '.$order_detail->payment_lastname,
            'IVDATE'   => $receipt_date,
            'BOOKNUM'  => 'Istore-'.$order_detail->order_id,
            'DETAILS'  => 'Istore-'.$order_detail->order_id
        ];
        // billing customer details
        $customer_data                = [
            'PHONE' => $order_detail->telephone,
            'EMAIL' => $order_detail->email,
            'ADRS'  => $order_detail->payment_address_1,
        ];
        $data['TINVOICESCONT_SUBFORM'][] = $customer_data;
        // payment info

        //The card number with mask at the middle (not full number)
        $card_mask = (string)$order_detail->payment_data->card_mask;
        $last_4d = substr($card_mask, -4);
        $card_exp = (string)$order_detail->payment_data->card_exp;
        $payment_code = $this->get_user_api_config('ISTORE_PAYMENTCODE'); 
        $data['TPAYMENT2_SUBFORM'][] = [
            'PAYMENTCODE'    => $payment_code,
            'PAYMENTNAME'    => $order_detail->payment_method,
            'QPRICE'         => (float)$order_detail->total,
            'PAYCODE'        => (string) $order_detail->payment_data->number_of_payments, //is it right?
            //'FIRSTPAY'       => (float) $order_detail->payment_data->first_payment,
            'PAYDATE'        =>  $stripDate,
            'PAYACCOUNT'     => $last_4d, 
            'VALIDMONTH'     => $card_exp, 
            'CONFNUM'        => ($order_detail->payment_data->auth_number)? $order_detail->payment_data->auth_number: null
            //'CARDNUM'        => $credit_cart_payments->shovar_number
        ];

        // make request
        $response = $this->makeRequest( 'POST', 'TINVOICES', [ 'body' => json_encode( $data ) ], $user );
        return $response;
    }




    function get_payment_details($order){  //i dont know where to insert payment method and details
        // payment info
        $istore_cards_dictionary   = array(
            
        );
        $order_detail = $this->get_orders_details_by_id($order);
        $payment_method =  $order_detail->payment_method;
        $payment_code = $this->get_user_api_config('ISTORE_PAYMENTCODE'); 
        $card_mask = (string)$order_detail->payment_data->card_mask;
        $last_4d = substr($card_mask, -4);
        $card_exp = (string)$order_detail->payment_data->card_exp;
        
        $data = [
            //'PAYMENTCODE' => $payment_code,
            //debug
            'PAYMENTCODE' => $payment_code,  
            'PAYMENTNAME' =>  $payment_method,
            'PAYACCOUNT'     => $last_4d, 
            'VALIDMONTH'     => $card_exp, 
            'QPRICE'         => (float)$order_detail->total,
            'CONFNUM'        => ($order_detail->payment_data->auth_number)? $order_detail->payment_data->auth_number: null
        ];
        return $data;
    }

    function get_user_api_config($key){
        return json_decode(get_user_meta($this->get_user()->ID,'description',true))->$key ?? null;
    }
}


