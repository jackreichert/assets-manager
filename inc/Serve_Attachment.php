<?php

class Assets_Manager_Serve_Attachment {
	public $attachment_id;
	private $attachment_title;
	private $path;
	private $post_type;

	/**
	 * Assets_Manager_Serve_Attachment constructor.
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
		add_action( 'wp', array( $this, 'main' ), 1 );
	}

	/**
	 * Checks via action if file should be served and serves file
	 *
	 * @return bool
	 */
	public function main() {
		global $wp_query;

		if ( is_admin() || 'attachment' != $wp_query->posts[0]->post_type || 'asset' != get_post_type( $wp_query->posts[0]->post_parent ) ) {
			return;
		}

		$this->setup_vars( $wp_query );

		// checks to see if asset is active, tie in plugins can hook here
		do_action( 'pre_asset_serve', $this->attachment_id );

		if ( headers_sent() ) {
			die( 'Headers Sent' );
		}

		if ( $this->path && is_readable( $this->path ) ) {
			$this->serve_file();
		} else {
			$wp_query->set_404();
			status_header( 404 );
			get_template_part( 404 );
			exit();
		}
	}

	/**
	 * Sets up varables needed to serve file
	 */
	private function setup_vars( $wp_query ) {
		$this->attachment_id    = $wp_query->posts[0]->ID;
		$this->attachment_title = $wp_query->posts[0]->post_title;
		$this->post_type        = $wp_query->posts[0]->post_type;
		$this->path             = get_attached_file( $this->attachment_id );
	}

	/**
	 * Serves the file
	 */
	public function serve_file() {
		$filetype            = wp_check_filetype( basename( $this->path ) );
		$content_disposition = $this->determine_content_disposition( $filetype );
		$this->send_headers( $filetype, $content_disposition );

		ob_clean();
		flush();

		$handle = fopen( $this->path, "rb" );
		while ( ! feof( $handle ) ) {
			echo fread( $handle, 512 );
		}
		fclose( $handle );

		exit();
	}

	/**
	 * If Microsoft attachment, should serve as attachment
	 *
	 * @param $filetype
	 *
	 * @return string
	 */
	public function determine_content_disposition( $filetype ) {
		if ( strpos( $filetype['type'], 'msword' ) > 0 || strpos( $filetype['type'], 'ms-excel' ) || strpos( $filetype['type'], 'officedocument' ) ) {
			$content_disposition = 'attachment';
		} else {
			$content_disposition = 'inline';
		}

		return $content_disposition;
	}

	/**
	 * Sends headers
	 *
	 * @param $filetype
	 * @param $content_disposition
	 */
	public function send_headers( $filetype, $content_disposition ) {
		header( "HTTP/1.1 200 OK" );
		header( "Pragma: public" );
		header( "Expires: 0" );
		header( "Cache-Control: must-revalidate, post-check=0, pre-check=0" );
		header( "Cache-Control: private", false );
		header( "Content-Description: File Transfer" );
		header( "Content-Type: " . $filetype['type'] );
		header( 'Content-Disposition: ' . $content_disposition . '; filename="' . $this->attachment_title . "." . $filetype['ext'] . '"' );
		header( "Content-Transfer-Encoding: binary" );
		header( "Content-Length: " . (string) ( filesize( $this->path ) ) );
	}

	/**
	 * Get's filename and path
	 *
	 * @return array
	 */
	public function get_file_location() {
		$upload_dir = wp_upload_dir();
		$filepath   = get_post_meta( $this->attachment_id, '_wp_attached_file', true );
		$path       = $upload_dir['basedir'] . '/' . $filepath;
		$filename   = end( explode( '/', $filepath ) );

		return array( $path, $filename );
	}
}
