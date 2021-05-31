<?php
class Shopify extends \Priority_Hub {
function get_service_name(){
        return 'Shopify';
    }
function get_orders_by_user(  ) {
    $user = $this->get_user();
    //$last_sync_time = get_user_meta( $user->ID, 'shopify_last_sync_date', true );
    $last_sync_time = $this->get_last_sync_time();
    $order_id         = '';
    $orders_limit  = '?created_at_min=' . $last_sync_time.'&limit=250&status=any';
    //$orders_limit     = '?created_at_min=2020-10-01T00:00:00Z&created_at_max=2020-10-30T00:00:00Z&limit=250&status=any';
    //$orders_limit     = '?created_at_=2020-09-23T00:00:00Z&limit=250&status=any';
    $shopify_base_url = 'https://'.get_user_meta( $user->ID, 'shopify_url', true ).'/admin/api/2021-01/orders.json'.$orders_limit;
    //$shopify_base_url = 'https://'.get_user_meta( $user->ID, 'shopify_url', true ).'/admin/api/2020-04/orders.json';
    
    if ( !$this->debug ) {
        $this->set_last_sync_time();
    }
    if ($this->debug) {
        $shopify_base_url = 'https://'.get_user_meta( $user->ID, 'shopify_url', true ).'/admin/api/2020-04/orders.json?name='.$this->order.'&status=any';
    }
    $method = 'GET';
    $YOUR_USERNAME = get_user_meta( $user->ID, 'shopify_username', true );
    $YOUR_PASSWORD = get_user_meta( $user->ID, 'shopify_password', true );

    $args   = [
        'headers' => array(
            'Authorization' => 'Basic ' . base64_encode( $YOUR_USERNAME . ':' . $YOUR_PASSWORD ),
            'rel' => 'next'
    ),
        'timeout' => 450,
        'method'  => strtoupper( $method ),
        //'sslverify' => $this->option('sslverify', false)
        ];
    if ( ! empty( $options ) ) {
        $args = array_merge( $args, $options );
    }
    $response = wp_remote_request( $shopify_base_url, $args );
    $subject = 'shopify Error for user ' . get_user_meta( $user->ID, 'nickname', true );
    if ( is_wp_error( $response ) ) {
        $this->sendEmailError($subject, $response->get_error_message() );
    } else {
        $respone_code    = (int) wp_remote_retrieve_response_code( $response );
        $respone_message = $response['body'];
        If ( $respone_code <= 201 ) {
            $orders = json_decode( $response['body'] )->orders;
            $header = $response['headers'];
            $header_data = $header->getAll();
            //$header_data = $header['data'];
            $header_url = explode('<',$header_data['link'],2)[1];
            $header_url = explode('>',$header_url,2)[0];
            while($header_url) {
                $response2 = wp_remote_request($header_url,$args);
                if ($respone_code <= 201) {
                    $next_orders = json_decode($response2['body'])->orders;
                    if(sizeof($next_orders)==0){
                        break;
                    }
                    $orders = array_merge($orders, $next_orders);
                    $header = $response2['headers'];
                    $header_data = $header->getAll();
                    //$header_data = $header['data'];
                    $rel_next = explode(',',$header_data['link'])[1];
                    if(null==$rel_next){
                        break;
                    }
                    if(isset($rel_next)){
                        $new_url = $rel_next;
                    }else{
                        $new_url = $header_data['link'];
                    }
                    $header_url = explode('<', $new_url, 2)[1];
                    $header_url = explode('>', $header_url, 2)[0];

                }
            }
            if ( $this->debug ) {
                //$orders = [json_decode( $response['body'])->orders];
            }
            return $orders;
        }
        if ( $respone_code >= 400 && $respone_code <= 499 &&  $respone_code != 404 ) {
            $error = $respone_message . '<br>' . $shopify_base_url;
            $this->sendEmailError( $subject, $error );
            return null;
        }
        if($respone_code == 404 ){
            return null;
        }
    }
} // return array of shopify orders
function process_orders( $orders, $user ) {
// this function return array of all responses one per order
$index = 0;
$error = '';
$responses = [];
if(empty($orders)){
return ;
}
foreach ( $orders as $order ) {
    // check if receipt already been posted, and continue
    $post_type = 'shopify_'.$this->document;
    $meta_key = $post_type.'_order';
    $args = array(
            'post_type' => $post_type,
            'meta_query' => array(
                array(
                    'key' => $meta_key,
                    'value' => $order->id,
                    'compare' => '=',
                )
            )
        );
    // The Query
    $the_query = new WP_Query( $args );
    // The Loop
    if ( $the_query->have_posts() ) {
            continue;
        }
    switch($this->document){
            case 'order':
                $response = $this->post_order_to_priority( $order, $user );
                break;
            case 'otc':
                $response = $this->post_otc_to_priority($order,$user);
                break;
             case 'shipment':
                $response = $this->post_shipment_to_priority($order,$user);
                break;

        }
    $responses[$order->id]= $response;
    $response_body = json_decode($response['body']);
    $body_array = json_decode( $response["body"], true );
    // Create post object
    $my_post = array(
        'post_type'    => $post_type,
        'post_title'   => $order->name . ' ' . $order->billing_address->first_name.' '.$order->billing_address->last_name,
        'post_content' => $response["body"] ,
        'post_status'  => 'publish',
        'post_author'  => $user->ID,
        'tags_input'   =>  $body_array["ORDNAME"].$body_array["IVNUM"]
    );
    // Insert the post into the database
    $pid = wp_insert_post( $my_post );
    update_post_meta($pid,$meta_key,$order->name);

    if ( $response['code'] <= 201 && $response['code'] >= 200 ) {

    }
    /*
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
    */
    //echo $response['message'] . '<br>';
    $index ++;
    if('' == $response['code']){
    break;
    }
}
return $responses;
} // return array of Priority responses by user
function check_customer_existence($order){
    $user = $this->get_user();
    $response_post_cust = array();
      //check if customer already exist in priority
      $phone = $order->customer->phone;
      $email = $order->customer->email;
    //$url_addition = 'CUSTOMERS?$filter=email eq \''.$email.'\' or phone eq \''.$phone.'\'';
    $url_addition = 'CUSTOMERS?$filter=email eq \''.$email.'\'';
    $response = $this->makeRequest('GET', $url_addition,[],$user);
    if($response['code']== '200' && !empty(json_decode($response['body'])->value)){
        $reponse_customer =  $response;
    }
    else{
        $response = $this->post_customer_to_priority($order);
        $reponse_customer =  $response ;
    }
    return $reponse_customer;
}
function post_order_to_priority( $order ) {
    $user = $this->get_user();
    
    if(!empty($this->get_user_api_config('POST_CUSTOMER')) && $this->get_user_api_config('POST_CUSTOMER') == "true"){
        $reponse_customer =$this->check_customer_existence($order);
        //get response of customer
        if($reponse_customer['code']== '200' && !empty(json_decode($reponse_customer['body'])->value)){
            $cust_number = json_decode($reponse_customer['body'])->value[0]->CUSTNAME;

        }
        //if customer doesnt exist, return response of post_customer
        elseif($reponse_customer['code']== 201){
            $cust_number = json_decode( $reponse_customer['body'])->CUSTNAME;

        }
        //didn't success to get or post customer, so stop post order and return response to display it in post
        else{
            return $reponse_customer;
        }
    }
    else{
        $cust_number = get_user_meta( $user->ID, 'walk_in_customer_number', true );
    }


    //check if tax included- and calculate price according to this field
    $tax_included = $order->taxes_included;
    if($tax_included == 'true'){
        $price_field = 'VATPRICE';
    }
    else{
        $price_field = 'PRICE';
    }

    $data        = [
        'CUSTNAME' => $cust_number,
        'CDES'     => $order->billing_address->first_name.' '.$order->billing_address->last_name,
        //'CURDATE'  => date('Y-m-d', strtotime($order->get_date_created())),
        'BOOKNUM'  => 'SHOPIFY'.$order->name,
        //'DETAILS'  => trim(preg_replace('/\s+/', ' ', $order->note))
    ];
    // billing customer details
    $customer_data                = [
        'PHONE' => $order->customer->phone,
        'EMAIL' => $order->customer->email,
        'ADRS'  => $order->default_address->address1,
    ];
    $data['ORDERSCONT_SUBFORM'][] = $customer_data;
    // shipping
    $shipping_data           = [
    'NAME'      => $order->shipping_address->first_name.' '.$order->shipping_address->last_name,
    'EMAIL' => $order->customer->email,
    'CUSTDES'   => $order->shipping_address->first_name.' '.$order->shipping_address->last_name,
    'PHONENUM'  => $order->shipping_address->phone,
    'ADDRESS'   => $order->shipping_address->address1,
    'ADDRESS2'   => $order->shipping_address->address2,
    'ADDRESS3'   => $order->shipping_address->province, //.' '.$order->shipping_address->country,
    'ZIP'   => $order->shipping_address->zip,
    'STATE'      => $order->shipping_address->city
    ];
    $data['SHIPTO2_SUBFORM'] = $shipping_data;

    // get ordered items
    foreach ( $order->line_items as $item ) {
        $partname = $item->sku;
        // debug
        if (!empty($this->generalpart)) {
        $partname = '000';
        }
        $second_code = isset($item->second_code) ? $item->second_code : '';
        $unit_price = isset($item->unit_price) ? (float) $item->unit_price : 0.0;
        $quantity = isset($item->quantity) ? (int)$item->quantity : 0;

        $discount_allocations = $item->discount_allocations;
        $discount_amount = 0;
        if(!empty($discount_allocations)){
            foreach ( $discount_allocations as $discount_line) {
                $discount_amount+= (float) $discount_line->amount;
            }
        }
        $data['ORDERITEMS_SUBFORM'][] = [
        'PARTNAME' => $partname,
        //'PARTNAME' => '000',
        'TQUANT'   => (int) $item->quantity,
        $price_field => (($tax_included) ? ((float)$item->price * (float)$item->quantity - $discount_amount) : (float)$item->price - ($discount_amount / (float)$item->quantity))
        
        //'REMARK1'  =>$second_code,
        //'DUEDATE' => date('Y-m-d', strtotime($campaign_duedate)),
        ];
    }
    // get discounts as items
    //04/04/21- recalculate discount - we dont need the general discount because eah line has his discount.
    // $discount_data =$this->get_discounts($order);
    // if(!is_null($discount_data)){
    //     $data['ORDERITEMS_SUBFORM'][] = $this->get_discounts($order);
    // }


    // shipping rate
    $shipping = $order->total_shipping_price_set->presentment_money;
    if($shipping->amount>0){
        $shipping_sku = (($this->get_user_api_config('SHIPPING_PARTNAME')) ? $this->get_user_api_config('SHIPPING_PARTNAME') : '000');
        $data['ORDERITEMS_SUBFORM'][] = [
            'PARTNAME' => $shipping_sku,
            //'PDES'     => '',
            'TQUANT'   => (int)1,
            $price_field => (float)$shipping->amount
        ];
    }
    $data['PAYMENTDEF_SUBFORM'] = $this->get_payment_details($order);

    $response = $this->makeRequest( 'POST', 'ORDERS', [ 'body' => json_encode( $data ) ], $user );
    if(!empty($reponse_customer)){
        //add customer response to response to display it in post
        $response['customer_response'] = $reponse_customer['body'];
    }
    return $response;    

}
function post_otc_to_priority( $order ) {
    $user = $this->get_user();
    if(!empty($this->get_user_api_config('POST_CUSTOMER')) && $this->get_user_api_config('POST_CUSTOMER') == "true"){
        $reponse_customer =$this->check_customer_existence($order);
        //get response of customer
        if($reponse_customer['code']== '200' && !empty(json_decode($reponse_customer['body'])->value)){
            $cust_number = json_decode($reponse_customer['body'])->value[0]->CUSTNAME;

        }
        //if customer doesnt exist, return response of post_customer
        elseif($reponse_customer['code']== 201){
            $cust_number = json_decode( $reponse_customer['body'])->CUSTNAME;

        }
        //didn't success to get or post customer, so stop post order and return response to display it in post
        else{
            return $reponse_customer;
        }
    }
    else{
        $cust_number = get_user_meta( $user->ID, 'walk_in_customer_number', true );
    }

    
        $data        = [
            'CUSTNAME' => $cust_number,
            'CDES'     => $order->billing_address->first_name.' '.$order->billing_address->last_name,
            'IVDATE'  => date('Y-m-d', strtotime($order->created_at)),
            'BOOKNUM'  => 'SHOPIFY'.$order->name,
            'DETAILS'  => 'SHOPIFY'.$order->name,
        ];
    // billing customer details
        $customer_data                = [
            'PHONE' => $order->customer->phone,
            'EMAIL' => $order->customer->email,
            'ADRS'  => $order->default_address->address1,
        ];
        $data['EINVOICESCONT_SUBFORM'][] = $customer_data;
        // shipping
        $shipping_data           = [
            'NAME'      => $order->shipping_address->first_name.' '.$order->shipping_address->last_name,
            'CUSTDES'   => $order->shipping_address->first_name.' '.$order->shipping_address->last_name,
            'PHONENUM'  => $order->shipping_address->phone,
            'ADDRESS'   => $order->shipping_address->address1,
            'STATE'      => $order->shipping_address->city
        ];
        $data['SHIPTO2_SUBFORM'] = $shipping_data;
        // get ordered items
        foreach ( $order->line_items as $item ) {
            $partname = $item->sku;
        // debug
            if (!empty($this->generalpart)) {
                $partname = '000';
            }
            $second_code = isset($item->second_code) ? $item->second_code : '';
            $unit_price = isset($item->unit_price) ? (float) $item->unit_price : 0.0;
            $quantity = isset($item->quantity) ? (int)$item->quantity : 0;
            $discount_allocations = $item->discount_allocations;
            $discount_amount = 0;
            if(!empty($discount_allocations)){
                foreach ( $discount_allocations as $discount_line) {
                    $discount_amount+= (float) $discount_line->amount;
                }
            }
            $data['EINVOICEITEMS_SUBFORM'][] = [
                'PARTNAME' => $partname,
                'TQUANT'   => (int) $item->quantity,
                // 'TOTPRICE' => (float)$item->price * (float)$item->quantity - $item->total_discount,  /* roy change discount calcs 20.5.21 */
                'TOTPRICE' => (float)$item->price * (float)$item->quantity - $discount_amount,
            //  if you are working without tax prices you need to modify this line Roy 7.10.18
            //'REMARK1'  =>$second_code,
            //'DUEDATE' => date('Y-m-d', strtotime($campaign_duedate)),
            ];
        }

        // get discounts as items
        //$discount =  $order->total_discount_set->presentment_money;
        $discount_partname = '000';
        $discount_codes = $order->discount_codes;
        foreach ( $discount_codes as $discount_line) {
            $data['EINVOICEITEMS_SUBFORM'][] = $this->get_discounts($order);
        }

        // shipping rate

        $shipping = $order->total_shipping_price_set->presentment_money;
        if((int)$shipping->amount>0){
            $shipping_sku = $this->get_user_api_config('SHIPPING_PARTNAME');
            $shipping_sku = (($shipping_sku) ? ($shipping_sku) : '000');
            $data['EINVOICEITEMS_SUBFORM'][] = [
                'PARTNAME' => $shipping_sku,
                //'PDES'     => '',
                'TQUANT'   => (int)1,
                'TOTPRICE' => (float)$shipping->amount
            ];
        }

        $data['EPAYMENT2_SUBFORM'][] = $this->get_payment_details($order);
        
        // make request
        $response = $this->makeRequest( 'POST', 'EINVOICES', [ 'body' => json_encode( $data ) ], $user );
        if(!empty($reponse_customer)){
            //add customer response to response to display it in post
            $response['customer_response'] = $reponse_customer['body'];
        }
        return $response;  
}
function get_discounts($order){
    //$discount =  $order->total_discount_set->presentment_money;
    $discount_partname = '000';
    $discount_codes = $order->discount_codes;
    foreach ( $discount_codes as $discount_line) {
        $data = [
            'PARTNAME' => $discount_partname,
            'TQUANT'   => (int)-1,
            ($this->document == 'order' ? 'VPRICE' : 'TOTPRICE') => (float) $discount_line->amount * - 1.0,
            'PDES'     => $discount_line->code.' '.$discount_line->type,
        ];
    }
    return $data;
}
function post_customer_to_priority( $order ) {
    $user = $this->get_user();

    $data        = [
        'CUSTDES'   => $order->customer->first_name.' '.$order->customer->last_name,
        'PHONE' => $order->customer->phone,
        'EMAIL' => $order->customer->email,
        'ADDRESS'     => $order->customer->default_address->address1,
        'ADDRESS2'  => $order->customer->default_address->address2,
        'STATE'     => $order->customer->default_address->country,
        'ZIP'  => $order->customer->default_address->zip,
    ];
    $response = $this->makeRequest( 'POST', 'CUSTOMERS', [ 'body' => json_encode( $data ) ], $user );
    //$custname = json_decode( $response['body'])->CUSTNAME;
    return  $response;
   
}
function post_shipment_to_priority( $order ) {
    $user = $this->get_user();
    if(!empty($this->get_user_api_config('POST_CUSTOMER')) && $this->get_user_api_config('POST_CUSTOMER') == "true"){
        $reponse_customer =$this->check_customer_existence($order);
        //get response of customer
        if($reponse_customer['code']== '200' && !empty(json_decode($reponse_customer['body'])->value)){
            $cust_number = json_decode($reponse_customer['body'])->value[0]->CUSTNAME;

        }
        //if customer doesnt exist, return response of post_customer
        elseif($reponse_customer['code']== 201){
            $cust_number = json_decode( $reponse_customer['body'])->CUSTNAME;

        }
        //didn't success to get or post customer, so stop post order and return response to display it in post
        else{
            return $reponse_customer;
        }
    }
    else{
        $cust_number = get_user_meta( $user->ID, 'walk_in_customer_number', true );
    }
    $data        = [
        'CUSTNAME' => $cust_number,
        'CDES'     => $order->billing_address->first_name.' '.$order->billing_address->last_name,
        'CURDATE'  => date('Y-m-d', strtotime($order->created_at)),
        'BOOKNUM'  => 'SHOPIFY'.$order->name,
    ];
    // billing customer details
        $customer_data                = [
            'PHONE' => $order->customer->phone,
            'EMAIL' => $order->customer->email,
            'ADRS'  => $order->default_address->address1,
        ];
        $data['DOCUMENTS_DCONT_SUBFORM'][] = $customer_data;
        // shipping
        $shipping_data           = [
            'NAME'      => $order->shipping_address->first_name.' '.$order->shipping_address->last_name,
            'CUSTDES'   => $order->shipping_address->first_name.' '.$order->shipping_address->last_name,
            'PHONENUM'  => $order->shipping_address->phone,
            'ADDRESS'   => $order->shipping_address->address1,
            'STATE'      => $order->shipping_address->city
        ];
        $data['SHIPTO2_SUBFORM'] = $shipping_data;

        // get ordered items
        foreach ( $order->line_items as $item ) {
            $partname = $item->sku;
        // debug
            if (!empty($this->generalpart)) {
                $partname = '000';
            }
            $second_code = isset($item->second_code) ? $item->second_code : '';
            $unit_price = isset($item->unit_price) ? (float) $item->unit_price : 0.0;
            $quantity = isset($item->quantity) ? (int)$item->quantity : 0;
            $data['TRANSORDER_D_SUBFORM'][] = [
                'PARTNAME' => $partname,
                'TQUANT'   => (int) $item->quantity,
                'VPRICE' => (float)$item->price,
        //  if you are working without tax prices you need to modify this line Roy 7.10.18
        //'REMARK1'  =>$second_code,
        //'DUEDATE' => date('Y-m-d', strtotime($campaign_duedate)),
            ];
        }

        // get discounts as items
        $discount =  $order->total_discounts_set->presentment_money;
        $discount_partname = '000';
        if(!empty($discount)){
                $data['TRANSORDER_D_SUBFORM'][] = [
                    'PARTNAME' => $discount_partname,
                    'TQUANT'   => (int)-1,
                    'VPRICE' => (float) $discount->amount,
                    //'PDES'     => $item->title,
                    //'DUEDATE' => date('Y-m-d', strtotime($campaign_duedate)),
                ];
        }
    // shipping rate

        $shipping = $order->total_shipping_price_set->presentment_money;

        $data['TRANSORDER_D_SUBFORM'][] = [
    // 'PARTNAME' => $this->option('shipping_' . $shipping_method_id, $order->get_shipping_method()),
            'PARTNAME' => '000',
            'PDES'     => '',
            'TQUANT'   => (int)1,
            'VPRICE' => (float)$shipping->amount
        ];
    // $data['PAYMENTDEF_SUBFORM'] = $this->get_payment_details($order);
    // make request
    //echo json_encode($data);
    $response = $this->makeRequest( 'POST', 'DOCUMENTS_D', [ 'body' => json_encode( $data ) ], $user );
    if(!empty($reponse_customer)){
        //add customer response to response to display it in post
        $response['customer_response'] = $reponse_customer['body'];
    }
    return $response;      
}
function update_products_to_service(){
    $user = $this->get_user();
    $shopify_base_url = 'https://'.get_user_meta( $user->ID, 'shopify_url', true ).'/admin/api/2020-04/products.json';
    $method = 'POST';
    $YOUR_USERNAME = get_user_meta( $user->ID, 'shopify_username', true );
    $YOUR_PASSWORD = get_user_meta( $user->ID, 'shopify_password', true );

    $products = $this->get_products_from_priority();
    foreach ($products as $product){
        $p_data['title'] = $product['PARTDES'];
        $p_data['sku'] = $product['PARTNAME'];
        $p_data['product_type'] = $product['FAMILYNAME'];
        $data['product'] = $p_data;
        $args   = [
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode( $YOUR_USERNAME . ':' . $YOUR_PASSWORD ),
                'Content-Type'  => 'application/json'
            ),
            'timeout' => 450,
            'method'  => strtoupper( $method ),
            //'sslverify' => $this->option('sslverify', false)
            'body'    => json_encode($data)
        ];
        if ( ! empty( $options ) ) {
            $args = array_merge( $args, $options );
        }
        $responses[] = wp_remote_request( $shopify_base_url, $args );
    }
    return $responses;
}
function get_payment_details($order){
        // payment info
        $shopify_cards_dictionary   = array(
            1 => '1',  // Isracard
            2 => '5',  // Visa
            3 => '3',  // Diners
            4 => '4',  // Amex
            5 => '5',  // JCB
            6 => '6'   // Leumi Card
        );
        $payment_code               = ''; // Shopify does not provide credit card data
        $payment_code = $this->get_user_api_config('PAYMENTCODE');
        if('paypal' == $order->gateway){
            if(!empty($this->get_user_api_config('PAYPALCODE'))){
                $payment_code = $this->get_user_api_config('PAYPALCODE');
            }
        }
        $data = [
            'PAYMENTCODE' => $payment_code,
            'QPRICE'      => (float) $order->total_price_set->presentment_money->amount,
            // shopify can handle multi paymnets so this might be modified
            //'PAYACCOUNT'  => '',
            //'PAYCODE'     => '',
            //'PAYACCOUNT'  => '',
            //'VALIDMONTH'  => '',
            'CCUID'       => $order->token,
            'CONFNUM'     => $order->checkout_token,
            //'FIRSTPAY' => $order_first_payment,
        ];
        return $data;
    }
