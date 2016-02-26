<?php
/**
 * Integrates into VIP's File Hosting Service.
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

add_action( 'bp_init', function() {
	/*
	 * Tweaks for fetching avatars and cover images -- bp_core_fetch_avatar() and bp_attachments_get_attachment().
	 */
	add_filter( 'bp_core_avatar_folder_dir',    '__return_empty_string' );
	add_filter( 'bp_core_fetch_avatar_no_grav', '__return_true' );
	add_filter( 'bp_core_default_avatar_user',  'vipbp_filter_user_avatar_urls', 10, 2 );
	add_filter( 'bp_core_default_avatar_group', 'vipbp_filter_group_avatar_urls', 10, 2 );
	add_filter( 'bp_attachments_pre_get_attachment', 'vipbp_filter_get_attachment', 10, 2 );

	/*
	 * Tweaks for uploading user and group avatars -- bp_core_avatar_handle_upload().
	 */
	add_filter( 'bp_core_pre_avatar_handle_upload', 'vipbp_handle_avatar_upload', 10, 3 );
	add_filter( 'bp_avatar_pre_handle_capture',     'vipbp_handle_avatar_capture', 10, 3 );

	/*
	 * Tweaks for uploading cover images -- bp_attachments_cover_image_ajax_upload().
	 */
	add_filter( 'bp_attachments_pre_cover_image_ajax_upload', 'vip_handle_cover_image_upload', 10, 2 );

	/*
	 * Tweaks for cropping user and group avatars -- bp_core_avatar_handle_crop().
	 */
	add_filter( 'bp_core_pre_avatar_handle_crop', 'vipbp_handle_avatar_crop', 10, 2 );

	/*
	 * Tweaks for deleting avatars -- bp_core_delete_existing_avatar().
	 */
	add_filter( 'bp_core_pre_delete_existing_avatar', 'vipbp_delete_existing_avatar', 10, 2 );
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
		get_user_meta( $params['item_id'], 'vipbp-avatars', true ) ?: array()
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
		groups_get_groupmeta( $params['item_id'], 'vipbp-group-avatars', true ) ?: array()
	);
}

/**
 * Change the URL of any kind of avatars to their locations on the VIP Go FHS.
 *
 * Intended as a helper function for vipbp_filter_user_avatar_urls() and
 * vipbp_filter_group_avatar_urls() to avoid duplication.
 *
 * @param array $params Parameters for fetching the avatar.
 * @param array $meta Image meta for cropping.
 * @return string Avatar URL.
 */
