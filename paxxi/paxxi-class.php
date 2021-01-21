<?php
class Paxxi extends \Priority_Hub {
    function get_service_name(){
        return 'Paxxi';
    }
    function get_order_from_priority(){
        $url_addition = '?$top=1&$filter=ORDNAME eq \'SO21000028\'&$select=ORDNAME,CURDATE&$expand=SHIPTO2_SUBFORM';
        $response = $this->makeRequest( 'GET', 'ORDERS'.$url_addition, [ ],$this->get_user());
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
        $input_array = $this->createPackageQuery();
        $privateKey = $keys->private_key; // your private key
        ksort($input_array, SORT_STRING);
        $jsonInput = json_encode($input_array, JSON_UNESCAPED_UNICODE);
        $hash = hash_hmac('sha256', $jsonInput, $privateKey);
        $createPackageUrl = 'https://paxxi.info/api/v3/customer/packages';
        $additional_headers = [
            "X-Authorization: $keys->public_key",
            "X-Authorization-Hash: $hash"
        ];
        $result = wp_remote_request($createPackageUrl, $input_array, $additional_headers, true);
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
        return wp_remote_post($url, $data, [], true);
    }
    function createPackageQuery()
    {
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
                ],
                'reference_number' => '123qasd123'
            ]
        ];
    }



}