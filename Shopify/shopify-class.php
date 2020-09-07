<?php
class Shopify extends \Priority_Hub {
public static $instance;
public  $debug;
public static function instance()
{
if (is_null(static::$instance)) {
static::$instance = new static();
}

return static::$instance;
}
public function __construct() {
add_action( 'init', array($this,'custom_post_type'), 998 );
add_action('init', array($this,'register_tag'),999);
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
    'meta_key' => 'shopify_activate_sync',
    'meta_value' => true
    );
    // The User Query
    $user_query = new WP_User_Query( $args );
    // The User Loop
    if ( ! empty( $user_query->results ) ) {
        foreach ( $user_query->results as $user ) {
            $activate_sync = get_user_meta( $user->ID, 'shopify_activate_sync',true );
            if ( $activate_sync ) {
                //echo 'Start sync  ' . get_user_meta( $user->ID, 'nickname', true ) . '<br>';
                ini_set( 'MAX_EXECUTION_TIME', 0 );
                $responses[$user->ID] = $this->get_orders_by_user( $user );
            }
        }
    } else {
    // no shop_manager found
    }
    return $responses;
} // return array user/orders
function get_orders_by_user( $user ) {
    // this function return the orders as array, if error return null
    // the function handles the error internally
    //echo 'Getting orders from  Shopify...<br>';
    $last_sync_time = get_user_meta( $user->ID, 'shopify_last_sync_date', true );
    $order_id         = '';
    //$orders_limit     = '?created_at_min=2020-06-15T00:00:00Z';
    $orders_limit  = '?created_at_min=' . $last_sync_time;
    $shopify_base_url = 'https://'.get_user_meta( $user->ID, 'shopify_url', true ).'/admin/api/2020-04/orders.json'.$orders_limit;
    $new_sync_time = date( "c" );
    if ( !$this->debug ) {
        update_user_meta( $user->ID, 'shopify_last_sync_date', $new_sync_time );
    }
    $filter_status = '&payment_status=אשראי - מלא';
    //$konimbo_url   = $konimbo_base_url . $order_id . $token . $orders_limit . $filter_status;
    // debug url
    if ($this->debug) {
        $shopify_base_url = 'https://'.get_user_meta( $user->ID, 'shopify_url', true ).'/admin/api/2020-04/orders.json?name='.$this->order;
    }
    $method = 'GET';
    //$YOUR_USERNAME = 'aa4f3bc167e3b86c475eb2aefac63bf3';
    $YOUR_USERNAME = get_user_meta( $user->ID, 'shopify_username', true );
    //$YOUR_PASSWORD = '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';
    $YOUR_PASSWORD = get_user_meta( $user->ID, 'shopify_password', true );
    $args   = [
        'headers' => array(
            'Authorization' => 'Basic ' . base64_encode( $YOUR_USERNAME . ':' . $YOUR_PASSWORD )
    ),
    'timeout' => 450,
    'method'  => strtoupper( $method ),
    //'sslverify' => $this->option('sslverify', false)
    ];


    if ( ! empty( $options ) ) {
        $args = array_merge( $args, $options );
    }
    $response = wp_remote_request( $shopify_base_url, $args );
    $subject = 'Shopify Error for user ' . get_user_meta( $user->ID, 'nickname', true );
    if ( is_wp_error( $response ) ) {
        //echo 'internal server error<br>';
        //echo 'Konimbo error: '.$response->get_error_message();
        $this->sendEmailError($subject, $response->get_error_message() );
    } else {
        $respone_code    = (int) wp_remote_retrieve_response_code( $response );
        $respone_message = $response['body'];
        If ( $respone_code <= 201 ) {
            $orders = json_decode( $response['body'] )->orders;
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
} // return array of Shopify orders
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
$body_array = json_decode( $response["body"], true );
// Create post object
$my_post = array(
    'post_type'    => 'shopify_order',
    'post_title'   => $order->name . ' ' . $order->billing_address->first_name.' '.$order->billing_address->last_name,
    'post_content' => json_encode( $response["body"] ),
    'post_status'  => 'publish',
    'post_author'  => $user->ID,
    'tags_input'   =>  $body_array["FORM"]["ORDERS"]["ORDNAME"]
);
// Insert the post into the database
$pid = wp_insert_post( $my_post );
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
function post_order_to_priority( $order, $user ) {

$cust_number = get_user_meta( $user->ID, 'walk_in_customer_number', true );
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
'CUSTDES'   => $order->shipping_address->first_name.' '.$order->shipping_address->last_name,
'PHONENUM'  => $order->shipping_address->phone,
'ADDRESS'   => $order->shipping_address->address1,
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
$data['ORDERITEMS_SUBFORM'][] = [
'PARTNAME' => $partname,
'TQUANT'   => (int) $item->quantity,
'VATPRICE' => (float)$item->price,
//  if you are working without tax prices you need to modify this line Roy 7.10.18
//'REMARK1'  =>$second_code,
//'DUEDATE' => date('Y-m-d', strtotime($campaign_duedate)),
];
}

// get discounts as items
$discount =  $order->total_discount_set->presentment_money;
$discount_partname = '000';
if(!empty($discount)){
    foreach ( $order->discounts as $item ) {
    $data['ORDERITEMS_SUBFORM'][] = [
    'PARTNAME' => $discount_partname,
    'TQUANT'   => (int)-1,
    'VATPRICE' => (float) $discount->price * - 1.0,
    //'PDES'     => $item->title,
    //'DUEDATE' => date('Y-m-d', strtotime($campaign_duedate)),
    ];
    }
}
// shipping rate

$shipping = $order->total_shipping_price_set->presentment_money;

$data['ORDERITEMS_SUBFORM'][] = [
// 'PARTNAME' => $this->option('shipping_' . $shipping_method_id, $order->get_shipping_method()),
'PARTNAME' => '000',
'PDES'     => '',
'TQUANT'   => (int)1,
'VATPRICE' => (float)$shipping->amount
];



// payment info
$payment              = $order->payment_details;
$shopify_cards_dictionary   = array(
1 => '1',  // Isracard
2 => '5',  // Visa
3 => '3',  // Diners
4 => '4',  // Amex
5 => '5',  // JCB
6 => '6'   // Leumi Card
);
$payment_code               = $payment->credit_card_bin;
$data['PAYMENTDEF_SUBFORM'] = [
'PAYMENTCODE' => $payment_code,
'QPRICE'      => (float) $order->total_price_set->presentment_money->amount,
// Shopify can handle multi paymnets so this might be modified
//'PAYACCOUNT'  => '',
//'PAYCODE'     => '',
'PAYACCOUNT'  => $payment->credit_card_number,
//'VALIDMONTH'  => $credit_cart_payments->card_expiration,
//'CCUID'       => $credit_cart_payments->credit_cart_token,
//'CONFNUM'     => $credit_cart_payments->order_confirmation_id,
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
$message .=  'New Priority order ' . $response_body->ORDNAME.' places successfully for Shopify order '.$response_body->BOOKNUM.'<br>';
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
$subject = 'Shopify Error for user ' . get_user_meta( $user->ID, 'nickname', true );

if ( is_wp_error( $response ) ) {
echo 'internal server error<br>';
echo $response->get_error_message();


$error = $response->get_error_message();
$this->sendEmailError($subject, $error);
} else {
$respone_code    = (int) wp_remote_retrieve_response_code( $response );
$respone_message = $response['body'];
If ( $respone_code <= 201 ) {
//echo 'Konimbo ok!!!<br>';
if ($this->debug) {
$orders = [ json_decode( $response['body'] ) ];
}
} elseif ( $respone_code >= 400 && $respone_code <= 499 ) {
echo $respone_code . ' Shopify error occures <br>';
echo $respone_message . '<br>';
echo $konimbo_url . '<br>';
if ( $respone_code != 404 ) {
$error = $respone_message . '<br>' . $konimbo_url;
$this->sendEmailError( $subject, $error );
}

}
}


}
// post type Shopify order
public function custom_post_type() {

$labels = array(
'name'                  => _x( 'Shopify Orders', 'Post Type General Name', 'text_domain' ),
'singular_name'         => _x( 'Shopify Order', 'Post Type Singular Name', 'text_domain' ),
'menu_name'             => __( 'Shopify Orders', 'text_domain' ),
'name_admin_bar'        => __( 'Shopify Order', 'text_domain' ),
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
'label'               => __( 'Shopify Order', 'text_domain' ),
'description'         => __( 'Shopify order log', 'text_domain' ),
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
register_post_type( 'shopify_order', $args );
}
function register_tag() {
    register_taxonomy_for_object_type( 'post_tag', 'shopify_order' );
}
public function sendEmailError($subject = '', $error = '')
{
$user = wp_get_current_user();
$emails  = [ $user->user_email ];
$bloguser = get_users('role=Administrator')[0];
array_push($emails,$bloguser->user_email);


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
