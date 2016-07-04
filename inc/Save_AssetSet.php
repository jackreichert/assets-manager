<?php

class Assets_Manager_Save_Asset_Set {
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
		add_action( 'save_post', array( $this, 'save_assetset' ) );
	}

	/**
	 * On page update, saves
	 *
	 * @param $post_id
	 */
	public function save_assetset( $post_id ) {
		if ( 'asset' !== get_post_type( $post_id ) ) {
			return;
		}

		$post      = get_post( $post_id );
		$meta_hash = get_post_meta( $post->ID, 'hash', true );

		if ( '' === $meta_hash || '' === $post->post_name ) {
			$post_hash = $this->get_new_post_hash( $post );
			$this->update_post_hash( $post, $post_hash );
		}

	}

	/**
	 * Generates short hash for post_name
	 *
	 * @param $post
	 *
	 * @return string
	 */
	private function get_new_post_hash( $post ) {
		return hash( 'CRC32', $post->ID . $post->post_title );
	}

	/**
	 * Updates the asset set hash
	 *
	 * @param $post
	 * @param $post_hash
	 */
	private function update_post_hash( $post, $post_hash ) {
		add_post_meta( $post->ID, 'hash', $post_hash, true );
		$update = array(
			'ID'        => $post->ID,
			'post_name' => $post_hash
		);
		wp_update_post( $update );
	}

}