<?php
/*
Plugin Name: Activity Sticker
Plugin URI: https://github.com/imath/activity-sticker/
Description: Stick a BuddyPress activity to the top of the Site Wide Activity directory
Version: 1.0-beta1
Author: imath
Author URI: http://imathi.eu/
License: GPLv2
Network: true
Text Domain: activity-sticker
Domain Path: /languages/
*/

// Exit if accessed directly
if ( !defined( 'ABSPATH' ) ) exit;

if ( !class_exists( 'ActivitySticker' ) ) :
/**
 * Main ActivitySticker Class
 *
 * Sticks One activity to the top of the Site Wide Activity directory
 *
 * @since Activity Sticker 1.0-beta1
 */
final class ActivitySticker {
	
	public $plugin_url;
	public $plugin_dir;
	public $version;
	public $plugin_js;
	public $plugin_css;
	public $domain;

	function __construct() {
		$this->setup_globals();
		$this->includes();
		$this->setup_actions();
		$this->setup_filters();
	}
	
	function setup_globals() {
		$this->version     = '1.0-beta1';
		$this->plugin_dir  = plugin_dir_path( __FILE__ );
		$this->plugin_url  = plugin_dir_url( __FILE__ );
		$this->plugin_js   = trailingslashit( $this->plugin_url . 'js' );
		$this->plugin_css  = trailingslashit( $this->plugin_url . 'css' );
		$this->domain      = 'activity-sticker';
	}
	
	function includes() {
		$includes_dir = trailingslashit( $this->plugin_dir . 'includes' );
		
		require( $includes_dir . 'functions.php' );
		require( $includes_dir . 'filters.php' );
	}
	
	function setup_actions() {
		
		if( is_admin() ) {
			
			add_action( 'bp_admin_menu',                    array( $this, 'admin_menu'            )        );
			add_action( 'bp_admin_head',                    array( $this, 'admin_head'            )        );
			add_action( 'bp_activity_admin_load',           array( $this, 'admin_enqueue_scripts' ), 10    );
			add_action( 'bp_activity_admin_load',           array( $this, 'add_sticky_type'       ), 11, 1 );
			add_action( 'bp_activity_admin_edit_after',     array( $this, 'update_sticky'         ), 10, 2 );
			add_action( 'bp_activity_list_table_get_views', array( $this, 'sticky_admin_link'     )        );
			add_action( 'bp_activity_admin_index',          array( $this, 'admin_prepend_sticky'  )        );
			
		} else {
			add_action( 'bp_before_directory_activity_list', array( $this, 'front_prepend_sticky'  )       );
			add_action( 'bp_actions',                        array( $this, 'front_enqueue_scripts' )       );
		}
		
		// if the activity is deleted from back end or front end we need to eventually delete the sticky
		add_action( 'bp_before_activity_delete', array( $this, 'delete_sticky' ), 10, 1 );
		// loads the languages..
		add_action( 'bp_init', array( $this, 'load_textdomain' ), 6 );
	}
	
	function setup_filters() {
		//let's put the 'sticky_update' type out of the loop
		add_filter( 'bp_activity_get_user_join_filter', array( $this, 'filter_activity_select' ), 10, 5 );
		add_filter( 'bp_activity_total_activities_sql', array( $this, 'filter_activity_count' ), 10, 3 );
	}
	
	function filter_activity_select( $request_sql, $select_sql, $from_sql, $where_sql, $sort, $pag_sql = 0 ) {
		if( !activitysticker_maybe_filter() )
			return $request_sql;

		if( preg_match( "/a.type != 'activity_comment'/", $where_sql, $matches ) )
			$where_sql = str_replace( "a.type != 'activity_comment'", "a.type NOT IN('activity_comment', 'sticky_update')", $where_sql );
		else
			$where_sql .= " AND a.type != 'sticky_update' ";

		if( !empty( $pag_sql ) )
			$request_sql = "{$select_sql} {$from_sql} {$where_sql} ORDER BY a.date_recorded {$sort} {$pag_sql}";
		else
		 	$request_sql = "{$select_sql} {$from_sql} {$where_sql} ORDER BY a.date_recorded {$sort}";


		return $request_sql;
	}
	
