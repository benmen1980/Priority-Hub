<?php
/*
* @package     PriorityHub
* @author      Roy Ben Menachem
* @copyright   2020 SimplyCT
*
* @wordpress-plugin
* Plugin Name: Priority Hub
* Plugin URI: http://www.simplyCT.co.il
* Description: Priority hub connnects any platform to Priority ERP
* Version: 1.04
* Author: SimplyCT
* Author URI: http://simplyCT.co.il
* Licence: GPLv2
* Text Domain: p18a
* Domain Path: languages
*
*/


define('PHUB_VERSION'       , '1.04');
define('PHUB_SELF'          , __FILE__);
define('PHUB_URI'           , plugin_dir_url(__FILE__));
define('PHUB_DIR'           , plugin_dir_path(__FILE__));
define('PHUB_ASSET_DIR'     , trailingslashit(PHUB_DIR)    . 'assets/');
define('PHUB_ASSET_URL'     , trailingslashit(PHUB_URI)    . 'assets/');
define('PHUB_INCLUDES_DIR'  , trailingslashit(PHUB_DIR)    . 'includes/');
define('PHUB_CLASSES_DIR'   , trailingslashit(PHUB_DIR)    . 'includes/classes/');
define('PHUB_ADMIN_DIR'     , trailingslashit(PHUB_DIR)    . 'includes/admin/');
// define plugin name and plugin admin url
define('PHUB_PLUGIN_NAME'      , 'Priority Hub');
define('PHUB_PLUGIN_ADMIN_URL' , sanitize_title(PHUB_PLUGIN_NAME));

add_action('init', function(){
    include_once (PHUB_ADMIN_DIR.'acf.php');
    include_once (PHUB_DIR.'priority-hub-class.php');
    include_once (PHUB_INCLUDES_DIR.'front-panel.php');
    include_once (PHUB_DIR.'konimbo/konimbo-class.php');
    include_once (PHUB_DIR.'shopify/shopify-class.php');
    include_once (PHUB_DIR.'istore/istore-class.php');
    add_action( 'admin_menu','add_menu_items');
    add_action('admin_init', function () {
        wp_localize_script('p18a-admin-js', 'P18A', [
            'nonce' => wp_create_nonce('p18a_request'),
            'working' => __('Working', 'p18a'),
            'json_response' => __('JSON Response', 'p18a'),
            'json_request' => __('JSON Request', 'p18a'),
        ]);
        wp_enqueue_style('p18a-admin-css', PHUB_ASSET_URL . 'style.css');
    });
    add_action('wp_enqueue_scripts', function () {
        wp_enqueue_script('p18a-admin-js', PHUB_ASSET_URL . 'admin.js', ['jquery']);
        wp_enqueue_style('p18a-admin-css', PHUB_ASSET_URL . 'style.css');
    });
    $services = ['Shopify','Konimbo','Istore'];
    restart_Services($services);
	});
