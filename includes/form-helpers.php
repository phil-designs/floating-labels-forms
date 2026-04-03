<?php
/**
 * Helpers to retrieve lists of forms from CF7 and Gravity Forms.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Returns an array of CF7 forms: [ id => title ]
 *
 * @return array<int, string>
 */
function flfg_get_cf7_forms(): array {
	if ( ! defined( 'WPCF7_VERSION' ) ) {
		return [];
	}

	$posts = get_posts( [
		'post_type'      => 'wpcf7_contact_form',
		'posts_per_page' => -1,
		'orderby'        => 'title',
		'order'          => 'ASC',
	] );

	$forms = [];
	foreach ( $posts as $post ) {
		$forms[ $post->ID ] = $post->post_title;
	}
	return $forms;
}

/**
 * Returns an array of Gravity Forms: [ id => title ]
 *
 * @return array<int, string>
 */
function flfg_get_gf_forms(): array {
	if ( ! class_exists( 'GFForms' ) || ! class_exists( 'GFAPI' ) ) {
		return [];
	}

	$gf_forms = GFAPI::get_forms();
	$forms    = [];

	if ( is_array( $gf_forms ) ) {
		foreach ( $gf_forms as $form ) {
			$forms[ (int) $form['id'] ] = $form['title'];
		}
	}

	return $forms;
}
