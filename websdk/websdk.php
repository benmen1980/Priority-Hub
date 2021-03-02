<?php
// websdk options
echo ('<br><br>');

$form = new WebSDK('','');
$form->generate_hub_form();

if ( isset( $_POST['submit'] ) && isset($_POST['websdk_username'])&& isset($_POST['websdk_document'])) {
    $user = get_user_by('login', $_POST['websdk_username']);
    if (!$user) {
        echo 'Username does not exists<br>';
    }
    $websdk = new WebSDK($_POST['websdk_document'], $_POST['websdk_username']);
    if ($_POST['websdk_document'] == 'upload-image-to-priority-product') {
        $message['message'] = 'Message... <br>';

        if(!empty($updated_items)) $message['message'] = 'List of inventory level updates <br>';
        $is_error = null;
        if(!empty($updated_items)) {
            foreach ($updated_items as $item) {
                $message['message'] .= $item['sku'] . ' >> ' . $item['stock'] . '<br>';
            }
        }
    } else {
        //$messages = array();
        //$message = $shopify->post_user_by_username($_POST['shopify_username'], $_POST['shopify_order'], $_POST['shopify_document']);
    }
    if(isset($message['message'])) echo $message['message'];
}
?>
<hr>


