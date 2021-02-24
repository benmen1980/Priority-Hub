<?php
defined('ABSPATH') or die('No direct script access!');
$hub_options = new Priority_Hub('hub','user');
?>

<h1>
	<?php echo 'Priority Hub'; ?>
	<span id="p18a_version"><?php echo PHUB_VERSION; ?></span>
</h1>

<br />

<div id="p18a_tabs_menu">
	<ul>
		<li>
			<a href="<?php echo admin_url('admin.php?page=' . PHUB_PLUGIN_ADMIN_URL); ?>" class="<?php if(is_null($hub_options->get('tab'))) echo 'active'; ?>">
				<?php _e('Settings', 'p18a'); ?>
			</a>
		</li>
		<li>
			<a href="<?php echo admin_url('admin.php?page=' . PHUB_PLUGIN_ADMIN_URL . '&tab=konimbo'); ?>" class="<?php if($hub_options->get('tab') == 'konimbo') echo 'active'; ?>">
				<?php _e('konimbo', 'p18a'); ?>
			</a>
		</li>
		<li>
			<a href="<?php echo admin_url('admin.php?page=' . PHUB_PLUGIN_ADMIN_URL . '&tab=shopify'); ?>" class="<?php if($hub_options->get('tab') == 'shopify') echo 'active'; ?>">
				<?php _e('shopify', 'p18a'); ?>
			</a>
		</li>
		<li>
			<a href="<?php echo admin_url('admin.php?page=' . PHUB_PLUGIN_ADMIN_URL . '&tab=Amazon'); ?>" class="<?php if($hub_options->get('tab') == 'amazon') echo 'active'; ?>">
				<?php _e('Amazon', 'p18a'); ?>
			</a>
		</li>
        <li>
            <a href="<?php echo admin_url('admin.php?page=' . PHUB_PLUGIN_ADMIN_URL . '&tab=istore'); ?>" class="<?php if($hub_options->get('tab') == 'istore') echo 'active'; ?>">
                <?php _e('istore', 'p18a'); ?>
            </a>
        </li>
        <li>
            <a href="<?php echo admin_url('admin.php?page=' . PHUB_PLUGIN_ADMIN_URL . '&tab=paxxi'); ?>" class="<?php if($hub_options->get('tab') == 'paxxi') echo 'active'; ?>">
                <?php _e('paxxi', 'p18a'); ?>
            </a>
        </li>

	</ul>
</div>

<?php
if(isset($_GET['tab'])){
	switch ($_GET['tab']){
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


?>
