<?php
class Paxxi extends \Priority_Hub {
    private $service;
    private $doctype;
    private $user;
    //
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
        $foo = $this->user->ID;
        return json_decode(get_user_meta($this->user->ID,'description',true))->$key ?? null;
    }
    function get_service_name(){
        return 'Paxxi';
    }
    function get_order_from_priority(){
        $url_addition = '?$top=1&$filter=ORDNAME eq \'SO21000028\'&$select=ORDNAME,CURDATE&$expand=SHIPTO2_SUBFORM';
        $response = $this->makeRequest( 'GET', 'ORDERS'.$url_addition, [ ],$this->user);
        if($response['code']<=201){
            $data = json_decode($response['body']);
            foreach($data->value as $order){
                $order = $this->check_address($order);
                if($order->is_valid_address){
                    $res = $this->get_tracking_number($order);
                }
            }
        }
    }
    function check_address($order){
        $order->is_valid_address = false;
        $base_url = 'https://paxxi.net/autocomplete/full_search?q=';
        $pri_city = $order->SHIPTO2_SUBFORM->STATE;
        $pri_street = $order->SHIPTO2_SUBFORM->ADDRESS;
        $pri_street_number = $order->SHIPTO2_SUBFORM->ADDRESS2;
        $pri_full_address = urlencode($pri_street.' '.$pri_city);
        $url = $base_url.$pri_full_address;
       $res = wp_remote_request($url);
       if($res['code']<=201){
           $addresses = json_decode($res['body'])->data;
           if(sizeof($addresses->full_search) == 1){
               $codes = $addresses->full_search[0];
               $street_code = $codes->street_code;
               $city_code = $codes->city_code;
           }
       }
       $order->is_valid_address = true;
       $order->city_code = $city_code;
       $order->street_code = $street_code;
       return $order;
    }
    function get_tracking_number($order){
        // get keys
        $keys = json_decode($this->loginToTest());

        // get prices
        $input_array = $this->createPackageQuery($order);
        $privateKey = $keys->private_key; // your private key
        ksort($input_array, SORT_STRING);
        $jsonInput = json_encode($input_array, JSON_UNESCAPED_UNICODE);
        $hash = hash_hmac('sha256', $jsonInput, $privateKey);
        $createPackageUrl = 'https://paxxi.info/api/v3/customer/packages';
        $additional_headers = [
            "X-Authorization: $keys->public_key",
            "X-Authorization-Hash: $hash"
        ];
        $result = $this->sendCurl($createPackageUrl, $input_array, $additional_headers, true);
        // get json result with package and order details
        die($result);
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
                        'name'         => $order->SHIPTO2_SUBFORM->CUSTDES,
                        'phone_prefix' => substr($order->SHIPTO2_SUBFORM->PHONENUM,0,3),
                        'phone_number' => substr($order->SHIPTO2_SUBFORM->PHONENUM,3,7),
                        'city_code'    => $order->city_code,
                        'street_code'  => $order->street_code,
                        'house'        => $order->SHIPTO2_SUBFORM->ADDRESS2,
                        'notes'        => $order->SHIPTO2_SUBFORM->ADDRESS3
                    ],
                    'notes' => 'הערות כלליות',
                    'insurance_id' => '6',
                    'type_id' => '1',
                    'urgency_id' => '3'
                ],
                'reference_number' => $order->ORDNAME
            ]
        ];
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