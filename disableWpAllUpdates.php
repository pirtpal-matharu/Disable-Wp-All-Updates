<?php
/*
Plugin Name: Disable WordPress All Updates
Description: Disables the theme, plugin and core update checking, the related cronjobs and notification system.
Plugin URI:  http://pirtamatharu.com/
Version:     1.0.1
Author:      Pirta Matharu
Author URI:  http://pirtamatharu.com/


*/ 
class RwitDisableWpAllUpdates { 
	function __construct() {
		add_action( 'admin_init', array(&$this, 'admin_init') );

		/* Disable Theme Updates (2.8 to 3.0) */
		add_filter( 'pre_transient_update_themes', array($this, 'last_checked_atm') );
		/* 3.0 */
		add_filter( 'pre_site_transient_update_themes', array($this, 'last_checked_atm') );

		/* Disable Plugin Updates (2.8 to 3.0) */
		add_action( 'pre_transient_update_plugins', array($this, 'last_checked_atm') );
		/* 3.0 */
		add_filter( 'pre_site_transient_update_plugins', array($this, 'last_checked_atm') );


		/* Disable Core Updates (2.8 to 3.0) */
		add_filter( 'pre_transient_update_core', array($this, 'last_checked_atm') );
		/* 3.0 */
		add_filter( 'pre_site_transient_update_core', array($this, 'last_checked_atm') );
		
		/* Filter schedule checks */
		add_action('schedule_event', array($this, 'filter_cron_events'));
		add_action( 'pre_set_site_transient_update_plugins', array($this, 'last_checked_atm'), 21, 1 );
		add_action( 'pre_set_site_transient_update_themes', array($this, 'last_checked_atm'), 21, 1 );

		/* Disable All Automatic Updates 3.7+ */
		add_filter( 'auto_update_translation', '__return_false' );
		add_filter( 'automatic_updater_disabled', '__return_true' );
		add_filter( 'allow_minor_auto_core_updates', '__return_false' );
		add_filter( 'allow_major_auto_core_updates', '__return_false' );
		add_filter( 'allow_dev_auto_core_updates', '__return_false' );
		add_filter( 'auto_update_core', '__return_false' );
		add_filter( 'wp_auto_update_core', '__return_false' );
		add_filter( 'auto_core_update_send_email', '__return_false' );
		add_filter( 'send_core_update_notification_email', '__return_false' );
		add_filter( 'auto_update_plugin', '__return_false' );
		add_filter( 'auto_update_theme', '__return_false' );
		add_filter( 'automatic_updates_send_debug_email', '__return_false' );
		add_filter( 'automatic_updates_is_vcs_checkout', '__return_true' );

		add_filter( 'automatic_updates_send_debug_email ', '__return_false', 1 );
		if( !defined( 'AUTOMATIC_UPDATER_DISABLED' ) ) define( 'AUTOMATIC_UPDATER_DISABLED', true );
		if( !defined( 'WP_AUTO_UPDATE_CORE') ) define( 'WP_AUTO_UPDATE_CORE', false );

		add_filter( 'pre_http_request', array($this, 'block_request'), 10, 3 );
	}


	/* Initialize and load the plugin stuff */
	function admin_init() {
		if ( !function_exists("remove_action") ) return; 
		/* Remove 'update plugins' option from bulk operations select list */
		global $current_user;
		$current_user->allcaps['update_plugins'] = 0;
		
		/* Hide maintenance and update nag */
		remove_action( 'admin_notices', 'update_nag', 3 );
		remove_action( 'network_admin_notices', 'update_nag', 3 );
		remove_action( 'admin_notices', 'maintenance_nag' );
		remove_action( 'network_admin_notices', 'maintenance_nag' );
		

		/* Disable Theme Updates (2.8 to 3.0) */
		remove_action( 'load-themes.php', 'wp_update_themes' );
		remove_action( 'load-update.php', 'wp_update_themes' );
		remove_action( 'admin_init', '_maybe_update_themes' );
		remove_action( 'wp_update_themes', 'wp_update_themes' );
		wp_clear_scheduled_hook( 'wp_update_themes' );


		/* 3.0 */
		remove_action( 'load-update-core.php', 'wp_update_themes' );
		wp_clear_scheduled_hook( 'wp_update_themes' );


		/* Disable Plugin Updates (2.8 to 3.0) */
		remove_action( 'load-plugins.php', 'wp_update_plugins' );
		remove_action( 'load-update.php', 'wp_update_plugins' );
		remove_action( 'admin_init', '_maybe_update_plugins' );
		remove_action( 'wp_update_plugins', 'wp_update_plugins' );
		wp_clear_scheduled_hook( 'wp_update_plugins' );

		/* 3.0 */
		remove_action( 'load-update-core.php', 'wp_update_plugins' );
		wp_clear_scheduled_hook( 'wp_update_plugins' );


		/* Disable Core Updates (2.8 to 3.0) */
		add_filter( 'pre_option_update_core', '__return_null' );

		remove_action( 'wp_version_check', 'wp_version_check' );
		remove_action( 'admin_init', '_maybe_update_core' );
		wp_clear_scheduled_hook( 'wp_version_check' );


		/* 3.0 */
		wp_clear_scheduled_hook( 'wp_version_check' );


		/* 3.7+ */
		remove_action( 'wp_maybe_auto_update', 'wp_maybe_auto_update' );
		remove_action( 'admin_init', 'wp_maybe_auto_update' );
		remove_action( 'admin_init', 'wp_auto_update_core' );
		wp_clear_scheduled_hook( 'wp_maybe_auto_update' );
	}




	/* Check the outgoing request */
	public function block_request($pre, $args, $url) {
		/* Empty url */
		if( empty( $url ) ) {
			return $pre;
		}

		/* Invalid host */
		if( !$host = parse_url($url, PHP_URL_HOST) ) {
			return $pre;
		}

		/* block request */
		$url_data = parse_url( $url ); 
		if( false !== stripos( $host, 'api.wordpress.org' ) && (false !== stripos( $url_data['path'], 'update-check' ) || false !== stripos( $url_data['path'], 'browse-happy' )) ) {
			return true;
		} 
		return $pre;
	}


	/* Filter cron events */
	public function filter_cron_events($event) {
		switch( $event->hook ) {
			case 'wp_version_check':
			case 'wp_update_plugins':
			case 'wp_update_themes':
			case 'wp_maybe_auto_update':
				$event = false;
				break;
		}
		return $event;
	}
	 
	/* Override version check info */
	public function last_checked_atm( $t ) {
		include( ABSPATH . WPINC . '/version.php' );
		$current = new stdClass;
		$current->updates = array();
		$current->version_checked = $wp_version;
		$current->last_checked = time();
		return $current;
	}
}

if ( class_exists('RwitDisableWpAllUpdates') ) {
	$RwitDisableWpAllUpdates = new RwitDisableWpAllUpdates();
}

// Disable all plugin add & delete option and theme installation & editor
define('DISALLOW_FILE_MODS', true);
define('DISALLOW_FILE_EDIT', true);

?>
