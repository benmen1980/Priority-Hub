<?php

//istore options
echo ('<br><br>');

$form = new Istore('','');
$form->generate_hub_form();

if ( isset( $_POST['submit'] ) && isset($_POST['istore_username'])&& isset($_POST['istore_document'])) {
    $user = get_user_by('login', $_POST['istore_username']);
    $activate_sync = get_user_meta($user->ID, 'istore_active', true);
    if (!$user) {
        echo 'Username does not exists<br>';
    }
    if (!$activate_sync) {
        echo 'User does not set to integrate with Istore';
    }
    $istore = new Istore($_POST['istore_document'], $_POST['istore_username']);
    if (!empty($_POST['istore_order'])) {
        $istore->debug = true;
    }

    $message = $istore->post_user_by_username($_POST['istore_username'], $_POST['istore_order'], $_POST['istore_document']);
    if(isset($message['message'])) echo $message['message'];
}
?>