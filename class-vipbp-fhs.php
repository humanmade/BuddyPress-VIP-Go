<?php
// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Extends Automattic's File Hosting Service plugin for BuddyPress to re-use.
 */
class VIPBP_FHS extends A8C_Files {

	/**
	 * Constructor.
	 *
	 * Intentionally does NOT call parent::__construct().
	 */
	public function __construct() {
	}

	/**
	 * Upload any kind of BuddyPress avatar to the VIP Go FHS as a PNG image.
	 *
	 * @param string $upload_dir_filter A filter we use to determine avatar type.
	 * @param string $file Appropriate entry from $_FILES superglobal.
	 * @return array
	 */
	public function bp_upload_file( $upload_dir_filter, $file ) {
		$file            = $file['file'];
		$mime_type       = wp_check_filetype( $file['name'] )['type'];
		$upload_dir_info = call_user_func( $upload_dir_filter );

		// Convert image to a PNG for convenience.
		self::convert_image_to_png( $file['tmp_name'], $mime_type );

		// See https://github.com/wpcomvip/buddypress-core-test/issues/6
		$get_upload_path = new ReflectionMethod( __CLASS__, 'get_upload_path' );
		$get_upload_path->setAccessible( true );

		$upload_url = $this->get_files_service_hostname() . '/' . $get_upload_path->invoke( $this );
		if ( is_multisite() ) {
			$upload_url .= '/sites/' . bp_get_root_blog_id();
		}
		$upload_url .= $upload_dir_info['subdir'] . '/avatar.png';

		wp_mail( 'p@hmn.md', 'Before upload_file ' . time(), print_r( array(
			'file' => $file['tmp_name'],
			'type' => $mime_type,
			'url'  => $upload_url,
		), true ) );

		$response = $this->upload_file( array(
			'file' => $file['tmp_name'],
			'type' => $mime_type,
			'url'  => $upload_url,
		), 'editor_save' );

		wp_mail( 'p@hmn.md', 'After upload_file ' . time(), print_r( $response, true ) );
		return $response;
	}

	/**
	 * Convert the specified image to a PNG.
	 *
	 * @param string $file
	 * @param string $mime_type
	 */
	public static function convert_image_to_png( $file, $mime_type ) {
		if ( $mime_type === 'image/png' ) {
			return;
		} elseif ( $mime_type === 'image/gif' ) {
			$image = imagecreatefromgif( $file );
		} elseif ( $mime_type === 'image/jpeg' ) {
			$image = imagecreatefromjpeg( $file );
		} else {
			$image = false;
		}

		if ( is_resource( $image ) ) {
			imagealphablending( $image, false );
			imagesavealpha( $image, true );
			imagepng( $image, $file );
		}
	}
}
