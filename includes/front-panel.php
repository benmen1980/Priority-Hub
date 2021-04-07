<?php
add_shortcode('priority_hub__front_panel','priority_hub_front_panel');

function priority_hub_front_panel(){
    if(!is_user_logged_in()){
        global $wp;
        wp_die('You are not <a href="'.wp_login_url(home_url( $wp->request )).'">logged in');
    }
	defined('ABSPATH') or die('No direct script access!');
	wp_enqueue_script( 'myshortcodejs', PHUB_ASSET_URL.'front.js',[],null,true );
	ob_start();
	$tab = '';
	if(!empty($_GET['tab'])){
	    $tab = $_GET['tab'];
    }
	global $wp;
	$current_url = home_url( add_query_arg( array(), $wp->request ) );

    $user = wp_get_current_user();
    $konimbo_activate_sync = get_user_meta( $user->ID, 'konimbo_activate_sync',true );
    $shopify_activate_sync = get_user_meta( $user->ID, 'shopify_activate_sync',true );
    $istore_activate_sync = get_user_meta( $user->ID, 'istore_active',true );
	?>
    <br />

    <div id="p18a_tabs_menu">
        <ul>
            <?php if($konimbo_activate_sync):?>
                <li>
                    <a href="<?php echo $current_url . '/?tab=konimbo'; ?>" class="<?php echo ($tab == 'konimbo') ? 'active' : ''; ?> ">
                        <?php _e('konimbo', 'p18a'); ?>
                    </a>
                </li>
            <?php endif;?>
            <?php if($shopify_activate_sync):?>
                <li>
                    <a href="<?php echo $current_url . '/?tab=shopify'; ?>" class="<?php if($tab == 'shopify') echo 'active'; ?>">
                        <?php _e('shopify', 'p18a'); ?>
                    </a>
                </li>
            <?php endif;?>
            <?php if($istore_activate_sync):?>
                <li>
                    <a href="<?php echo $current_url. '/?tab=istore'; ?>" class="<?php if($tab == 'istore') echo 'active'; ?>">
                        <?php _e('istore', 'p18a'); ?>
                    </a>
                </li>
            <?php endif;?>     
        </ul>
    </div>

	<?php
	if(isset($_GET['tab'])){
        switch ($_GET['tab']){
            case 'websdk':
                include_once (PHUB_DIR.'websdk/websdk-class.php');
                include_once (PHUB_DIR.'websdk/websdk.php');
                break;
            case 'konimbo':
                include_once (PHUB_DIR.'konimbo/konimbo.php');
                break;
            case 'shopify':
                include_once (PHUB_DIR.'shopify/shopify.php');
                break;
            case 'istore':
                include_once (PHUB_DIR.'istore/istore.php');
                break;
            case 'paxxi':
                include_once (PHUB_DIR.'paxxi/paxxi.php');
                break;
        }
    }
    $content = ob_get_contents();
    ob_end_clean();
    return $content;
}