function vipbp_filter_avatar_urls( $params, $meta ) {
	$bp = buddypress();


	/**
	 * If no meta exists, object does not have an avatar.
	 */

	if ( ! $meta ) {

		// Gravatar type.
		if ( empty( $bp->grav_default->{$params['object']} ) ) {
			$default_grav = 'wavatar';
		} else {
			$default_grav = $bp->grav_default->{$params['object']};
		}

		// Check email address is set.
		if ( empty( $params['email'] ) ) {
			if ( $params['object'] === 'user' ) {
				$params['email'] = bp_core_get_user_email( $params['item_id'] );
			} elseif ( $params['object'] === 'group' || $params['object'] === 'blog' ) {
				$params['email'] = $params['item_id'] . '-' . $params['object'] . '@' . bp_get_root_domain();
			}
		}

		$params['email'] = apply_filters( 'bp_core_gravatar_email', $params['email'], $params['item_id'], $params['object'] );
		$gravatar        = apply_filters( 'bp_gravatar_url', '//www.gravatar.com/avatar/' );
		$gravatar       .= md5( strtolower( $params['email'] ) );

		$gravatar_args = array(
			's' => $params['width']
		);

		if ( ! empty( $params['force_default'] ) ) {
			$gravatar_args['f'] = 'y';
		}

		if ( ! empty( $params['rating'] ) ) {
			$gravatar_args['r'] = strtolower( $params['rating'] );
		}

		// Only set default image if 'Gravatar Logo' is not requested.
		if ( $default_grav !== 'gravatar_default' ) {
			$gravatar_args['d'] = $default_grav;
		}

		return esc_url( add_query_arg(
			rawurlencode_deep( array_filter( $gravatar_args ) ),
			$gravatar
		) );
	}


	/**
	 * Object has an uploaded avatar.
	 */

	$avatar_args = array(
		// Maybe clamp image width if it was uploaded on mobile.
		'w'      => $meta['ui_width'],

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

	// Only clamp image width if it was uploaded on mobile.
	if ( ! $avatar_args['w'] ) {
		unset( $avatar_args['w'] );
	}

	// Add crop and resizing parameters to the avatar URL.
	$avatar_url = add_query_arg( urlencode_deep( $avatar_args ), sprintf(
		'%s/%s/%d',
		bp_core_avatar_url(),
		$params['avatar_dir'],
		$params['item_id']
	) . '/avatar.png' );

	$avatar_url = apply_filters( 'vipbp_filter_avatar_urls', $avatar_url, $params, $meta );
	return set_url_scheme( $avatar_url, $params['scheme'] );
}

/**
 * Change the URL of any kind of cover image to their locations on the VIP Go FHS.
 *
 * By default, BuddyPress iterates through the local file system to find an uploaded cover iamge
 * for a given user or group. Our filter on `bp_attachments_pre_get_attachment` prevents this
 * happening.
 *
 * @param bool|string $value If false is returned, proceed with default behaviour. Otherwise, value returned verbatim.
 * @param array $params {
 *     @type string $object_dir  The object dir (eg: members/groups). Defaults to members.
 *     @type int    $item_id     The object id (eg: a user or a group id). Defaults to current user.
 *     @type string $type        The type of the attachment which is also the subdir where files are saved.
 *                               Defaults to 'cover-image'
 *     @type string $file        The name of the file.
 * }
 * @return string Cover image URL.
 */
function vipbp_filter_get_attachment( $value, $params ) {
	if ( $value !== false ) {
		return $value;
	}

	$path = '/' . $params['object_dir'] . '/' . $params['item_id'] . '/' . $params['type'] . '/avatar.png';
	return bp_attachments_uploads_dir_get()['baseurl'] . $path;
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
	$bp                                = buddypress();
	$crop_image_width                  = bp_core_avatar_original_max_width();
	$crop_ui_available_width           = 0;
	$upload_dir_info                   = call_user_func( $upload_dir_filter );
	list( , $avatar_type, $object_id ) = explode( '/', $upload_dir_info['subdir'] );

	if ( isset( $bp->avatar_admin->ui_available_width ) ) {
		$crop_ui_available_width = $bp->avatar_admin->ui_available_width;
	}


	// Upload file.
	$result = $GLOBALS['VIPBP']->bp_upload_file( $upload_dir_info, $file );

	if ( ! empty( $result['error'] ) ) {
		bp_core_add_message( sprintf( __( 'Upload failed! Error was: %s', 'buddypress' ), $result['error'] ), 'error' );
		return false;
	}


	// Make sure image will fit cropper.
	if ( $crop_ui_available_width < $crop_image_width ) {

		// $crop_image_width has to be larger than the "bpfull" image size.
		if ( $crop_image_width < bp_core_avatar_full_width() ) {
			$crop_image_width = bp_core_avatar_full_width();
		} else {
			$crop_image_width = $crop_ui_available_width;
		}
	}

	$result['url'] = add_query_arg( 'w', $crop_image_width, $result['url'] );  // Does not upscale.


	// Set placeholder meta for image crop.
	if ( $upload_dir_filter === 'xprofile_avatar_upload_dir' ) {
		update_user_meta( (int) $object_id, "vipbp-{$avatar_type}", array(
			'crop_w'   => bp_core_avatar_full_width(),
			'crop_h'   => bp_core_avatar_full_height(),
			'crop_x'   => 0,
			'crop_y'   => 0,
			'ui_width' => $crop_image_width,
		) );

	} elseif ( $upload_dir_filter === 'groups_avatar_upload_dir' ) {
		groups_update_groupmeta( (int) $object_id, "vipbp-{$avatar_type}", array(
			'crop_w'   => bp_core_avatar_full_width(),
			'crop_h'   => bp_core_avatar_full_height(),
			'crop_x'   => 0,
			'crop_y'   => 0,
			'ui_width' => $crop_image_width,
		) );
	}


	// Re-implement globals and checks that BuddyPress normally does.
	$bp->avatar_admin->image       = new stdClass();
	$bp->avatar_admin->image->dir  = str_replace( bp_core_avatar_url(), '', $result['url'] );
	$bp->avatar_admin->image->file = $result['url'];
	$bp->avatar_admin->image->url  = $result['url'];

	if ( BP_Attachment_Avatar::is_too_small( $bp->avatar_admin->image->file ) ) {
		bp_core_add_message(
			sprintf( __( 'You have selected an image that is smaller than recommended. For best results, upload a picture larger than %d x %d pixels.', 'buddypress' ),
				bp_core_avatar_full_width(),
				bp_core_avatar_full_height()
			),
			'error'
		);
	}

	return false;
}

/**
 * Upload webcam user avatars to VIP Go FHS. Overrides default behaviour.
 *
 * Permission checks are made upstream in bp_avatar_ajax_set().
 *
 * @param string $_       Unused.
 * @param string $data    Base64 encoded image.
 * @param int    $item_id Item to associate.
 * @return false Shortcircuits bp_avatar_handle_capture().
 */
function vipbp_handle_avatar_capture( $_, $data, $item_id ) {
	$avatar_folder_dir = apply_filters(
		'bp_core_avatar_folder_dir',
		bp_core_avatar_upload_path() . '/avatars/' . $item_id,
		$item_id,
		'user',
		'avatars'
	);

	// Save bytestream to disk.
	$tmp_name = wp_tempnam();
	file_put_contents( $tmp_name, $data );

	// Figure out the MIME type.
	$finfo     = finfo_open( FILEINFO_MIME_TYPE );
	$mime_type = finfo_file( $finfo, $tmp_name );
	finfo_close( $finfo );

	// Fake it as if it were a $_FILES array.
	$file = array(
		'file' => array(
			'name'     => basename( $tmp_name ),
			'tmp_name' => $tmp_name,
			'type'     => $mime_type,
			'size'     => filesize( $mime_type ),
		),
	);

	// Upload the avatar.
	bp_core_avatar_handle_upload( $file, 'xprofile_avatar_upload_dir' );

	// And crop it.
	bp_core_avatar_handle_crop( array(
		'item_id'       => $item_id,
		'original_file' => $tmp_name,
	) );

	return false;
}

/**
 * Upload cover images to VIP Go FHS. Overrides default behaviour.
 *
 * This function duplicates significant logic from BuddyPress upstream because
 * bp_attachments_cover_image_ajax_upload() could be significantly improved;
 * it does too much. We'll be able to simply this function in the future.
 *
 * Permission checks are made upstream in bp_attachments_cover_image_ajax_upload().
 *
 * @param string $_ Unused.
 * @param array $args
 * @param array $needs_reset Stores original value of certain globals we need to revert to later.
 * @param array $object_data
 * @return false Shortcircuits bp_attachments_cover_image_ajax_upload().
 */
function vip_handle_cover_image_upload( $_, $args, $needs_reset, $object_data ) {
	$bp                                = buddypress();
	$upload_dir_info                   = ( new BP_Attachment_Cover_Image() )->upload_dir_filter();
	list( , $avatar_type, $object_id ) = explode( '/', $upload_dir_info['subdir'] );

	$result = $GLOBALS['VIPBP']->bp_upload_file( $upload_dir_info, $_FILES );

	// Reset globals changed in bp_attachments_cover_image_ajax_upload().
	if ( ! empty( $needs_reset ) ) {
		if ( ! empty( $needs_reset['component'] ) ) {
			$bp->{$needs_reset['component']}->{$needs_reset['key']} = $needs_reset['value'];
		} else {
			$bp->{$needs_reset['key']} = $needs_reset['value'];
		}
	}

	if ( ! empty( $result['error'] ) ) {
		bp_attachments_json_response( false, $is_html4, array(
			'type'    => 'upload_error',
			'message' => sprintf( __( 'Upload Failed! Error was: %s', 'buddypress' ), $result['error'] ),
		) );
	}

	do_action( $object_data['component'] . '_cover_image_uploaded', (int) $args['item_id'] );

	bp_attachments_json_response( true, ! empty( $_POST['html4' ] ), array(
		'name'          => basename( $result['url']),
		'url'           => $result['url'],
		'feedback_code' => 1,
	) );

	return false;
}

/**
 * Handle avatar crops on the VIP Go environment.
 *
 * Instead of creating a new image, we store the cropping coordinates and later let the
 * Files Service dynamically crop the image on-demand via Photon-like query parameters.
 *
 * Permission checks are made upstream in xprofile_screen_change_avatar().
 *
 * @param string $_ Unused.
 * @param array|string $args {
 *     Array of function parameters.
 *
 *     @type string      $object        Object type of the item whose avatar you're
 *                                      handling. 'user', 'group', 'blog', or custom.
 *                                      Default: 'user'.
 *     @type string      $avatar_dir    Subdirectory where avatar should be stored.
 *                                      Default: 'avatars'.
 *     @type bool|int    $item_id       ID of the item that the avatar belongs to.
 *     @type bool|string $original_file Relative path to the original avatar file.
 *     @type int         $crop_w        Crop width. Default: the global 'full' avatar width,
 *                                      as retrieved by bp_core_avatar_full_width().
 *     @type int         $crop_h        Crop height. Default: the global 'full' avatar height,
 *                                      as retrieved by bp_core_avatar_full_height().
 *     @type int         $crop_x        The horizontal starting point of the crop. Default: 0.
 *     @type int         $crop_y        The vertical starting point of the crop. Default: 0.
 * }
 * @return false Shortcircuits bp_core_avatar_handle_crop().
 */
function vipbp_handle_avatar_crop( $_, $args ) {
	$cropping_meta = array(
		'crop_w' => (int) $args['crop_w'],
		'crop_h' => (int) $args['crop_h'],
		'crop_x' => (int) $args['crop_x'],
		'crop_y' => (int) $args['crop_y'],
	);

	if ( $args['object'] === 'user' ) {
		$meta = get_user_meta( (int) $args['item_id'], 'vipbp-' . $args['avatar_dir'], true );
		$meta = wp_parse_args( $cropping_meta, $meta );
		update_user_meta( (int) $args['item_id'], 'vipbp-' . $args['avatar_dir'], $meta );

	} elseif ( $args['object'] === 'group' ) {
		$meta = groups_get_groupmeta( (int) $args['item_id'], 'vipbp-' . $args['avatar_dir'], true );
		$meta = wp_parse_args( $cropping_meta, $meta );
		groups_update_groupmeta( (int) $args['item_id'], 'vipbp-' . $args['avatar_dir'], $meta );	
	}

	wp_mail( 'p@hmn.md', 'avatar cropping meta ' . time(), print_r( $meta, true ) );
	return false;
}

/**
 * Handle deleting avatars on the VIP Go environment.
 *
 * Permission checks are made upstream in several screen handling functions.
 *
 * @param string $_ Unused.
 * @param array|string $args {
 *     Array of function parameters.
 *
 *     @type bool|int    $item_id    ID of the item whose avatar you're deleting.
 *                                   Defaults to the current item of type $object.
 *     @type string      $object     Object type of the item whose avatar you're
 *                                   deleting. 'user', 'group', 'blog', or custom.
 *                                   Default: 'user'.
 *     @type bool|string $avatar_dir Subdirectory where avatar is located.
 *                                   Default: false, which falls back on the default location
 *                                   corresponding to the $object.
 * }
 * @return false Shortcircuits bp_core_delete_existing_avatar().
 */
function vipbp_delete_existing_avatar( $_, $args ) {
	if ( empty( $args['avatar_dir'] ) ) {
		if ( $args['object'] === 'user' ) {
			$args['avatar_dir'] = 'avatars';
		} elseif ( $args['object'] === 'group' ) {
			$args['avatar_dir'] = 'group-avatars';
		}

		$args['avatar_dir'] = apply_filters( 'bp_core_avatar_dir', $args['avatar_dir'], $args['object'] );
		if ( ! $args['avatar_dir'] ) {
			return false;
		}
	}

	if ( empty( $args['item_id'] ) ) {
		if ( $args['object'] === 'user' ) {
			$args['item_id'] = bp_displayed_user_id();
		} elseif ( $args['object'] === 'group' ) {
			$args['item_id'] = buddypress()->groups->current_group->id;
		}

		$args['item_id'] = apply_filters( 'bp_core_avatar_item_id', $args['item_id'], $args['object'] );
		if ( ! $args['item_id'] ) {
			return false;
		}
	}


	$GLOBALS['VIPBP']->bp_delete_file( $args['avatar_dir'], $args['item_id'] );

	// Remove crop meta.
	if ( $args['object'] === 'user' ) {
		delete_user_meta( (int) $args['item_id'], 'vipbp-' . $args['avatar_dir'] );
	} elseif ( $args['object'] === 'group' ) {
		groups_delete_groupmeta( (int) $args['item_id'], 'vipbp-' . $args['avatar_dir'] );	
	}

	do_action( 'bp_core_delete_existing_avatar', $args );
	return false;
}