function set_inventory_level_to_location($location_id,$partname){
    // get inventory from Priority
    $updated_items = [];
    $daysback = $this->get_user_api_config('SYNC_INVENTORY_DAYS_BACK') ?? '3';
    $stamp = mktime(1 - ($daysback*24), 0, 0);
    $bod = date(DATE_ATOM,$stamp);
    $url_time_filter = urlencode('(WARHSTRANSDATE ge '.$bod. ' or PURTRANSDATE ge '.$bod .' or SALETRANSDATE ge '.$bod.')');
    $url_eddition = 'LOGPART?$filter='.$url_time_filter.'&$select=PARTNAME&$expand=LOGCOUNTERS_SUBFORM,PARTBALANCE_SUBFORM($select=WARHSNAME,LOCNAME,TBALANCE)';
    if(!empty($partname)){
        $url_eddition = 'LOGPART?$filter=PARTNAME eq \''.$partname.'\' &$select=PARTNAME&$expand=LOGCOUNTERS_SUBFORM,PARTBALANCE_SUBFORM($select=WARHSNAME,LOCNAME,TBALANCE)';
    }
    $response = $this->makeRequest( 'GET', $url_eddition, null, $this->get_user());
    if($response['code']== '200'){
        $items = json_decode($response['body'])->value;
    }else{
        $error_message = $response['body'];
        $this->sendEmailError('Shopify Error while sync inventory',$error_message);
        return $error_message;
    }
    // get from Shopify
    $inventory_levels =  $this->get_stock_level_from_shopify($location_id);
    $variants = $this->get_list_of_variants_from_shopify();
    // loop over items
    foreach($items as $item){
        $sku = $item->PARTNAME;
        // use filter priority_hub_shopify_inventory to manipulate the PARTNAME
        $data = apply_filters('priority_hub_shopify_inventory',['user'=>$this->get_user(),'sku'=>$sku]);
        $sku = $data['sku'];
        $item->stock = $item->LOGCOUNTERS_SUBFORM[0]->BALANCE;
        $priority_stock_data = apply_filters( 'simply_change_priority_stock_field', ['user'=>$this->get_user(), 'item' => $item ]);
        $priority_stock = $priority_stock_data['item']->stock;
        if(!empty($variants)) {
            foreach ($variants as $variant) {
                $shopify_sku = $variant->sku;
                if ($shopify_sku == $sku) {
                    $id = $variant->id;
                    $inventory_management = $variant->inventory_management;
                    $inventory_item_id = $variant->inventory_item_id;
                    // compare Shopify stock and Priority stock
                    $item_has_level = false;

                    if (empty($inventory_levels)) {
                        $response = $this->set_inventory_level($location_id, $inventory_item_id, $priority_stock);
                        $updated_items[] = ['sku' => $shopify_sku, 'stock' => $priority_stock, 'response' => $response];
                    }else{
                        foreach ($inventory_levels as $inv) {
                            if ($inventory_item_id == $inv->inventory_item_id) {
                                if ($inv->available != $priority_stock) {
                                    // update Shopify
                                    $item_has_level = true;
                                    $response = $this->set_inventory_level($location_id, $inventory_item_id, $priority_stock);
                                    $updated_items[] = ['sku' => $shopify_sku, 'stock' => $priority_stock, 'response' => $response];
                                }
                            }
                        }
                    }
                    // do the trick of update variant data and stock !
                }
            }
        }
    }
return $updated_items;
}
function set_inventory_level_to_user(){
    $this->location_id = $this->get_user_api_config('LOCATION_ID');
    $this->set_inventory_level2($sku = null);

   // $location_id = $this->get_user_api_config('LOCATION_ID');
   // $updated_items = $this->set_inventory_level_to_location($location_id,null);
   // error_log('Sync inventory to shopify '.print_r($updated_items));
}
function get_stock_level_from_shopify($location_id){
    // get stock levels from Shopify
    $shopify_base_url = 'https://'.get_user_meta( $this->get_user()->ID, 'shopify_url', true ).'/admin/api/2020-04/inventory_levels.json?location_ids='.$location_id;
    $method = 'GET';
    $YOUR_USERNAME = get_user_meta( $this->get_user()->ID, 'shopify_username', true );
    $YOUR_PASSWORD = get_user_meta( $this->get_user()->ID, 'shopify_password', true );
    $args   = [
        'headers' => array(
            'Authorization' => 'Basic ' . base64_encode( $YOUR_USERNAME . ':' . $YOUR_PASSWORD ),
            'rel' => 'next'
        ),
        'timeout' => 450,
        'method'  => strtoupper( $method ),
        //'sslverify' => $this->option('sslverify', false)
    ];
    if ( ! empty( $options ) ) {
        $args = array_merge( $args, $options );
    }
    $response = wp_remote_request( $shopify_base_url, $args );
    $subject = 'shopify Error for user ' . get_user_meta( $this->get_user()->ID, 'nickname', true );
    if ( is_wp_error( $response ) ) {
        $this->sendEmailError($subject, $response->get_error_message() );
    } else {
        $respone_code    = (int) wp_remote_retrieve_response_code( $response );
        $respone_message = $response['body'];
        If ( $respone_code <= 201 ) {
            $inventory_levels = json_decode( $response['body'] )->inventory_levels;
            $header = $response['headers'];
            $header_data = $header->getAll();
            //$header_data = $header['data'];
            $header_url = explode('<',$header_data['link'],2)[1];
            $header_url = explode('>',$header_url,2)[0];
            while($header_url) {
                $response2 = wp_remote_request($header_url,$args);
                if ($respone_code <= 201) {
                    $next_inventory_levels = [];
                    if(isset(json_decode($response2['body'])->inventory_levels)){
                        $next_inventory_levels = json_decode($response2['body'])->inventory_levels;
                    }
                    if(sizeof($next_inventory_levels)==0){
                        break;
                    }
                    $inventory_levels = array_merge($inventory_levels, $next_inventory_levels);
                    $header = $response2['headers'];
                    $header_data = $header->getAll();
                    //$header_data = $header['data'];
                    //$link_array1 = explode(',',$header_data['link']);
                    //$link_array2 = explode(';',$header_data['link']);
                    if(!empty(explode(',',$header_data['link'])[1])){
                        $rel_next = explode(',',$header_data['link'])[1];
                    }else{
                        break;
                    }
                    if(isset($rel_next)){
                        $new_url = $rel_next;
                    }else{
                        $new_url = $header_data['link'];
                    }
                    $header_url = explode('<', $new_url, 2)[1];
                    $header_url = explode('>', $header_url, 2)[0];

                }
            }
            return $inventory_levels;
        }
        if ( $respone_code >= 400 && $respone_code <= 499 &&  $respone_code != 404 ) {
            $error = $respone_message . '<br>' . $shopify_base_url;
            $this->sendEmailError( $subject, $error );
            return null;
        }
        if($respone_code == 404 ){
            return null;
        }
    }
}
function get_list_of_variants_from_shopify(){
        // get stock levels from Shopify
        $shopify_base_url = 'https://'.get_user_meta( $this->get_user()->ID, 'shopify_url', true ).'/admin/api/2020-04/variants.json';
        $method = 'GET';
        $YOUR_USERNAME = get_user_meta( $this->get_user()->ID, 'shopify_username', true );
        $YOUR_PASSWORD = get_user_meta( $this->get_user()->ID, 'shopify_password', true );
        $args   = [
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode( $YOUR_USERNAME . ':' . $YOUR_PASSWORD ),
                'rel' => 'next'
            ),
            'timeout' => 450,
            'method'  => strtoupper( $method ),
            //'sslverify' => $this->option('sslverify', false)
        ];
        if ( ! empty( $options ) ) {
            $args = array_merge( $args, $options );
        }
        $response = wp_remote_request( $shopify_base_url, $args );
        $subject = 'shopify Error for user ' . get_user_meta( $this->get_user()->ID, 'nickname', true );
        if ( is_wp_error( $response ) ) {
            $this->sendEmailError($subject, $response->get_error_message() );
        } else {
            $respone_code    = (int) wp_remote_retrieve_response_code( $response );
            $respone_message = $response['body'];
            If ( $respone_code <= 201 ) {
                $products = json_decode( $response['body'] )->variants;
                $header = $response['headers'];
                $header_data = $header->getAll();
                //$header_data = $header['data'];
                $header_url = explode('<',$header_data['link'],2)[1];
                $header_url = explode('>',$header_url,2)[0];
                while($header_url) {
                    $response2 = wp_remote_request($header_url,$args);
                    if ($respone_code <= 201) {
                        $next_products = json_decode($response2['body'])->variants;
                        if(sizeof($next_products)==0){
                            break;
                        }
                        $products = array_merge($products, $next_products);
                        $header = $response2['headers'];
                        $header_data = $header->getAll();
                        //$header_data = $header['data'];
                        if(!empty(explode(',',$header_data['link'])[1])){
                            $rel_next = explode(',',$header_data['link'])[1];
                        }else{
                            break;
                        }
                        if(isset($rel_next)){
                            $new_url = $rel_next;
                        }else{
                            $new_url = $header_data['link'];
                        }
                        $header_url = explode('<', $new_url, 2)[1];
                        $header_url = explode('>', $header_url, 2)[0];

                    }
                }
                return $products;
            }
            if ( $respone_code >= 400 && $respone_code <= 499 &&  $respone_code != 404 ) {
                $error = $respone_message . '<br>' . $shopify_base_url;
                $this->sendEmailError( $subject, $error );
                return null;
            }
            if($respone_code == 404 ){
                return null;
            }
        }
    }
