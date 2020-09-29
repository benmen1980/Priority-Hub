<?php
// istore options
echo ('<br><br>');

?>

    <form action="<?php echo esc_url( admin_url('admin.php?page=priority-hub&tab=istore')); ?>" method="post">
        <input type="hidden" name="istore_action" value="sync_istore">
        <div><input type="checkbox" name="istore_debug" value="debug"><span>Debug</span></div>
        <div><input type="checkbox" name="istore_generalpart" value="generalpart"><span>Post general item</span></div>
        <div>
            <select value="" name="istore_document" id="istore_document">
                <option selected="selected"></option>
                <option value="order">Order</option>
                <option value="otc">Over The counter invoice</option>
                <option value="invoice">Sales Invoice</option>
                <option value="orderreceipt">Order + Receipt</option>
            </select>
            <label>Select Priority Entity target</label></div>
        <input type="text" name="istore_order" value=""><span>Post single Order, if you keep it empty, the system will post all orders from last sync date as defined in the user page</span></div>

        <br>
        <?php
        //<input type="submit" value="Click here to sync Konimbo to Priority"> 4567567

        wp_nonce_field( 'acme-settings-save', 'acme-custom-message' );
        submit_button('Get Orders');

        ?>
    </form>
<?php
if ( isset( $_POST['submit'] ) ) {
    istore::instance()->document = $_POST['istore_document'];
    if(isset($_POST['istore_generalpart'])){
        istore::instance()->generalpart = true;
    }
    if(true == $_POST['istore_debug'] || '' != $_POST['istore_order']){
        istore::instance()->debug = true;
    }else{
        istore::instance()->debug = false;
    }
    if(isset($_POST['istore_order'])){
        istore::instance()->order = $_POST['istore_order'];
    }
    $user_orders = istore::instance()->get_orders_all_users();
    foreach($user_orders as $user_id => $orders){
        $user = get_user_by('ID',$user_id);
        $responses[$user_id] = istore::instance()->process_orders($orders,$user);
    }
    $messages =  istore::instance()->processResponse($responses);
    if(empty($messages)){
        wp_die('No data to process, you might dont have orders to post or error so check your email.');
    }
    foreach($messages as $user_id => $message){
        $user = get_user_by('ID',$user_id);
        if (true == $message['is_error']) {
            $subject = 'istore Error for user ' . get_user_meta( $user->ID, 'nickname', true );
            //	istore::instance()->sendEmailError($subject, $message);
        }
        echo $message['message'];
    }
}
?>
<hr>
    <ol>
        <li>Sync orders from iStore</li>
        <li>Sync inventory to iStore</li>
        <li>Open items from Priority to iStore</li>
    </ol>

