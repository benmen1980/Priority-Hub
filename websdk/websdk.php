<?php
// websdk options

$form = new WebSDK('','');
$form->generate_hub_form();

if ( isset( $_POST['submit'] ) && isset($_POST['websdk_username'])&& isset($_POST['websdk_document'])) {
    $user = get_user_by('login', $_POST['websdk_username']);
    if (!$user) {
        echo 'Username does not exists<br>';
    }
    $websdk = new WebSDK($_POST['websdk_document'], $_POST['websdk_username']);
    if ($_POST['websdk_document'] == 'upload-image-to-priority-product') {
        $websdk_config = $_POST['websdk_config'];


        //$raw_option = str_replace(array('.',  "\n", "\t", "\r"), '', $websdk_config);
        $config = json_decode(stripslashes($websdk_config));
        $img_url =  $config->imageURL;
        $sku = $config->SKU;
        $username = $_POST['websdk_username'];
        $user = get_user_by('login',$username);
        $message['message'] = $websdk->upload_image_to_priority_product($user,$img_url, $sku);

        //$message['message'] = 'Message... <br>';
    }
    if ($_POST['websdk_document'] == 'close-ainvoice') {
        $websdk_config = $_POST['websdk_config'];
        //$raw_option = str_replace(array('.',  "\n", "\t", "\r"), '', $websdk_config);
        $config = json_decode(stripslashes($websdk_config));
        $ivnum =  $config->ivnum;
        $username = $_POST['websdk_username'];
        $user = get_user_by('login',$username);
        $message['message'] = $websdk->close_ainvoice($user,$ivnum);
        //$message['message'] = 'Message... <br>';
    }
    if(isset($message['message'])) echo $message['message'];
}
?>
<hr>