	function filter_activity_count( $request_sql, $where_sql, $sort ) {
		if( !activitysticker_maybe_filter() )
			return $request_sql;

		if( preg_match( "/a.type != 'activity_comment'/", $where_sql, $matches ) )
			$where_new = str_replace( "a.type != 'activity_comment'", "a.type NOT IN('activity_comment', 'sticky_update')", $where_sql );
		else
			$where_new = $where_sql . " AND a.type != 'sticky_update' ";

		$request_sql = str_replace( $where_sql, $where_new, $request_sql );

		return $request_sql;
	}
	
	function get_sticky() {
		// let's get the sticky update
		$sticky_update = activitysticker_get_sticky_update();
		
		return $sticky_update;
	}
	
	function admin_menu() {
		$hook = add_submenu_page( 
			'bp-activity', 
			'New Sticky Activity', 
			'New Sticky Activity', 
			'manage_options', 
			'sticky-activity', 
			array( $this, 'sticky_editor' ) );

		add_action( "load-$hook", array( $this, 'admin_load' ) );
	}
	
	function admin_head() {
		remove_submenu_page( 'bp-activity', 'sticky-activity'   );
		remove_submenu_page( 'bp-activity', 'bp-activity'   );
	}
	
	function admin_enqueue_scripts() {
		wp_enqueue_style(  'activity-sticker-css', $this->plugin_css . 'activity-sticker.css', false, $this->version );
		wp_enqueue_script( 'activity-sticker-js', $this->plugin_js . 'activity-sticker.js', array( 'jquery'), $this->version );
	}
	
	function add_sticky_type( $doaction ) {
		$bp = buddypress();
		
		if( in_array( $doaction, array( 'edit', 'save' ) ) ) {
			$activity_id = intval( $_REQUEST['aid'] );
			
			if( activitysticker_is_stickable( $activity_id ) )
				$bp->activity->actions->activity->sticky_update = array( 'key' => 'sticky_update', 'value' => __( 'Stick to top', 'activity-sticker' ) );
		}
	}
	
	function update_sticky( $activity, $error = false ) {
		if( empty( $activity ) )
			return;
			
		activitysticker_maybe_update_sticky( $activity );
	}
	
	function delete_sticky( $args ) {
		
		$activity_id = $args['id'];
			
		if( empty( $activity_id ) )
			return;
			
		activitysticker_maybe_delete_sticky( $activity_id );
	}
	
	function admin_load() {
		
		$action = ! empty( $_REQUEST['action'] ) ? $_REQUEST['action'] : '';
		$redirect_error = remove_query_arg( array( 'action', 'error' ), $_SERVER['REQUEST_URI'] );
		$redirect_success = bp_get_admin_url( 'admin.php?page=bp-activity' );

		if( "save" == $action ) {
			
			check_admin_referer( 'stick-activity-new' );
			
			$content = apply_filters( 'bp_activity_post_update_content', $_POST['activitysticker-content'] );
			
			if( empty( $content ) ) {
				$redirect_error = add_query_arg( 'error', '1', $redirect_error );
				wp_redirect( apply_filters( 'activitysticker_admin_error_redirect', $redirect_error ) );
				exit;
			}
			
			activitysticker_set_sticky_update( $content, $redirect_error, $redirect_success );
				
		} elseif( "unstick" == $action ) {

			check_admin_referer( 'unstick-activity_' . $_REQUEST['aid'] );

			activitysticker_unset_sticky( $_REQUEST['aid'], $redirect_success );

		} else {
			add_screen_option( 'layout_columns', array( 'default' => 2, 'max' => 2, ) );

			add_meta_box( 'submitdiv',           _x( 'Save Sticky', 'activitysticker admin sticky screen', 'activity-sticker' ), array( &$this, 'metabox_save' ), get_current_screen()->id, 'side', 'core' );

			// Enqueue javascripts
			wp_enqueue_script( 'postbox' );
			wp_enqueue_script( 'dashboard' );
			wp_enqueue_script( 'comment' );
		}
		
	}
	
	function metabox_save( $sticky_item = '' ) {
		?>
		<div class="submitbox" id="submitcomment">

			<div id="major-publishing-actions">
				<div id="publishing-action">
					<?php submit_button( __( 'Publish', 'activity-sticker' ), 'primary', 'save', false, array( 'tabindex' => '4' ) ); ?>
				</div>
				<div class="clear"></div>
			</div><!-- #major-publishing-actions -->

		</div><!-- #submitcomment -->
		<?php
	}
	
