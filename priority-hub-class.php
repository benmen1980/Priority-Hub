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
    function get_service_name_lower()
    {
        return strtolower($this->get_service_name());
    }
    function get_user(){
        return $this->user;
    }
    function get_post_type(){
        return strtolower($this->service.'_'.$this->doctype);
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
    public function generate_hub_form(){

        $user = wp_get_current_user();
        $user_id                = get_current_user_id();
        $username =  wp_get_current_user()->user_login;
        ?>

        <form action="" method="post" class="sync_priority_form">
            <input type="hidden" name="<?php echo $this->get_service_name_lower(); ?>>_action" value="sync_<?php echo $this->get_service_name_lower(); ?>">
            <div class="checkbox_wrapper">
                <input type="checkbox" id="generalpart" name="<?php echo $this->get_service_name_lower(); ?>_generalpart" value="generalpart">  
                <label for="generalpart"> <?php _e('Post general item','p18a');?></label><br>  
            </div>
            <label for="username"><?php _e('User Name:','p18a');?></label>
            <?php if(is_admin() ):?>
                <input type="text" id="username" name="<?php echo $this->get_service_name_lower(); ?>_username">
            <?php else:?>
                <input id="username" name="<?php echo $this->get_service_name_lower(); ?>_username" type="text"  value="<?php echo $username; ?>" readonly="reaonly"> 
            <?php endif;?>
            <label for="<?php echo $this->get_service_name_lower(); ?>_document">
                <?php _e('Select priority Entity target:','p18a');?>
            </label> 
            <select name="<?php echo $this->get_service_name_lower(); ?>_document" id="<?php echo $this->get_service_name_lower(); ?>_document">
                <option value="order"><?php _e('Order','p18a');?></option>
                <option value="ainvoice"><?php _e('Ainvoice','p18a');?></option>
                <option value="receipt"><?php _e('Receipt','p18a');?></option>
                <option value="otc"><?php _e('Over The counter invoice','p18a');?></option>
                <option value="invoice"><?php _e('Sales Invoice','p18a');?></option>
                <option value="shipment"><?php _e('Shipment','p18a');?></option>
                <option value="orderreceipt"><?php _e('Order + Receipt','p18a');?></option>
                <option value="sync_products_to_<?php echo $this->get_service_name(); ?>"><?php _e('Sync products to','p18a');?> <?php echo $this->get_service_name(); ?></option>
                <option value="sync_products_from_<?php echo $this->get_service_name(); ?>"><?php _e('Sync products from','p18a');?> <?php echo $this->get_service_name(); ?></option>
                <option value="sync_inventory_to_<?php echo $this->get_service_name(); ?>"><?php _e('Sync Inventory to','p18a');?> <?php echo $this->get_service_name(); ?></option>
            </select>
            <h6>
                Post single Order, if you keep it empty, the system will post all orders from last sync date as defined in the user page
                <br>
                In case of inventory sync, you can specify single product sku
            </h6>
            <label for="<?php echo $this->get_service_name_lower(); ?>_order">
                <?php _e('Order or SKU:','p18a');?>
            </label>
            <input type="text" id="<?php echo $this->get_service_name_lower(); ?>_order" name="<?php echo $this->get_service_name_lower(); ?>_order" value="" >
            <input name="submit" type="submit"  id="submit" class="button button-primary" value="<?php _e('Execute API','p18a');?>" />    
        </form>

    <?php 
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
    public function write_custom_log($log_msg,$user)
    {
        $log_filename = PHUB_DIR."log\\".$user;
        if (!file_exists($log_filename))
        {
            // create directory/folder uploads.
            mkdir($log_filename, 0777, true);
        }
        $log_file_data = $log_filename.'/' . date('d-M-Y') . '.log';
        // if you don't add `FILE_APPEND`, the file will be erased each time you add a log
        file_put_contents($log_file_data, date('H:i:s').' '.$log_msg . "\n", FILE_APPEND);
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
    function get_user_api_config($key){
        return json_decode(get_user_meta($this->get_user()->ID,'description',true))->$key ?? null;
    }
    public function sendEmailError($subject = '', $error = '')
    {
        $user =  $this->get_user();
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
        $service_name = $this->get_service_name();
        $this->document = $document;
        $this->order = $order;
        $this->debug = null != $order;
        //$this->generalpart = '';
        // process
        if( $document == 'sync_products_to_'.$service_name){
            $products = $this->update_products_to_service();
            $message['message'] = 'Update Products to '.$service_name.' Done!';
            return $message;
        }
        else{
            $orders = $this->get_orders_by_user();
            $responses[$user->ID] = $this->process_documents($orders);
            $messages =  $this->processResponse($responses);
            $message = $messages[$user->ID];
            $emails  = [ $user->user_email ];
            $subject = $username. ' '.$service_name.' '.$document;
            if (true == $message['is_error']) {
                $this->sendEmailError($subject, $message['message']);
            }
            return $message;
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
                        'value' => $doc->id,
                        'compare' => '=',
                    )
                )
            );
            // The Query
            $the_query = new WP_Query($args);
            // The Loop
            if ($the_query->have_posts() && !$this->get_user_api_config('allow_duplicate')) {
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
                case 'ainvoice':
                    $response = $this->post_ainvoice_to_priority($doc);
                    break;
            }

            $responses[$doc->id] = $response;
 
            $error_prefix = '';


            if (($response['code'] <= 201 && $response['code'] >= 200) ) {

            }
        
    
            if (!$response['status'] || $response['code'] >= 400) {
                $error_prefix = 'Error ';
            }
            
            $body_array = json_decode($response["body"], true);
            
            // Create post object
            $ret_doc_name = $this->doctype == 'order' ? 'ORDNAME' : 'IVNUM';

            $post_content = json_encode($response,JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            $my_post = array(
                'post_type' => $this->get_service_name().'_'.$this->get_doctype(),
                'post_title' => $error_prefix . $doc->name . ' ' . $doc->id,
                'post_content' => $post_content,
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
                    $order_args =  $response['args'] ;
                    $ordernumber =  json_decode($order_args['body'])->BOOKNUM ?? '';
                    if ( $response_code >= 200 & $response_code <= 201 ) {
                        $doc = $response_body->IVNUM ?? $response_body->ORDNAME;
                        $message .=  'New Priority  '.$this->get_doctype().' '. $doc .' places successfully for '.$this->get_service_name().' order '.$response_body->BOOKNUM.'<br>';
                    }
                    if ( $response_code >= 400 && $response_code < 500 ) {
                        $is_error = true;
                        $message .= 'Error while posting '.$this->get_doctype().' '. $ordernumber . PHP_EOL;
                        $interface_errors = $response_body->FORM->InterfaceErrors ?? 'unknown error';
                        if ( is_array( $interface_errors ) ) {
                            foreach ( $interface_errors as $err_line ) {
                                if ( is_object( $err_line ) ) {
                                    $message .=  $err_line->text .PHP_EOL;
                                }
                            }
                        }else {
                            $message .= $interface_errors->text ?? 'unknown error' . PHP_EOL;
                        }
                    }elseif(500 == $response_code || 0 == $response_code){
                        $message .= 'Server Error while posting order ' . $ordernumber .' '.$response['message'].PHP_EOL;
                    }
                }elseif(isset($response['response']['code'])){
                    $message .= $response['body'].PHP_EOL;
                }
            }
            $response3[$user_id] = array("message" => $message,"is_error" => $is_error);
        }
        return $response3;
    }
    // last sync time
    function get_last_sync_time(){
        $user = $this->get_user();
        $doctype = $this->get_doctype();
        if($doctype == 'orderreceipt'){
            $doctype = 'order';
        }
        return get_user_meta( $user->ID, strtolower($this->get_service_name()).'_last_sync_time_'.strtolower($doctype), true );
    }
    function set_last_sync_time(){
        $user = $this->get_user();
        update_user_meta( $user->ID, strtolower($this->get_service_name()).'_last_sync_time_'.strtolower($this->get_doctype()), date( "c" ));
    }
    // Priority
    function post_order_to_priority($order){
        return null;
    }
    function post_items_to_priority(){
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
    function post_order_and_receipt_to_priority($order){
        $this->post_order_to_priority( $order );
        $this->post_receipt_to_priority( $order);
    }
    function post_ainvoice_to_priority($invoice){
        return null;
    }
    function get_products_from_priority(){
        $additional_url = 'LOGPART';
        $response = $this->makeRequest( 'GET', $additional_url, null,$this->get_user());
        if(isset($response['code'])){
            $response_code = (int) $response['code'];
            if ( $response_code >= 200 & $response_code <= 201 ) {
                $products = json_decode( $response['body'],true)['value'];
                return  $products;
            }
            if ( $response_code >= 400 && $response_code < 500 ) {

            }elseif(500 == $response_code || 0 == $response_code){

            }
        }elseif(isset($response['response']['code'])){

        }
    }
    // service
    function update_products_to_service(){
      // each service has unique function
    }
    function set_inventory_level_to_location($location_id,$sku){
        return null;
    }
    function set_inventory_level_to_user(){
        return null;
    }
    function write_to_log($message){

        $service_name =$this->get_service_name_lower();
        $current_user= $this->get_user();
        $username = $current_user->user_login;
        $uploads  = wp_upload_dir( null, false );
        $logs_dir = $uploads['basedir'] . '/logs';

        if ( ! is_dir( $logs_dir ) ) {
            mkdir( $logs_dir, 0755, true );
        }
        $user_dir =  $logs_dir . '/' . $username ;

        if ( ! is_dir( $user_dir ) ) {
            mkdir( $user_dir, 0755, true );
        }
        $service_name_dir = $user_dir . '/' . $service_name;
        if ( ! is_dir( $service_name_dir ) ) {
            mkdir( $service_name_dir, 0755, true );
        }
        $d = date("dmY");

        $file = fopen($service_name_dir . '/' .$d.'.log',"a"); 
        echo fwrite($file, "\n" . date('Y-m-d h:i:s') . " :: " . $message); 
        fclose($file);
    }
    // function upload_image_to_priority_product($user, $img_url, $sku){
    //     return  null;
    // }


}
