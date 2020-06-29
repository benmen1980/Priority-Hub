<?php
/*
Plugin Name: Priority Hub
*/

define('PHUB_VERSION'       , '1.0');
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

include_once (PHUB_INCLUDES_DIR.'konimbo.php');
include_once (PHUB_ADMIN_DIR.'acf.php');

class Priority_Hub {
	private static $instance; // api instance
	// constants
	public static function instance()
	{
		if (is_null(static::$instance)) {
			static::$instance = new static();
		}

		return static::$instance;
	}
	public function __construct() {
		add_action( 'admin_menu',array( $this,'add_menu_items'));
	}
	public function run()
	{
		return is_admin() ? $this->backend(): $this->frontend();
	}
	protected function get($key, $filter = null, $options = null)
	{
		if (is_null($filter)) {
			return isset($_GET[$key]) ? $_GET[$key] : null;
		}

		return filter_var($_GET[$key], filter_id($filter), $options);
	}
	public function makeRequest($method, $url_addition = null,$options = [], $user)
	{
		$args = [
			'headers' => [
				'Authorization' => 'Basic ' . base64_encode($this->option('username') . ':' . $this->option('password')),
				'Content-Type'  => 'application/json',
				'X-App-Id' => get_user_meta( $user->ID, 'x-app-id' ,true),
				'X-App-Key' => get_user_meta( $user->ID, 'x-app-key' ,true)
			],
			'timeout'   => 45,
			'method'    => strtoupper($method),
			'sslverify' => get_user_meta( $user->ID, 'ssl_verify' ,true)
		];


		if ( ! empty($options)) {
			$args = array_merge($args, $options);
		}

		$url = sprintf('https://%s/odata/Priority/%s/%s/%s',
			get_user_meta( $user->ID, 'url' ,true),
			get_user_meta( $user->ID, 'application' ,true),
			get_user_meta( $user->ID, 'environment_name' ,true),
			is_null($url_addition) ? '' : stripslashes($url_addition)
		);

		$response = wp_remote_request($url, $args);

		$response_code    = wp_remote_retrieve_response_code($response);
		$response_message = wp_remote_retrieve_response_message($response);
		$response_body    = wp_remote_retrieve_body($response);

		if ($response_code >= 400) {
			$response_body = strip_tags($response_body);
		}

		// decode hebrew
		$response_body_decoded = $this->decodeHebrew($response_body);


		return [
			'url'      => $url,
			'args'     => $args,
			'method'   => strtoupper($method),
			'body'     => $response_body_decoded,
			'body_raw' => $response_body,
			'code'     => $response_code,
			'status'   => ($response_code >= 200 && $response_code < 300) ? 1 : 0,
			'message'  => ($response_message ? $response_message : $response->get_error_message())
		];
	}
	// frontend
	function frontend(){


	}
	// backend
	function backend(){

	}

	// admin pages
	function hub_options() {
		include_once ('includes\admin\options-header.php');





	}
	// admin menu
	function add_menu_items(){
	 $hook = add_menu_page( 'Priority Hub', 'Priority Hub', 'activate_plugins', 'priority-hub', array($this,'hub_options'));
	 add_action( "load-$hook", 'add_options' );

	}
}

add_action('plugins_loaded', function(){
	Priority_Hub::instance()->run();
	Konimbo::instance()->run();
	});















