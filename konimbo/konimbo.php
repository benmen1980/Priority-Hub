<?php

// konimbo options
	echo ('<br><br>');

	?>

    <form action="<?php echo esc_url( admin_url('admin.php?page=priority-hub&tab=konimbo')); ?>" method="post">
        <input type="hidden" name="action" value="sync_konimbo">
        <div><input type="checkbox" name="debug" value="debug"><span>Debug</span></div>
        <div><input type="checkbox" name="generalpart" value="generalpart"><span>Post general item</span></div>
        <div><input type="text" name="konimbo_username"><span>User Name</span></div>
        <div>
            <select value="" name="konimbo_document" id="konimbo_document">
                <option selected="selected"></option>
                <option value="order">Order</option>
                <option value="receipt">Receipts</option>
                <option value="invoice">Sales Invoice</option>
                <option value="orderreceipt">Order + Receipt</option>
            </select>
            <label>Select Priority Entity target</label></div>
        <input type="text" name="konimbo_order" value=""><span>Post single Order</span></div>
        <br>
        <?php
        //<input type="submit" value="Click here to sync konimbo to Priority"> 4567567

        wp_nonce_field( 'acme-settings-save', 'acme-custom-message' );
        submit_button('Get Orders');

        ?>
    </form>
<?php
if ( isset( $_POST['submit'] ) && isset($_POST['konimbo_username'])&& isset($_POST['konimbo_document'])){
    $user = get_user_by('login',$_POST['konimbo_username']);
    $activate_sync = get_user_meta( $user->ID, 'konimbo_activate_sync',true );
    if(!$user){
        echo 'Username does not exists';
    }
    if(!$activate_sync){
        echo 'User does not set to integrate with Konimbo';
    }
    $konimbo = new Konimbo();
    $message = $konimbo->post_user_by_username($_POST['konimbo_username'],$_POST['konimbo_order'],$_POST['konimbo_document']);
    echo $message['message'];
}



