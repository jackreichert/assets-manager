<?php

class Assets_Manager_Admin {
	private $plugin_url;

	/**
	 * Assets_Manager_Admin constructor.
	 */
	public function __construct() {
		$this->plugin_url = plugins_url() . '/assets-manager';
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
		add_action( 'add_meta_boxes', array( $this, 'assets_manager_register_meta_box' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'load_admin_scripts' ) );
	}

	/**
	 * Add meta boxes to asset type post edit page
	 */
	public function assets_manager_register_meta_box() { # meta box on plupload page
		add_meta_box( 'upload_assets', __( 'Upload Assets', 'upload_assets_textdomain' ), array(
			$this,
			'assets_manager_upload_meta_box'
		), 'asset', 'normal' );
		add_meta_box( 'attached_assets', __( 'Attached Assets', 'attached_assets_textdomain' ), array(
			$this,
			'assets_manager_attached_meta_box'
		), 'asset', 'normal' );
	}

	/**
	 * Upload box
	 */
	public function assets_manager_upload_meta_box() { ?>
		<button class="button button-large asset-button" id="asset_select_button">Select Files</button>
		<ul id="filelist" class="assets"></ul>
		<button class="button button-primary button-large asset-button hide" id="asset_attach_button" style="display: none;">Attach Files</button>
		<?php
	}

	/**
	 * Meta box containing attachments and settings
	 */
	public function assets_manager_attached_meta_box() {
		global $post;
		$attachments = $this->get_asset_attachments_ordered( $post->ID ); ?>
		<div class="assets">
			<ul>
				<?php foreach ( $attachments as $i => $attach ) :
					$asset = new Assets_Manager_Asset( $attach->ID );
					$expires_val = $this->get_expires_val( $asset->get_meta( 'expires' ) ); ?>
					<li id="asset_<?php echo $asset->get_meta( 'id' ); ?>" class="asset">
						<div class="niceName">
							<input type="text" disabled="disabled" value="<?php echo $asset->get_meta( 'title' ); ?>" class="assetVal">.<span class="fileExt"><?php echo $asset->get_meta( 'extension' ); ?></span>
						</div>
						<hr>
						<div class="assetMeta">
							<p>When should this file expire?
							<span class="expires">
								<input type="number" disabled="disabled" value="<?php echo $expires_val; ?>" class="<?php echo ( 'never' === $asset->get_meta( 'expires' ) ) ? 'hidden' : ''; ?> timeLen assetVal">
								<select class="timeTerm assetVal" disabled="disabled">
									<?php echo $this->build_timeTerm_options( $asset->get_meta( 'expires' ) ); ?>
								</select>
							</span>
								<i<?php echo( ( $asset->has_expired() ) ? ' class="expired"' : '' ); ?>>(<?php echo $asset->get_expiry_date(); ?>)</i>
								<input type="hidden" name="base_date" class="baseDate" value="<?php echo $asset->get_meta( 'base_date' ); ?>">
							</p>
							<p>
								<span>Secure this file? <input class="secureFile assetVal" type="checkbox" disabled="disabled" <?php checked( true, $asset->is_secure() ); ?>></span>
								<span>Enable this file? <input class="enableFile assetVal" type="checkbox" disabled="disabled" <?php checked( true, $asset->is_enabled() ); ?>></span>
							</p>
							<p class="linkElem">Link:
								<input class="assetLink" readonly="readonly" type="text" value="<?php echo ( 'publish' == $post->post_status ) ? $asset->get_permalink() : 'Please publish to activate links'; ?>">
								<?php if ( 'publish' == $post->post_status ): ?>
									<a href="<?php echo $asset->get_permalink(); ?>" target="_BLANK">view</a>
								<?php endif; ?>
							</p>
							<div class="assetHits">Hits: <?php echo $asset->get_meta( 'hits' ); ?></div>
						</div>
						<span class="edit corner" title="remove">edit</span>
					</li>
				<?php endforeach; ?>
			</ul>
		</div>
		<?php
	}

	/**
	 * Gets attachments for asset posttype ordered by set order
	 *
	 * @param $post_id
	 *
	 * @return array
	 */
	private function get_asset_attachments_ordered( $post_id ) {
		$attachments = get_posts( array(
				'post_parent'    => $post_id,
				'post_type'      => 'attachment',
				'order'          => 'ASC',
				'orderby'        => 'meta_value_num',
				'meta_key'       => 'order',
				'posts_per_page' => - 1
			) );

		return $attachments;
	}

	/**
	 * Extracts time unit value from set value
	 *
	 * @param $asset
	 *
	 * @return mixed
	 */
	public function get_expires_val( $expires ) {
		$expires_val = current( explode( ' ', $expires ) );

		return $expires_val;
	}

	/**
	 * Builds option for select with time unit
	 *
	 * @param $expires
	 *
	 * @return string
	 */
	private function build_timeTerm_options( $expires ) {
		$selected_unit = $this->get_expires_unit( $expires );
		$units         = array(
			'day'   => 'Day(s)',
			'week'  => 'Week(s)',
			'month' => 'Month(s)',
			'year'  => 'Year(s)',
			'never' => 'Never'
		);
		$options       = '';

		foreach ( $units as $unit => $label ) {
			$options .= '<option ' . selected( $selected_unit, $unit, false ) . 'value="' . $unit . '">' . $label . '</option>';
		}

		return $options;
	}

	/**
	 * Extracts time unit from set value
	 *
	 * @param $expires
	 *
	 * @return mixed
	 */
	private function get_expires_unit( $expires ) {
		$selected_unit = rtrim( end( explode( ' ', $expires ) ), 's' );

		return $selected_unit;
	}

	/**
	 * Enqueues scripts for page
	 */
	public function load_admin_scripts() {
		global $post;
		if ( is_admin() && is_object( $post ) && 'asset' === $post->post_type ) {
			wp_enqueue_script( 'wp_assets', $this->plugin_url . '/js/assets-manager.js', array(
				'jquery',
				'jquery-ui-sortable',
				'plupload-all'
			), 201510 );
			wp_enqueue_style( 'wp_assets_admin', $this->plugin_url . '/css/assets-manager.css' );
			wp_localize_script( 'wp_assets', 'AM_Ajax', array(
					'ajaxurl' => admin_url( 'admin-ajax.php' ),
					'amNonce' => wp_create_nonce( 'update-amNonce' )
				) );
		}
	}

}