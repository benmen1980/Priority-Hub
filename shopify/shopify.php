<?php
// shopify options

$form = new Shopify('','');
$form->generate_hub_form();

if ( isset( $_POST['submit'] ) && isset($_POST['shopify_username'])&& isset($_POST['shopify_document'])) {
    $user = get_user_by('login', $_POST['shopify_username']);
    $activate_sync = get_user_meta($user->ID, 'shopify_activate_sync', true);
    if (!$user) {
        echo 'Username does not exists<br>';
    }
    if (!$activate_sync) {
        echo 'User does not set to integrate with Shopify';
    }
    $shopify = new Shopify($_POST['shopify_document'], $_POST['shopify_username']);
    if (!empty($_POST['shopify_order'])) {
        $shopify->debug = true;
    }
    if ($_POST['shopify_document'] == 'sync_inventory_to_Shopify') {
        //$location_id = 35456548943;
        $location_id = $shopify->get_user_api_config('LOCATION_ID');
        $sku = $_POST['shopify_order'];
        $message['message'] = 'There are no inventory levels to sync <br>';
        $updated_items = $shopify->set_inventory_level_to_location($location_id,$sku);
        if(!empty($updated_items)) $message['message'] = 'List of inventory level updates <br>';
        $is_error = null;
        if(!empty($updated_items)) {
            foreach ($updated_items as $item) {
                $message['message'] .= $item['sku'] . ' >> ' . $item['stock'] . '<br>';
            }
        }
    } else {
    //$messages = array();
    $message = $shopify->post_user_by_username($_POST['shopify_username'], $_POST['shopify_order'], $_POST['shopify_document']);
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

