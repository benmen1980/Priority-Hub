<?php

// konimbo options
	echo ('<br><br>');
$form = new Konimbo('','');
$form->generate_hub_form();
if ( isset( $_POST['submit'] ) && isset($_POST['konimbo_username'])&& isset($_POST['konimbo_document'])){
    $user = get_user_by('login',$_POST['konimbo_username']);
    $activate_sync = get_user_meta( $user->ID, 'konimbo_activate_sync',true );
    if(!$user){
        echo 'Username does not exists';
    }
    if(!$activate_sync){
        echo 'User does not set to integrate with Konimbo';
    }
    $konimbo = new Konimbo($_POST['konimbo_document'],$_POST['konimbo_username']);
    $message = $konimbo->post_user_by_username($_POST['konimbo_username'],$_POST['konimbo_order'],$_POST['konimbo_document']);
    echo $message['message'];
}



