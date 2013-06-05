<?php
/*
Plugin Name: Simple Sponsors
Plugin URI: http://plugins.findingsimple.com
Description: Build a library of Sponsors.
Version: 1.0
Author: Finding Simple ( Jason Conroy & Brent Shepherd )
Author URI: http://findingsimple.com
License: GPL2
*/
/*
Copyright 2012  Finding Simple  (email : plugins@findingsimple.com)

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

require_once dirname( __FILE__ ) . '/simple-sponsors-link-to.php';

if ( ! class_exists( 'Simple_Sponsors' ) ) :

/**
 * So that themes and other plugins can customise the text domain, the Simple_Sponsors
 * should not be initialized until after the plugins_loaded and after_setup_theme hooks.
 * However, it also needs to run early on the init hook.
 *
 * @author Jason Conroy <jason@findingsimple.com>
 * @package Simple Sponsors
 * @since 1.0
 */
function initialize_sponsors(){
	Simple_Sponsors::init();
}
add_action( 'init', 'initialize_sponsors', -1 );

/**
 * Plugin Main Class.
 *
 * @package Simple Sponsors
 * @author Jason Conroy <jason@findingsimple.com>
 * @since 1.0
 */
class Simple_Sponsors {

	static $text_domain;

	static $post_type_name;

	static $admin_screen_id;
	
	/**
	 * Initialise
	 */
	public static function init() {
		global $wp_version;

		self::$text_domain = apply_filters( 'simple_sponsors_text_domain', 'Simple_Sponsors' );

		self::$post_type_name = apply_filters( 'simple_sponsors_post_type_name', 'simple_sponsor' );

		self::$admin_screen_id = apply_filters( 'simple_sponsors_admin_screen_id', 'simple_sponsor' );

		add_action( 'init', array( __CLASS__, 'register' ) );
		
		add_action( 'init', array( __CLASS__, 'register_taxonomies' ), 0 );
		
		add_filter( 'post_updated_messages', array( __CLASS__, 'updated_messages' ) );
		
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_meta_box' ) );
		
		add_action( 'save_post', array( __CLASS__, 'save_meta' ), 10, 1 );
		
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_admin_styles_and_scripts' ) );
		
		add_shortcode( 'sponsor', array( __CLASS__, 'shortcode_sponsor') );
		
		register_widget('WP_Widget_Sponsor');
		
		add_image_size( 'sponsor-admin-thumb', 60, 60, false );
						
		add_filter( 'manage_edit-' . self::$post_type_name . '_columns' , array( __CLASS__, 'add_columns') , 10 );
		
		add_action( 'manage_' . self::$post_type_name . '_posts_custom_column' , array( __CLASS__, 'thumbnail_column_contents') , 10, 2 );

		add_action( 'manage_' . self::$post_type_name . '_posts_custom_column' , array( __CLASS__, 'taxonomy_column_contents') , 10, 2 );

		add_filter( 'enter_title_here', __CLASS__ . '::change_default_title' );

		add_filter( 'admin_post_thumbnail_html', __CLASS__ . '::change_featured_image_metabox_text' );

		add_filter( 'gettext', __CLASS__ . '::change_featured_image_link_text' );

		add_action( 'add_meta_boxes_' . self::$post_type_name, __CLASS__ . '::rename_featured_image_metabox' );

		add_filter( 'image_size_names_choose', __CLASS__ . '::remove_image_size_options' );
		
	}

	/**
	 * Register the post type
	 */
	public static function register() {
		
		$labels = array(
			'name' => _x('Sponsors', 'post type general name', self::$text_domain ),
			'singular_name' => _x('Sponsor', 'post type singular name', self::$text_domain ),
			'add_new' => _x('Add New', 'sponsor', self::$text_domain ),
			'add_new_item' => __('Add New Sponsor', self::$text_domain ),
			'edit_item' => __('Edit Sponsor', self::$text_domain ),
			'new_item' => __('New Sponsor', self::$text_domain ),
			'view_item' => __('View Sponsor', self::$text_domain ),
			'search_items' => __('Search Sponsors', self::$text_domain ),
			'not_found' =>  __('No sponsors found', self::$text_domain ),
			'not_found_in_trash' => __('No sponsors found in Trash', self::$text_domain ),
			'parent_item_colon' => ''
		);
		$args = array(
			'labels' => $labels,
			'public' => true,
			'publicly_queryable' => true,
			'show_ui' => true, 
			'query_var' => true,
			'has_archive' => false,
			'rewrite' => array( 'slug' => 'sponsor', 'with_front' => false ),
			'capability_type' => 'post',
			'hierarchical' => false,
			'menu_position' => null,
			'taxonomies' => array(''),
			'supports' => array('title', 'editor', 'thumbnail', 'custom-fields')
		); 

		register_post_type( self::$post_type_name , $args );
	}

