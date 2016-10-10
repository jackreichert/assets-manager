<?php

/**
 * Class Asset
 */
class Assets_Manager_Asset {
	private $post;
	private $id;
	private $link;
	private $title;
	private $parent;
	private $extension;
	private $hits;

	// meta fields
	private $base_date;
	private $enabled;
	private $expires;
	private $hash;
	private $meta_keys;
	private $order;
	private $secure;

	/**
	 * Asset constructor.
	 *
	 * @param $asset_id
	 */
	public function __construct( $asset_id ) {
		$this->id        = $asset_id;
		$this->post      = get_post( $this->id );
		$this->link      = $this->format_asset_link();
		$this->title     = $this->post->post_title;
		$this->parent    = $this->post->post_parent;
		$this->extension = $this->get_extension();
		$this->hits      = $this->get_hits();

		$this->meta_keys = array( 'expires', 'secure', 'enabled', 'base_date', 'order', 'hash' );
		$this->get_asset_meta();
	}

	/**
	 * Reformats permalink from asset to look like an actual file
	 *
	 * @return false|string
	 */
	private function format_asset_link() {
		$link = get_permalink( $this->id );

		return rtrim( $this->replace_last_dash_with_period( $link ), '/' );
	}

	/**
	 * @return false|string
	 */
	public function get_permalink() {
		return $this->link;
	}

	/**
	 * Replaces last dash in link to period
	 *
	 * @param $link
	 *
	 * @return string
	 */
	private function replace_last_dash_with_period( $link ) {
		return strrev( implode( '.', explode( '-', strrev( $link ), 2 ) ) );
	}

	/**
	 * Gets extension for file
	 * @return mixed
	 */
	private function get_extension() {
		$attached_file = get_post_meta( $this->id, '_wp_attached_file', true );

		return pathinfo( $attached_file, PATHINFO_EXTENSION );
	}

	/**
	 * Get hit count for attachment
	 *
	 * @return mixed|string
	 */
	private function get_hits() {
		global $wpdb;
		$table_name = $wpdb->prefix . "assets_log";
		$hits       = $wpdb->get_var( $wpdb->prepare( "SELECT SUM(count) as hits FROM $table_name WHERE aID = %d;", $this->id ) );

		return is_null( $hits ) ? '0' : $hits;
	}

	/**
	 * Loads asset's meta to object
	 */
	private function get_asset_meta() {
		foreach ( $this->meta_keys as $key ) {
			$this->{$key} = get_post_meta( $this->id, $key, true );
		}
	}

	/**
	 * @param $asset_id
	 *
	 * @return bool
	 */
	public static function is_asset( $asset_id ) {
		return '' !== get_post_meta( $asset_id, 'hash', true );
	}

	/**
	 * @param $key
	 *
	 * @return bool
	 */
	public function get_meta( $key ) {
		return isset( $this->{$key} ) ? $this->{$key} : false;
	}

	/**
	 * Checks basic settings if asset should be served
	 */
	public function can_serve_asset() {
		if ( ! $this->is_published() ) {
			return false;
		}

		if ( ! $this->is_enabled() ) {
			return false;
		}

		if ( $this->has_expired() ) {
			return false;
		}

		return true;
	}

	/**
	 * Checks if post parent is set to publish
	 *
	 * @return bool
	 */
	private function is_published() {
		return ( 'publish' === get_post_status( $this->post->post_parent ) );
	}

	/**
	 * Checks if asset is enabled
	 *
	 * @return bool
	 */
	public function is_enabled() {
		return filter_var( $this->enabled, FILTER_VALIDATE_BOOLEAN );
	}

	/**
	 * Checks if asset expiration has passed
	 *
	 * @return bool
	 */
	public function has_expired() {
		// it never expires
		if ( 'never' === $this->expires ) {
			return false;
		}

		// now is before the expiration date
		$date = date_create( $this->base_date );
		date_add( $date, date_interval_create_from_date_string( $this->expires ) );
		if ( date_format( $date, 'U' ) < date( 'U' ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Checks if asset is enabled
	 *
	 * @return bool
	 */
	public function is_secure() {
		return filter_var( $this->secure, FILTER_VALIDATE_BOOLEAN );
	}

	/**
	 * Checks if instantiated post_id is really an asset attachment
	 *
	 * @return bool
	 */
	public function is_asset_attachment() {
		return 'attachment' === $this->post->post_type && 'asset' === get_post_type( $this->post->post_parent );
	}

	/**
	 * Checks if asset requires login for access
	 *
	 * @return bool
	 */
	public function requires_login() {
		return ( $this->is_secure() && ! is_user_logged_in() );
	}

	/**
	 * Translates expiration meta into date
	 *
	 * @param string $format
	 *
	 * @return bool|mixed|string
	 */
	public function get_expiry_date( $format = 'Y-m-d' ) {
		if ( 'never' === $this->expires ) {
			return $this->expires;
		}

		$date = date_create( get_post_meta( $this->id, 'base_date', true ) );
		date_add( $date, date_interval_create_from_date_string( $this->expires ) );

		return date_format( $date, $format );
	}

	/**
	 * Updates title for asset attachment
	 *
	 * @param $new_title
	 */
	public function update_title( $new_title, $post_parent ) {
		$update_asset = array(
			'ID'          => $this->id,
			'post_title'  => $new_title,
			'post_name'   => $this->hash,
			'post_parent' => $post_parent
		);

		wp_update_post( $update_asset );
	}

	/**
	 * Updates meta values for asset attachment
	 *
	 * @param $asset_meta
	 */
	public function update_meta_values( $asset_meta ) {
		$asset_meta         = $this->sanitize_vals( $asset_meta );
		$asset_meta['hash'] = $this->get_hash();
		foreach ( $this->meta_keys as $key ) {
			if ( isset( $asset_meta[ $key ] ) && $this->{$key} !== $asset_meta[ $key ] ) {
				delete_post_meta( $this->id, $key );
				$this->{$key} = $asset_meta[ $key ];
				add_post_meta( $this->id, $key, $this->{$key}, true );
			}
		}
	}

	/**
	 * @param $meta_vals
	 *
	 * @return array
	 */
	private function sanitize_vals( $meta_vals ) {
		$now       = new DateTime();
		$meta_vals = array(
			'id'        => intval( $meta_vals['id'] ),
			'name'      => sanitize_file_name( $meta_vals['name'] ),
			'ext'       => preg_replace( "/[^A-Za-z0-9?!]/", '', $meta_vals['ext'] ),
			'expires'   => preg_replace( "/[^A-Za-z0-9 ?!]/", '', $meta_vals['expires'] ),
			# keeping these strings so it can be backwards compatible
			'secure'    => filter_var( $meta_vals['secure'], FILTER_VALIDATE_BOOLEAN ) ? 'true' : 'false',
			'enabled'   => filter_var( $meta_vals['enabled'], FILTER_VALIDATE_BOOLEAN ) ? 'true' : 'false',
			'base_date' => ( $this->is_valid_date( $meta_vals['base_date'] ) ? $meta_vals['base_date'] : $now->format( 'Y-m-d' ) ),
			'order'     => intval( $meta_vals['order'] )
		);

		return $meta_vals;
	}

	/**
	 * @param $date_string
	 *
	 * @return bool
	 */
	private function is_valid_date( $date_string ) {
		return (bool) strtotime( $date_string );
	}

	/**
	 * Generates hash if it doesn't yet have one
	 *
	 * @return string
	 */
	private function get_hash() {
		return ( '' !== $this->hash ) ? $this->hash : hash( 'CRC32', $this->id . $this->post_title ) . '.' . $this->extension;
	}
}