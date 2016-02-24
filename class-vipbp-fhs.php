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
	 * @param array $upload_dir_info Info about uploaded avatar and upload directory.
	 * @param string $file Appropriate entry from $_FILES superglobal.
	 * @return array Upload results.
	 *
	 * @todo REVIEW TO SUPPORT MULTINETWORK ACTIVATION.
	 */
	public function bp_upload_file( $upload_dir_info, $file ) {
		$file = $file['file'];

		// Convert image to a PNG for convenience.
		self::convert_image_to_png( $file['tmp_name'], wp_check_filetype( $file['name'] )['type'] );
		$mime_type = 'image/png';

		// See https://github.com/wpcomvip/buddypress-core-test/issues/6
		$get_upload_path = new ReflectionMethod( __CLASS__, 'get_upload_path' );
		$get_upload_path->setAccessible( true );

		$upload_url = $this->get_files_service_hostname() . '/' .
			            $get_upload_path->invoke( $this ) .
			            $upload_dir_info['subdir'] . '/avatar.png';

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


		if ( empty( $response['error'] ) ) {
			wp_mail( 'p@hmn.md', 'After upload_file ' . time(), print_r( $response, true ) );
		} else {
			wp_mail( 'p@hmn.md', 'During upload_file, error uploading ' . time(), print_r( $response, true ) );

			// Fix URL to point to real location.
			$response['url'] = str_replace(
				$this->get_files_service_hostname() . '/' . $get_upload_path->invoke( $this ),
				bp_core_avatar_url(),
				$response['url']
			);
		}

		return $response;
	}

	/**
	 * Delete an avatar from the VIP Go FHS, and purge from caches.
	 *
	 * Based on `A8C_Files->delete_file()`.
	 *
	 * @param bool|string $avatar_dir Subdirectory where avatar is located.
	 *                                Default: false, which falls back on the default location
	 *                                corresponding to the $object.
	 * @param int         $item_id    ID of the item whose avatar you're deleting.
	 */
	public function bp_delete_file( $avatar_dir, $item_id ) {
		// See https://github.com/wpcomvip/buddypress-core-test/issues/6
		$get_upload_path = new ReflectionMethod( __CLASS__, 'get_upload_path' );
		$get_upload_path->setAccessible( true );

		$ch = curl_init(
			$this->get_files_service_hostname() . '/' .
			$get_upload_path->invoke( $this ) .
			"/{$avatar_dir}/{$item_id}/avatar.png";
		);

		curl_setopt( $ch, CURLOPT_CUSTOMREQUEST, 'DELETE' );
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
		curl_setopt( $ch, CURLOPT_HEADER, false );
		curl_setopt( $ch, CURLOPT_TIMEOUT, 10 );
		curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, 0 );
		curl_setopt( $ch, CURLOPT_HTTPHEADER, array(
			'X-Client-Site-ID: ' . constant( 'FILES_CLIENT_SITE_ID' ),
			'X-Access-Token: ' . constant( 'FILES_ACCESS_TOKEN' ),
		) );

		curl_exec( $ch );
		$http_code = (int) curl_getinfo( $ch, CURLINFO_HTTP_CODE );
		curl_close( $ch );

		if ( $http_code !== 200 ) {
			error_log( sprintf( 'Error deleting the BuddyPress file from the remote servers: Code %d', $http_code ) );
			return;
		}

		wp_mail( 'p@hmn.md', 'After delete_file ' . time(), print_r( array(
			$http_code,
			$this->get_files_service_hostname() . '/' . $get_upload_path->invoke( $this ) .	"/{$avatar_dir}/{$item_id}/avatar.png"
		), true ) );

		// See https://github.com/wpcomvip/buddypress-core-test/issues/6
		$purge_file_cache = new ReflectionMethod( __CLASS_, 'purge_file_cache' );
		$purge_file_cache->setAccessible( true );

		$purge_file_cache->invoke(
			$this,
			get_site_url() . '/' . $get_upload_path->invoke( $this ) . "/{$avatar_dir}/{$item_id}/avatar.png",
			'PURGE'
		);
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
