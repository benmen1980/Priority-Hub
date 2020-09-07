<?php

// Konimbo options
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
        <input name="submit" type="submit" value="<?php _e('Post order','p18a');?>" />
    </form>
    </div>
		<?php
		wp_nonce_field( 'acme-settings-save', 'acme-custom-message' );


if ( isset( $_POST['submit'] ) & !empty($_POST['order'])){
    // fetch data
	$user = wp_get_current_user();
	Konimbo::instance()->order = $_POST['order'];
	Konimbo::instance()->debug = true;
	Konimbo::instance()->generalpart = '';
	// procees
	$orders = Konimbo::instance()->get_orders_by_user( $user );
	$responses[$user->ID] = Konimbo::instance()->process_orders($orders,$user);
	$messages =  Konimbo::instance()->processResponse($responses);
	$message = $messages[$user->ID];
	$emails  = [ $user->user_email ];
	$subject = 'Priority Konimbo API error ';
	if (true == $message['is_error']) {
		Konimbo::instance()->sendEmailError($subject, $message['message']);
	}
	echo $message['message'].'<br>';
}