	/**
	 * Filter the "post updated" messages
	 *
	 * @param array $messages
	 * @return array
	 */
	public static function updated_messages( $messages ) {
		global $post;

		$messages['simple_sponsor'] = array(
			0 => '', // Unused. Messages start at index 1.
			1 => sprintf( __('Sponsor updated. <a href="%s">View sponsor</a>', self::$text_domain ), esc_url( get_permalink($post->ID) ) ),
			2 => __('Custom field updated.', self::$text_domain ),
			3 => __('Custom field deleted.', self::$text_domain ),
			4 => __('Sponsor updated.', self::$text_domain ),
			/* translators: %s: date and time of the revision */
			5 => isset($_GET['revision']) ? sprintf( __('Sponsor restored to revision from %s', self::$text_domain ), wp_post_revision_title( (int) $_GET['revision'], false ) ) : false,
			6 => sprintf( __('Sponsor published. <a href="%s">View sponsor</a>', self::$text_domain ), esc_url( get_permalink($post->ID) ) ),
			7 => __('Sponsor saved.', self::$text_domain ),
			8 => sprintf( __('Sponsor submitted. <a target="_blank" href="%s">Preview sponsor</a>', self::$text_domain ), esc_url( add_query_arg( 'preview', 'true', get_permalink($post->ID) ) ) ),
			9 => sprintf( __('Sponsor scheduled for: <strong>%1$s</strong>. <a target="_blank" href="%2$s">Preview sponsor</a>', self::$text_domain ),
			  // translators: Publish box date format, see http://php.net/date
			  date_i18n( __( 'M j, Y @ G:i' ), strtotime( $post->post_date ) ), esc_url( get_permalink($post->ID) ) ),
			10 => sprintf( __('Sponsor draft updated. <a target="_blank" href="%s">Preview sponsor</a>', self::$text_domain ), esc_url( add_query_arg( 'preview', 'true', get_permalink($post->ID) ) ) ),
		);

		return $messages;
	}

	/**
	 * Enqueues the necessary scripts and styles for the plugins
	 *
	 * @since 1.0
	 */
	public static function enqueue_admin_styles_and_scripts() {
				
		if ( is_admin() ) {
	
			wp_register_style( 'simple-sponsors', self::get_url( '/css/simple-sponsors-admin.css', __FILE__ ) , false, '1.0' );
			wp_enqueue_style( 'simple-sponsors' );
		
		}
		
	}
	
	/**
	 * Add the sponsor meta box
	 *
	 * @wp-action add_meta_boxes
	 */
	public static function add_meta_box() {
		add_meta_box( 'sponsor-meta', __( 'Sponsor Meta', self::$text_domain  ), array( __CLASS__, 'do_meta_box' ), self::$post_type_name , 'normal', 'high' );
	}

