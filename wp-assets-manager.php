<?php 
/*
	Plugin Name: Assets Manager for WordPress
	Plugin URI: http://www.jackreichert.com/2014/01/12/introducing-assets-manager-for-wordpress/
	Description: Plugin creates an assets manager. Providing a self hosted file sharing platfrom.
	Version: 0.2
	Author: Jack Reichert
	Author URI: http://www.jackreichert.com
	License: GPL2
*/

$wp_assets_manager = new wp_assets_manager();

class wp_assets_manager {
	private $meta_keys;

	/* 
	 * Assets Manager class construct
	 */
	public function __construct() {
		register_activation_hook( __FILE__, array( $this, 'wp_assets_manager_activate') ); # plugin activation
		register_deactivation_hook( __FILE__, array( $this, 'wp_assets_manager_deactivate') ); # plugin deactivation
		
		add_action( 'wp_head', array( $this, 'check_url') ); # serve the file
		
		add_action( 'init', array( $this, 'create_uploaded_files') ); # creates custom post type `assets`
		add_action( 'add_meta_boxes', array( $this, 'assets_manager_register_meta_box') ); # creates meta for uploading, managing assets
		add_action( 'admin_enqueue_scripts', array( $this, 'load_admin_scripts') ); # load admin js
		add_action( 'wp_ajax_update_asset', array( $this, 'update_asset_action_func') ); # plupload ajax function_exists
		add_action( 'wp_ajax_trash_asset', array( $this, 'trash_asset_action_func' ) ); # detach asset from post
		add_action( 'wp_ajax_order_assets', array( $this, 'order_assets_action_func' ) ); # detach asset from post		
		add_action( 'pre_asset_serve', array( $this, 'asset_active_check'), 10, 1 ); # check asset criteria
		add_filter( 'the_content', array( $this, 'single_asset_content') ); # list files on single
		add_action( 'save_post', array( $this, 'save_assetset' ) ); # makes sure post_name is the hash
		
		$this->meta_keys = array('expires', 'secure', 'order', 'enabled', 'base_date', 'ext', 'order');
	}


	


