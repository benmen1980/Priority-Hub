<?php
add_shortcode('simply_front_panel','simply_front_panel');

function simply_front_panel(){
	defined('ABSPATH') or die('No direct script access!');
	wp_enqueue_script( 'myshortcodejs', PHUB_ASSET_URL.'front.js',[],null,true );
	ob_start();
	$tab = '';
	if(!empty($_GET['tab'])){
	    $tab = $_GET['tab'];
    }
	global $wp;
	$current_url = home_url( add_query_arg( array(), $wp->request ) );
	?>
    <br />

    <div id="p18a_tabs_menu">
        <ul>
            <li>
                <a href="<?php echo $current_url; ?>" class="<?php if($tab='') echo 'active'; ?>">
					<?php _e('Settings', 'p18a'); ?>
                </a>
            </li>
            <li>
                <a href="<?php echo $current_url . '/?tab=konimbo'; ?>" class="<?php if($tab == 'konimbo') echo 'active'; ?>">
					<?php _e('Konimbo', 'p18a'); ?>
                </a>
            </li>
            <li>
                <a href="<?php echo $current_url . '/?tab=shopify'; ?>" class="<?php if($tab == 'shopify') echo 'active'; ?>">
					<?php _e('Shopify', 'p18a'); ?>
                </a>
            </li>
            <li>
                <a href="<?php echo $current_url. '/?tab=Amazon'; ?>" class="<?php if($tab == 'amazon') echo 'active'; ?>">
					<?php _e('Amazon', 'p18a'); ?>
                </a>
            </li>

        </ul>
    </div>

	<?php
	if(isset($_GET['tab'])){
		switch ($_GET['tab']){
			case 'konimbo':
				include_once (PHUB_ADMIN_DIR.'front-konimbo.php');
				break;
		}
	}
    $content = ob_get_contents();
    ob_end_clean();
    return $content;
}
