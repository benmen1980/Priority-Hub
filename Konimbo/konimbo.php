<?php

// Konimbo options
	echo ('<br><br>');

	?>

    <form action="<?php echo esc_url( admin_url('admin.php?page=priority-hub&tab=konimbo')); ?>" method="post">
        <input type="hidden" name="action" value="sync_konimbo">
        <div><input type="checkbox" name="debug" value="debug"><span>Debug</span></div>
        <div><input type="checkbox" name="generalpart" value="generalpart"><span>Post general item</span></div>
        <div><input type="text" name="order" value=""><span>Post sungle Order</span></div>
        <br>
        <?php
        //<input type="submit" value="Click here to sync Konimbo to Priority"> 4567567

        wp_nonce_field( 'acme-settings-save', 'acme-custom-message' );
        submit_button('Get Orders');

        ?>
    </form>
<?php
if ( isset( $_POST['submit'] ) ) {
    if(isset($_POST['generalpart'])){
		Konimbo::instance()->generalpart = true;
    }
	if(true == $_POST['debug'] || '' != $_POST['order']){
		Konimbo::instance()->debug = true;
	}else{
		Konimbo::instance()->debug = false;
    }
	if(isset($_POST['order'])){
		Konimbo::instance()->order = $_POST['order'];
	}
	$user_orders = Konimbo::instance()->get_orders_all_users();
	foreach($user_orders as $user_id => $orders){
	    $user = get_user_by('ID',$user_id);
	    $responses[$user_id] = Konimbo::instance()->process_orders($orders,$user);
    }
	$messages =  Konimbo::instance()->processResponse($responses);
	if(empty($messages)){
	    wp_die('No data to process, you might dont have orders to post or error so check your email.');
    }
	foreach($messages as $user_id => $message){
	    $user = get_user_by('ID',$user_id);
		if (true == $message['is_error']) {
			$subject = 'Konimbo Error for user ' . get_user_meta( $user->ID, 'nickname', true );
		//	Konimbo::instance()->sendEmailError($subject, $message);
		}
    echo $message['message'];
    }
}



