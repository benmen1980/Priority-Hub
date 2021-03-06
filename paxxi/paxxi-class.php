<?php
class Paxxi extends \Priority_Hub {
    private $service;
    private $doctype;
    private $user;
    private $phone_prefix;
    private $phone_number;
    private $password;
    public function __construct($doctype,$username)
    {
        $this->service = $this->get_service_name();
        $this->user =  get_user_by('login',$username);
        $this->doctype = $doctype;
       // add sender configuration here
        $this->phone_prefix = $this->get_user_api_config('paxxi_prefix');   // your login mobile phone prefix: 05x (Israel mobile phone only)
        $this->phone_number = $this->get_user_api_config('paxxi_phone');   // your login mobile phone number (without prefix): xxxxxxx
        $this->password     = $this->get_user_api_config('paxxi_password');

    }
    function get_user_api_config($key){
       // $foo = $this->user->ID;
        if(is_object($this->user)){
            return json_decode(get_user_meta($this->user->ID,'description',true))->$key ?? null;
        }
        return null;
    }
    function get_service_name(){
        return 'Paxxi';
    }
    function get_order_from_priority(){
        $stamp = mktime(0 , 0, 0);
        $bod = date(DATE_ATOM,$stamp);
        $url_addition = '?$filter=CURDATE ge '.urlencode($bod).'&$select=ORDNAME,CURDATE&$expand=SHIPTO2_SUBFORM';
        $response = $this->makeRequest( 'GET', 'ORDERS'.$url_addition, [ ],$this->user);
        $paxxi_orders = [];
        if($response['code']<=201){
            $data = json_decode($response['body']);
            foreach($data->value as $order){
                $order = $this->check_address($order);
                $order->paxxi =  new \stdClass();
                if($order->paxxi_is_valid_address){
                    $res =json_decode($this->get_tracking_number($order));
                    if(!empty($res->packages)){
                       $order->paxxi->id = $res->packages[0]->id;
                       $order->paxxi->order_id = $res->packages[0]->order_id;
                       $order->paxxi->tracking_id = $res->packages[0]->tracking_id;
                       $order->paxxi->status_name = $res->packages[0]->status->name;
                       $order->paxxi->display_name = $res->packages[0]->status->display_name;
                       $order->paxxi->description = $display_name = $res->packages[0]->status->description;
                       $paxxi_orders[] = $order;
                    }elseif(isset($res->errors)){
                        // do something when there's an error
                        $this->sendEmailError('Paxxi error',$res->message);
                        $order->paxxi->error = $res->message;
                        $paxxi_orders[] = $order;
                    }
                }else{
                    $order->paxxi->error = 'Address not found';
                    $paxxi_orders[] = $order;
                }
            }
        }else{
            $paxxi_orders = [$response];
        }
        return $paxxi_orders;
    }
    function update_priority_order($order){
        $url_addition = 'ORDERS(\''.$order->ORDNAME.'\')';
        $data = [
                'DETAILS' => $order->paxxi->tracking_id ?? $order->paxxi->error
            ];
        $res = $this->makeRequest('PATCH',$url_addition,[ 'body' => json_encode( $data ) ],$this->user);
        if($res['code']<=201){
            // Success
        }else{
            // error updating Priority ...
        }
        $url_addition = 'ORDERS(\''.$order->ORDNAME.'\')/GENCUSTNOTES_SUBFORM';
        $stamp = mktime(0 , 0, 0);
        $bod = date(DATE_ATOM,$stamp);
        $message = 'Sorry but no message found...';
        if($order->paxxi_is_valid_address){
            $message = $order->paxxi->display_name.', '.$order->paxxi->description;
        }else{
            $message = 'The address is not valid';
        }
        if($order->paxxi->error){
            $message = $order->paxxi->error;
        }

        $data = [
            'CURDATE' => $bod,
            'SUBJECT' => 'paxxi : '. $message
            ];
        $res = $this->makeRequest('POST',$url_addition,[ 'body' => json_encode( $data ) ],$this->user);
        if($res['code']<=201){
            // Success
        }else{
            // error updating Priority ...
        }
    }
    function check_address($order){
       $order->paxxi_is_valid_address = false;
       $base_url = 'https://paxxi.net/autocomplete/full_search?q=';
       $full_priority_address = $order->SHIPTO2_SUBFORM->STATE.' '.$order->SHIPTO2_SUBFORM->ADDRESS.' '.$order->SHIPTO2_SUBFORM->ADDRESS2.' '.
                                 $order->SHIPTO2_SUBFORM->ADDRESS3;
       // full Priority address must have a filter by Priority implementation
	   $google_address = $this->get_address_from_google_maps($full_priority_address);
       $order->street_number = $google_address['street_number'];
	   $pri_full_address = $google_address['street'].' '.$google_address['city'];
       $url = $base_url.$pri_full_address;
       $res = wp_remote_request($url);
       if($res['code']<=201){
           $addresses = json_decode($res['body'])->data;
           if(sizeof($addresses->full_search) > 0){
               $codes = $addresses->full_search[0];
               $order->paxxi_is_valid_address = true;
               $order->city_code = $codes->city_code;
               $order->street_code = $codes->street_code;
           }
       }

       return $order;
    }
    function get_tracking_number($order){
        // get keys
        $keys = json_decode($this->loginToTest());

        // get prices
        $input_array = $this->createPackageQuery($order);
        //$input_array = json_decode('{"is_api":"1","packages":[{"insurance_id":"6","overnight":"1","receiver":{"city_code":"5000","house":"5","name":"elena","phone_number":"2580645","phone_prefix":"052","street_code":"2173"},"sender":{"city_code":"8300","house":"36","name":"אגן","phone_number":"6508854","phone_prefix":"050","street_code":"903"},"type_id":"3","urgency_id":"3"}]}',true);
        $privateKey = $keys->private_key; // your private key
     //   $privateKey = 'f9e99e2c846422698e30e3e5c518cb4d';
        ksort($input_array, SORT_STRING);
        $jsonInput =json_encode($input_array, JSON_UNESCAPED_UNICODE);
        $hash = hash_hmac('sha256', $jsonInput, $privateKey);
        $createPackageUrl = 'https://paxxi.net/api/v3/customer/packages';
        $additional_headers = [
            "X-Authorization: $keys->public_key",
            "X-Authorization-Hash: $hash"
        ];
        $result = $this->sendCurl($createPackageUrl, $input_array, $additional_headers, true);
        // get json result with package and order details
        return $result;
    }
    function loginToTest()
    {
        //$url = 'https://paxxi.info/api/v3/auth/login';
        $url = 'https://paxxi.net/api/v3/auth/login';
        $phone_prefix = $this->get_user_api_config('paxxi_prefix');
        $phone_number = $this->get_user_api_config('paxxi_phone');
        $password = $this->get_user_api_config('paxxi_password');
        $data = [
            'phone_prefix' => $phone_prefix,
            'phone_number' => $phone_number,
            'password'     => $password,
            'is_api'       => '1'
        ];
        return $this->sendCurl($url, $data, [], true);
    }
    function createPackageQuery($order)
    {
        // receiver name is static per Priority server.

	    $insurance_id = '6'; // what are the options ?
	    $overnight = '1';    // what are the options ?
        return $orderData = [
            'is_api'   => '1',
            'packages' => [
                [
                    'insurance_id' => $insurance_id,
                    'overnight' => $overnight,
                    'receiver'    => [
                        'city_code'    => (string)$order->city_code,
                        'house'        => $order->street_number,
                        'name'         => $order->SHIPTO2_SUBFORM->CUSTDES,
                        'phone_number' => substr($order->SHIPTO2_SUBFORM->PHONENUM,3,7),
                        'phone_prefix' => substr($order->SHIPTO2_SUBFORM->PHONENUM,0,3),
                        'street_code'  => (string)$order->street_code,
                        'notes' => $order->SHIPTO2_SUBFORM->CUSTDES.PHP_EOL.
                                   $order->SHIPTO2_SUBFORM->STATE.PHP_EOL.
                                   $order->SHIPTO2_SUBFORM->ADDRESS.PHP_EOL.
                                   $order->SHIPTO2_SUBFORM->ADDRESS2.PHP_EOL.
                                   $order->SHIPTO2_SUBFORM->ADDRESS3.PHP_EOL.
                                   $order->SHIPTO2_SUBFORM->PHONENUM.PHP_EOL
                    ],
                    'sender'  => [
                        'city_code'    => '5000',
                        'house'        => '5',
                        'name'         => 'test',
                        'phone_number' => substr($order->SHIPTO2_SUBFORM->PHONENUM,3,7),
                        'phone_prefix' => substr($order->SHIPTO2_SUBFORM->PHONENUM,0,3),
                        'street_code'  => '903',
                    ],


                    'type_id' => '1',
                    'urgency_id' => '3',
                    'reference_number' => $order->ORDNAME
                ]
                //'reference_number' => $order->ORDNAME
            ]
        ];
         /*
        return $orderData = [
            'is_api'   => '1',
            'packages' => [
                [
                    'overnight' => '1',
                    'sender'    => [
                        'name'         => 'שם השולח',
                        'phone_prefix' => '050',
                        'phone_number' => '0000001',
                        'city_code'    => '5000',
                        'street_code'  => '1514',
                        'house'        => '2',
                        'notes'        => 'הערות שולח'
                    ],
                    'receiver'  => [
                        'name'         => 'שם המקבל',
                        'phone_prefix' => '050',
                        'phone_number' => '0000002',
                        'city_code'    => '5000',
                        'street_code'  => '1514',
                        'house'        => '2',
                        'notes'        => 'הערות מקבל'
                    ],
                    'notes' => 'הערות כלליות',
                    'insurance_id' => '6',
                    'type_id' => '1',
                    'urgency_id' => '3'
                ]           ]
        ];
         */

    }
    function sendCurl($url, $data, $additional_headers = [], $httpQuery = false)
    {

        if ($httpQuery) {
            $data = http_build_query($data);
        }

        $headers = ['X-Requested-With: XMLHttpRequest'];
        $headers = array_merge($headers, $additional_headers);

        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
        $result = curl_exec($curl);
        curl_close($curl);

        return $result;
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
                <option value="otc"><?php _e('Over The counter invoice','p18a');?></option>
                <option value="invoice"><?php _e('Sales Invoice','p18a');?></option>
                <option value="shipment"><?php _e('Shipment','p18a');?></option>
            </select>
            <h6>
                Post single Order, if you keep it empty, the system will post all orders from last sync date as defined in the user page

            </h6>
            <label for="<?php echo $this->get_service_name_lower(); ?>_order">
                <?php _e('Order :','p18a');?>
            </label>
            <input type="text" id="<?php echo $this->get_service_name_lower(); ?>_order" name="<?php echo $this->get_service_name_lower(); ?>_order" value="" >
            <input name="submit" type="submit"  id="submit" class="button button-primary" value="<?php _e('Execute API','p18a');?>" />
        </form>

        <?php
    }
	function get_address_from_google_maps($full_address) {
		$key = $this->get_user_api_config('google_api_key');
		$curl = curl_init();
		curl_setopt_array( $curl, array(
			CURLOPT_URL            => 'https://maps.googleapis.com/maps/api/geocode/json?language=iw&address='.urlencode($full_address).'&key='.$key,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_ENCODING       => '',
			CURLOPT_MAXREDIRS      => 10,
			CURLOPT_TIMEOUT        => 0,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
			CURLOPT_CUSTOMREQUEST  => 'GET',
		) );

		$response = curl_exec( $curl );

		curl_close( $curl );
		$data = json_decode( $response );
		$components = $data->results[0]->address_components;
		foreach($components as $types){
			if($types->types[0]=='route'){
				$words = ['משעול','סימטת','שדרות'];
				$str_arry = explode(' ',$types->long_name);
				$street = in_array($str_arry[0],$words)  ? $str_arry[1].' '.$str_arry[2] : $types->long_name;
			}
			if($types->types[0]=='locality'){
				$city = $types->long_name;
			}
			if($types->types[0]=='street_number'){
				$street_number = $types->long_name;
			}

		};
		return [
			'status'        => (is_null($street) || is_null($city) || is_null($street_number) ? 'DATA_MISSING'  : $data->status),
			'city'          => $city,
			'street'        => $street,
			'street_number' => $street_number,
			'response'      => $data
		];
	}
}