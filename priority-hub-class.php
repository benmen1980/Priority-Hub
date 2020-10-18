<?php
class Priority_Hub
{
    private $service;
    private $doctype;
    private $username;
    private $user;
    function get_service_name()
    {
        return 'hub';
    }
    function get_user(){
        return $this->user;
    }
    function get_post_type(){
        return strtolower($this->doctype.'_'.$this->service);
    }
    function get_doctype(){
        return strtolower($this->doctype);
    }
    public function __construct($doctype,$username)
    {
        $this->service = $this->get_service_name();
        $this->user =  get_user_by('login',$username);
        $this->doctype = $doctype;
    }
    public function run()
    {
        return is_admin() ? $this->backend() : $this->frontend();
    }
    public function get($key, $filter = null, $options = null)
    {
        if (is_null($filter)) {
            return isset($_GET[$key]) ? $_GET[$key] : null;
        }

        return filter_var($_GET[$key], filter_id($filter), $options);
    }
    public function option($option, $default = false)
    {
        return get_option(static::$prefix . $option, $default);
    }
    // decode unicode hebrew text
    public function decodeHebrew($string)
    {
        return preg_replace_callback('/\\\\u([0-9a-fA-F]{4})/', function ($match) {
            return mb_convert_encoding(pack('H*', $match[1]), 'UTF-8', 'UTF-16BE');
        }, $string);
    }
    public function makeRequest($method, $url_addition = null, $options = [], $user)
    {
        $args = [
            'headers' => [
                'Authorization' => 'Basic ' . base64_encode(get_user_meta($user->ID, 'username', true) . ':' . get_user_meta($user->ID, 'password', true)),
                'Content-Type' => 'application/json',
                'X-App-Id' => get_user_meta($user->ID, 'x-app-id', true),
                'X-App-Key' => get_user_meta($user->ID, 'x-app-key', true)
            ],
            'timeout' => 45,
            'method' => strtoupper($method),
            'sslverify' => get_user_meta($user->ID, 'ssl_verify', true)
        ];


        if (!empty($options)) {
            $args = array_merge($args, $options);
        }

        $url = sprintf('https://%s/odata/Priority/%s/%s/%s',
            get_user_meta($user->ID, 'url', true),
            get_user_meta($user->ID, 'application', true),
            get_user_meta($user->ID, 'environment_name', true),
            is_null($url_addition) ? '' : stripslashes($url_addition)
        );

        $response = wp_remote_request($url, $args);

        $response_code = wp_remote_retrieve_response_code($response);
        $response_message = wp_remote_retrieve_response_message($response);
        $response_body = wp_remote_retrieve_body($response);

        if ($response_code >= 400) {
            $response_body = strip_tags($response_body);
        }

        // decode hebrew
        $response_body_decoded = $this->decodeHebrew($response_body);


        return [
            'url' => $url,
            'args' => $args,
            'method' => strtoupper($method),
            'body' => $response_body_decoded,
            'body_raw' => $response_body,
            'code' => $response_code,
            'status' => ($response_code >= 200 && $response_code < 300) ? 1 : 0,
            'message' => ($response_message ? $response_message : $response->get_error_message())
        ];
    }
    public function sendEmailError($subject = '', $error = '')
    {
        $user = wp_get_current_user();
        $emails = [$user->user_email, get_bloginfo('admin_email')];
        if (!$emails) return;

        if ($emails && !is_array($emails)) {
            $pattern = "/[\._a-zA-Z0-9-]+@[\._a-zA-Z0-9-]+/i";
            preg_match_all($pattern, $emails, $result);
            $emails = $result[0];
        }
        $to = array_unique($emails);
        $headers = [
            'content-type: text/html'
        ];

        wp_mail($to, get_bloginfo('name') . ' ' . $subject, $error, $headers);
    }
    // process documents
    function get_orders_by_user(){
        return null; // this must be replaces by instance!
    }
    function post_user_by_username($username,$order,$document){
        $user = get_user_by('login',$username);
        $this->document = $document;
        $this->order = $order;
        $this->debug = null != $order;
        $this->generalpart = '';
        // process
        $orders = $this->get_orders_by_user();
        $responses[$user->ID] = $this->process_documents($orders);
        $messages =  $this->processResponse($responses);
        $message = $messages[$user->ID];
        $emails  = [ $user->user_email ];
        $subject = 'Priority '.$this->service.' API error ';
        if (true == $message['is_error']) {
            $this->sendEmailError($subject, $message['message']);
        }
        return $message;
    }
    function process_documents($documents)
    {
        $user = $this->get_user();
        // this function return array of all responses one per order
        $index = 0;
        $error = '';
        $responses = [];
        if (empty($documents)) {
            return;
        }
        foreach ($documents as $doc) {
            // check if receipt already been posted, and continue
            $args = array(
                'post_type' => $this->get_post_type(),
                'meta_query' => array(
                    array(
                        'key' => 'order_number',
                        'value' => $doc->id,
                        'compare' => '=',
                    )
                )
            );
            // The Query
            $the_query = new WP_Query($args);
            // The Loop
            if ($the_query->have_posts()) {
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
            }
            $responses[$doc->id] = $response;
            $response_body = json_decode($response['body']);
            $error_prefix = '';
            if ($response['code'] <= 201 && $response['code'] >= 200) {

            }
            if (!$response['status'] || $response['code'] >= 400) {
                $error_prefix = 'Error ';
            }
            $body_array = json_decode($response["body"], true);
            // Create post object
            $ret_doc_name = $this->doctype == 'order' ? 'ORDNAME' : 'IVNUM';
            $my_post = array(
                'post_type' => 'konimbo_receipt',
                'post_title' => $error_prefix . $doc->name . ' ' . $doc->id,
                'post_content' => json_encode($response),
                'post_status' => 'publish',
                'post_author' => $user->ID,
                'tags_input' => array($body_array[$ret_doc_name])
            );
            // Insert the post into the database
            $post_id = wp_insert_post($my_post);
            update_post_meta($post_id, 'order_number', $doc->id);
            $index++;
            if ('' == $response['code']) {
                break;
            }
        }
        return $responses;
    }
    function processResponse($responses){
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
                        $message .=  'New Priority  '.$this->get_doctype().' '. $response_body->IVNUM.' places successfully for '.$this->get_service_name().' order '.$response_body->BOOKNUM.'<br>';
                    }
                    if ( $response_code >= 400 && $response_code < 500 ) {
                        $is_error = true;
                        $message .= 'Error while posting '.$this->get_doctype().', Order '. $order . '<br>';
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
                        $message .= 'Server Error while posting order ' . $order .' '.$response['message'].'<br>';
                    }
                }elseif(isset($response['response']['code'])){
                    $message .= $response['body'].'<br>';
                }
            }
            $response3[$user_id] = array("message" => $message,"is_error" => $is_error);
        }
        return $response3;
    }
    function post_order_to_priority($order){
        return null;
    }
    function post_otc_to_priority($invoice){
        return null;
    }
    function post_invoice_to_priority($invoice){
        return null;
    }
    function post_receipt_to_priority($invoice){
        return null;
    }




}