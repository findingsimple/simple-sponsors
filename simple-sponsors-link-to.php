<?php

/* Based on pages links to plugin by Mark Jaquith http://txfx.net/wordpress-plugins/page-links-to/ */

if ( ! class_exists( 'Simple_Sponsors_Link_To' ) ) {

/**
 * So that themes and other plugins can customise the text domain, the 
 * Simple_Sponsors_Link_To should not be initialized until after the plugins_loaded and 
 * after_setup_theme hooks. However, it also needs to run early on the init hook.
 *
 * @author Jason Conroy <jason@findingsimple.com>
 * @package Simple_Sponsors_Link_To
 * @since 1.0
 */
function simple_initialize_sponsors_link_to() {
	Simple_Sponsors_Link_To::init();
}
add_action( 'init', 'simple_initialize_sponsors_link_to', -1 );


class Simple_Sponsors_Link_To {

	static $text_domain;

	static $post_type_name;

	static $links;

	static $targets;
	
	static $targets_on_this_page;

	public static function init() { 
	
		self::$text_domain = apply_filters( 'simple_sponsors_text_domain', 'Simple_Sponsors' ); 
	
		self::$post_type_name = apply_filters( 'simple_sponsors_post_type_name', 'simple_sponsor' );		

		add_action( 'add_meta_boxes', array( __CLASS__, 'add_meta_box' ) );
		
		add_action( 'save_post', array( __CLASS__, 'save_meta' ), 10, 1 );
		
		add_filter( 'post_type_link', array( __CLASS__, 'filter_link' ), 20, 2 );

		add_filter( 'wp_list_pages', array( __CLASS__, 'wp_list_pages' ) );
		
		add_action( 'template_redirect', array( __CLASS__, 'template_redirect' ) );
		
		add_filter( 'wp_nav_menu_objects', array( __CLASS__, 'wp_nav_menu_objects' ), 10, 2 );
		
		add_action( 'load-post.php', array( __CLASS__, 'load_post' ) );
		
		add_filter( 'the_posts', array( __CLASS__, 'the_posts' ) );

	}
	
	/**
	 * Returns all post ids and meta values that have a given key for a given post type
	 * @param string $post_type_name post type name
	 * @param string $key post meta key
	 * @return array an array of objects with post_id and meta_value properties
	 */
	public static function get_meta( $post_type_name, $meta_key ) {
	
		global $wpdb;

		$array = $wpdb->get_results( $wpdb->prepare( "SELECT $wpdb->posts.ID, $wpdb->postmeta.meta_value FROM $wpdb->posts JOIN $wpdb->postmeta ON $wpdb->posts.ID = $wpdb->postmeta.post_id WHERE $wpdb->posts.post_type = %s AND $wpdb->postmeta.meta_key = %s", $post_type_name, $meta_key ) );
				
		return $array;
		
	}
	
	/**
	 * Returns all sponsor url links for the current site
	 * @return array an array of links, keyed by post ID
	 */
	public static function get_links() {
	
		global $wpdb, $blog_id;
		
		//if links have already been set for this site return them otherwise get from db
		if ( !isset( self::$links[$blog_id] ) )
			$links_to = self::get_meta( self::$post_type_name , '_sponsor-url' );			
		else
			return self::$links[$blog_id];
		
		//if no links return false
		if ( !$links_to ) {
			self::$links[$blog_id] = false;
			return false;
		}
		
		//loop through links_to array adding to $links
		foreach ( (array) $links_to as $link )
			self::$links[$blog_id][$link->post_id] = $link->meta_value;
		
		//return links for the current site
		return self::$links[$blog_id];
		
	}
	
	/**
	 * Returns all targets for the current site (i.e. target="_blank")
	 * @return array an array of targets, keyed by post ID
	 */
	public static function get_targets() {
	
		global $wpdb, $links_to_target_cache, $blog_id;
						
		//if targets have already been set for this site return them otherwise get from db
		if ( !isset( self::$targets[$blog_id] ) )
			$links_to_targets = self::get_meta( self::$post_type_name ,'_sponsor-links-to-target' );
		else
			return self::$targets[$blog_id];
			
		//if no targets return false
		if ( !$links_to_targets ) {
			self::$targets[$blog_id] = false;
			return false;
		}

		//loop through links_to_targets array adding to $targets
		foreach ( (array) $links_to_targets as $link )
			self::$targets[$blog_id][$link->post_id] = $link->meta_value;

		//return targets for the current site
		return self::$targets[$blog_id];
		
	}

	/**
	 * Add the sponsor link meta box
	 *
	 * @wp-action add_meta_boxes
	 */
	public static function add_meta_box() {
		add_meta_box( 'sponsor-link', __( 'Sponsor Link', self::$text_domain  ), array( __CLASS__, 'do_meta_box' ), self::$post_type_name , 'normal', 'high' );
	}

	/**
	 * Output the sponsor link meta box HTML
	 *
	 * @param WP_Post $object Current post object
	 * @param array $box Metabox information
	 */
	public static function do_meta_box( $object, $box ) {
	
		$choice = get_post_meta( $object->ID, '_sponsor-links-to-choice', true );
				
		$target = get_post_meta( $object->ID, '_sponsor-links-to-target', true );	

		wp_nonce_field( basename( __FILE__ ), 'sponsor-link' );
?>
		<p><?php _e( 'Point this sponsor to:', self::$text_domain ); ?></p>
		<p><label><input type="radio" id="sponsor-links-to-wp" name="sponsor-links-to-choice" value="wp" <?php checked( 'wp', $choice ); ?> /> <?php _e( 'Its normal WordPress URL', self::$text_domain ); ?></label></p>
		<p><label><input type="radio" id="sponsor-links-to-sponsor-url" name="sponsor-links-to-choice" value="sponsor-url" <?php checked( 'sponsor-url', $choice  ); ?> /> <?php _e( 'The Sponsor URL', self::$text_domain ); ?></label></p>
		<div style="margin-left: 30px;" id="sponsor-links-to-sponsor-url-section" class="">
			<p><label><input type="radio" id="sponsor-links-to-self" name="sponsor-links-to-target" value="_self" <?php checked( '_self', $target ); ?> /> <?php _e( 'Open this link in the same window (default)', self::$text_domain ); ?></label></p>
			<p><label><input type="radio" id="sponsor-links-to-new-window" name="sponsor-links-to-target" value="_blank" <?php checked( '_blank', $target ); ?> /> <?php _e( 'Open this link in a new window', self::$text_domain ); ?></label></p>
		</div>		
<?php
	}

	/**
	 * Save the sponsor metadata
	 *
	 * @wp-action save_post
	 * @param int $post_id The ID of the current post being saved.
	 */
	public static function save_meta( $post_id ) {

		/* Verify the nonce before proceeding. */
		if ( !isset( $_POST['sponsor-link'] ) || !wp_verify_nonce( $_POST['sponsor-link'], basename( __FILE__ ) ) )
			return $post_id;

		$meta = array(
			'sponsor-links-to-choice',
			'sponsor-links-to-target'
		);
		
		foreach ( $meta as $meta_key ) {
			$new_meta_value = $_POST[$meta_key];

			/* Get the meta value of the custom field key. */
			$meta_value = get_post_meta( $post_id, '_' . $meta_key , true );

			/* If there is no new meta value but an old value exists, delete it. */
			if ( '' == $new_meta_value && $meta_value )
				delete_post_meta( $post_id, '_' . $meta_key , $meta_value );

			/* If a new meta value was added and there was no previous value, add it. */
			elseif ( $new_meta_value && '' == $meta_value )
				add_post_meta( $post_id, '_' . $meta_key , $new_meta_value, true );

			/* If the new meta value does not match the old value, update it. */
			elseif ( $new_meta_value && $new_meta_value != $meta_value )
				update_post_meta( $post_id, '_' . $meta_key , $new_meta_value );
		}
	}	

	/**
	 * Filter for post or page links
	 * @param string $link the URL for the post or page
	 * @param int|object $post Either a post ID or a post object
	 * @return string output URL
	 */
	public static function filter_link( $link, $post ) {
	
		if ( $post->post_type == self::$post_type_name ) {
	
			$links = self::get_links();
			
			// Really strange, but page_link gives us an ID and post_link gives us a post object
			$id = ( is_object( $post ) && $post->ID ) ? $post->ID : $post;
	
			if ( isset( $links[$id] ) && $links[$id] )
				$link = esc_url( $links[$id] );
			
		}

		return $link;
		
	}	
	
	/**
	 * Performs a redirect, if appropriate
	 */
	public static function template_redirect() {
	
		//if ( !is_single() && !is_page() )
		//	return;

		global $wp_query;

		$link = get_post_meta( $wp_query->post->ID, '_sponsor-url', true );
		
		$choice = get_post_meta( $wp_query->post->ID, '_sponsor-links-to-choice', true );
				
		if ( !$link || ( $choice != 'sponsor-url' ) )
			return;

		wp_redirect( $link, 301 );
		
		exit;
		
	}
	
	/**
	 * Filters the list of pages to alter the links and targets
	 * @param string $pages the wp_list_pages() HTML block from WordPress
	 * @return string the modified HTML block
	 */
	public static function wp_list_pages( $pages ) {
	
		$highlight = false;
		$links = self::get_links();
		$links_to_target_cache = self::get_targets();

		if ( !$links && !$links_to_target_cache )
			return $pages;

		$this_url = ( is_ssl() ? 'https' : 'http' ) . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
		
		$targets = array();

		foreach ( (array) $links as $id => $page ) {
		
			if ( isset( $links_to_target_cache[$id] ) )
				$targets[$page] = $links_to_target_cache[$id];

			if ( str_replace( 'http://www.', 'http://', $this_url ) == str_replace( 'http://www.', 'http://', $page ) || ( is_home() && str_replace( 'http://www.', 'http://', trailingslashit( get_bloginfo( 'url' ) ) ) == str_replace( 'http://www.', 'http://', trailingslashit( $page ) ) ) ) {
				$highlight = true;
				$current_page = esc_url( $page );
			}
			
		}

		if ( count( $targets ) ) {
		
			foreach ( $targets as  $p => $t ) {
				$p = esc_url( $p );
				$t = esc_attr( $t );
				$pages = str_replace( '<a href="' . $p . '"', '<a href="' . $p . '" target="' . $t . '"', $pages );
			}
			
		}

		if ( $highlight ) {
		
			$pages = preg_replace( '| class="([^"]+)current_page_item"|', ' class="$1"', $pages ); // Kill default highlighting
			$pages = preg_replace( '|<li class="([^"]+)"><a href="' . preg_quote( $current_page ) . '"|', '<li class="$1 current_page_item"><a href="' . $current_page . '"', $pages );
		}
		
		return $pages;
		
	}

	/**
	 * Filters nav menu
	 */
	public static function wp_nav_menu_objects( $items, $args ) {
	
		$links_to_target_cache = self::get_targets();
		$new_items = array();
		
		foreach ( $items as $item ) {
		
			if ( isset( $links_to_target_cache[$item->object_id] ) )
				$item->target = $links_to_target_cache[$item->object_id];
				
			$new_items[] = $item;
			
		}
		
		return $new_items;
		
	}

	/**
	 * Display Notification
	 */	
	public static function load_post() {
	
		if ( isset( $_GET['post'] ) ) {
		
			if ( get_post_meta( absint( $_GET['post'] ), '_sponsor-links-to-choice', true ) == 'sponsor-url' )
				add_action( 'admin_notices', array( __CLASS__, 'notify_of_sponsor_link' ) );
			
		}
		
	}

	/**
	 * Notification
	 */		
	public static function notify_of_sponsor_link() {
		?><div class="updated"><p><?php _e( '<strong>Note</strong>: This sponsor is pointing to the sponsor url.', self::$text_domain ); ?></p></div><?php
	}

	/**
	 * Filter the_posts array of posts.
	 */	
	public static function the_posts( $posts ) {
	
		$links_to_target_cache = self::get_targets();
										
		if ( is_array( $links_to_target_cache ) && count( $links_to_target_cache ) ) {
		
			$pids = array();
			
			foreach ( (array) $posts as $p )
				$pids[$p->ID] = $p->ID;
				
			$targets = array_keys( array_intersect_key( $links_to_target_cache , $pids ) );
			
			if ( count( $targets ) ) {
						
				array_walk( $targets, array( __CLASS__, 'id_to_url_callback' ) );
				
				$targets = array_unique( $targets );
				
				self::$targets_on_this_page = $targets;
				
				wp_enqueue_script( 'jquery' );
				
				add_action( 'wp_head', array( __CLASS__, 'targets_in_new_window_via_js' ) );
				
			}
			
		}
		
		return $posts;
		
	}

	/**
	 * ??
	 */	
	public static function id_to_url_callback( &$val, $key ) {
		$val = get_permalink( $val );
	}

	/**
	 * Filters nav menu
	 */	
	public static function targets_in_new_window_via_js() {
		?><script>(function($){var t=<?php echo json_encode( self::$targets_on_this_page ); ?>;$(document).ready(function(){var a=$('a');$.each(t,function(i,v){a.filter('[href="'+v+'"]').attr('target','_blank');});});})(jQuery);</script><?php
	}
		

} // end Simple_Sponsors_Link_To

}