function set_inventory_level($location_id,$inventory_item_id,$available){
        // set stock level
        $shopify_base_url = 'https://'.get_user_meta( $this->get_user()->ID, 'shopify_url', true ).'/admin/api/2020-04/inventory_levels/set.json';
        $method = 'POST';
        $YOUR_USERNAME = get_user_meta( $this->get_user()->ID, 'shopify_username', true );
        $YOUR_PASSWORD = get_user_meta( $this->get_user()->ID, 'shopify_password', true );
        $body = [
            'location_id'       => $location_id,
            'inventory_item_id' => $inventory_item_id,
            'available'         => $available
        ];
        $args   = [
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode( $YOUR_USERNAME . ':' . $YOUR_PASSWORD ),
                'Content-Type'  => 'application/json'
            ),
            'timeout' => 450,
            'data_format' => 'body',
            'method'  => strtoupper( $method ),
            'body'    =>  json_encode( $body ),
            //'sslverify' => $this->option('sslverify', false),
        ];
        if (!empty($options)){
            $args = array_merge( $args, $options );
        }
        $response = wp_remote_request( $shopify_base_url, $args );
        $subject = 'shopify Error for user ' . get_user_meta( $this->get_user()->ID, 'nickname', true );
        if ( is_wp_error( $response ) ) {
            $this->write_to_log('wp_error -  updating stock to Shopify: '.$response->get_error_message());
            $this->sendEmailError($subject, $response->get_error_message() );
        } else {
            $respone_code    = (int) wp_remote_retrieve_response_code( $response );
            $respone_message = $response['body'];
            If ( $respone_code <= 201 ) {
               return $respone_code;
            }
            if ( $respone_code >= 400 && $respone_code <= 499 &&  $respone_code != 404 ) {
                $this->write_to_log($respone_code.' error updating stock to Shopify: '.$respone_message);
                $error = $respone_message . '<br>' . $shopify_base_url;
                $this->sendEmailError( $subject, $error );
                return $respone_code;
            }
            if($respone_code == 404 ){
                $this->write_to_log('404 error updating stock to Shopify: '.$respone_message);
                return 404;
            }
        }

    }