	/* 
	 * Plugin activation
	 */
	public function wp_assets_manager_activate() {		
		global $wpdb;
		$table_name = $wpdb->prefix . "assets_log"; 
		$sql = "CREATE TABLE $table_name (
			id int(11) NOT NULL AUTO_INCREMENT,
			last_accessed TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,			
			uID VARCHAR(7) NOT NULL DEFAULT 0,
			aID int(11) NOT NULL DEFAULT 0,
			count int(11) NOT NULL DEFAULT 0,
			UNIQUE KEY id (id)
		);";

		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		dbDelta( $sql );	
		// schedule log cron
	}
	
	private function log_asset($aID) {
		
		global $wpdb;
		$uID = get_current_user_id();
		$table_name = $wpdb->prefix . "assets_log"; 
		
		$query = $wpdb->prepare("SELECT count FROM $table_name WHERE aID = $aID AND uID = $uID;");
		$result = $wpdb->get_results($query, ARRAY_A);
		
		if (0 == count($result)) {
			$result = $wpdb->insert($table_name, 
				array( 'uID' => $uID, 'aID' => $aID, 'count' => 1 ), 
				array( '%s', '%s', '%d' )
			);	
		} else {
			$count = (isset($result[0]['count'])) ? intval($result[0]['count']) + 1 : 1;
			echo $result[0]['count'];
			$wpdb->update( 
				$table_name, 
				array( 'count' => $count ), 
				array( 'uID' => $uID, 'aID' => $aID ), 
				array( '%d', '%d' ), 
				array( '%d' ) 
			);
		}

	}

	/* 
	 * Plugin deactivation
	 */
	public function wp_assets_manager_deactivate() {
		// remove log cron
	}

	/* 
	 * When: Pre getting post headers
	 * What: Checks to see if file fits requirements, serves file
	 */	
	public function check_url() {  
		global $wpdb, $wp_query;

		// skip if not asset file
		if (!$wp_query->is_attachment || get_post_type($wp_query->posts[0]->post_parent) != 'asset') {  
			return false;  
		} 

		
		// get attachment id
		$asset_id = $wp_query->posts[0]->ID;
		
		// checks to see if asset is active, tie in plugins can hook here
		do_action ('pre_asset_serve', $asset_id);
		
		$this->log_asset($asset_id);

		if( headers_sent() ) {
			die('Headers Sent'); 
		}
		
		$path = get_attached_file($asset_id);
		$filename = $wp_query->posts[0]->post_title;
		$ext = get_post_meta($asset_id, 'ext', true);
		$filesize = filesize($path); 

		switch($ext){
			case 'xlsx':
				$mm_type = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
				break;
			case 'docx':
				$mm_type = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
				break;
			default:
				$mm_type = ($wp_query->posts[0]->post_mime_type == '') ? 'text/plain' : $wp_query->posts[0]->post_mime_type;
		}

		header("HTTP/1.1 200 OK");
		header("Pragma: public");
		header("Expires: 0");
		header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
		header("Cache-Control: private",false);
		header("Content-Description: File Transfer");
		header("Content-Type: ".$mm_type);
		if (strpos($mm_type,'msword') > 0 || strpos($mm_type,'ms-excel') || strpos($mm_type,'officedocument'))
			header('Content-Disposition: attachment; filename="'.$filename.'"');
		else 
			header('Content-Disposition: inline; filename="'.$filename.'"');
		header("Content-Transfer-Encoding: binary");
		header("Content-Length: " .(string) $filesize );
			
		ob_clean();
		flush();
		readfile($path); 

		exit();
		
	}
	
	public function asset_active_check($asset_id) {
		
		if ( !$this->is_published($asset_id) ) {
			echo 'This file has expired.';
			exit();
		}
		
		if (!$this->is_enabled($asset_id)) {
			echo 'This file has expired.';
			exit();
		}
		
		if ($this->has_expired($asset_id)) {
			echo 'This file has expired.';
			exit();
		}
		
		if ($this->requires_login($asset_id)) {
			wp_redirect(wp_login_url($this->get_asset_link($asset_id)));
			exit();
		}
	}
	
	private function is_published($asset_id){
		$asset = get_post($asset_id);
		$assetset = get_post($asset->post_parent);
		
		return ( 'publish' === $assetset->post_status );
	}
	
	private function is_enabled($asset_id) {
		return ('true' === get_post_meta($asset_id, 'enabled', true));
	}
	
	private function has_expired($asset_id) {
		$expires = get_post_meta($asset_id, 'expires', true);
		// it never expires
		if ('never' === $expires) {
			return false;
		}
		
		// now is before the expiration date
		$date = date_create(get_post_meta($asset_id, 'base_date', true));
		date_add($date, date_interval_create_from_date_string($expires));
		if (date_format($date, 'U') < date('U')) {
			return true;			
		}
		return false;
	}
	
	private function get_expiry_date($asset_id, $format = 'Y-m-d') {
		$expires = get_post_meta($asset_id, 'expires', true);
		
		if ('never' === $expires) {
			return $expires;
		}

		$date = date_create(get_post_meta($asset_id, 'base_date', true));
		date_add($date, date_interval_create_from_date_string($expires));
		
		return date_format($date, $format);
	}	
	
	private function requires_login($asset_id) {
		return ('true' === get_post_meta($asset_id, 'secure', true) && !is_user_logged_in());
	}
	
	public function create_uploaded_files() { # creates custom post type `uploaded_files`
		register_post_type( 'asset',
			array( 
				'labels' => array( 
					'name' => __( 'Assets Manager' ), 
					'singular_name' => __( 'Assets Set' )
				),
				'public' => true,
				'menu_position' => 10,
				'exclude_from_search' => true,
				'show_in_menu' => true,
				'supports' => array('title','thumbnail'),
				'taxonomies' => array('category','post_tag')
			)
		);
	}
	
	public function assets_manager_register_meta_box() { # meta box on plupload page
		add_meta_box( 'upload_assets', __( 'Upload Assets', 'upload_assets_textdomain' ), array($this, 'assets_manager_upload_meta_box'), 'asset', 'normal' );
		add_meta_box( 'attached_assets', __( 'Attached Assets', 'attached_assets_textdomain' ), array($this, 'assets_manager_attached_meta_box'), 'asset', 'normal' );
	}
	
	
	
	public function assets_manager_upload_meta_box() { 
		global $post;
		media_upload_form(); ?>		
		<div id="filelist" class="assets"></div>
		<button id="upload_asset" class="button button-large hidden">Upload</button>
		<input type="hidden" name="post_id" value="<?php echo $post->ID; ?>">
		<input type="hidden" name="post_url" value="<?php echo $post->post_name; ?>">
<?php		
	}
	
	public function assets_manager_attached_meta_box() {
		global $post;
		$attachments = get_posts(array(
			'post_parent' 	=> $post->ID,
			'post_type'		=> 'attachment',
			'order'			=> 'ASC',
			'orderby'		=> 'meta_value_num',
			'meta_key'		=> 'order',
			'posts_per_page' => -1
		)); ?>
		<div class="assets">
			<ul>
				<?php foreach ($attachments as $i => $asset) : 
						
						// get stats
						global $wpdb;
						$aID = $asset->ID;
						$table_name = $wpdb->prefix . "assets_log"; 
						$query = $wpdb->prepare("SELECT SUM(count) as hits FROM $table_name WHERE aID = %d;", $aID);
						$stats = current($wpdb->get_results($query, ARRAY_A));
				
						// prepare meta vals
						$meta_vals = $this->get_asset_values($asset->ID); 
						$expires = $meta_vals['expires']; 
						$link = $this->get_asset_link($asset->ID); ?>
					<li id="<?php echo $asset->ID; ?>" class="asset">
						<div class="niceName">
							<input type="text" disabled="disabled" value="<?php echo $asset->post_title; ?>" class="assetVal">.<span class="fileExt"><?php echo $meta_vals['ext']; ?></span>
						</div>
						<hr>
						<div class="assetMeta">
							When should this file expire? <span class="expires"> <input type="number" disabled="disabled" value="<?php echo $expires[0]; ?>" class="<?php echo ($expires[0] == 0) ? 'hidden' : ''; ?> timeLen assetVal">
								<select class="timeTerm assetVal" disabled="disabled">
									<option <?php echo (strpos($expires, 'day') !== false) ? 'selected="selected"' : ''; ?> value="day">Day(s)</option>
									<option <?php echo (strpos($expires, 'week') !== false) ? 'selected="selected"' : ''; ?> value="week">Week(s)</option>
									<option <?php echo (strpos($expires, 'month') !== false) ? 'selected="selected"' : ''; ?> value="month">Month(s)</option>
									<option <?php echo (strpos($expires, 'year') !== false) ? 'selected="selected"' : ''; ?> value="year">Year(s)</option>
									<option <?php echo (strpos($expires, 'never') !== false) ? 'selected="selected"' : ''; ?> value="never">Never</option>
								</select>
							</span> <i<?php echo (($this->has_expired($asset->ID)) ? ' class="expired"' : ''); ?>>(<?php echo $this->get_expiry_date($asset->ID); ?>)</i><br>
							Secure this file? <input class="secureFile assetVal" type="checkbox" disabled="disabled" <?php echo ('true' === $meta_vals['secure']) ? 'checked="checked"' : ''; ?><br>
							Enable this file? <input class="enableFile assetVal" type="checkbox" disabled="disabled" <?php echo ('true' === $meta_vals['enabled']) ? 'checked="checked"' : ''; ?>><br>
							Link: <input class="assetLink" readonly="readonly" type="text" value="<?php echo ('publish' == $post->post_status) ? $link : 'Please publish to activate links'; ?>"> 
							<input type="hidden" name="base_date" class="baseDate" value="<?php echo $meta_vals['base_date']; ?>">
							<?php if ('publish' == $post->post_status): ?>
								<a href="<?php echo $link; ?>" target="_BLANK">view</a>
							<?php endif; ?> <div class="assetHits">Hits: <?php echo is_null($stats['hits']) ? '0' : $stats['hits']; ?></div>
						</div><span class="edit corner" title="remove">edit</span>
					</li>
				<?php endforeach; ?>
			</ul>
		</div>
<?php
	}
	
	private function get_asset_link ($asset_id) {
		$link = get_permalink($asset_id);
		return preg_replace('{/$}', '', substr_replace($link, '.', strrpos($link, '-'), strlen('-')));
	}
	
	public function load_admin_scripts() {
		global $post; 
		if (is_admin() && is_object($post) && 'asset' === $post->post_type) {
			wp_enqueue_script( 'jquery' );
			wp_enqueue_script('jquery-ui-sortable');
			wp_enqueue_script( 'plupload-all', array('jquery') );
			wp_enqueue_script( 'wp_assets', plugin_dir_url( __FILE__ ) . '/js/wp-assets-manager.js' );
			wp_enqueue_style( 'wp_assets_admin', plugin_dir_url( __FILE__ ) . '/css/wp-assets-manager.css' );
			wp_localize_script( 'wp_assets', 'AM_Ajax', array(
		        'ajaxurl'       => admin_url( 'admin-ajax.php' ),
		        'amNonce'     => wp_create_nonce( 'update-amNonce' ))
		    );
		}
	}
	
	public function update_asset_action_func() { 
		// checks nonce
		$nonce = $_POST['amNonce']; 	
		if ( ! wp_verify_nonce( $nonce, 'update-amNonce' ) ) {
			die ( 'Busted!');
		}
		
		$post_vals = $_POST['vals']['post'];
		$current_post = get_post($post_vals['ID']);
		$post_hash = get_post_meta($post_vals['ID'], 'hash', true);
		$post_hash = (('' !== $post_hash) ? $post_hash : hash('CRC32', $current_post->ID.$post_vals['title']));
		add_post_meta($post_vals['ID'], 'hash', $post_hash, true);
		$update_post = array(
			'ID' 			=> $current_post->ID,
			'post_title' 	=> $post_vals['title'],
			'post_name'		=> $post_hash,
			'post_status'	=> ('auto-draft' === $current_post->post_status) ? 'draft' : $current_post->post_status
		);
		wp_update_post($update_post);
		
		$asset_vals = $_POST['vals']['asset'];
		$current_asset = get_post($asset_vals['ID']);
		$asset_hash = get_post_meta($current_asset->ID, 'hash', true);
		$asset_hash = (('' !== $asset_hash) ? $asset_hash : hash('CRC32', $current_asset->ID.$current_asset->post_title).'.'.substr(strrchr($current_asset->guid,'.'),1));
		add_post_meta($current_asset->ID, 'hash', $asset_hash, true);
		
		$path = get_attached_file($asset_vals['ID']);
		$pathinfo = pathinfo($path);
		$newfile = $pathinfo['dirname']."/".$asset_hash;
	    rename($path, $newfile);    
	    update_attached_file( $asset_vals['ID'], $newfile );
				
		$update_asset = array(
			'ID'			=> $current_asset->ID,
			'post_title'	=> $asset_vals['name'],
			'post_name' 	=> $asset_hash
		);
		wp_update_post($update_asset);
		
		$meta_vals = $this->update_asset_values($asset_vals);
		
		$asset_vals['has_expired'] = $this->has_expired($current_asset->ID);
		$asset_vals['expiry_date'] = $this->get_expiry_date($current_asset->ID);
		
		$response = array( 'post_vals' => array( 'post_name' => $update_post['post_name'], 'url' => $this->get_asset_link($current_asset->ID), 'post_status' => $update_post['post_status']), 'asset_vals' => $asset_vals );
		
		header('Content-Type: application/json');
		echo json_encode($response);
		exit();
	}
	
	public function trash_asset_action_func() { 
		// checks nonce
		$nonce = $_POST['amNonce']; 	
		if ( ! wp_verify_nonce( $nonce, 'update-amNonce' ) ) {
			die ( 'Busted!');
		}
		
		$update_asset = array(
			'ID'			=> $_POST['ID'],
			'post_parent'	=> 0
		);
		wp_update_post($update_asset);
		
		header('Content-Type: application/json');
		echo json_encode('success');
		exit();
	}
	
	public function order_assets_action_func() {
		// checks nonce
		$nonce = $_POST['amNonce']; 	
		if ( ! wp_verify_nonce( $nonce, 'update-amNonce' ) ) {
			die ( 'Busted!');
		}
		
		foreach($_POST['order'] as $order => $id) {
			delete_post_meta($id, 'order');
			add_post_meta($id, 'order', $order, true);
		}
		
		header('Content-Type: application/json');
		echo json_encode($_POST['order']);
		exit();
	}
	
	public function single_asset_content($content) {
		global $post;
		$attachments = get_posts(array(
			'post_parent' 	=> $post->ID,
			'post_type'		=> 'attachment',
			'meta_query' => array (
				array (
					'key' => 'enabled',
					'value' => 'true',
					'compare' => 'IN'
				)),
			'order'			=> 'ASC',
			'orderby'		=> 'meta_value_num',
			'meta_key'		=> 'order',
			'posts_per_page' => -1
		));
		
		$content .= '<hr><ul>';
		foreach ($attachments as $i => $asset) {
			if ( $this->is_published($asset->ID) && $this->is_enabled($asset->ID) && !$this->has_expired($asset->ID) && !$this->requires_login($asset->ID) ) {		
				$content .=	'<li><a href="' . $this->get_asset_link($asset->ID) . '" target="_BLANK">' . $asset->post_title .'</a> <i>(' . get_post_meta($asset->ID, 'ext', true) . ')</i></li>';
		}
			}
		$content .=	'</ul>';
		$content .= '<style>.nav-links { display: none; }</style>';
		
		return $content;
	}
	
	public function save_assetset($post_id) {
		if ('asset' !== get_post_type($post_id)) {
			return;
		}
		
		$post = get_post($post_id);
		
		$post_hash = (('' !== $post->post_name) ? $post->post_name : hash('CRC32', $post->ID.$post->post_title));
		$post_name = get_post_meta($post_id, 'hash', true);
		if ($post_name == '') {
			$post_name = $post_hash;
			add_post_meta($post_id, 'hash', $post_hash, true);
		}
		
		if ($post->post_name != $post_name) {
			$update = array(
				'ID' => $post_id,
				'post_name'	=> $post_name
			);
			wp_update_post($update);
		}
		
	}
	
	private function get_asset_values($aID) {
		$meta_vals = array();
		foreach ($this->meta_keys as $key) {
			$meta_vals[$key] = get_post_meta($aID, $key, true);
		}
		return $meta_vals;
	}
	
	private function update_asset_values($asset_vals) {
		$meta_vals = $this->get_asset_values($asset_vals['ID']);
		foreach ($this->meta_keys as $key) {
			delete_post_meta($asset_vals['ID'], $key);
			if (isset($asset_vals[$key]) && $asset_vals[$key] != ''){
				$meta_vals[$key] = $asset_vals[$key];
			}
			add_post_meta($asset_vals['ID'], $key, $meta_vals[$key], true);
		}
		
		return $meta_vals;
	}
	
}
		