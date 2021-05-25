<?php
class WebSDK extends \Priority_Hub{
    function get_service_name(){
        return 'WebSDK';
    }
    public function generate_hub_form(){
        ?>

        <form action="<?php echo esc_url( admin_url('admin.php?page=priority-hub&tab='.$this->get_service_name_lower())); ?>" method="post">
            <input type="hidden" name="<?php echo $this->get_service_name_lower(); ?>>_action" value="sync_<?php echo $this->get_service_name_lower(); ?>">
            <div><input type="checkbox" name="<?php echo $this->get_service_name_lower(); ?>_generalpart" value="generalpart"><span>Post general item</span></div>
            <div><input type="text" name="<?php echo $this->get_service_name_lower(); ?>_username"><span>User Name</span></div>
            <div>
                <select value="" name="<?php echo $this->get_service_name_lower(); ?>_document" id="<?php echo $this->get_service_name_lower(); ?>_document">
                    <option selected="selected"></option>
                    <option value="upload-image-to-priority-product">Upload image to Priority Item</option>
                    <option value="close-ainvoice">Close Sales Invoice</option>
                    <option value="close-open-invoices">Close Open Sales Invoices</option>



                </select>
                <label>Select Priority Entity target</label></div>
            <textarea name="<?php echo $this->get_service_name_lower(); ?>_config" rows="15" cols="50" value=""
                      placeholder='{"imageURL":"url","SKU":"000"}'></textarea>
            <span><p><br></p></span></div>

            <br>
            <?php
            //<input type="submit" value="Click here to sync konimbo to Priority"> 4567567

            wp_nonce_field( 'acme-settings-save', 'acme-custom-message' );
            submit_button('Execute API');

            ?>
        </form>
        <?php
    }
    function upload_image_to_priority_product($user,$image_url, $sku){
        $priority_url = get_user_meta($user->ID, 'url', true);
        $tabulaini = get_user_meta($user->ID, 'application', true);
        $company = get_user_meta($user->ID, 'environment_name', true);
        $username = get_user_meta($user->ID, 'username', true);
        $password = get_user_meta($user->ID, 'password', true);
        $path = dirname(__FILE__);
        $command = sprintf('node '.$path.'/node/uploadFileToPriority/index.js %s %s %s %s %s %s %s ',
        $username,$password,$priority_url,$tabulaini,$company,$sku,$image_url);
         echo $command.'<br>';

        $res = shell_exec($command);
        echo 'shell exec done with message: <br>';
        echo $res;
        return $res;


    }
    function close_invoice($ivnum,$ivtype){
        $user = $this->get_user();
        $priority_url = 'https://'.get_user_meta($user->ID, 'url', true);
        $tabulaini = get_user_meta($user->ID, 'application', true);
        $company = get_user_meta($user->ID, 'environment_name', true);
        $username = get_user_meta($user->ID, 'username', true);
        $password = get_user_meta($user->ID, 'password', true);
        $path = dirname(__FILE__);
        $command = sprintf('node '.$path.'/node/close_ainvoice/index.js %s %s %s %s %s %s %s',
            $username,$password,$priority_url,$tabulaini,$company,$ivnum,$ivtype);
        $res = shell_exec($command);
        $res = empty($res) ? 'Node return empty response...' : $res;
        return $command.'<br>'.$res;
    }
    function close_open_invoices($ivtype){
        $user = $this->get_user();
        $api_user = get_user_meta($user->ID, 'username', true);
        $additional_url = $ivtype.'?$select=IVNUM&$filter=OWNERLOGIN eq \''.$api_user.'\' and FINAL ne \'Y\'';
        $response = $this->makeRequest( 'GET', $additional_url,null, $user );
        if($response['code']== '200'){
            $invoices = json_decode($response['body'])->value;
            if(empty($invoices)){
                $this->write_to_log('No invoices to close for request: '  . $additional_url);
                return  'No invoices to close';
            }
            $res = '';
            foreach ($invoices as $invoice){
                $ivnum = $invoice->IVNUM;
                $response = $this->close_invoice($ivnum,$ivtype);
                $res .=  $response. '<br>';
            }
            $this->write_to_log($res);
            return $res;
        }else{
            $error_message = $response['body'];
            $this->sendEmailError('WebSdk Error while close '.$ivtype,$error_message);
            $this->write_to_log($error_message);
            return $error_message;
        }
    }
}