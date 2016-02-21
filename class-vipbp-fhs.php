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
	 * Intentionally does NOT call parent::__construct() because A8C_Files is instantiated elsewhere.
	 */
	public function __construct() {
	}

	/**
	 * Upload any kind of BuddyPress avatar to the VIP Go FHS.
	 *
	 * @param string $upload_dir_filter A filter we use to determine avatar type.
	 * @param string $file Appropriate entry from $_FILES superglobal.
	 * @return array
	 */
	public function bp_upload_file( $upload_dir_filter, $file ) {
		$file             = $file['file'];
		$upload_dir_info  = call_user_func( $upload_dir_filter );

		// See https://github.com/wpcomvip/buddypress-core-test/issues/6
		$get_upload_path = new ReflectionMethod( __CLASS__, 'get_upload_path' );
		$get_upload_path->setAccessible( true );
		$upload_url      = $this->get_files_service_hostname() . '/' . $get_upload_path->invoke( $this );

		if ( is_multisite() ) {
			$upload_url .= '/sites/' . bp_get_root_blog_id();
		}
		$upload_url .= $upload_dir_info['subdir'] . '/avatar.jpg';

		wp_mail( 'p@hmn.md', 'Before upload_file ' . time(), print_r( array(
			'file' => $file['tmp_name'],
			'type' => wp_check_filetype( $file['name'] )['type'],
			'url'  => $upload_url,
		), true ) );

		$response = $this->upload_file( array(
			'file' => $file['tmp_name'],
			'type' => wp_check_filetype( $file['name'] )['type'],
			'url'  => $upload_url,
		), 'editor_save' );

		wp_mail( 'p@hmn.md', 'After upload_file ' . time(), print_r( $response, true ) );
		return $response;
	}
}