function get_inv_level_by_sku_graphql($sku){
        $location_id = $this->get_user_api_config('LOCATION_ID');
        $endpoint =  'https://'.explode('@',get_user_meta( $this->get_user()->ID, 'shopify_url', true ))[1].'/admin/api/2020-04/graphql.json';
        $query = <<<MARKER
                  {"query":"{\\r\\n  inventoryItems(query:\\"sku:$sku\\", first:5) {\\r\\n    edges {\\r\\n      node {\\r\\n        id\\r\\n        sku\\r\\n        inventoryLevel (locationId:\\"gid://shopify/Location/$location_id\\") {\\r\\n           available\\r\\n           id\\r\\n        }\\r\\n      }\\r\\n    }\\r\\n  }\\r\\n}","variables":{}}
MARKER;
        $accessToken = get_user_meta( $this->get_user()->ID, 'shopify_password', true );

        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => $endpoint,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $query,
            CURLOPT_HTTPHEADER => array(
                'X-Shopify-Access-Token: '.$accessToken,
                'Content-Type: application/json',
                'Cookie: '
            ),
        ));

        $response = curl_exec($curl);
        $this->write_to_log($response);
        curl_close($curl);
        $res = json_decode($response);

        if(empty($res->data->inventoryItems->edges)){
            return null;
        }
        if(sizeof($res->data->inventoryItems->edges)>0){
            $inventory_level = $res->data->inventoryItems->edges[0]->node;
        }else{
            $inventory_level = null;
        }
        return $inventory_level;


    }
