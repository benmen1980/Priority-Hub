<?php
// shopify options
echo ('<br><br>');

$form = new Shopify('','');
$form->generate_hub_form();

if ( isset( $_POST['submit'] ) && isset($_POST['shopify_username'])&& isset($_POST['shopify_document'])){
    $user = get_user_by('login',$_POST['shopify_username']);
    $activate_sync = get_user_meta( $user->ID, 'shopify_activate_sync',true );
    if(!$user){
        echo 'Username does not exists<br>';
    }
    if(!$activate_sync){
        echo 'User does not set to integrate with Shopify';
    }
    $shopify = new Shopify($_POST['shopify_document'],$_POST['shopify_username']);
    $message = $shopify->post_user_by_username($_POST['shopify_username'],$_POST['shopify_order'],$_POST['shopify_document']);
    echo $message['message'];
}
?>
<hr>
    <ol>
        <li>Sync orders from Shopify</li>
        <li>Sync inventory to Shopify</li>
        <li>Open items from Priority to Shopify</li>
    </ol>

