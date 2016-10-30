<?php

class Assets_Manager_Update_Asset {

	/**
	 * Assets_Manager_Update_Asset constructor.
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
		add_action( 'wp_ajax_attach_asset', array( $this, 'update_asset_action_func' ) );
		add_action( 'wp_ajax_update_asset', array( $this, 'update_asset_action_func' ) );
		add_action( 'wp_ajax_trash_asset', array( $this, 'trash_asset_action_func' ) );
		add_action( 'wp_ajax_order_assets', array( $this, 'order_assets_action_func' ) );
		add_action( 'wp_ajax_parent_asset', array( $this, 'parent_asset_action_func' ) );
	}

	/**
	 * Attaches files to asset post
	 */
	public function update_asset_action_func() {
		$this->check_nonce();

		$asset_meta = $_POST['meta'];

		$post_id = intval( $_POST['post_id'] );

		if ( filter_var( $_POST['isNew'], FILTER_VALIDATE_BOOLEAN ) ) {
			$this->save_draft( $post_id );
		}

		$should_duplicate = filter_var( $_POST['duplicate'], FILTER_VALIDATE_BOOLEAN );
		if ( $this->should_duplicate_asset_post( $should_duplicate, $post_id, $asset_meta['id'] ) ) {
			$asset_meta['id'] = $this->duplicate_attachment_post( $asset_meta['id'], $post_id );
		}

		$this->update_asset_values( $asset_meta, $post_id, $should_duplicate );

		$response = $this->build_response( $asset_meta );

		$this->return_response( $response );
	}

	/**
	 * Checks nonce from ajax call
	 */
	public function check_nonce() {
		if ( ! wp_verify_nonce( $_POST['amNonce'], 'update-amNonce' ) ) {
			die ( 'Busted!' );
		}
	}

	/**
	 * @param $post_id
	 */
	private function save_draft( $post_id ) {
		$new_post = array(
			'ID'        => $post_id,
			'post_type' => 'asset'
		);

		wp_insert_post( $new_post );
	}

	/**
	 * @param $should_duplicate
	 * @param $post_id
	 * @param $attach_id
	 *
	 * @return bool
	 */
	private function should_duplicate_asset_post( $should_duplicate, $post_id, $attach_id ) {
		return $should_duplicate || $this->has_parent_but_not_current_post( $post_id, $attach_id );
	}

	/**
	 * @param $post_id
	 * @param $attach_id
	 *
	 * @return bool
	 */
	private function has_parent_but_not_current_post( $post_id, $attach_id ) {
		return 0 < wp_get_post_parent_id( $attach_id ) && $post_id !== wp_get_post_parent_id( $attach_id );
	}

	/**
	 * @param $attach_id
	 * @param $parent_post_id
	 *
	 * @return int
	 */
	private function duplicate_attachment_post( $attach_id, $parent_post_id ) {
		$source_attachment = get_post( $attach_id );

		if ( $source_attachment->post_parent === $parent_post_id && ! Assets_Manager_Asset::is_asset( $source_attachment->ID ) ) {
			$this->detach_post_from_parent( $source_attachment );
		}

		$filename = get_attached_file( $attach_id );
		$filetype = wp_check_filetype( basename( $filename ), null );

		// Get the path to the upload directory.
		$wp_upload_dir = wp_upload_dir();

		// Prepare an array of post data for the attachment.
		$attachment = array(
			'guid'           => $wp_upload_dir['url'] . '/' . basename( $filename ),
			'post_mime_type' => $filetype['type'],
			'post_title'     => $source_attachment->post_title,
			'post_content'   => '',
			'post_status'    => 'inherit'
		);

		$attach_id = wp_insert_attachment( $attachment, $filename, $parent_post_id );
		$this->update_attachment_metadata( $attach_id, $filename );

		return $attach_id;
	}

	/**
	 * @param $source_attachment
	 */
	private function detach_post_from_parent( $source_attachment ) {
		$detached_source = array(
			'ID'          => $source_attachment->ID,
			'post_parent' => 0
		);
		wp_update_post( $detached_source );
	}

	/**
	 * @param $attach_id
	 * @param $filename
	 */
	private function update_attachment_metadata( $attach_id, $filename ) {
		// Make sure that this file is included, as wp_generate_attachment_metadata() depends on it.
		require_once( ABSPATH . 'wp-admin/includes/image.php' );

		// Generate the metadata for the attachment, and update the database record.
		$attach_data = wp_generate_attachment_metadata( $attach_id, $filename );
		wp_update_attachment_metadata( $attach_id, $attach_data );
	}

	/**
	 * @param $asset_meta
	 * @param $post_id
	 * @param $should_duplicate
	 */
	private function update_asset_values( $asset_meta, $post_id, $should_duplicate ) {
		$asset = new Assets_Manager_Asset( $asset_meta['id'] );
		$asset->update_meta_values( $asset_meta );
		$asset->update_title( $asset_meta['name'], $post_id );

		$this->update_asset_filename( $asset->get_meta( 'id' ), $asset->get_meta( 'hash' ), $should_duplicate );
	}

	/**
	 * Renames asset file on update
	 *
	 * @param      $asset_id
	 * @param      $asset_hash
	 * @param bool $duplicate
	 */
	private function update_asset_filename( $asset_id, $asset_hash, $duplicate = false ) {
		$path     = get_attached_file( $asset_id );
		$pathinfo = pathinfo( $path );
		$newfile  = $pathinfo['dirname'] . "/" . $asset_hash;

		$changed = false;
		if ( $duplicate ) {
			copy( $path, $newfile );
			$changed = true;
		} else {
			if ( $path !== $newfile ) {
				rename( $path, $newfile );
				$changed = true;
			}
		}

		if ( $changed ) {
			update_attached_file( $asset_id, $newfile );
		}
	}

	/**
	 * Builds ajax response
	 *
	 * @param $asset_meta
	 *
	 * @return array
	 */
	private function build_response( $asset_meta ) {
		$asset                     = new Assets_Manager_Asset( $asset_meta['id'] );
		$asset_meta['has_expired'] = $asset->has_expired();
		$asset_meta['expiry_date'] = $asset->get_expiry_date();

		$response = array(
			'post_vals'  => array(
				'url' => $asset->get_meta( 'link' )
			),
			'asset_vals' => $asset_meta
		);

		return $response;
	}

	/**
	 * Sends ajax response
	 *
	 * @param $response
	 */
	private function return_response( $response ) {
		header( 'Content-Type: application/json' );
		echo json_encode( $response );
		exit();
	}

	/**
	 * Disconnects file from asset post
	 */
	public function trash_asset_action_func() {
		$this->check_nonce();

		$update_asset = array(
			'ID'          => intval( $_POST['id'] ),
			'post_parent' => 0
		);
		wp_update_post( $update_asset );


		$this->return_response( 'success' );
	}

	/**
	 * Ajax call to process reordering of asset attachments
	 */
	public function order_assets_action_func() {
		$this->check_nonce();

		foreach ( $_POST['order'] as $order => $id ) {
			delete_post_meta( $id, 'order' );
			add_post_meta( $id, 'order', $order, true );
		}

		$this->return_response( $_POST['order'] );
	}

	/**
	 * Checks if asset is already linked.
	 */
	public function parent_asset_action_func() {
		$this->return_response( wp_get_post_parent_id( $_POST['ID'] ) );
	}

}