<?php

// Konimbo options
	echo ('<br><br>');

	?>

    <form action="<?php echo esc_url( admin_url('admin-post.php')); ?>" method="post">
        <input type="hidden" name="action" value="sync_konimbo">
        <div><input type="checkbox" name="debug" value="debug"><span>Debug</span></div>
        <br>
        <input type="submit" value="Click here to sync Konimbo to Priority">
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



