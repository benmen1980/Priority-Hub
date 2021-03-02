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



}