<?php
/**
 * Integrates into VIP's File Hosting Service.
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

add_action( 'bp_init', function() {
	/*
	 * Tweaks for bp_core_fetch_avatar().
	 */
	add_filter( 'bp_core_avatar_folder_dir',    '__return_empty_string' );
	add_filter( 'bp_core_fetch_avatar_no_grav', '__return_true' );
	add_filter( 'bp_core_default_avatar_user',  'vipbp_change_user_avatar_urls', 10, 2 );
	//add_filter( 'bp_core_default_avatar_group', 'vipbp_change_avatar_urls', 10, 2 );

	/*
	 * Tweaks for bp_core_avatar_handle_upload().
	 */
	add_filter( 'bp_core_pre_avatar_handle_upload', 'vipbp_handle_avatar_upload', 10, 3 );
} );

/**
 * Change user and group avatars' URL to their location on VIP Go FHS.
 *
 * By default, BuddyPress iterates through the local file system to find an uploaded avatar
 * for a given user or group. Our filter on `bp_core_avatar_folder_dir()` prevents this happening,
 * and our filter on `bp_core_fetch_avatar_no_grav` will make the code flow to this
 * bp_core_default_avatar_* filter.
 * 
 * It's normally used to override the fallback image for Gravatar, but by duplicating some logic, we
 * use it to here to set a custom URL to support VIP Go FHS without any core changes to BuddyPress.
 *
 * @param string $_ Unused.
 * @param array $params Parameters for fetching the avatar.
 * @return string
 *
 * @todo GRAVATAR FALLBACK - =d or user meta?
 */
function vipbp_change_user_avatar_urls( $_, $params ) {
	$bp = buddypress();

	$folder_url = sprintf(
		'%s/%s/%d',
		$bp->avatar->url,
		$params['avatar_dir'],
		$params['item_id']
	);
	$avatar_url = $folder_url . '/avatar.jpg';

	// bpthumb and bpfull sizes are handled via this width/height.
	$avatar_url = add_query_arg( array(
		'crop' => 1,
		'h'    => (int) $params['height'],
		'w'    => (int) $params['width'],
	), $avatar_url );

	return set_url_scheme( $avatar_url, $params['scheme'] );
}

/**
 * ?
 *
 * @param string $_ Unused.
 * @param array $file Appropriate entry from $_FILES superglobal.
 * @param string $upload_dir_filter A filter we use to determine avatar type.
 * @return false
 */
function vipbp_handle_avatar_upload( $_, $file, $upload_dir_filter ) {
	wp_mail( 'p@hmn.md', 'BP image debug', 'In vipbp_handle_avatar_upload' );

	if ( ! isset( $GLOBALS['VIPBP'] ) ) {
		// @todo should this happen?
		wp_mail( 'p@hmn.md', 'BP image debug', 'Missing global.' );
		return $_;
	}

	$bp = buddypress();

	if ( $upload_dir_filter === 'xprofile_avatar_upload_dir' ) {
		$object_id = bp_get_displayed_user_id();

	} elseif ( $upload_dir_filter === 'groups_avatar_upload_dir' ) {
		$object_id = bp_get_current_group_id();

	} else {
		// @todo Do something here?
	}

	// 1) Upload file.
	$result = $GLOBALS['VIPBP']->bp_upload_file( $upload_dir_filter, $file, $object_id );
	wp_mail( 'p@hmn.md', 'BP image debug', print_r( $result, true ) );
	exit;


	// Return false to shortcircuit bp_core_avatar_handle_upload().
	return false;
}
