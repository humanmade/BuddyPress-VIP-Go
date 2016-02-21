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
	 * @param int $object_id An ID for what the uploaded file should relate to (e.g. group ID).
	 * @return array
	 */
	public function bp_upload_file( $upload_dir_filter, $file, $object_id ) {
		$upload_file_path = parse_url( $file['tmp_name'], PHP_URL_PATH );
		$upload_dir_info  = call_user_func( $upload_dir_filter, $object_id );

		$upload_url = $this->get_files_service_hostname() . '/' . $this->get_upload_path();
		if ( is_multisite() ) {
			$upload_url .= '/sites/' . bp_get_root_blog_id();
		}
		$upload_url .= $upload_dir_info['subdir'] . '/' . array_pop( $upload_file_path );

		$response = $this->upload_file( array(
			'file' => $file['tmp_name'],
			'type' => wp_check_filetype( $new_file )['type'],
			'url'  => $upload_url,
		), 'editor_save' );

		// die(var_dump( $response ));
	}
}
