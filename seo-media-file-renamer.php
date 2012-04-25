<?php
/**
 * Plugin Name:	SEO Media File Renamer
 * Plugin URI:	http://austinpassy.com/wordpress-plugins/seo-media-file-renamer/
 * Description:	Rename media files for SEO purposes.
 * Author:		Austin Passy
 * Author URI:	http://austinpassy.com/
 * Version:     1.1
 * License:		GPL
 * Text Domain:	seo-media-file-renamer
 * Domain Path:	/languages/
 */

/**
 * Used code from:
 *
 * 	Enable Media Replace
 * 	Media Tags
 * 	WP Smush.it
 * 	AJAX Thumbnail Rebuild
 *	Rename Media
 *	Rename Media Files
 *
 * Forked from:
 *
 *	Rename Media Files
 */

/* Exit if accessed directly */
if ( ! defined( 'ABSPATH' ) ) exit;

/* Load on init */
add_action( 'init', 'seo_media_file_renamer_init' );

/**
 * Hook functions on init
 *
 * @since 0.1
 */
function seo_media_file_renamer_init() {

	/* Add filters to load and save media edit screen for field */
	add_filter( 'attachment_fields_to_edit', 'seo_media_file_renamer_fields_to_edit', 11, 2 );
	add_filter( 'attachment_fields_to_save', 'seo_media_file_renamer_attachment_fields_to_save', 11, 2 );

	/* Show donate link in plugin's links row */
	add_filter( 'plugin_action_links', 'seo_media_file_renamer_filter_plugin_actions', 10, 2 );

	if ( is_admin() ) {
		$config = array(
			'slug' => plugin_basename( __FILE__ ), // this is the slug of your plugin
			'proper_folder_name' => 'seo-media-file-renamer', // this is the name of the folder your plugin lives in
			'api_url' => 'https://api.github.com/repos/thefrosty/seo-media-file-renamer', // the github API url of your github repo
			'raw_url' => 'https://raw.github.com/thefrosty/master/seo-media-file-renamer', // the github raw url of your github repo
			'github_url' => 'https://github.com/thefrosty/seo-media-file-renamer', // the github url of your github repo
			'zip_url' => 'https://github.com/thefrosty/seo-media-file-renamer/zipball/master', // the zip url of the github repo
			'sslverify' => true // wether WP should check the validity of the SSL cert when getting an update, see https://github.com/jkudish/WordPress-GitHub-Plugin-Updater/issues/2 and https://github.com/jkudish/WordPress-GitHub-Plugin-Updater/issues/4 for details
			'requires' => '3.3', // which version of WordPress does your plugin require?
			'tested' => '3.4', // which version of WordPress is your plugin tested up to?
		);
		if ( !class_exists( 'WPGitHubUpdater' ) ) require_once( trailingslashit( plugin_dir_path( __FILE__ ) ) . 'updater.php' );
		new WPGitHubUpdater( $config );
	}
}

/**
 * Load plugin's translation
 *
 * @since 1.0
 */
