<?php

// Exit if accessed directly
if ( !defined( 'ABSPATH' ) ) exit;


/**
 * Restores a sticky_update to the good type
 *
 * @param int $id the activity id 
 * @uses BP_Activity_Activity::save()
 */
function activitysticker_restore_type( $id = 0 ) {
	if( empty( $id ) )
		return;

	$restored_activity = new BP_Activity_Activity( $id );
				
	if( !empty( $restored_activity ) ) {

		switch( $restored_activity->component ) {

			case 'blogs' :
				$restored_activity->type = 'new_blog_post';
				break;

			case 'bbpress' :
				$restored_activity->type = 'bbp_topic_create';
				break;
					
			case 'activity' :
				$restored_activity->type = 'activity_update';
				break;
			
		}
				
		$restored_activity->save();
	}

}


/**
 * Checks if an activity status can be sticked to top
 *
 * @param int $id the activity if
 * @uses BP_Activity_Activity::populate()
 * @return boolean (true or false)
 */
function activitysticker_is_stickable( $id = 0 ) {
	if( empty( $id ) )
		return false;
	
	$retval = true;
	$maybe_sticky = new BP_Activity_Activity( $id );
	
	// first do we have the right component ?
	if( !in_array( $maybe_sticky->component, array( 'activity', 'blogs', 'bbpress' ) ) )
		$retval = false;
		
	//if so do we have the right type ?
	if( !in_array( $maybe_sticky->type, array( 'sticky_update', 'activity_update', 'new_blog_post', 'bbp_topic_create' ) ) )
		$retval = false;
		
	return $retval;
}


/**
 * Gets the sticky_update from options table and returns it
 *
 * This is the tricky part! it builds a similar object to 
 * BP_Activity_Template class
 *  
 * @uses bp_get_option()
 * @uses bp_core_get_user_email()
 * @uses bp_core_get_username()
 * @uses bp_core_get_user_displayname()
 */
function activitysticker_get_sticky_update() {
	$sticky = bp_get_option( '_activitysticker_activity' );
	
	if( empty( $sticky ) || !is_array( $sticky ) )
		return false;
	
	$sticky_activity = $sticky['activity_content'];
	
	if( empty( $sticky_activity ) || !is_object( $sticky_activity ) )
		return false;

	$sticky_activity->user_email    = bp_core_get_user_email( $sticky_activity->user_id );
    $sticky_activity->user_nicename = bp_core_get_username( $sticky_activity->user_id, true );
    $sticky_activity->user_login    = bp_core_get_username( $sticky_activity->user_id, false, true );
    $sticky_activity->display_name  = bp_core_get_user_displayname( $sticky_activity->user_id );
    $sticky_activity->user_fullname = $sticky_activity->display_name;

    $sticky_activity->children = false;

	$sticky_update = new stdClass();

	$sticky_update->current_activity = intval( $sticky );
	$sticky_update->activity_count = $sticky_update->total_activity_count = 1;
	$sticky_update->disable_blogforum_replies = 1;

	$sticky_update->activity = $sticky_activity;
	
	return $sticky_update;
}


/**
 * Sets a new sticky update
 *
 * @param string $content the activity content
 * @param string $redirect_error the url to redirect to in case of error 
 * @param string $redirect_success the url to redirect to in case of success
 * @uses buddypress()
 * @uses bp_loggedin_user_id()
 * @uses bp_core_get_userlink()
 * @uses bp_activity_add()
 * @uses BP_Activity_Activity::populate()
 * @uses bp_get_option()
 * @uses activitysticker_restore_type()
 * @uses bp_update_option()
 * @uses add_query_arg()
 * @uses wp_redirect()
 */