function set_inventory_level2($partname){
        // get inventory from Priority
        $this->write_to_log('Start to sync inventory');
        $updated_items = '';
        $daysback = $this->get_user_api_config('SYNC_INVENTORY_DAYS_BACK') ?? '3';
        $stamp = mktime(1 - ($daysback*24), 0, 0);
        $bod = date(DATE_ATOM,$stamp);
        $url_time_filter = urlencode('INVFLAG eq \'Y\' and (WARHSTRANSDATE ge '.$bod. ' or PURTRANSDATE ge '.$bod .' or SALETRANSDATE ge '.$bod.')');
        $url_eddition = 'LOGPART?$filter='.$url_time_filter.'&$select=PARTNAME&$expand=LOGCOUNTERS_SUBFORM,PARTBALANCE_SUBFORM($select=WARHSNAME,LOCNAME,TBALANCE)';
        if(!empty($partname)){
            $url_eddition = 'LOGPART?$filter=PARTNAME eq \''.$partname.'\' &$select=PARTNAME&$expand=LOGCOUNTERS_SUBFORM,PARTBALANCE_SUBFORM($select=WARHSNAME,LOCNAME,TBALANCE)';
        }
        $response = $this->makeRequest( 'GET', $url_eddition, null, $this->get_user());
        if($response['code']== '200'){
            $items = json_decode($response['body'])->value;
        }else{
            $error_message = $response['body'];
            $this->sendEmailError('Shopify Error while sync inventory',$error_message);
            return $error_message;
        }
        // loop over items
        $updated_item = 'Start loop items from Priority';
        foreach($items as $item){
            $this->write_to_log($updated_item);
            $sku = $item->PARTNAME;
            // use filter priority_hub_shopify_inventory to manipulate the PARTNAME
            $data = apply_filters('priority_hub_shopify_inventory',['user'=>$this->get_user(),'sku'=>$sku]);
            $sku = $data['sku'];
            if(empty($item->LOGCOUNTERS_SUBFORM[0]->BALANCE)){
                $updated_items .= 'SKU: '.$sku.' does not have stock in Priority! <br>';
                continue;
            }
            $item->stock = $item->LOGCOUNTERS_SUBFORM[0]->BALANCE;
            $priority_stock_data = apply_filters( 'simply_change_priority_stock_field', ['user'=>$this->get_user(), 'item' => $item ]);
            $priority_stock = $priority_stock_data['item']->stock;
            $inventory_level = $this->get_inv_level_by_sku_graphql($sku);
            if(is_null($inventory_level)){
                $updated_item = 'SKU: '.$sku.' does not exists in Shopify.'.$inventory_level;
                $updated_items .= $updated_item.'<br>';
                continue;
            }
            if($priority_stock == $inventory_level->inventoryLevel->available){
                $updated_item = 'SKU: '.$sku.' Stock in Shopify '.$priority_stock.' equal to stock in Priority.';
                $updated_items .= $updated_item.'<br>';

            }else{
                // update shopify
                $inventory_item_id = str_replace('gid://shopify/InventoryItem/','',explode('?',$inventory_level->id))[0];
                $this->write_to_log('params: '.$this->location_id.' '.$inventory_item_id.' '.$priority_stock);
                $this->set_inventory_level($this->location_id,$inventory_item_id,$priority_stock);
                $updated_item = 'SKU: '.$sku.' Stock set to '.$priority_stock.' .';
                $updated_items .= $updated_item.'<br>';

            }

        }
        //$this->write_to_log($updated_items);
        return $updated_items;
    }
}