	/**
	 * Output the sponsor meta box HTML
	 *
	 * @param WP_Post $object Current post object
	 * @param array $box Metabox information
	 */
	public static function do_meta_box( $object, $box ) {
		wp_nonce_field( basename( __FILE__ ), 'sponsor-meta' );
?>
		
		<p>
			<label for="sponsor-url"><?php _e( 'Sponsor URL:', self::$text_domain ); ?></label>
			<br />
			<input type="url" name="sponsor-url" id="sponsor-url"
				value="<?php echo esc_attr( get_post_meta( $object->ID, '_sponsor-url', true ) ); ?>"
				placeholder="http://" 
				size="30" tabindex="30" style="width: 99%;" />
		</p>

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
		if ( !isset( $_POST['sponsor-meta'] ) || !wp_verify_nonce( $_POST['sponsor-meta'], basename( __FILE__ ) ) )
			return $post_id;

		$meta = array(
			'sponsor-url'
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

	/**#@+
	 * @internal Template tag for use in templates
	 */
	/**
	 * Get the sponsor's name
	 *
	 * @param int $post_ID Post ID. Defaults to the current post's ID
	 */
	public static function get_sponsor( $post_ID = 0 , $size = 'thumbnail' ) {
	
		if ( absint($post_ID) === 0 )
			$post_ID = $GLOBALS['post']->ID;
			
		$img_src =  wp_get_attachment_image_src( get_post_thumbnail_id( $post_ID ), $size );
		
		if ( empty( $img_src) )
			return '';
					
		return sprintf('<img src="%1$s" alt="%2$s" />', $img_src[0] , get_the_title( $post_ID ) );
		
	}

	/**
	 * Get the sponsor's URL
	 *
	 * @param int $post_ID Post ID. Defaults to the current post's ID
	 */
	public static function get_sponsor_url( $post_ID = 0 ) {
	
		if ( absint($post_ID) === 0 )
			$post_ID = $GLOBALS['post']->ID;

		return get_post_meta( $post_ID , '_sponsor-url', true );
		
	}
	
	/**
	 * Get a link to the sponsor
	 *
	 * Either returns the sponsor thumbnail, or if the sponsor URL has been set,
	 * returns a HTML link to the sponsor.
	 *
	 * @param int $post_ID Post ID. Defaults to the current post's ID
	 * @param string $string. Defaults to 'thumbnail'
	 */
	public static function get_sponsor_link( $post_ID = 0 , $size = 'thumbnail') {
	
		$sponsor = self::get_sponsor( $post_ID , $size );
		
		if ( empty( $sponsor ) )
			return '';

		$url = self::get_sponsor_url($post_ID);
	
		if ( !empty( $url ) )
			return sprintf('<a href="%1$s" title="%2$s" target="_blank">%3$s</a>', $url , get_the_title( $post_ID ) , $sponsor );

		return $sponsor;
		
	}
	/**#@-*/

	/**
	 * Build sponsor shortcode.
	 *
	 * @since 1.0
	 * @author Jason Conroy <jason@findingsimple.com>
	 * @package Simple Sponsors
	 *
	 */
	 
	public static function shortcode_sponsor( $atts, $content = null ) {
	
		extract( shortcode_atts( 
			array(	'id' => ''
			) , $atts)
		);
		
		$content = '';
	
		return self::sponsors_remove_wpautop($content);
	
	}

	/**
	 * Replaces WP autop formatting 
	 *
	 * @since 1.0
	 * @author Jason Conroy <jason@findingsimple.com>
	 * @package Simple Sponsors
	 */
	public static function sponsors_remove_wpautop($content) { 
		$content = do_shortcode( shortcode_unautop( $content ) ); 
		$content = preg_replace( '#^<\/p>|^<br \/>|<p>$#', '', $content);
		return $content;
	}
	
	/**
	 * Helper function to get the URL of a given file. 
	 * 
	 * As this plugin may be used as both a stand-alone plugin and as a submodule of 
	 * a theme, the standard WP API functions, like plugins_url() can not be used. 
	 *
	 * @since 1.0
	 * @return array $post_name => $post_content
	 */
	public static function get_url( $file ) {

		// Get the path of this file after the WP content directory
		$post_content_path = substr( dirname( str_replace('\\','/',__FILE__) ), strpos( __FILE__, basename( WP_CONTENT_DIR ) ) + strlen( basename( WP_CONTENT_DIR ) ) );

		// Return a content URL for this path & the specified file
		return content_url( $post_content_path . $file );
	}	

	/**
	 * Add a column to the manage pages page to display sponsor thumbnail. 
	 * 
	 * @since 1.0
	 * @author Jason Conroy
	 * @package Simple Sponsors
	 */
	public static function add_columns( $columns ) {
	
		global $post_type;
	
  		$columns_start = array_slice( $columns, 0, 1, true );
  		$columns_end   = array_slice( $columns, 1, null, true );
		
		// add logo coloumn in first
  		$columns = array_merge(
    		$columns_start,
    		array( 'logo' => __( '', self::$text_domain ) ),
    		$columns_end
  		);
  		
  		// append taxonomy columns on end
		$taxonomy_names = get_object_taxonomies( self::$post_type_name );
	
		foreach ( $taxonomy_names as $taxonomy_name ) {
	
			$taxonomy = get_taxonomy( $taxonomy_name );
	
			if ( $taxonomy->_builtin || !in_array( $post_type , $taxonomy->object_type ) )
				continue;
				
			$columns[ $taxonomy_name ] = $taxonomy->label;
		}
		
		return $columns;
		
	}	
	
	/**
	 * Add the sponsor logo / thumbnail to the custom column on the manage page.
	 * 
	 * @since 1.0
	 * @author Jason Conroy
	 * @package Simple Sponsors
	 */
	function thumbnail_column_contents( $column_name, $post_id ) {
				
		if ( $column_name != 'logo' )
			return;
				
		if ( function_exists('the_post_thumbnail') )
			echo '<a href="' . get_edit_post_link( $post_id ) . '" title="' . __( 'Edit Sponsor', self::$text_domain ) . '">' . get_the_post_thumbnail( $post_id, 'sponsor-admin-thumb' ) . '</a>';
					
	}

	/**
	 * Replaces the "Enter title here" text with 
	 *
	 * @author Brent Shepherd <brent@findingsimple.com>
	 * @package Simple Sponsors
	 * @since 1.0
	 */
	public static function change_default_title( $title ){
		$screen = get_current_screen();

		if  ( self::$post_type_name == $screen->post_type )
			$title = __( 'Enter Sponsor Name', self::$text_domain );

		return $title;
	}
	
	/**
	 * Replaces the 'Featured Image' with 'Logo' on the Edit page for the simple_sponsor post type.
	 *
	 * @author Brent Shepherd <brent@findingsimple.com>
	 * @package Simple Sponsors
	 * @since 1.0
	 */
	public static function change_featured_image_metabox_text( $metabox_html ) {

		if ( get_post_type() == self::$post_type_name )
			$metabox_html = str_replace( 'featured image', esc_attr__( 'Logo', self::$text_domain ), $metabox_html );

		return $metabox_html;
		
	}


	/**
	 * Changes the 'Use as featured image' link text on the media panel
	 *
	 * @author Brent Shepherd <brent@findingsimple.com>
	 * @package Simple Sponsors
	 * @since 1.0
	 */
	public static function change_featured_image_link_text( $text ) {
		global $post;

		if ( $text == 'Use as featured image' ) {

			if ( isset( $_GET['post_id'] ) )
				$calling_post_id = absint( $_GET['post_id'] );
			elseif ( isset( $_POST ) && count( $_POST ) )
				$calling_post_id = $post->post_parent;
			else
				$calling_post_id = 0;

			if ( get_post_type( $calling_post_id ) == self::$post_type_name )
				$text = __( "Use as the sponsors logo", self::$text_domain );

		}

		return $text;
	}


	/**
	 * Renames the "Featured Image" metabox to "Sponsor Logo"
	 *
	 * @author Brent Shepherd <brent@findingsimple.com>
	 * @package Simple Sponsor
	 * @since 1.0
	 */
	public static function rename_featured_image_metabox() {

		remove_meta_box( 'postimagediv', self::$post_type_name, 'side' );

		add_meta_box( 'postimagediv', __( "Sponsor Logo", self::$text_domain ), 'post_thumbnail_meta_box', self::$post_type_name, 'side', 'low' );

	}	
	
	/**
	 * Remove admin thumbnail size from the list of available sizes in the media uploader
	 *
	 * @author Jason Conroy <jason@findingsimple.com>
	 * @package Simple Sponsors
	 * @since 1.0
	 */	
	public static function remove_image_size_options( $sizes ){
	 
		unset($sizes['sponsor-admin-thumb']);
		
		return $sizes;
	 
	}

	/**
	 * Add a sponsor level taxonomy for grouping sponsors
	 *
	 * @author Jason Conroy <jason@findingsimple.com>
	 * @package Simple Sponsors
	 * @since 1.0
	 */		
	public static function register_taxonomies() {
	
		register_taxonomy( 'sponsor_level', 
			array( self::$post_type_name ),
			array( 
				'hierarchical'  => true, 
				'query_var'     => 'sponsor_level', 
				'rewrite'       => array(
					'slug'         => __( 'sponsor-level', self::$text_domain ),
					'with_front'   => false,
					'hierarchical' => true
				),
				'label'         => __( 'Sponsor Level', self::$text_domain ),
				'labels'        => array( 
					'name'              => _x( 'Sponsor Levels', self::$text_domain ),
					'singular_name'     => _x( 'Sponsor Level', self::$text_domain ),
					'search_items'      => __( 'Search Sponsor Levels', self::$text_domain ),
					'popular_items'     => __( 'Popular Sponsor Levels', self::$text_domain ),
					'all_items'         => __( 'All Sponsor Levels', self::$text_domain ),
					'parent_item'       => __( 'Parent Sponsor Level', self::$text_domain ),
					'parent_item_colon' => __( 'Parent Sponsor Level:', self::$text_domain ),
					'edit_item'         => __( 'Edit Sponsor Level', self::$text_domain ),
					'update_item'       => __( 'Update Sponsor Level', self::$text_domain ),
					'add_new_item'      => __( 'Add New Sponsor Level', self::$text_domain ),
					'new_item_name'     => __( 'New Sponsor Level Name', self::$text_domain )
				)
			) 
		);
	
	}
		
	/**
	 * Add the terms assigned to a post for each registered custom taxonomy to the
	 * custom column on the manage posts page.
	 *
	 * @author Brent Shepherd <brent@findingsimple.com>
	 * @package Simple Sponsors
	 * @version 1.0
	 */
	
	function taxonomy_column_contents( $column_name, $post_id ) {
		global $wpdb, $post_type;
	
		$taxonomy_names = get_object_taxonomies( self::$post_type_name );
		
		$type = ''; //set blank post type
		
		if ($post_type != 'post') {
			$type = 'post_type=' . $post_type . '&';
		}
	
		foreach ( $taxonomy_names as $taxonomy_name ) {
		
			$taxonomy = get_taxonomy( $taxonomy_name );
	
			if ( $taxonomy->_builtin || $column_name != $taxonomy_name )
				continue;
	
			$terms = get_the_terms( $post_id, $taxonomy_name ); //lang is the first custom taxonomy slug
			
			if ( !empty( $terms ) ) {
				$out = array();
				foreach ( $terms as $term )
					$termlist[] = "<a href='edit.php?" . $type . $taxonomy->query_var."=$term->slug'> " . esc_html( sanitize_term_field( 'name', $term->name, $term->term_id, $taxonomy_name, 'display' ) ) . "</a>";
				echo join( ', ', $termlist );
			} else {
				printf( __( 'No %s.'), $taxonomy->label );
			}
			
		}
	}
	
}

endif;

/**
 * Sponsor widget class
 *
 * @since 1.0
 */
class WP_Widget_Sponsor extends WP_Widget {

	function __construct() {
	
		$widget_ops = array('classname' => 'widget_sponsor', 'description' => __('Display a sponsor'));
		
		$control_ops = array('width' => 400, 'height' => 350);
		
		parent::__construct('sponsor', __('Sponsor'), $widget_ops, $control_ops);
		
	}

	function widget( $args, $instance ) {
		
		$cache = get_transient( 'widget_simple_sponsors' );
				
		if ( ! is_array( $cache ) )
			$cache = array();

		if ( ! isset( $args['widget_id'] ) )
			$args['widget_id'] = $this->id;
		
		if ( isset( $cache[ $args['widget_id'] ] ) ) {
			echo $cache[ $args['widget_id'] ];
			return;
		}	
	
		extract($args);
		
		$output = '';
		
		$title = apply_filters( 'widget_title', empty( $instance['title'] ) ? '' : $instance['title'], $instance, $this->id_base );
		
		if ( ! empty( $instance['ids'] ) )
			$ids = split(',' , str_replace (" ", "", $instance['ids'] ) );
		
		if ( empty( $instance['number'] ) || ! $number = absint( $instance['number'] ) )
 			$number = 5;
		
		//default args
		$query_args = array(
			'post_type' => 'simple_sponsor',
			'posts_per_page' => $number
		);
		
		//if ids set get specific ids and remove posts_per_page limit
		if ( !empty ( $ids )  ) {
			$query_args['post__in'] = $ids;
			$query_args['posts_per_page'] = -1;
		}
		
		if ( !empty ( $instance['randomize'] ) ) {
			$query_args['orderby'] = 'rand';
		}
		
		//run query
		$sponsors = get_posts( $query_args );
				
		// If user has entered a list of IDs display in the order entered
		if ( empty ( $instance['randomize'] ) && !empty ( $ids ) ) {
		
			$sorted_list = array();
		
			foreach( $ids as $id ) :
				foreach( $sponsors as $sponsor ) :		
					
					if( $sponsor->ID == $id )
						$sorted_list[] = $sponsor;			
					
				endforeach;
			endforeach;
		
			$sponsors = $sorted_list;
		
		}
		
		$output .= $before_widget;
		
		if ( !empty( $title ) ) $output .= $before_title . $title . $after_title; 
		
		$count = 1;
		
		if ( !empty ( $sponsors ) ) :
		 
			$output .= '<div class="sponsorwidget">';
			
			$output .= '<ul>';
			
			foreach( $sponsors as $sponsor ) : 
			
				$output .= '<li>';
				
				$output .= Simple_Sponsors::get_sponsor_link( $sponsor->ID , $instance['imgsize'] );
				
				$output .= '</li>';
									
			endforeach;
			
			$output .= '</ul>';

			$output .= '</div><!-- .sponsorwidget -->';
		
		endif; //end if !empty ( $resources );
		
		$output .= $after_widget;
		
		echo $output;
		
		//cache output
		$cache[ $args['widget_id'] ] = $output;
		
		set_transient( 'widget_simple_sponsors', $cache, 60*60*12 );
		
	}

	function update( $new_instance, $old_instance ) {
	
		$instance = $old_instance;
		
		$instance['title'] = strip_tags($new_instance['title']);
				
		$instance['number'] = absint( $new_instance['number'] );

		$instance['ids'] = strip_tags($new_instance['ids']);
		
		$instance['randomize'] = isset($new_instance['randomize']);
		
		$instance['imgsize'] = strip_tags($new_instance['imgsize']);
				
		//flush cache
		delete_transient( 'widget_simple_sponsors' );
		
		return $instance;
		
	}

	function form( $instance ) {
	
		$instance = wp_parse_args( (array) $instance, array( 'title' => '', 'ids' => '' ) );
		
		$title = strip_tags($instance['title']);
				
		$number = isset($instance['number']) ? absint($instance['number']) : 5;
		
		$ids = strip_tags($instance['ids']);
		
		$imgsize = strip_tags($instance['imgsize']);

?>
		<p>
			<label for="<?php echo $this->get_field_id('title'); ?>"><?php _e('Title:'); ?></label>
			<input class="widefat" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" type="text" value="<?php echo esc_attr($title); ?>" />
		</p>
		<p>
			<label for="<?php echo $this->get_field_id('number'); ?>"><?php _e('Number of sponsors to show:'); ?></label>
			<input id="<?php echo $this->get_field_id('number'); ?>" name="<?php echo $this->get_field_name('number'); ?>" type="text" value="<?php echo $number; ?>" size="3" />
		</p>
		<p>
			<label for="<?php echo $this->get_field_id('ids'); ?>"><?php _e('Sponsor IDs: (optional - overrides number of sponsors above)'); ?></label>
			<input class="widefat" id="<?php echo $this->get_field_id('ids'); ?>" name="<?php echo $this->get_field_name('ids'); ?>" type="text" value="<?php echo esc_attr($ids); ?>" />
		</p>
		<p>
			<input id="<?php echo $this->get_field_id('randomize'); ?>" name="<?php echo $this->get_field_name('randomize'); ?>" type="checkbox" <?php checked(isset($instance['randomize']) ? $instance['randomize'] : 0); ?> />
			&nbsp;<label for="<?php echo $this->get_field_id('randomize'); ?>"><?php _e('Randomize sponsors'); ?></label>
		</p>
		<p>
			<label for="<?php echo $this->get_field_id('imgsize'); ?>"><?php _e('Image Size'); ?>
			<select id="<?php echo $this->get_field_id('imgsize'); ?>" name="<?php echo $this->get_field_name('imgsize'); ?>">
			<?php foreach( get_intermediate_image_sizes() as $size ): ?>
				<option value="<?php echo $size; ?>" <?php selected( $size, $imgsize ); ?>><?php echo $size; ?></option>
			<?php endforeach; ?>
			</select>
			</label>
		</p>
<?php
	}
	
}