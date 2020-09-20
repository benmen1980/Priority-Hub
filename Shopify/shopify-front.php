<?php
include_once (PHUB_DIR.'shopify/shopify-class.php');
// Shopify options
echo ('<br><br>');
?>
    <div>
    <div class="wrap woocommerce">
        <form action="" method="post">
            <input type="hidden" name="action" value="sync_shopify">
            <table class="form-table">
                <tbody>
                <tr valign="top">
                    <th scope="row" class="titledesc">
                        <label for="order">Shopify Order <span class="woocommerce-help-tip"></span></label>
                    </th>
                    <td class="forminp forminp-text">
                        <input name="order" id="shopify-order" type="text" style="" value="" class="" placeholder="">
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row" class="titledesc">
                        <label for="last-sync-date">Last Sync Date<span class="woocommerce-help-tip"></span></label>
                    </th>
                    <td class="forminp forminp-text">
                        <p><?php $user = wp_get_current_user();
                            $user_meta = get_user_meta( $user->ID);
                            echo get_user_meta( $user->ID, 'shopify_last_sync_date', true );?></p>
                    </td>
                </tr>
                <tr>
                    <td>Sync to Priority as</td>
                    <td>
                        <select name="document" id="document">
                            <option value="order">Sales Order</option>
                            <option value="otc">Over The Counter Invoice</option>
                            <option value="invoice">Sales Invoice</option>
                            <option value="orderreceipt">Sales Order + Receipt</option>
                        </select>
                    </td>
                </tr>
                </tbody>
            </table>
            <input name="submit" type="submit" value="<?php _e('Post order','p18a');?>" />
        </form>
    </div>
<?php
wp_nonce_field( 'acme-settings-save', 'acme-custom-message' );


if ( isset( $_POST['submit'] ) & !empty($_POST['order'])){
    // fetch data
    $user = wp_get_current_user();
    Shopify::instance()->document = $_POST['shopify_document'];
    Shopify::instance()->order = $_POST['order'];
    Shopify::instance()->debug = true;
    Shopify::instance()->generalpart = '';
    // procees
    $orders = Shopify::instance()->get_orders_by_user( $user );
    $responses[$user->ID] = Shopify::instance()->process_orders($orders,$user);
    $messages =  Shopify::instance()->processResponse($responses);
    $message = $messages[$user->ID];
    $emails  = [ $user->user_email ];
    $subject = 'Priority Shopify API error ';
    if (true == $message['is_error']) {
        Shopify::instance()->sendEmailError($subject, $message['message']);
    }
    echo $message['message'].'<br>';
}