function add_menu_items(){
    $hook = add_menu_page( 'Priority Hub', 'Priority Hub', 'activate_plugins', 'priority-hub', 'hub_options');
    //add_action( "load-$hook", 'add_options' );
}
function hub_options() {
    include_once ( PHUB_ADMIN_DIR.'options-header.php');
}
function restart_Services($services){
    foreach ($services as $service){
        //$service_shopify = new Service($service);
        $service = new Service($service);
    }
}
// new post type
class Service
{
    public $service;
    public function __construct($service){
        $this->service = $service;
        $this->register_custom_post_type('Order');
        $this->register_custom_post_type('otc');
        $this->register_custom_post_type('Invoice');
        $this->register_custom_post_type('Receipt');
        $this->register_custom_post_type('Shipment');
        add_action('add_meta_boxes', array($this, 'custom_post_data_form_meta_box'));
        $this->register_cron_action();

    }
    public function register_custom_post_type($document)
    {
        $labels = array(
            'name' => _x($this->service . ' ' . $document, 'Post Type General Name', 'text_domain'),
            'singular_name' => _x($this->service . ' ' . $document, 'Post Type Singular Name', 'text_domain'),
            'menu_name' => __($this->service . ' ' . $document, 'text_domain'),
            'name_admin_bar' => __($this->service . ' ' . $document, 'text_domain'),
            'archives' => __('Item Archives', 'text_domain'),
            'attributes' => __('Item Attributes', 'text_domain'),
            'parent_item_colon' => __('Parent Item:', 'text_domain'),
            'all_items' => __('All Items', 'text_domain'),
            'add_new_item' => __('Add New Item', 'text_domain'),
            'add_new' => __('Add New', 'text_domain'),
            'new_item' => __('New Item', 'text_domain'),
            'edit_item' => __('Edit Item', 'text_domain'),
            'update_item' => __('Update Item', 'text_domain'),
            'view_item' => __('View Item', 'text_domain'),
            'view_items' => __('View Items', 'text_domain'),
            'search_items' => __('Search Item', 'text_domain'),
            'not_found' => __('Not found', 'text_domain'),
            'not_found_in_trash' => __('Not found in Trash', 'text_domain'),
            'featured_image' => __('Featured Image', 'text_domain'),
            'set_featured_image' => __('Set featured image', 'text_domain'),
            'remove_featured_image' => __('Remove featured image', 'text_domain'),
            'use_featured_image' => __('Use as featured image', 'text_domain'),
//'insert_into_item'      => __( 'Insert into item', 'text_domain' ),
            'uploaded_to_this_item' => __('Uploaded to this item', 'text_domain'),
            'items_list' => __('Items list', 'text_domain'),
            'items_list_navigation' => __('Items list navigation', 'text_domain'),
            'filter_items_list' => __('Filter items list', 'text_domain'),
        );
        $args = array(
            'label' => __($this->service . ' ' . $document, 'text_domain'),
            'description' => __($this->service . ' ' . $document . ' log', 'text_domain'),
            'labels' => $labels,
            'supports' => array('title', 'editor'),
            'taxonomies' => array($this->service . ' ' . $document, 'OrderID', 'CustomerName'),
            'hierarchical' => false,
            'public' => true,
            'show_ui' => true,
            'show_in_menu' => true,
            'menu_position' => 23,
            'show_in_admin_bar' => true,
            'show_in_nav_menus' => true,
            'can_export' => true,
            'has_archive' => true,
            'exclude_from_search' => false,
            'publicly_queryable' => true,
            'capability_type' => 'post',
        );
        $post_type = strtolower($this->service.'_'.$document);
        register_post_type($this->service . '_' . $document, $args);
        //$this->custom_post_data_form_meta_box($document);
    }
    public function custom_post_data_form_meta_box()
    {
        // order
        $document = 'Order';
        $screen = strtolower($this->service) . '_' . $document;
        add_meta_box($this->service . '-meta-box-id', $this->service . ' Order Number', [$this, 'custom_post_data'], $screen, 'normal', 'high');
        // otc
        $document = 'otc';
        $screen = strtolower($this->service) . '_' . $document;
        add_meta_box($this->service . '-meta-box-id', $this->service . ' Order Number', [$this, 'custom_post_data'], $screen, 'normal', 'high');
        // invoice
        $document = 'Invoice';
        $screen = strtolower($this->service) . '_' . $document;
        add_meta_box($this->service . '-meta-box-id', $this->service . ' Order Number', [$this, 'custom_post_data'], $screen, 'normal', 'high');
        // receipt
        $document = 'Receipt';
        $screen = strtolower($this->service) . '_' . $document;
        add_meta_box($this->service . '-meta-box-id', $this->service . ' Order Number', [$this, 'custom_post_data'], $screen, 'normal', 'high');
    }
    function custom_post_data($post)
    {
        echo get_post_meta($post->ID, 'order_number', true);
    }
    function execute_cron_action($username,$doctype){
        $class_name = $this->service;
        $class_service = new $class_name($doctype,$username);
        $class_service->post_user_by_username($username,null,$doctype);
    }
    function execute_cron_action_inv($username){
        $class_name = $this->service;
        $class_service = new $class_name('sync_inventory_to_Shopify',$username);
        $class_service->post_user_by_username();
    }
    function register_cron_action(){
        // cron
        add_action(strtolower($this->service).'_action',array($this,'execute_cron_action'),1,3);
        add_action(strtolower($this->service).'_action_inv',array($this,'execute_cron_action_inv'),1,3);
    }
}
