	function sticky_editor() {
		$content = !empty( $_POST['activitysticker-content'] ) ? apply_filters( 'bp_activity_post_update_content', $_POST['activitysticker-content'] ) : false ;
		// Construct URL for form
		$form_url = bp_get_admin_url( 'admin.php?page=sticky-activity' );
		$form_url = add_query_arg( 'action', 'save', $form_url );
		?>
		<div class="wrap">
			<?php screen_icon( 'buddypress-activity' ); ?>
			<h2><?php _e( 'Adding a sticky Activity', 'activity-sticker' ) ?></h2>
			
			<?php do_action( 'activitysticker_message' );?>

			<form action="<?php echo esc_attr( $form_url ); ?>" id="activitysticker-sticky-form" method="post">
				<div id="poststuff">

					<div id="post-body" class="metabox-holder columns-<?php echo 1 == get_current_screen()->get_columns() ? '1' : '2'; ?>">
						<div id="post-body-content">
							<div id="postdiv" class="postarea">
								<div id="activitysticker_content" class="postbox">
									<h3><?php _e( 'Content', 'activity-sticker' ); ?></h3>
									<div class="inside">
										<?php wp_editor( $content, 'activitysticker-content', array( 'media_buttons' => false, 'teeny' => true, 'quicktags' => array( 'buttons' => 'strong,em,link,block,del,ins,img,code,spell,close' ) ) ); ?>
									</div>
								</div>
							</div>
						</div><!-- #post-body-content -->

						<div id="postbox-container-1" class="postbox-container">
							<?php do_meta_boxes( get_current_screen()->id, 'side', '' ); ?>
						</div>

					</div><!-- #post-body -->

				</div><!-- #poststuff -->
				<?php wp_nonce_field( 'closedpostboxes', 'closedpostboxesnonce', false ); ?>
				<?php wp_nonce_field( 'meta-box-order', 'meta-box-order-nonce', false ); ?>
				<?php wp_nonce_field( 'stick-activity-new' ); ?>
			</form>
		</div>
		<?php
	}
	
	function sticky_admin_link() {
		?>
		<li>| <a href="<?php echo bp_get_admin_url( 'admin.php?page=sticky-activity' );?>"><?php _e( 'New Sticky', 'activity-sticker' );?></a></li>
		<?php
	}
	
	function admin_prepend_sticky() {
		$sticky_update = $this->get_sticky();
		
		activitysticker_admin_output_sticky( $sticky_update );
	}
	
	function front_prepend_sticky() {		
		$sticky_update = $this->get_sticky();
		
		activitysticker_front_output_sticky( $sticky_update );
	}
	
	function front_enqueue_scripts() {
		$file = 'css/activity-sticker.css';
		
		// Check child theme
		if ( file_exists( trailingslashit( get_stylesheet_directory() ) . $file ) ) {
			$location = trailingslashit( get_stylesheet_directory_uri() ) . $file ; 
			$handle   = 'activity-sticker-child-css';

		// Check parent theme
		} elseif ( file_exists( trailingslashit( get_template_directory() ) . $file ) ) {
			$location = trailingslashit( get_template_directory_uri() ) . $file ;
			$handle   = 'activity-sticker-parent-css';

		// use our style
		} else {
			$location = $this->plugin_css . 'activity-sticker.css';
			$handle   = 'activity-sticker-css';
		}
		
		wp_enqueue_style(  $handle, $location, false, $this->version );

		if( bp_is_activity_component() && !bp_displayed_user_id() )
			wp_enqueue_script( 'activity-sticker-js', $this->plugin_js . 'activity-sticker.js', array( 'jquery'), $this->version );
	}
	
	function load_textdomain() {
		// try to get locale
		$locale = apply_filters( 'activitysticker_load_textdomain_get_locale', get_locale() );

		// if we found a locale, try to load .mo file
		if ( !empty( $locale ) ) {
			// default .mo file path
			$mofile_default = sprintf( '%s/languages/%s-%s.mo', $this->plugin_dir, $this->domain, $locale );
			// final filtered file path
			$mofile = apply_filters( 'activitysticker_textdomain_mofile', $mofile_default );
			// make sure file exists, and load it
			if ( file_exists( $mofile ) ) {
				load_textdomain( $this->domain, $mofile );
			}
		}
	}
	
}

function activitysticker() {
	return new ActivitySticker();
}

add_action( 'bp_include', 'activitysticker' );

endif;