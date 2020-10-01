<?php

// konimbo options
echo ('<br><br>');

?>
<div>
    <div class="wrap woocommerce">
    <form action="" method="post">
        <input type="hidden" name="action" value="sync_konimbo">
        <table class="form-table">
            <tbody>
            <tr valign="top">
                <th scope="row" class="titledesc">
                    <label for="order">Konimbo Order <span class="woocommerce-help-tip"></span></label>
                </th>
                <td class="forminp forminp-text">
                    <input name="order" id="konimbo-order" type="text" style="" value="" class="" placeholder="">
                </td>
            </tr>
            <tr valign="top">
                <th scope="row" class="titledesc">
                    <label for="last-sync-date">Last Sync Date<span class="woocommerce-help-tip"></span></label>
                </th>
                <td class="forminp forminp-text">
                    <p><?php $user = wp_get_current_user();
                    $user_meta = get_user_meta( $user->ID);
                    echo get_user_meta( $user->ID, 'konimbo_last_sync_time', true );?></p>
                </td>
            </tr>
            </tbody>
        </table>
        <input name="submit_order" type="submit" value="<?php _e('Post order','p18a');?>" />
    </form>
</div>
   <hr>
    <div>
    <div class="wrap woocommerce">
        <form action="" method="post">
            <input type="hidden" name="action" value="sync_konimbo">
            <table class="form-table">
                <tbody>
                <tr valign="top">
                    <th scope="row" class="titledesc">
                        <label for="receipt">Konimbo Receipt <span class="woocommerce-help-tip"></span></label>
                    </th>
                    <td class="forminp forminp-text">
                        <input name="receipt" id="konimbo-receipt" type="text" style="" value="" class="" placeholder="">
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row" class="titledesc">
                        <label for="last-sync-date">Last Sync Date<span class="woocommerce-help-tip"></span></label>
                    </th>
                    <td class="forminp forminp-text">
                        <p><?php $user = wp_get_current_user();
                            $user_meta = get_user_meta( $user->ID);
                            echo get_user_meta( $user->ID, 'konimbo_last_sync_time_receipt', true );?></p>
                    </td>
                </tr>
                </tbody>
            </table>
            <input name="submit_receipt" type="submit" value="<?php _e('Post receipt','p18a');?>" />
        </form>
    </div>

<?php
		wp_nonce_field( 'acme-settings-save', 'acme-custom-message' );

$user = wp_get_current_user();
Konimbo::instance()->debug = true;
Konimbo::instance()->generalpart = '';
// Orders
if ( isset( $_POST['submit_order'] ) & !empty($_POST['order'])){
	Konimbo::instance()->order = $_POST['order'];
	$orders = Konimbo::instance()->get_orders_by_user( $user );
	$responses[$user->ID] = Konimbo::instance()->process_orders($orders,$user);
	$messages =  Konimbo::instance()->processResponse($responses);
	$message = $messages[$user->ID];
	$emails  = [ $user->user_email ];
	$subject = 'Priority konimbo Orders API error ';
	if (true == $message['is_error']) {
		Konimbo::instance()->sendEmailError($subject, $message['message']);
	}
	echo $message['message'].'<br>';
}
// Receipt
if ( isset( $_POST['submit_receipt'] ) & !empty($_POST['receipt'])){
    Konimbo::instance()->receipt = $_POST['receipt'];
    $receipts = Konimbo::instance()->get_receipts_by_user( $user );
    $responses[$user->ID] = Konimbo::instance()->process_receipts($receipts,$user);
    $messages =  Konimbo::instance()->processResponse($responses);
    $message = $messages[$user->ID];
    $emails  = [ $user->user_email ];
    $subject = 'Priority konimbo Receipts API error ';
    if (true == $message['is_error']) {
        Konimbo::instance()->sendEmailError($subject, $message['message']);
    }
    echo $message['message'].'<br>';
}