function seo_media_file_renamer_load_textdomain() {
	load_plugin_textdomain( 'seo-media-file-renamer', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}

/**
 * Add action links to plugins page
 *
 * @since 1.0
 *
 * @param array $links Default links of plugin
 * @param string $file Name of plugin's file
 * @return array $links New & old links of plugin
 */
function seo_media_file_renamer_filter_plugin_actions( $links, $file ) {
	/* Load translations */
	seo_media_file_renamer_load_textdomain();

	static $this_plugin;

	if ( ! $this_plugin )
		$this_plugin = plugin_basename( __FILE__ );

	if ( $file == $this_plugin ) {
		$donate_link = '<a href="https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=7431290">' . __( 'Donate', 'seo-media-file-renamer' ) . '</a>';
		$links = array_merge( array( $donate_link ), $links ); // Before other links
	}

	return $links;
}

/**
 * Add field on media edit screen
 *
 * @since 0.1
 *
 * @param array $form_fields Existing form fields
 * @param object $post Current attachment post object
 * @return array $form_fields New form fields
 */
function seo_media_file_renamer_fields_to_edit( $form_fields, $post ) {
	/* Only show if not in Thickbox iframe */
	if ( defined( 'IFRAME_REQUEST' ) && true === IFRAME_REQUEST )
		return $form_fields;

	/* Load translations */
	seo_media_file_renamer_load_textdomain();

	/* Get original filename */
	$orig_file = get_attached_file( $post->ID );
	$orig_filename = basename( $orig_file );
	$old_filename = get_post_meta( $post->ID, '_original_filename', true );

	/* Setup a new field */
	$form_fields['seo_media_file_renamer_input'] = array(
       	'label' => __( 'New file name:', 'seo-media-file-renamer' ),
   		'value' => '',
		'helps' => sprintf( __( 'Enter a new file name in the field above. (current filename is %1$s)', 'seo-media-file-renamer'), '<strong>' . $orig_filename . '</strong>' )
	);
	$form_fields['original_media_file_name'] = array(
       	'label' => __( 'Original file name:', 'seo-media-file-renamer' ),
		'input' => 'html',
		'html' => "<input type='text' class='text' readonly='readonly' name='attachments[$post->ID][original_media_file_name]' value='" . esc_attr( $old_filename ) . "' /><br />",
   		'value' => $old_filename
	); //*/
	//print '<pre>'; print_r( $form_fields ); print '</pre>';

    return $form_fields;
}

/**
 * Save form field value on media edit screen
 *
 * @since 0.1
 *
 * @param array $post Current attachment post data
 * @param array $attachment Data submitted via form
 * @return array $post Current attachment post data
 */
function seo_media_file_renamer_attachment_fields_to_save( $post, $attachment ) {

	/* Only proceed if a new filename is submitted */
	if ( $attachment['seo_media_file_renamer_input'] ) {
		
		/* Get original filename */
		$orig_file = get_attached_file( $post['ID'] );
		$orig_filename = basename( $orig_file );
		$extension = str_replace( 'jpeg', 'jpg', pathinfo( basename( $orig_file ), PATHINFO_EXTENSION ) );
		$extension = '.' . $extension;

		/* Get original path of file */
		$orig_dir_path = substr( $orig_file, 0, ( strrpos( $orig_file, "/" ) ) );

		/* Get image sizes */
		$image_sizes = array_merge( get_intermediate_image_sizes(), array( 'full' ) );

		/* If image, get URLs to original sizes */
		if ( wp_attachment_is_image( $post['ID'] ) ) {
			$orig_image_urls = array();

			foreach ( $image_sizes as $image_size ) {
				$orig_image_data = wp_get_attachment_image_src( $post['ID'], $image_size );
				$orig_image_urls[$image_size] = $orig_image_data[0];
			}
		/* Otherwise, get URL to original file */
		} else {
			$orig_attachment_url = wp_get_attachment_url( $post['ID'] );
		}

		/* Make new filename and path */
		$new_filename = wp_unique_filename( $orig_dir_path, str_replace( $extension, '', $attachment['seo_media_file_renamer_input'] ) );
		$new_filename = strtolower( $new_filename );
		$new_file = $orig_dir_path . "/" . $new_filename . $extension; // DIRECTORY_SEPARATOR == "/"
		
		/* Check if new file exists */
		if ( file_exists( $new_file ) === false ) {
			$original_filename = get_post_meta( $post['ID'], '_original_filename', true );
			if ( empty( $original_filename ) )
				add_post_meta( $post['ID'], '_original_filename', $orig_filename );
		}
		
		/* Ensure attachment meta changes */
		$post['post_name'] = ( !empty( $post['post_title'] ) ) ? sanitize_title( $post['post_title'] ) : sanitize_title( basename( $new_filename ) );
		$post['guid'] = str_replace( $orig_filename, $new_filename, $post['guid'] );

		/* Save */
		wp_update_post( $post );

		/* Make new file with desired name */
		rename( $orig_file, $new_file );

		/* Update file location in database */
		update_attached_file( $post['ID'], $new_file );
		
		/* Remove the old intermediate media files */
		$meta = wp_get_attachment_metadata( $post['ID'] );
		foreach ( (array)$meta['sizes'] as $size => $meta_size ) {
			$old_file = dirname( $orig_file ) . "/" . $meta['sizes'][$size]['file'];
			
			while ( is_file( $old_file ) == true ) {
				chmod( $old_file, 0666 );
				unlink( $old_file );
			}
		}

		/* Update attachment's metadata */
		wp_update_attachment_metadata( $post['ID'], wp_generate_attachment_metadata( $post['ID'], $new_file) );

		/* Load global so that we can save to the database */
		global $wpdb;

		/* If image, get URLs to new sizes and update posts with old URLs */
		if ( wp_attachment_is_image( $post['ID'] ) ) {
			foreach ( $image_sizes as $image_size ) {
				$orig_image_url = $orig_image_urls[$image_size];
				$new_image_data = wp_get_attachment_image_src( $post['ID'], $image_size );
				$new_image_url = $new_image_data[0];

				$wpdb->query( $wpdb->prepare( "UPDATE $wpdb->posts SET post_content = REPLACE(post_content, '$orig_image_url', '$new_image_url');" ) );
			}
		/* Otherwise, get URL to new file and update posts with old URL */
		} else {
			$new_attachment_url = wp_get_attachment_url( $post['ID'] );

			$wpdb->query( $wpdb->prepare( "UPDATE $wpdb->posts SET post_content = REPLACE(post_content, '$orig_attachment_url', '$new_attachment_url');" ) );
		}
		
	} // end_if

	return $post;
}

?>