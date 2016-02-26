<?php
/**
 * Plugin Name: BuddyPress for VIP Go
 * Plugin URI:  https://github.com/humanmade/buddypress-vip-go
 * Description: Makes BuddyPress' media work with Automattic's VIP Go hosting.
 * Author:      Human Made
 * Author URI:  https://hmn.md/
 * License:     GPLv2 or later.
 * Text Domain: buddypress-vip-go
 * Version:     1.0
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

add_action( 'bp_loaded', function() {
	if ( ! class_exists( 'A8C_Files' ) || ! defined( 'FILES_CLIENT_SITE_ID' ) || !defined( 'FILES_ACCESS_TOKEN' ) ) {
		return;
	}

	require_once __DIR__ . '/files.php';
	require_once __DIR__ . '/class-vipbp-fhs.php';

	add_action( 'bp_init', function() {
		$GLOBALS['VIPBP'] = new VIPBP_FHS();
	}, 1 );
} );