function activitysticker_set_sticky_update( $content, $redirect_error = '', $redirect_success = '' ) {
	if( empty( $redirect_error ) || empty( $redirect_success ) )
		return false;
	
	$bp = buddypress();
	
	$user_id = bp_loggedin_user_id();
	$from_user_link   = bp_core_get_userlink( $user_id );
	$activity_action  = sprintf( __( '%s posted an update', 'activity-sticker' ), $from_user_link );
	$activity_content = $content;
	$primary_link     = bp_core_get_userlink( $user_id, false, true );
	
	$sticky_id = bp_activity_add( array(
		'user_id'      => $user_id,
		'action'       => apply_filters( 'bp_activity_new_update_action', $activity_action ),
		'content'      => apply_filters( 'bp_activity_new_update_content', $activity_content ),
		'primary_link' => apply_filters( 'bp_activity_new_update_primary_link', $primary_link ),
		'component'    => $bp->activity->id,
		'type' => 'sticky_update'
		) ) ;
	
	if( !empty( $sticky_id ) ) {
		
		$sticky = array( 'activity_id' => intval( $sticky_id ) );
		$sticky_activity = new BP_Activity_Activity( $sticky_id );
		
		if( !empty( $sticky_activity ) ) {
			$sticky['activity_content'] = $sticky_activity;
			
			// we need to check for a previous sticky and make it a regular activity update !
			$previous_sticky = bp_get_option( '_activitysticker_activity' );
			
			if( !empty( $previous_sticky ) && is_array( $previous_sticky ) ) {
				
				activitysticker_restore_type( intval( $previous_sticky['activity_id'] ) );
				
			}
			
			bp_update_option( '_activitysticker_activity', $sticky );
			
			$redirect_success = add_query_arg( 'updated', $sticky_id, $redirect_success );
			
			wp_redirect( apply_filters( 'activitysticker_admin_success_redirect', $redirect_success ) );
			exit;
			
		} else {
			$redirect_error = add_query_arg( 'error', '2', $redirect_error );
			wp_redirect( apply_filters( 'activitysticker_admin_error_redirect', $redirect_error ) );
			exit;
		}
		
	} else {
		$redirect_error = add_query_arg( 'error', '3', $redirect_error );
		wp_redirect( apply_filters( 'activitysticker_admin_error_redirect', $redirect_error ) );
		exit;
	}
}


/**
 * Unsets a sticky update
 *
 * @param int $activity_id the activity id
 * @param string $redirect_success the url to redirect to
 * @uses activitysticker_restore_type()
 * @uses bp_delete_option()
 * @uses add_query_arg()
 * @uses wp_redirect()
 */
function activitysticker_unset_sticky( $activity_id, $redirect_success = '' ) {
	if( empty( $redirect_success ) )
		return false;

	if( !empty( $activity_id ) ) {

		activitysticker_restore_type( $activity_id );
		bp_delete_option( '_activitysticker_activity' );

		$redirect_success = add_query_arg( 'updated', $activity_id, $redirect_success );
		wp_redirect( apply_filters( 'activitysticker_admin_unset_redirect', $redirect_success ) );
		exit;
	} else {
		$redirect_error = add_query_arg( 'error', $activity_id, $redirect_success );
		wp_redirect( apply_filters( 'activitysticker_admin_unset_redirect', $redirect_error ) );
		exit;
	}


}


/**
 * Output the html part of the sticky update in back end
 *
 * @param object $sticky_update the activity object
 * @uses get_avatar()
 * @uses bp_core_get_userlink()
 * @uses bp_get_root_domain()
 * @uses bp_get_activity_root_slug()
 * @uses date_i18n()
 * @uses get_option()
 * @uses bp_get_admin_url()
 * @uses esc_html()
 * @uses wp_create_nonce()
 * @uses esc_js()
 * @uses bp_activity_get_permalink()
 * @return the html
 */
