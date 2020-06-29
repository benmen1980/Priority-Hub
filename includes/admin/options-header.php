<?php
defined('ABSPATH') or die('No direct script access!'); ?>

<h1>
	<?php echo 'Priority Hub'; ?>
	<span id="p18a_version"><?php echo PHUB_VERSION; ?></span>
</h1>

<br />

<div id="p18a_tabs_menu">
	<ul>
		<li>
			<a href="<?php echo admin_url('admin.php?page=' . PHUB_PLUGIN_ADMIN_URL); ?>" class="<?php if(is_null($this->get('tab'))) echo 'active'; ?>">
				<?php _e('Settings', 'p18a'); ?>
			</a>
		</li>
		<li>
			<a href="<?php echo admin_url('admin.php?page=' . PHUB_PLUGIN_ADMIN_URL . '&tab=konimbo'); ?>" class="<?php if($this->get('tab') == 'konimbo') echo 'active'; ?>">
				<?php _e('Konimbo', 'p18a'); ?>
			</a>
		</li>
		<li>
			<a href="<?php echo admin_url('admin.php?page=' . PHUB_PLUGIN_ADMIN_URL . '&tab=shopify'); ?>" class="<?php if($this->get('tab') == 'shopify') echo 'active'; ?>">
				<?php _e('Shopify', 'p18a'); ?>
			</a>
		</li>
		<li>
			<a href="<?php echo admin_url('admin.php?page=' . PHUB_PLUGIN_ADMIN_URL . '&tab=Amazon'); ?>" class="<?php if($this->get('tab') == 'amazon') echo 'active'; ?>">
				<?php _e('Amazon', 'p18a'); ?>
			</a>
		</li>

	</ul>
</div>

<?php
if(isset($_GET['tab'])){
	switch ($_GET['tab']){
		case 'konimbo':
			include_once (PHUB_ADMIN_DIR.'konimbo.php');
			break;
	}
}


?>
