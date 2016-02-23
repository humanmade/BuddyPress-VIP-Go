<?php
/**
 * Integrates into VIP's File Hosting Service.
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

add_action( 'bp_init', function() {
	/*
	 * Tweaks for fetching avatars -- bp_core_fetch_avatar().
	 */
	add_filter( 'bp_core_avatar_folder_dir',    '__return_empty_string' );
	add_filter( 'bp_core_fetch_avatar_no_grav', '__return_true' );
	add_filter( 'bp_core_default_avatar_user',  'vipbp_filter_user_avatar_urls', 10, 2 );
	add_filter( 'bp_core_default_avatar_group', 'vipbp_filter_group_avatar_urls', 10, 2 );

	/*
	 * Tweaks for uploading user and group avatars -- bp_core_avatar_handle_upload().
	 */
	add_filter( 'bp_core_pre_avatar_handle_upload', 'vipbp_handle_avatar_upload', 10, 3 );
} );

/**
 * Change user avatars' URLs to their locations on VIP Go FHS.
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
 * @return string Avatar URL.
 */
function vipbp_filter_user_avatar_urls( $_, $params ) {
	return vipbp_filter_avatar_urls(
		$params,
		get_user_meta( bp_displayed_user_id(), 'vipbp-avatars', true ) ?: array()
	);
}

/**
 * Change group avatars' URLs to their locations on VIP Go FHS.
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
 * @return string Avatar URL.
 */
function vipbp_filter_group_avatar_urls( $_, $params ) {
	return vipbp_filter_avatar_urls(
		$params,
		groups_get_groupmeta( bp_get_current_group_id(), 'vipbp-group-avatars', true ) ?: array()
	);
}

/**
 * Change any the URL of any kind of avatars to their locations on VIP Go FHS.
 *
 * Intended as a helper function for vipbp_filter_user_avatar_urls() and
 * vipbp_filter_group_avatar_urls() to avoid duplication.
 *
 * @param array $params Parameters for fetching the avatar.
 * @param array $meta Image meta for cropping.
 * @return string Avatar URL.
 *
 * @todo GRAVATAR FALLBACK - =d or user meta?
 * @todo GRAVATAR - if $meta doesn't exist, use gravatar?
 */
function vipbp_filter_avatar_urls( $params, $meta ) {
	if ( ! $meta ) {
		//temp
		return bp_core_avatar_default( 'local' );
	}

	$avatar_args = array(
		// Maybe clamp image width if original is too wide to match normal BP behaviour.
		'w'      => $meta['ui_width'] ?: bp_core_avatar_original_max_width(),

		// Crop avatar.
		'crop'   => sprintf( '%dpx,%dpx,%dpx,%dpx',
			$meta['crop_x'],
			$meta['crop_y'],
			$meta['crop_w'],
			$meta['crop_h']
		),

		// Resize back to bpthumb or bpfull size.
		'resize' => sprintf( '%d,%d', $params['width'], $params['height'] ),

		// Removes EXIF and IPTC data.
		'strip'  => 'info',
	);

	// Only clamp image width if uploaded original was too wide.
	if ( $meta['original_width'] <= bp_core_avatar_original_max_width() ) {
		unset( $avatar_args['w'] );
	}

	// Add crop and resizing parameters to the avatar URL.
	$avatar_url = add_query_arg( $avatar_args, sprintf(
		'%s/%s/%d',
		bp_core_avatar_url(),
		$params['avatar_dir'],
		$params['item_id']
	) . '/avatar.png' );

	return set_url_scheme( $avatar_url, $params['scheme'] );
}

/**
 * Upload avatars to VIP Go FHS. Overrides default behaviour.
 *
 * Permission checks are made upstream in xprofile_screen_change_avatar().
 *
 * @param string $_ Unused.
 * @param array $file Appropriate entry from $_FILES superglobal.
 * @param string $upload_dir_filter Callable function to get uploaded avatar and upload directory info.
 * @return false Shortcircuits bp_core_avatar_handle_upload().
 */
function vipbp_handle_avatar_upload( $_, $file, $upload_dir_filter ) {
	if ( ! isset( $GLOBALS['VIPBP'] ) ) {
		// @todo should this happen?
		wp_mail( 'p@hmn.md', 'BP image debug, missing global.', 'hello world' );
		return false;
	}

	$bp                                = buddypress();
	$upload_dir_info                   = call_user_func( $upload_dir_filter );
	list( , $avatar_type, $object_id ) = explode( '/', $upload_dir_info['subdir'] );

	if ( ! get_user_by( 'ID', (int) $object_id ) ) {
		return false;
	}


	// Upload file.
	$result = $GLOBALS['VIPBP']->bp_upload_file( $upload_dir_info, $file );

	if ( ! empty( $result['error'] ) ) {
		bp_core_add_message( sprintf( __( 'Upload failed! Error was: %s', 'buddypress' ), $result['error'] ), 'error' );
		return false;
	}

	// Set placeholder meta for image crop.
	update_user_meta( (int) $object_id, "vipbp-{$avatar_type}", array(
		'crop_w'         => bp_core_avatar_full_width(),
		'crop_h'         => bp_core_avatar_full_height(),
		'crop_x'         => 0,
		'crop_y'         => 0,
		'original_width' => getimagesize( $file['file'] )[0],
		'ui_width'       => $bp->avatar_admin->ui_available_width ?: 0,
	) );


	// Re-implement globals and checks that BuddyPress normally does.
	$bp->avatar_admin->image       = new stdClass();
	$bp->avatar_admin->image->file = $result['url'];
	$bp->avatar_admin->image->dir  = str_replace( bp_core_avatar_url(), '', $result['url'] );

	if ( BP_Attachment_Avatar::is_too_small( $bp->avatar_admin->image->file ) ) {
		bp_core_add_message(
			sprintf( __( 'You have selected an image that is smaller than recommended. For best results, upload a picture larger than %d x %d pixels.', 'buddypress' ),
				bp_core_avatar_full_width(),
				bp_core_avatar_full_height()
			),
			'error'
		);
	}

	// Return false to shortcircuit bp_core_avatar_handle_upload().
	return false;
}
