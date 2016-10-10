<?php
class Check_Asset_Restrictions {

	/**
	 * Check_Asset_Restrictions constructor.
	 */
	public function __construct() {
	}

	/**
	 * Runs essential pieces of plugin to run within WordPress
	 */
	public function init() {
		$this->hooks();
	}

	/**
	 * Registers WordPress actions
	 */
	private function hooks() {
		add_action( 'pre_asset_serve', array( $this, 'asset_active_check' ), 1, 1 ); # check asset criteria
	}

	/**
	 * Checks assets settings if should be served
	 *
	 * @param $asset_id
	 */
	public function asset_active_check( $asset_id ) {
		$asset = new Assets_Manager_Asset( $asset_id );

		if ( ! $asset->is_asset_attachment() ) {
			return;
		}

		if ( ! $asset->can_serve_asset() ) {
			$this->no_serve_message();
		}

		if ( $asset->requires_login() ) {
			wp_redirect( wp_login_url( $asset->get_permalink() ) );
			exit();
		}
	}

	/**
	 * Filter to update, change asset's no_serve message
	 */
	protected function no_serve_message() {
		echo apply_filters( 'asset_no_serve_message', __( 'This file has expired.' ) );
		exit();
	}
}