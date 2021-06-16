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
        $pri_city = $order->SHIPTO2_SUBFORM->STATE;
        $pri_street = $order->SHIPTO2_SUBFORM->ADDRESS;
        $pri_street_number = $order->SHIPTO2_SUBFORM->ADDRESS2;
        $pri_full_address = urlencode($pri_street.' '.$pri_city);
        $url = $base_url.$pri_full_address;
       $res = wp_remote_request($url);
       if($res['code']<=201){
           $addresses = json_decode($res['body'])->data;
           if(sizeof($addresses->full_search) > 0){
               $codes = $addresses->full_search[0];
               $street_code = $codes->street_code;
               $city_code = $codes->city_code;
               $order->paxxi_is_valid_address = true;
               $order->city_code = $city_code;
               $order->street_code = $street_code;
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
        $createPackageUrl = 'https://paxxi.info/api/v3/customer/packages';
        $additional_headers = [
            "X-Authorization: $keys->public_key",
           // "X-Authorization: 37b17a22c5f17d5d55ead992175f1fdd",
            "X-Authorization-Hash: $hash"
        ];
        $result = $this->sendCurl($createPackageUrl, $input_array, $additional_headers, true);
        // get json result with package and order details
        return $result;
    }
    function loginToTest()
    {
        $url = 'https://paxxi.info/api/v3/auth/login';
        $data = [
            'phone_prefix' => '050',
            'phone_number' => '0123129',
            'password'     => '123qwe',
            'is_api'       => '1'
        ];
        return $this->sendCurl($url, $data, [], true);
    }
    function createPackageQuery($order)
    {
        // receiver name is static per Priority server.
        return $orderData = [
            'is_api'   => '1',
            'packages' => [
                [
                    'insurance_id' => '6',
                    'overnight' => '1',
                    'receiver'    => [
                        'city_code'    => (string)$order->city_code,
                        'house'        => '2',
                        'name'         => 'name',
                        'phone_number' => substr($order->SHIPTO2_SUBFORM->PHONENUM,3,7),
                        'phone_prefix' => substr($order->SHIPTO2_SUBFORM->PHONENUM,0,3),
                        'street_code'  => (string)$order->street_code
                    ],
                    'sender'  => [
                        'city_code'    => '5000',
                        'house'        => '5',
                        'name'         => 'test',
                        'phone_number' => substr($order->SHIPTO2_SUBFORM->PHONENUM,3,7),
                        'phone_prefix' => substr($order->SHIPTO2_SUBFORM->PHONENUM,0,3),
                        'street_code'  => '903',
                    ],
                  //  'notes' => 'הערות כלליות',

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
}