<?php

// Konimbo options
	echo ('<br><br>');

	?>

    <form action="<?php echo esc_url( admin_url('admin-post.php')); ?>" method="post">
        <input type="hidden" name="action" value="sync_konimbo">
        <div><input type="checkbox" name="debug" value="debug"><span>Debug</span></div>
        <div><input type="checkbox" name="generalpart" value="generalpart"><span>Post general item</span></div>
        <div><input type="text" name="order" value=""><span>Debug Order</span></div>
        <br>
        <?php
        //<input type="submit" value="Click here to sync Konimbo to Priority">
        wp_nonce_field( 'acme-settings-save', 'acme-custom-message' );
        submit_button('Get Orders');
        ?>
    </form>
<?php
	// show active users
	// show the last sync of each user
	// make a form to post one order
	if ( isset( $_GET['post_all'] ) ) {
		$this->process_all_users();
	}
if ( isset( $_POST['submit'] ) ) {
	echo 'Form submitted!!!';
}



