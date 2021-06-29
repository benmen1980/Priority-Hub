<?php
// Magento 2.0 options

$form = new Magento2('','');
$form->generate_hub_form();

if ( isset( $_POST['submit'] ) && isset($_POST['magento2_username'])&& isset($_POST['magento2_document'])) {
    $user = get_user_by('login', $_POST['magento2_username']);
    if (!$user) {
        echo 'Username does not exists<br>';
    }
    $magento2 = new Magento2($_POST['magento2_document'], $_POST['magento2_username']);
    //$location_id = $shopify->get_user_api_config('LOCATION_ID');
    //$shopify->location_id = $shopify->get_user_api_config('LOCATION_ID');
    $sku = $_POST['magento2_order'];

    if (!empty($_POST['magento2_order'])) {
        $magento2->debug = true;
    }
    if ($_POST['magento2_document'] == 'sync_inventory_to_Shopify') {
        $res =  $magento2->set_inventory_level2($sku);
        echo $res;
        return;
        $message['message'] = 'There are no inventory levels to sync <br>';
        $updated_items = $magento2->set_inventory_level_to_location($location_id,$sku);
        if(!empty($updated_items)) $message['message'] = 'List of inventory level updates <br>';
        $is_error = null;
        if(!empty($updated_items)) {
            foreach ($updated_items as $item) {
                $message['message'] .= $item['sku'] . ' >> ' . $item['stock'] . '<br>';
            }
        }
    } else {
    //$messages = array();
        $token_arr = $magento2->get_token();
       // $magento2->set_token('qf9rkqbjeo0zqp84zs0stpoof6fqixdj');
    $message = $magento2->post_user_by_username($_POST['magento2_username'], $_POST['magento2_order'], $_POST['magento2_document']);
    }
    if(isset($message['message'])) echo $message['message'];
}
?>
<hr>
    <ol>
        <li>Sync orders from Shopify</li>
        <li>Sync inventory to Shopify</li>
        <li>Open items from Priority to Shopify</li>
    </ol>

