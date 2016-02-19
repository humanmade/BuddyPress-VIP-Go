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
}
