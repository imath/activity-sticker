<?php

// Exit if accessed directly
if ( !defined( 'ABSPATH' ) ) exit;


/**
 * Filters can delete
 *
 * @param boolean $can_delete 
 * @uses bp_get_activity_type()
 * @uses activity_sticker_parse_bp_cookie()
 * @uses bp_is_activity_component()
 * @uses bp_displayed_user_id()
 * @return boolean (true or false)
 */
function activity_sticker_filter_can_delete( $can_delete ) {
	global $activities_template;
	
	$type = !empty( $activities_template->activity->type ) ? $activities_template->activity->type : false ;

	if( $type == 'sticky_update' && activity_sticker_parse_bp_cookie() && bp_is_activity_component() && !bp_displayed_user_id() )
		$can_delete = false;

	return $can_delete;
}

add_filter( 'bp_activity_user_can_delete', 'activity_sticker_filter_can_delete', 10, 1 );


/**
 * Filters can favorite and can comment
 *
 * @param boolean $can_do 
 * @uses bp_get_activity_type()
 * @return boolean (true or false)
 */
function activity_sticker_filter_cant_do( $can_do ) {

	global $activities_template;

	if( ! empty( $activities_template->activity ) && bp_get_activity_type() == 'sticky_update' )
		$can_do = false;

	return $can_do;
}

add_filter( 'bp_activity_can_favorite', 'activity_sticker_filter_cant_do', 10, 1 );
add_filter( 'bp_activity_can_comment', 'activity_sticker_filter_cant_do', 10, 1 );
