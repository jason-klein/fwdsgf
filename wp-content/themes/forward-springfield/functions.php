<?php
// Exit if accessed directly
if ( !defined( 'ABSPATH' ) ) exit;

// BEGIN ENQUEUE PARENT ACTION
// AUTO GENERATED - Do not modify or remove comment markers above or below:

if ( !function_exists( 'chld_thm_cfg_locale_css' ) ):
    function chld_thm_cfg_locale_css( $uri ){
        if ( empty( $uri ) && is_rtl() && file_exists( get_template_directory() . '/rtl.css' ) )
            $uri = get_template_directory_uri() . '/rtl.css';
        return $uri;
    }
endif;
add_filter( 'locale_stylesheet_uri', 'chld_thm_cfg_locale_css' );

if ( !function_exists( 'chld_thm_cfg_parent_css' ) ):
    function chld_thm_cfg_parent_css() {
        wp_enqueue_style( 'chld_thm_cfg_parent', trailingslashit( get_template_directory_uri() ) . 'style.css', array(  ) );
    }
endif;
add_action( 'wp_enqueue_scripts', 'chld_thm_cfg_parent_css', 10 );

// END ENQUEUE PARENT ACTION

/*
* Remove Jetpack Related Posts on Event Pages
* Jetpack was showing relevant matches, but date was date added to calendar instead of date of event
* https://theeventscalendar.com/support/forums/topic/how-to-disable-jetpack-related-posts-on-single-event-posts/
*/
function jetpackme_no_related_posts( $options ) {
    if ( is_singular( 'tribe_events' ) ) {
        $options['enabled'] = false;
    }
    return $options;
}
add_filter( 'jetpack_relatedposts_filter_options', 'jetpackme_no_related_posts' );


/*
* The Events Calendar Get Events for 1 Year from Today in iCal Export File
* add coding to theme's functions.php
* @version 3.12
* trigger export with link: http://yoursite.com/events/?ical=1&sync
* change 365 for a different range
*
* Details
* https://theeventscalendar.com/support/forums/topic/how-to-export-all-events-with-the-export-listed-events/
*/
add_action( 'pre_get_posts', 'tribe_one_year_ics_export' );
function tribe_one_year_ics_export( WP_Query $query ) {
	if ( ! isset( $_GET['ical'] ) || ! isset( $_GET['sync'] ) ) {
		return;
	}
	if ( ! isset( $query->tribe_is_event_query ) || ! $query->tribe_is_event_query ) {
		return;
	}
	$query->set( 'eventDisplay', 'custom' );
	$query->set( 'start_date', '- 365 day' );
	$query->set( 'end_date', " + 365 day" );
	$query->set( 'posts_per_page', - 1 );
}