function activitysticker_admin_output_sticky( $sticky_update = false ) {
	if( empty( $sticky_update ) )
		return false;
		
	$checkbox = sprintf( '<input type="checkbox" name="aid[]" value="%d" />', (int) $sticky_update->activity->id );

	$authortd = '<strong>' . get_avatar( $sticky_update->activity->user_id, '32' ) . ' ' . bp_core_get_userlink( $sticky_update->activity->user_id ) . '</strong>';

	$submittedon = sprintf( __( 'Submitted on <a href="%1$s">%2$s at %3$s</a>', 'activity-sticker' ), bp_get_root_domain() . '/' . bp_get_activity_root_slug() . '/p/' . $sticky_update->activity->id . '/', date_i18n( get_option( 'date_format' ), strtotime( $sticky_update->activity->date_recorded ) ), date_i18n( get_option( 'time_format' ), strtotime( $sticky_update->activity->date_recorded ) ) );

	// Get activity content - if not set, use the action
	if ( ! empty( $sticky_update->activity->content ) ) {
		$content = apply_filters_ref_array( 'bp_get_activity_content_body', array( $sticky_update->activity->content ) );
	} else {
		$content = apply_filters_ref_array( 'bp_get_activity_action', array( $sticky_update->activity->action ) );
	}

	$base_url   = bp_get_admin_url( 'admin.php?page=bp-activity&amp;aid=' . $sticky_update->activity->id );
	$unstick_url = bp_get_admin_url( 'admin.php?page=sticky-activity&amp;aid=' . $sticky_update->activity->id );
	$spam_nonce = esc_html( '_wpnonce=' . wp_create_nonce( 'spam-activity_' . $sticky_update->activity->id ) );
	$unstick_nonce = esc_html( '_wpnonce=' . wp_create_nonce( 'unstick-activity_' . $sticky_update->activity->id ) );

	$actions = array(
		'edit'   => '',
		'delete' => '',
		'unstick' => '',
	);

	$delete_url = $base_url . "&amp;action=delete&amp;$spam_nonce";
	$edit_url   = $base_url . '&amp;action=edit';
	$unstick_url = $unstick_url . "&amp;action=unstick&amp;$unstick_nonce";

	// Rollover actions
	$actions['edit'] = sprintf( '<a href="%s">%s</a>', $edit_url, __( 'Edit', 'activity-sticker' ) );

	$actions['delete'] = sprintf( '<a href="%s" onclick="%s">%s</a>', $delete_url, "javascript:return confirm('" . esc_js( __( 'Are you sure?', 'activity-sticker' ) ) . "'); ", __( 'Delete Permanently', 'activity-sticker' ) );

	$actions['unstick'] = sprintf( '<a href="%s">%s</a>', $unstick_url, __( 'Unstick', 'activity-sticker' ) );

	$viewtd = sprintf( __( '<a href="%1$s">View Activity</a>', 'activity-sticker' ), bp_activity_get_permalink( $sticky_update->activity->id, $sticky_update->activity ) );

	?>
	<table id="prepend-sticky">
		<tr class="activity-sticky" id="activity-<?php echo $sticky_update->activity->id;?>" data-parent_id="<?php echo $sticky_update->activity->id;?>" data-root_id="0">
			<th scope="row" class="check-column">
				<?php echo $checkbox;?>
			</th>
			<td class="author column-author">
				<?php echo $authortd;?>
			</td>
			<td class="comment column-comment">
				<div class="submitted-on"><?php echo $submittedon;?></div>
				<p><?php echo $content;?></p>
		 		<div class="row-actions">
					<span class="edit"><?php echo $actions['edit'];?> | </span>
					<span class="delete"><?php echo $actions['delete'];?> | </span>
					<span class="unstick"><?php echo $actions['unstick'];?></span>
				</div>
			</td>
			<td class="response column-response">
				<?php echo $viewtd;?>
			</td>
		</tr>
	</table>
	<?php
}


/**
 * Outputs the html of the sticky update in front
 *
 * @param object $sticky_update the activity object
 * @global $activities_template
 * @uses remove_filter()
 * @uses add_filter()
 * @uses bp_get_template_part()
 * @return the html
 */
function activitysticker_front_output_sticky( $sticky_update = false ) {
	global $activities_template;
	
	if( empty( $sticky_update ) )
		return false;
		
	$activities_template = $sticky_update;

	remove_filter( 'bp_get_activity_content_body', 'bp_activity_truncate_entry', 5 );
	?>
	<div class="sticky-activity">
		<ul id="activity-sticker" class="activity-list item-list">
			<?php bp_get_template_part( 'activity/entry' ); ?>
		</ul>
	</div>
	<?php
	add_filter( 'bp_get_activity_content_body', 'bp_activity_truncate_entry', 5 );

	$activities_template = false;

}


