<?php
/**
 * Admin settings page for Floating Labels for Forms.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'admin_menu', 'flfg_admin_menu' );
add_action( 'admin_enqueue_scripts', 'flfg_admin_enqueue' );
add_action( 'admin_init', 'flfg_handle_settings_save' );

function flfg_admin_menu() {
	add_options_page(
		__( 'Floating Labels for Forms', 'floating-labels-forms' ),
		__( 'Floating Labels', 'floating-labels-forms' ),
		'manage_options',
		'floating-labels-forms',
		'flfg_settings_page'
	);
}

function flfg_admin_enqueue( $hook ) {
	if ( 'settings_page_floating-labels-forms' !== $hook ) {
		return;
	}
	wp_enqueue_style(
		'flfg-admin',
		FLFG_URL . 'assets/css/admin.css',
		[],
		FLFG_VERSION
	);

	wp_enqueue_script(
		'flfg-admin',
		FLFG_URL . 'assets/js/admin.js',
		[],
		FLFG_VERSION,
		true
	);
}

function flfg_handle_settings_save() {
	if (
		! isset( $_POST['flfg_nonce'] ) ||
		! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['flfg_nonce'] ) ), 'flfg_save_settings' )
	) {
		return;
	}

	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$options = flfg_get_options();

	// Global style
	$valid_styles = [ '1', '2', '3', 'disabled' ];
	$global       = sanitize_text_field( $_POST['flfg_global_style'] ?? '1' );
	$options['global_style'] = in_array( $global, $valid_styles, true ) ? $global : '1';

	// CF7 per-form overrides
	$cf7_raw              = $_POST['flfg_cf7'] ?? [];
	$options['cf7_overrides'] = [];
	if ( is_array( $cf7_raw ) ) {
		foreach ( $cf7_raw as $id => $style ) {
			$id    = absint( $id );
			$style = sanitize_text_field( $style );
			if ( $id && in_array( $style, array_merge( $valid_styles, [ 'global' ] ), true ) ) {
				$options['cf7_overrides'][ $id ] = $style;
			}
		}
	}

	// GF per-form overrides
	$gf_raw              = $_POST['flfg_gf'] ?? [];
	$options['gf_overrides'] = [];
	if ( is_array( $gf_raw ) ) {
		foreach ( $gf_raw as $id => $style ) {
			$id    = absint( $id );
			$style = sanitize_text_field( $style );
			if ( $id && in_array( $style, array_merge( $valid_styles, [ 'global' ] ), true ) ) {
				$options['gf_overrides'][ $id ] = $style;
			}
		}
	}

	// Colour overrides
	$color_keys = [ 'accent', 'border', 'bg', 'label_idle', 'label_float', 'text' ];
	$raw_colors = isset( $_POST['flfg_colors'] ) && is_array( $_POST['flfg_colors'] ) ? $_POST['flfg_colors'] : [];
	$options['colors'] = [];
	foreach ( $color_keys as $key ) {
		if ( ! empty( $raw_colors[ $key ] ) ) {
			$hex = sanitize_hex_color( $raw_colors[ $key ] );
			if ( $hex ) {
				$options['colors'][ $key ] = $hex;
			}
		}
	}

	update_option( 'flfg_settings', $options );

	add_settings_error( 'flfg_settings', 'flfg_saved', __( 'Settings saved.', 'floating-labels-forms' ), 'updated' );
}

function flfg_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	settings_errors( 'flfg_settings' );

	$options    = flfg_get_options();
	$cf7_forms  = flfg_get_cf7_forms();
	$gf_forms   = flfg_get_gf_forms();
	$has_cf7    = defined( 'WPCF7_VERSION' );
	$has_gf     = class_exists( 'GFForms' );

	$color_defaults = [
		'accent'      => '#0073aa',
		'border'      => '#cccccc',
		'bg'          => '#fafafa',
		'label_idle'  => '#888888',
		'label_float' => '#555555',
		'text'        => '#222222',
	];
	$saved_colors = $options['colors'] ?? [];
	$colors       = array_merge( $color_defaults, $saved_colors );

	$style_options = [
		'global'   => __( '— Use Global —', 'floating-labels-forms' ),
		'1'        => __( 'Style 1: Underline', 'floating-labels-forms' ),
		'2'        => __( 'Style 2: Outlined Box', 'floating-labels-forms' ),
		'3'        => __( 'Style 3: Padded Box', 'floating-labels-forms' ),
		'disabled' => __( 'Disabled', 'floating-labels-forms' ),
	];
	?>
	<div class="wrap flfg-admin-wrap">
		<h1><?php esc_html_e( 'Floating Labels for Forms', 'floating-labels-forms' ); ?></h1>
		<p class="flfg-tagline"><?php esc_html_e( 'Transform form labels and legends into accessible CSS floating labels. Labels remain in the DOM at all times — only their visual position changes.', 'floating-labels-forms' ); ?></p>

		<form method="post" action="">
			<?php wp_nonce_field( 'flfg_save_settings', 'flfg_nonce' ); ?>

			<!-- ================================================================
			     Global Style
			     ================================================================ -->
			<h2><?php esc_html_e( 'Default Style', 'floating-labels-forms' ); ?></h2>
			<p><?php esc_html_e( 'This applies to all forms unless overridden below.', 'floating-labels-forms' ); ?></p>

			<div class="flfg-style-cards">

				<!-- Disabled -->
				<label class="flfg-style-card <?php echo 'disabled' === $options['global_style'] ? 'is-selected' : ''; ?>">
					<input type="radio" name="flfg_global_style" value="disabled" <?php checked( $options['global_style'], 'disabled' ); ?>>
					<span class="flfg-card-preview flfg-card-disabled">
						<span class="flfg-card-icon">✕</span>
					</span>
					<span class="flfg-card-label"><?php esc_html_e( 'Disabled', 'floating-labels-forms' ); ?></span>
				</label>

				<!-- Style 1 -->
				<label class="flfg-style-card <?php echo '1' === $options['global_style'] ? 'is-selected' : ''; ?>">
					<input type="radio" name="flfg_global_style" value="1" <?php checked( $options['global_style'], '1' ); ?>>
					<span class="flfg-card-preview flfg-card-style-1" aria-hidden="true">
						<span class="flfg-demo-wrap flfg-demo-style-1">
							<span class="flfg-demo-field"></span>
							<span class="flfg-demo-label flfg-demo-label--float">Label</span>
						</span>
					</span>
					<span class="flfg-card-label"><?php esc_html_e( 'Style 1 — Underline', 'floating-labels-forms' ); ?></span>
					<span class="flfg-card-desc"><?php esc_html_e( 'Clean single bottom border, label floats above', 'floating-labels-forms' ); ?></span>
				</label>

				<!-- Style 2 -->
				<label class="flfg-style-card <?php echo '2' === $options['global_style'] ? 'is-selected' : ''; ?>">
					<input type="radio" name="flfg_global_style" value="2" <?php checked( $options['global_style'], '2' ); ?>>
					<span class="flfg-card-preview flfg-card-style-2" aria-hidden="true">
						<span class="flfg-demo-wrap flfg-demo-style-2">
							<span class="flfg-demo-field"></span>
							<span class="flfg-demo-label flfg-demo-label--float">Label</span>
						</span>
					</span>
					<span class="flfg-card-label"><?php esc_html_e( 'Style 2 — Outlined Box', 'floating-labels-forms' ); ?></span>
					<span class="flfg-card-desc"><?php esc_html_e( 'Full border, label floats to the top edge', 'floating-labels-forms' ); ?></span>
				</label>

				<!-- Style 3 -->
				<label class="flfg-style-card <?php echo '3' === $options['global_style'] ? 'is-selected' : ''; ?>">
					<input type="radio" name="flfg_global_style" value="3" <?php checked( $options['global_style'], '3' ); ?>>
					<span class="flfg-card-preview flfg-card-style-3" aria-hidden="true">
						<span class="flfg-demo-wrap flfg-demo-style-3">
							<span class="flfg-demo-field"></span>
							<span class="flfg-demo-label flfg-demo-label--float">Label</span>
						</span>
					</span>
					<span class="flfg-card-label"><?php esc_html_e( 'Style 3 — Padded Box', 'floating-labels-forms' ); ?></span>
					<span class="flfg-card-desc"><?php esc_html_e( 'Tall box, label stays inside and shifts to top', 'floating-labels-forms' ); ?></span>
				</label>

			</div><!-- .flfg-style-cards -->

			<!-- ================================================================
			     Contact Form 7
			     ================================================================ -->
			<?php if ( $has_cf7 ) : ?>
			<h2><?php esc_html_e( 'Contact Form 7 — Per-Form Overrides', 'floating-labels-forms' ); ?></h2>
			<?php if ( empty( $cf7_forms ) ) : ?>
				<p><?php esc_html_e( 'No CF7 forms found. Create a form first.', 'floating-labels-forms' ); ?></p>
			<?php else : ?>
				<table class="flfg-forms-table widefat striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Form', 'floating-labels-forms' ); ?></th>
							<th><?php esc_html_e( 'ID', 'floating-labels-forms' ); ?></th>
							<th><?php esc_html_e( 'Style', 'floating-labels-forms' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $cf7_forms as $id => $title ) :
							$current = $options['cf7_overrides'][ $id ] ?? 'global';
						?>
						<tr>
							<td><?php echo esc_html( $title ); ?></td>
							<td><code><?php echo esc_html( $id ); ?></code></td>
							<td>
								<select name="flfg_cf7[<?php echo esc_attr( $id ); ?>]">
									<?php foreach ( $style_options as $val => $label ) : ?>
										<option value="<?php echo esc_attr( $val ); ?>" <?php selected( $current, $val ); ?>>
											<?php echo esc_html( $label ); ?>
										</option>
									<?php endforeach; ?>
								</select>
							</td>
						</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
			<?php else : ?>
				<div class="flfg-plugin-notice">
					<?php esc_html_e( 'Contact Form 7 is not active. Install and activate it to manage CF7 form styles here.', 'floating-labels-forms' ); ?>
				</div>
			<?php endif; ?>

			<!-- ================================================================
			     Gravity Forms
			     ================================================================ -->
			<?php if ( $has_gf ) : ?>
			<h2><?php esc_html_e( 'Gravity Forms — Per-Form Overrides', 'floating-labels-forms' ); ?></h2>
			<?php if ( empty( $gf_forms ) ) : ?>
				<p><?php esc_html_e( 'No Gravity Forms found. Create a form first.', 'floating-labels-forms' ); ?></p>
			<?php else : ?>
				<table class="flfg-forms-table widefat striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Form', 'floating-labels-forms' ); ?></th>
							<th><?php esc_html_e( 'ID', 'floating-labels-forms' ); ?></th>
							<th><?php esc_html_e( 'Style', 'floating-labels-forms' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $gf_forms as $id => $title ) :
							$current = $options['gf_overrides'][ $id ] ?? 'global';
						?>
						<tr>
							<td><?php echo esc_html( $title ); ?></td>
							<td><code><?php echo esc_html( $id ); ?></code></td>
							<td>
								<select name="flfg_gf[<?php echo esc_attr( $id ); ?>]">
									<?php foreach ( $style_options as $val => $label ) : ?>
										<option value="<?php echo esc_attr( $val ); ?>" <?php selected( $current, $val ); ?>>
											<?php echo esc_html( $label ); ?>
										</option>
									<?php endforeach; ?>
								</select>
							</td>
						</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
			<?php else : ?>
				<div class="flfg-plugin-notice">
					<?php esc_html_e( 'Gravity Forms is not active. Install and activate it to manage GF form styles here.', 'floating-labels-forms' ); ?>
				</div>
			<?php endif; ?>

			<!-- ================================================================
			     Colour Palette
			     ================================================================ -->
			<h2><?php esc_html_e( 'Colour Palette', 'floating-labels-forms' ); ?></h2>
			<p><?php esc_html_e( 'Override the default colours used across all styles. Click "Reset" to restore a colour to its built-in default.', 'floating-labels-forms' ); ?></p>

			<div class="flfg-color-grid">

				<div class="flfg-color-row">
					<label for="flfg-color-accent"><?php esc_html_e( 'Accent — focus border & active label', 'floating-labels-forms' ); ?></label>
					<div class="flfg-color-controls">
						<input type="color" id="flfg-color-accent" name="flfg_colors[accent]"
							value="<?php echo esc_attr( $colors['accent'] ); ?>"
							data-default="<?php echo esc_attr( $color_defaults['accent'] ); ?>">
						<button type="button" class="button-link flfg-color-reset"
							data-target="flfg-color-accent"
							data-default="<?php echo esc_attr( $color_defaults['accent'] ); ?>">
							<?php esc_html_e( 'Reset', 'floating-labels-forms' ); ?>
						</button>
					</div>
				</div>

				<div class="flfg-color-row">
					<label for="flfg-color-border"><?php esc_html_e( 'Border — idle field border', 'floating-labels-forms' ); ?></label>
					<div class="flfg-color-controls">
						<input type="color" id="flfg-color-border" name="flfg_colors[border]"
							value="<?php echo esc_attr( $colors['border'] ); ?>"
							data-default="<?php echo esc_attr( $color_defaults['border'] ); ?>">
						<button type="button" class="button-link flfg-color-reset"
							data-target="flfg-color-border"
							data-default="<?php echo esc_attr( $color_defaults['border'] ); ?>">
							<?php esc_html_e( 'Reset', 'floating-labels-forms' ); ?>
						</button>
					</div>
				</div>

				<div class="flfg-color-row">
					<label for="flfg-color-bg"><?php esc_html_e( 'Background — input background (Styles 2 &amp; 3)', 'floating-labels-forms' ); ?></label>
					<div class="flfg-color-controls">
						<input type="color" id="flfg-color-bg" name="flfg_colors[bg]"
							value="<?php echo esc_attr( $colors['bg'] ); ?>"
							data-default="<?php echo esc_attr( $color_defaults['bg'] ); ?>">
						<button type="button" class="button-link flfg-color-reset"
							data-target="flfg-color-bg"
							data-default="<?php echo esc_attr( $color_defaults['bg'] ); ?>">
							<?php esc_html_e( 'Reset', 'floating-labels-forms' ); ?>
						</button>
					</div>
				</div>

				<div class="flfg-color-row">
					<label for="flfg-color-label-idle"><?php esc_html_e( 'Label — default position (placeholder)', 'floating-labels-forms' ); ?></label>
					<div class="flfg-color-controls">
						<input type="color" id="flfg-color-label-idle" name="flfg_colors[label_idle]"
							value="<?php echo esc_attr( $colors['label_idle'] ); ?>"
							data-default="<?php echo esc_attr( $color_defaults['label_idle'] ); ?>">
						<button type="button" class="button-link flfg-color-reset"
							data-target="flfg-color-label-idle"
							data-default="<?php echo esc_attr( $color_defaults['label_idle'] ); ?>">
							<?php esc_html_e( 'Reset', 'floating-labels-forms' ); ?>
						</button>
					</div>
				</div>

				<div class="flfg-color-row">
					<label for="flfg-color-label-float"><?php esc_html_e( 'Label — floated position (filled, not focused)', 'floating-labels-forms' ); ?></label>
					<div class="flfg-color-controls">
						<input type="color" id="flfg-color-label-float" name="flfg_colors[label_float]"
							value="<?php echo esc_attr( $colors['label_float'] ); ?>"
							data-default="<?php echo esc_attr( $color_defaults['label_float'] ); ?>">
						<button type="button" class="button-link flfg-color-reset"
							data-target="flfg-color-label-float"
							data-default="<?php echo esc_attr( $color_defaults['label_float'] ); ?>">
							<?php esc_html_e( 'Reset', 'floating-labels-forms' ); ?>
						</button>
					</div>
				</div>

				<div class="flfg-color-row">
					<label for="flfg-color-text"><?php esc_html_e( 'Text — typed input text', 'floating-labels-forms' ); ?></label>
					<div class="flfg-color-controls">
						<input type="color" id="flfg-color-text" name="flfg_colors[text]"
							value="<?php echo esc_attr( $colors['text'] ); ?>"
							data-default="<?php echo esc_attr( $color_defaults['text'] ); ?>">
						<button type="button" class="button-link flfg-color-reset"
							data-target="flfg-color-text"
							data-default="<?php echo esc_attr( $color_defaults['text'] ); ?>">
							<?php esc_html_e( 'Reset', 'floating-labels-forms' ); ?>
						</button>
					</div>
				</div>

			</div><!-- .flfg-color-grid -->

			<?php submit_button( __( 'Save Settings', 'floating-labels-forms' ) ); ?>

		</form>
	</div>
	<?php
}
