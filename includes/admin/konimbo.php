<?php

// Konimbo options
	echo ('<br><br>');

	?>

    <form action="<?php echo esc_url( admin_url('admin.php?page=priority-hub&tab=konimbo')); ?>" method="post">
        <input type="hidden" name="action" value="sync_konimbo">
        <div><input type="checkbox" name="debug" value="debug"><span>Debug</span></div>
        <div><input type="checkbox" name="generalpart" value="generalpart"><span>Post general item</span></div>
        <div><input type="text" name="order" value=""><span>Debug Order</span></div>
        <br>
        <?php
        //<input type="submit" value="Click here to sync Konimbo to Priority"> 4567567

        wp_nonce_field( 'acme-settings-save', 'acme-custom-message' );
        submit_button('Get Orders');

        ?>
    </form>
<?php
if ( isset( $_POST['submit'] ) ) {
	$responses = Konimbo::instance()->process_all_users();
	$messages =  Konimbo::instance()->processResponse($responses);
	foreach($messages as $user_id => $message){
	    $user = get_user_by('ID',$user_id);
		if (true == $message['is_error']) {
			$subject = 'Konimbo Error for user ' . get_user_meta( $user->ID, 'nickname', true );
		//	Konimbo::instance()->sendEmailError($subject, $message);
        echo $message['message'];
		}

    }
}