/**
 * Checks if we're viewing the site wide activity mentions tab
 *
 * @uses wp_parse_args()
 * @return boolean (true or false)
 */
function activity_sticker_parse_bp_cookie( $scope = 'mentions' ) {
	// Set up the cookies passed on this AJAX request. Store a local var to avoid conflicts
	if ( ! empty( $_POST['cookie'] ) ) {
		$_BP_COOKIE = wp_parse_args( str_replace( '; ', '&', urldecode( $_POST['cookie'] ) ) );
	} else {
		$_BP_COOKIE = &$_COOKIE;
	}

	if( !empty( $_BP_COOKIE['bp-activity-scope'] ) && $_BP_COOKIE['bp-activity-scope'] == $scope )
		return true;
	else
		return false;
}


/**
 * Checks the cases when we do not have to filter the activity loop
 *
 * @uses bp_is_activity_component()
 * @uses bp_is_current_action()
 * @uses bp_is_single_activity()
 * @uses bp_is_user_activity()
 * @uses activity_sticker_parse_bp_cookie()
 * @uses bp_displayed_user_id()
 * @uses is_admin()
 * @return boolean true or false
 */
function activitysticker_maybe_filter() {
	$filter = true;
	
	if( bp_is_activity_component() && bp_is_current_action( 'p' ) )
		$filter = false;
		
	if( bp_is_single_activity() )
		$filter = false;

	if( ( bp_is_user_activity() && bp_is_current_action( 'mentions' ) ) || ( activity_sticker_parse_bp_cookie() && bp_is_activity_component() && !bp_displayed_user_id() ) )
		$filter = false;
		
	if( ( bp_is_user_activity() && bp_is_current_action( 'favorites' ) ) || ( activity_sticker_parse_bp_cookie( 'favorites' ) && bp_is_activity_component() && !bp_displayed_user_id() ) )
		$filter = false;
		
	if( is_admin() && !empty( $_REQUEST['action'] ) && in_array( $_REQUEST['action'], array( 'edit', 'delete' ) ) )
		$filter = false;
	
	return $filter;
}


/**
 * Updates the content of a sticky update or changes the type of an activity for a sticky one
 *
 * @param object $activity 
 * @uses bp_get_option()
 * @uses activitysticker_restore_type()
 * @uses bp_update_option()
 * @uses bp_delete_option()
 */
function activitysticker_maybe_update_sticky( $activity = false ) {
	if( empty( $activity ) )
		return;

	$sticky_id = false;
	$previous_sticky = bp_get_option( '_activitysticker_activity' );

	if( !empty( $previous_sticky ) && is_array( $previous_sticky ) )
		$sticky_id = intval( $previous_sticky['activity_id'] );

	if( $activity->type == 'sticky_update' ) {

		if( !empty( $sticky_id ) && $activity->id != $sticky_id ) {
			//we need to change the type of previous sticky
			activitysticker_restore_type( $sticky_id );
		}

		$sticky_data = array( 'activity_id' => intval( $activity->id ), 'activity_content' => $activity );
		bp_update_option( '_activitysticker_activity', $sticky_data );

	} elseif( $activity->type != 'sticky_update' && $activity->id == $sticky_id ) {
		//just delete the option
		bp_delete_option( '_activitysticker_activity' );	
	}
	
}


/**
 * Eventually deletes a sticky
 *
 * @param int $activity_id 
 * @uses bp_get_option()
 * @uses bp_delete_option()
 */
function activitysticker_maybe_delete_sticky( $activity_id = 0 ) {
	if( empty( $activity_id ) )
		return;
		
	$previous_sticky = bp_get_option( '_activitysticker_activity' );
	
	if( !empty( $previous_sticky ) && is_array( $previous_sticky ) ) {
		$sticky_id = intval( $previous_sticky['activity_id'] );
		
		if( $sticky_id == $activity_id )
			bp_delete_option( '_activitysticker_activity' );
	}
	
}