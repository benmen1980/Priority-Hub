<?php
// Shopify options
echo ('<br><br>');

?>

    <form action="<?php echo esc_url( admin_url('admin.php?page=priority-hub&tab=shopify')); ?>" method="post">
        <input type="hidden" name="shopify_action" value="sync_shopify">
        <div><input type="checkbox" name="shopify_debug" value="debug"><span>Debug</span></div>
        <div><input type="checkbox" name="shopify_generalpart" value="generalpart"><span>Post general item</span></div>
        <div><input type="text" name="shopify_order" value=""><span>Post single Order</span></div>
        <br>
        <?php
        //<input type="submit" value="Click here to sync Konimbo to Priority"> 4567567

        wp_nonce_field( 'acme-settings-save', 'acme-custom-message' );
        submit_button('Get Orders');

        ?>
    </form>
<?php
if ( isset( $_POST['submit'] ) ) {
    if(isset($_POST['shopify_generalpart'])){
        Shopify::instance()->generalpart = true;
    }
    if(true == $_POST['shopify_debug'] || '' != $_POST['shopify_order']){
        Shopify::instance()->debug = true;
    }else{
        Shopify::instance()->debug = false;
    }
    if(isset($_POST['shopify_order'])){
        Shopify::instance()->order = $_POST['shopify_order'];
    }
    $user_orders = Shopify::instance()->get_orders_all_users();
    foreach($user_orders as $user_id => $orders){
        $user = get_user_by('ID',$user_id);
        $responses[$user_id] = Shopify::instance()->process_orders($orders,$user);
    }
    $messages =  Shopify::instance()->processResponse($responses);
    if(empty($messages)){
        wp_die('No data to process, you might dont have orders to post or error so check your email.');
    }
    foreach($messages as $user_id => $message){
        $user = get_user_by('ID',$user_id);
        if (true == $message['is_error']) {
            $subject = 'Shopify Error for user ' . get_user_meta( $user->ID, 'nickname', true );
            //	Shopify::instance()->sendEmailError($subject, $message);
        }
        echo $message['message'];
    }
}
?>
<hr>
    <ol>
        <li>Sync orders from Shopify</li>
        <li>Sync inventory to Shopify</li>
        <li>Open items from Priority to Shopify</li>
    </ol>

