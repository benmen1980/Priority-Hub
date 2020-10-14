<?php
// shopify options
echo ('<br><br>');

?>

    <form action="<?php echo esc_url( admin_url('admin.php?page=priority-hub&tab=shopify')); ?>" method="post">
        <input type="hidden" name="shopify_action" value="sync_shopify">
        <div><input type="checkbox" name="shopify_generalpart" value="generalpart"><span>Post general item</span></div>
        <div><input type="text" name="shopify_username"><span>User Name</span></div>
        <div>
            <select value="" name="shopify_document" id="shopify_document">
                <option selected="selected"></option>
                <option value="order">Order</option>
                <option value="otc">Over The counter invoice</option>
                <option value="invoice">Sales Invoice</option>
                <option value="orderreceipt">Order + Receipt</option>
            </select>
            <label>Select Priority Entity target</label></div>
        <input type="text" name="shopify_order" value=""><span>Post single Order, if you keep it empty, the system will post all orders from last sync date as defined in the user page</span></div>

        <br>
        <?php
        //<input type="submit" value="Click here to sync konimbo to Priority"> 4567567

        wp_nonce_field( 'acme-settings-save', 'acme-custom-message' );
        submit_button('Get Orders');

        ?>
    </form>
<?php
if ( isset( $_POST['submit'] ) && isset($_POST['shopify_username'])&& isset($_POST['shopify_document'])){
    $user = get_user_by('login',$_POST['shopify_username']);
    $activate_sync = get_user_meta( $user->ID, 'shopify_activate_sync',true );
    if(!$user){
        echo 'Username does not exists';
    }
    if(!$activate_sync){
        echo 'User does not set to integrate with Shopify';
    }
    $shopify = new Shopify;
    $message = $shopify->post_user_by_id($user->ID,$_POST['shopify_order'],$_POST['shopify_document']);
    echo $message['message'];
}
?>
<hr>
    <ol>
        <li>Sync orders from Shopify</li>
        <li>Sync inventory to Shopify</li>
        <li>Open items from Priority to Shopify</li>
    </ol>

