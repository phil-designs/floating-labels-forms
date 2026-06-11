<?php
/**
 * Plugin Name:       PhilDesigns Floating Labels for Forms
 * Plugin URI:        https://phildesigns.com
 * Description:       Transforms Contact Form 7 and Gravity Forms labels and legends into accessible CSS floating labels. Choose from three distinct styles per form or globally.
 * Version:           1.0.0
 * Author:            PhilDesigns
 * Author URI:        https://phildesigns.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       floating-labels-forms
 * Domain Path:       /languages
 * Requires at least: 6.7
 * Tested up to:      7.0
 * Requires PHP:      7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'FLFG_VERSION', '1.0.0' );
define( 'FLFG_DIR', plugin_dir_path( __FILE__ ) );
define( 'FLFG_URL', plugin_dir_url( __FILE__ ) );

require_once FLFG_DIR . 'includes/form-helpers.php';
require_once FLFG_DIR . 'admin/settings-page.php';

// ---------------------------------------------------------------------------
// Front-end assets
// ---------------------------------------------------------------------------

add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'flfg_plugin_action_links' );

function flfg_plugin_action_links( array $links ): array {
	$settings_link = '<a href="' . esc_url( admin_url( 'options-general.php?page=floating-labels-forms' ) ) . '">' . __( 'Settings', 'floating-labels-forms' ) . '</a>';
	array_unshift( $links, $settings_link );
	return $links;
}

add_action( 'wp_enqueue_scripts', 'flfg_enqueue_assets' );

function flfg_enqueue_assets() {
	$options      = flfg_get_options();
	$global_style = $options['global_style'];

	// Nothing to do if globally disabled and no per-form overrides are active.
	$cf7_active = flfg_any_form_active( $options['cf7_overrides'] ?? [] );
	$gf_active  = flfg_any_form_active( $options['gf_overrides'] ?? [] );

	if ( 'disabled' === $global_style && ! $cf7_active && ! $gf_active ) {
		return;
	}

	$has_cf7 = defined( 'WPCF7_VERSION' );
	$has_gf  = class_exists( 'GFForms' );

	if ( ! $has_cf7 && ! $has_gf ) {
		return;
	}

	wp_enqueue_style(
		'flfg-styles',
		FLFG_URL . 'assets/css/floating-labels.css',
		[],
		FLFG_VERSION
	);

	$color_css = flfg_build_color_css( $options['colors'] ?? [] );
	if ( $color_css ) {
		wp_add_inline_style( 'flfg-styles', $color_css );
	}

	wp_enqueue_script(
		'flfg-script',
		FLFG_URL . 'assets/js/floating-labels.js',
		[],
		FLFG_VERSION,
		true
	);

	wp_localize_script(
		'flfg-script',
		'flfgConfig',
		[
			'globalStyle'  => $global_style,
			'cf7Overrides' => $options['cf7_overrides'] ?? [],
			'gfOverrides'  => $options['gf_overrides'] ?? [],
			'hasCF7'       => $has_cf7 ? 1 : 0,
			'hasGF'        => $has_gf ? 1 : 0,
		]
	);
}

/**
 * Returns true if any form in an overrides array has a non-disabled style.
 *
 * @param array $overrides
 * @return bool
 */
function flfg_any_form_active( array $overrides ): bool {
	foreach ( $overrides as $style ) {
		if ( 'disabled' !== $style ) {
			return true;
		}
	}
	return false;
}

// ---------------------------------------------------------------------------
// Options helper
// ---------------------------------------------------------------------------

/**
 * Returns saved options merged with defaults.
 *
 * @return array{global_style: string, cf7_overrides: array, gf_overrides: array, colors: array}
 */
function flfg_get_options(): array {
	$defaults = [
		'global_style'  => '1',
		'cf7_overrides' => [],
		'gf_overrides'  => [],
		'colors'        => [],
	];
	$saved = get_option( 'flfg_settings', [] );
	return wp_parse_args( $saved, $defaults );
}

/**
 * Builds a :root CSS block from saved colour overrides.
 * Returns an empty string when no overrides are saved.
 *
 * @param array $colors Saved colour map (key → hex string).
 * @return string
 */
function flfg_build_color_css( array $colors ): string {
	$prop_map = [
		'accent'      => '--flfg-accent',
		'border'      => '--flfg-border',
		'bg'          => '--flfg-bg',
		'label_idle'  => '--flfg-label-idle',
		'label_float' => '--flfg-label-float',
		'text'        => '--flfg-text',
	];
	$vars = [];
	foreach ( $prop_map as $key => $prop ) {
		if ( ! empty( $colors[ $key ] ) ) {
			$hex = sanitize_hex_color( $colors[ $key ] );
			if ( $hex ) {
				$vars[] = $prop . ': ' . $hex . ';';
			}
		}
	}
	if ( ! $vars ) {
		return '';
	}
	return ':root { ' . implode( ' ', $vars ) . ' }';
}
