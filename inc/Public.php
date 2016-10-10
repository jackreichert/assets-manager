<?php

class Assets_Manager_Public {
	/**
	 * Assets_Manager_Public constructor.
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
		add_filter( 'the_content', array( $this, 'append_attachments_to_content' ) );
	}

	/**
	 * Appends list of attachments to content
	 *
	 * @param $content
	 *
	 * @return string
	 */
	public function append_attachments_to_content( $content ) {
		global $post;
		if ( 'asset' === $post->post_type ) {
			$attachments = $this->get_enabled_attachments( $post->ID );
			$content .= $this->format_attachments_as_list( $attachments );
		}

		return $content;
	}

	/**
	 * Gets list of enabled attachments
	 *
	 * @param $post_id
	 *
	 * @return array
	 */
	private function get_enabled_attachments( $post_id ) {
		$attachments = get_posts(
			array(
				'post_parent'    => $post_id,
				'post_type'      => 'attachment',
				'meta_query'     => array(
					array(
						'key'     => 'enabled',
						'value'   => 'true',
						'compare' => 'IN'
					)
				),
				'order'          => 'ASC',
				'orderby'        => 'meta_value_num',
				'meta_key'       => 'order',
				'posts_per_page' => - 1
			)
		);

		return $attachments;
	}

	/**
	 * Formats attachments as an unordered list
	 *
	 * @param $attachments
	 *
	 * @return string
	 */
	private function format_attachments_as_list( $attachments ) {
		$content = '<hr><ul class="assets-list">';
		foreach ( $attachments as $i => $attach ) {
			$asset = new Assets_Manager_Asset( $attach->ID );
			if ( $asset->can_serve_asset() ) {
				$content .= '<li><a href="' . $asset->get_meta( 'link' ) . '" target="_BLANK">' . $asset->get_meta( 'title' ) . '</a> <i>(' . $asset->get_meta( 'extension' ) . ')</i></li>';
			}
		}
		$content .= '</ul>';

		return $content;
	}
}