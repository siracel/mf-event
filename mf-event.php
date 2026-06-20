<?php
/**
 * Plugin Name:       MF Event
 * Description:       Agenda-style upcoming-events list via the [mf_event] shortcode. Recurring (annual) dates and year-specific dates (e.g. Hijri / religious days) you update each year. Inherits the active theme's typography. Custom event types with colours. Reusable on any site.
 * Version:           1.4.0
 * Author:            MF
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       mf-event
 * Domain Path:       /languages
 *
 * Usage: place [mf_event] (or [mf_events]) in any page, post or page-builder text element.
 * Attributes:
 *   months  = how many months ahead to show (default 12)
 *   limit   = max number of upcoming items (0 = no limit, default 0)
 *   today   = show the "Today" highlight section: yes|no (default yes)
 *   title   = optional heading shown above the list (default empty)
 *   type    = filter by one type slug, e.g. type="religious" (default all)
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MF_Event {

	const CPT     = 'mf_event';
	const PREFIX  = '_mfe_';
	const OPT     = 'mfe_types';
	const VERSION = '1.4.0';
	const TD       = 'mf-event';

	// For importing data from the original "isabet-events" plugin.
	const LEGACY_CPT    = 'isabet_event';
	const LEGACY_PREFIX = '_ie_';

	/** Built-in types used until the user customises them. slug => [label, color] */
	public static function default_types() {
		return array(
			'academic'  => array( 'label' => __( 'Academic', 'mf-event' ), 'color' => '#2b6cb0' ),
			'religious' => array( 'label' => __( 'Religious', 'mf-event' ), 'color' => '#2f855a' ),
			'holiday'   => array( 'label' => __( 'National Holiday', 'mf-event' ), 'color' => '#c05621' ),
			'festival'  => array( 'label' => __( 'Festival', 'mf-event' ), 'color' => '#b83280' ),
			'break'     => array( 'label' => __( 'Break', 'mf-event' ), 'color' => '#6b46c1' ),
			'other'     => array( 'label' => __( 'Other', 'mf-event' ), 'color' => '#4a5568' ),
		);
	}

	/** Current types (custom if saved, otherwise defaults). slug => [label, color] */
	public static function get_types() {
		$t = get_option( self::OPT );
		if ( ! is_array( $t ) || empty( $t ) ) {
			return self::default_types();
		}
		return $t;
	}

	public function __construct() {
		add_action( 'init', array( $this, 'load_textdomain' ) );
		add_action( 'init', array( $this, 'register_cpt' ) );
		add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ) );
		add_action( 'save_post_' . self::CPT, array( $this, 'save_meta' ), 10, 2 );
		add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );
		add_shortcode( 'mf_event', array( $this, 'shortcode' ) );
		add_shortcode( 'mf_events', array( $this, 'shortcode' ) ); // alias

		add_filter( 'manage_' . self::CPT . '_posts_columns', array( $this, 'admin_columns' ) );
		add_action( 'manage_' . self::CPT . '_posts_custom_column', array( $this, 'admin_column_content' ), 10, 2 );

		add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
		add_action( 'admin_init', array( $this, 'maybe_load_sample' ) );
		add_action( 'admin_init', array( $this, 'maybe_import_legacy' ) );
		add_action( 'admin_notices', array( $this, 'sample_notice' ) );
		add_action( 'admin_notices', array( $this, 'legacy_notice' ) );
	}

	/** Load translations from /languages. */
	public function load_textdomain() {
		load_plugin_textdomain( self::TD, false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
	}

	/* ---------------------------------------------------------------------
	 * Custom Post Type
	 * ------------------------------------------------------------------- */
	public function register_cpt() {
		register_post_type(
			self::CPT,
			array(
				'labels' => array(
					'name'          => __( 'MF Events', 'mf-event' ),
					'singular_name' => __( 'Event', 'mf-event' ),
					'add_new'       => __( 'Add Event', 'mf-event' ),
					'add_new_item'  => __( 'Add New Event', 'mf-event' ),
					'edit_item'     => __( 'Edit Event', 'mf-event' ),
					'new_item'      => __( 'New Event', 'mf-event' ),
					'view_item'     => __( 'View Event', 'mf-event' ),
					'search_items'  => __( 'Search Events', 'mf-event' ),
					'menu_name'     => __( 'MF Events', 'mf-event' ),
				),
				'public'              => false,
				'show_ui'             => true,
				'show_in_menu'        => true,
				'menu_position'       => 25,
				'menu_icon'           => 'dashicons-calendar-alt',
				'supports'            => array( 'title' ),
				'has_archive'         => false,
				'publicly_queryable'  => false,
				'exclude_from_search' => true,
				'rewrite'             => false,
			)
		);
	}

	/* ---------------------------------------------------------------------
	 * Meta box (date fields)
	 * ------------------------------------------------------------------- */
	public function add_meta_box() {
		add_meta_box( 'mfe_date', __( 'Event Date', 'mf-event' ), array( $this, 'render_meta_box' ), self::CPT, 'normal', 'high' );
	}

	private function months_list() {
		return array(
			1  => __( 'January', 'mf-event' ),
			2  => __( 'February', 'mf-event' ),
			3  => __( 'March', 'mf-event' ),
			4  => __( 'April', 'mf-event' ),
			5  => __( 'May', 'mf-event' ),
			6  => __( 'June', 'mf-event' ),
			7  => __( 'July', 'mf-event' ),
			8  => __( 'August', 'mf-event' ),
			9  => __( 'September', 'mf-event' ),
			10 => __( 'October', 'mf-event' ),
			11 => __( 'November', 'mf-event' ),
			12 => __( 'December', 'mf-event' ),
		);
	}

	public function render_meta_box( $post ) {
		wp_nonce_field( 'mfe_save', 'mfe_nonce' );
		$sm   = get_post_meta( $post->ID, self::PREFIX . 'start_month', true );
		$sd   = get_post_meta( $post->ID, self::PREFIX . 'start_day', true );
		$em   = get_post_meta( $post->ID, self::PREFIX . 'end_month', true );
		$ed   = get_post_meta( $post->ID, self::PREFIX . 'end_day', true );
		$year = get_post_meta( $post->ID, self::PREFIX . 'year', true );
		$type = get_post_meta( $post->ID, self::PREFIX . 'type', true );

		$months = $this->months_list();
		$types  = self::get_types();
		$type   = $type ? $type : key( $types );
		?>
		<style>
			.mfe-fields td{padding:8px 12px 8px 0;vertical-align:top}
			.mfe-fields select,.mfe-fields input[type=number]{min-width:90px}
			.mfe-fields .desc{color:#666;font-size:12px;margin-top:4px;max-width:560px}
		</style>
		<table class="mfe-fields">
			<tr>
				<td><strong><?php esc_html_e( 'Start date', 'mf-event' ); ?> <span style="color:#b32d2e">*</span></strong></td>
				<td>
					<select name="mfe_start_month">
						<option value=""><?php esc_html_e( '— Month —', 'mf-event' ); ?></option>
						<?php foreach ( $months as $n => $name ) : ?>
							<option value="<?php echo esc_attr( $n ); ?>" <?php selected( (int) $sm, $n ); ?>><?php echo esc_html( $name ); ?></option>
						<?php endforeach; ?>
					</select>
					<input type="number" name="mfe_start_day" min="1" max="31" placeholder="<?php esc_attr_e( 'Day', 'mf-event' ); ?>" value="<?php echo esc_attr( $sd ); ?>" />
				</td>
			</tr>
			<tr>
				<td><strong><?php esc_html_e( 'End date', 'mf-event' ); ?></strong><div class="desc"><?php esc_html_e( 'Only for multi-day events (e.g. a school break). Leave empty for single-day events.', 'mf-event' ); ?></div></td>
				<td>
					<select name="mfe_end_month">
						<option value=""><?php esc_html_e( '— Month —', 'mf-event' ); ?></option>
						<?php foreach ( $months as $n => $name ) : ?>
							<option value="<?php echo esc_attr( $n ); ?>" <?php selected( (int) $em, $n ); ?>><?php echo esc_html( $name ); ?></option>
						<?php endforeach; ?>
					</select>
					<input type="number" name="mfe_end_day" min="1" max="31" placeholder="<?php esc_attr_e( 'Day', 'mf-event' ); ?>" value="<?php echo esc_attr( $ed ); ?>" />
				</td>
			</tr>
			<tr>
				<td><strong><?php esc_html_e( 'Year', 'mf-event' ); ?></strong><div class="desc"><?php echo wp_kses_post( __( '<strong>Leave empty</strong> for dates that repeat the same day every year (most Gregorian dates).<br><strong>Set a year</strong> for dates that shift each year — Hijri / religious days like Ramadan, Eid, Mawlid. When that day passes it disappears automatically; add the next year\'s entry to keep it showing.', 'mf-event' ) ); ?></div></td>
				<td><input type="number" name="mfe_year" min="2000" max="2100" placeholder="<?php esc_attr_e( 'e.g. 2027', 'mf-event' ); ?>" value="<?php echo esc_attr( $year ); ?>" /></td>
			</tr>
			<tr>
				<td><strong><?php esc_html_e( 'Type', 'mf-event' ); ?></strong><div class="desc"><?php echo wp_kses_post( __( 'Controls the colour accent of the card. Manage types under <em>MF Events → Settings</em>.', 'mf-event' ) ); ?></div></td>
				<td>
					<select name="mfe_type">
						<?php foreach ( $types as $slug => $info ) : ?>
							<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $type, $slug ); ?>><?php echo esc_html( $info['label'] ); ?></option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
		</table>
		<p class="desc"><?php esc_html_e( 'The event name is the post title above. Order is handled automatically by date.', 'mf-event' ); ?></p>
		<?php
	}

	public function save_meta( $post_id, $post ) {
		if ( ! isset( $_POST['mfe_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['mfe_nonce'] ) ), 'mfe_save' ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$clamp = function ( $key, $min, $max ) {
			if ( ! isset( $_POST[ $key ] ) || '' === $_POST[ $key ] ) {
				return '';
			}
			$v = (int) $_POST[ $key ];
			return ( $v >= $min && $v <= $max ) ? $v : '';
		};

		update_post_meta( $post_id, self::PREFIX . 'start_month', $clamp( 'mfe_start_month', 1, 12 ) );
		update_post_meta( $post_id, self::PREFIX . 'start_day', $clamp( 'mfe_start_day', 1, 31 ) );
		update_post_meta( $post_id, self::PREFIX . 'end_month', $clamp( 'mfe_end_month', 1, 12 ) );
		update_post_meta( $post_id, self::PREFIX . 'end_day', $clamp( 'mfe_end_day', 1, 31 ) );
		update_post_meta( $post_id, self::PREFIX . 'year', $clamp( 'mfe_year', 2000, 2100 ) );

		$types = self::get_types();
		$type  = isset( $_POST['mfe_type'] ) ? sanitize_key( wp_unslash( $_POST['mfe_type'] ) ) : '';
		if ( ! array_key_exists( $type, $types ) ) {
			$type = (string) key( $types );
		}
		update_post_meta( $post_id, self::PREFIX . 'type', $type );
	}

	/* ---------------------------------------------------------------------
	 * Admin list columns
	 * ------------------------------------------------------------------- */
	public function admin_columns( $cols ) {
		$new = array();
		foreach ( $cols as $k => $v ) {
			$new[ $k ] = $v;
			if ( 'title' === $k ) {
				$new['mfe_when'] = __( 'Date', 'mf-event' );
				$new['mfe_type'] = __( 'Type', 'mf-event' );
			}
		}
		return $new;
	}

	public function admin_column_content( $col, $post_id ) {
		$months = $this->months_list();
		if ( 'mfe_when' === $col ) {
			$sm = (int) get_post_meta( $post_id, self::PREFIX . 'start_month', true );
			$sd = (int) get_post_meta( $post_id, self::PREFIX . 'start_day', true );
			$em = (int) get_post_meta( $post_id, self::PREFIX . 'end_month', true );
			$ed = (int) get_post_meta( $post_id, self::PREFIX . 'end_day', true );
			$yr = get_post_meta( $post_id, self::PREFIX . 'year', true );
			if ( ! $sm || ! $sd ) {
				echo '—';
				return;
			}
			$out = $this->short_month( $months[ $sm ] ) . ' ' . $sd;
			if ( $em && $ed ) {
				$out .= ' – ' . $this->short_month( $months[ $em ] ) . ' ' . $ed;
			}
			/* translators: %d: four-digit year. */
			$out .= $yr ? ', ' . $yr : ' ' . __( '(yearly)', 'mf-event' );
			echo esc_html( $out );
		} elseif ( 'mfe_type' === $col ) {
			$t     = get_post_meta( $post_id, self::PREFIX . 'type', true );
			$types = self::get_types();
			if ( isset( $types[ $t ] ) ) {
				printf(
					'<span style="display:inline-block;width:10px;height:10px;border-radius:50%%;background:%s;margin-right:6px;vertical-align:middle"></span>%s',
					esc_attr( $types[ $t ]['color'] ),
					esc_html( $types[ $t ]['label'] )
				);
			} else {
				echo '—';
			}
		}
	}

	/** Multibyte-safe 3-character month abbreviation. */
	private function short_month( $name ) {
		return function_exists( 'mb_substr' ) ? mb_substr( $name, 0, 3 ) : substr( $name, 0, 3 );
	}

	/* ---------------------------------------------------------------------
	 * Settings page (parameter docs + type manager)
	 * ------------------------------------------------------------------- */
	public function add_settings_page() {
		add_submenu_page(
			'edit.php?post_type=' . self::CPT,
			__( 'MF Event Settings', 'mf-event' ),
			__( 'Settings', 'mf-event' ),
			'manage_options',
			'mfe-settings',
			array( $this, 'render_settings_page' )
		);
	}

	private function save_types_from_post() {
		$rows = isset( $_POST['mfe_rows'] ) && is_array( $_POST['mfe_rows'] ) ? wp_unslash( $_POST['mfe_rows'] ) : array();
		$out  = array();

		foreach ( $rows as $row ) {
			if ( ! empty( $row['delete'] ) ) {
				continue;
			}
			$label = isset( $row['label'] ) ? sanitize_text_field( $row['label'] ) : '';
			if ( '' === $label ) {
				continue;
			}
			$slug = isset( $row['slug'] ) ? sanitize_key( $row['slug'] ) : '';
			if ( '' === $slug ) {
				$slug = sanitize_key( sanitize_title( $label ) );
			}
			if ( '' === $slug ) {
				continue;
			}
			// Ensure unique slug.
			$base = $slug;
			$i    = 2;
			while ( isset( $out[ $slug ] ) ) {
				$slug = $base . '-' . $i;
				$i++;
			}
			$color = isset( $row['color'] ) ? sanitize_hex_color( $row['color'] ) : '';
			if ( ! $color ) {
				$color = '#4a5568';
			}
			$out[ $slug ] = array( 'label' => $label, 'color' => $color );
		}

		if ( empty( $out ) ) {
			$out = self::default_types();
		}
		update_option( self::OPT, $out );
	}

	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$saved = false;
		if ( isset( $_POST['mfe_settings_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['mfe_settings_nonce'] ) ), 'mfe_settings' ) ) {
			if ( isset( $_POST['mfe_reset_types'] ) ) {
				update_option( self::OPT, self::default_types() );
			} else {
				$this->save_types_from_post();
			}
			$saved = true;
		}

		$types = self::get_types();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'MF Event — Settings', 'mf-event' ); ?></h1>
			<?php if ( $saved ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Settings saved.', 'mf-event' ); ?></p></div>
			<?php endif; ?>

			<h2 class="title"><?php esc_html_e( 'Shortcode', 'mf-event' ); ?></h2>
			<p><?php esc_html_e( 'Place this shortcode in any page, post, or page-builder text/HTML element:', 'mf-event' ); ?></p>
			<p><code style="font-size:14px;padding:6px 10px;background:#f0f0f1;display:inline-block;">[mf_event]</code> &nbsp;<span style="color:#666"><?php
				/* translators: %s: the [mf_events] shortcode alias. */
				printf( esc_html__( '(the alias %s works too)', 'mf-event' ), '<code>[mf_events]</code>' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			?></span></p>

			<h3><?php esc_html_e( 'Parameters', 'mf-event' ); ?></h3>
			<table class="widefat striped" style="max-width:920px">
				<thead><tr>
					<th style="width:110px"><?php esc_html_e( 'Parameter', 'mf-event' ); ?></th>
					<th style="width:90px"><?php esc_html_e( 'Default', 'mf-event' ); ?></th>
					<th><?php esc_html_e( 'What it does', 'mf-event' ); ?></th>
					<th style="width:230px"><?php esc_html_e( 'Example', 'mf-event' ); ?></th>
				</tr></thead>
				<tbody>
					<tr><td><code>months</code></td><td>12</td><td><?php esc_html_e( 'How many months ahead to show. Events further in the future than this are hidden (this is also what keeps next year’s religious dates from appearing too early).', 'mf-event' ); ?></td><td><code>[mf_event months="6"]</code></td></tr>
					<tr><td><code>limit</code></td><td>0</td><td><?php echo wp_kses_post( __( 'Maximum number of upcoming items to list. <code>0</code> means no limit.', 'mf-event' ) ); ?></td><td><code>[mf_event limit="5"]</code></td></tr>
					<tr><td><code>today</code></td><td>yes</td><td><?php echo wp_kses_post( __( 'Show the highlighted “Today” boxes at the top for events happening today. Set to <code>no</code> to hide that section.', 'mf-event' ) ); ?></td><td><code>[mf_event today="no"]</code></td></tr>
					<tr><td><code>title</code></td><td><?php esc_html_e( '(empty)', 'mf-event' ); ?></td><td><?php esc_html_e( 'Optional heading shown above the list.', 'mf-event' ); ?></td><td><code>[mf_event title="Academic Calendar"]</code></td></tr>
					<tr><td><code>type</code></td><td><?php esc_html_e( '(all)', 'mf-event' ); ?></td><td><?php echo wp_kses_post( __( 'Show only one type, using its <em>slug</em> (see the Types table below). Leave empty to show all.', 'mf-event' ) ); ?></td><td><code>[mf_event type="religious"]</code></td></tr>
				</tbody>
			</table>
			<p style="color:#666"><?php echo wp_kses_post( __( 'You can combine parameters, e.g. <code>[mf_event title="This Year" months="12" limit="10"]</code>.', 'mf-event' ) ); ?></p>

			<h3><?php esc_html_e( 'How dates work', 'mf-event' ); ?></h3>
			<ul style="list-style:disc;margin-left:20px;max-width:920px">
				<li><?php echo wp_kses_post( __( '<strong>No year</strong> on an event → it repeats the same day every year (use for fixed Gregorian dates and school events).', 'mf-event' ) ); ?></li>
				<li><?php echo wp_kses_post( __( '<strong>A year set</strong> on an event → a one-off date for that specific year (use for Hijri / religious days that shift). When the day passes it drops off automatically; add next year’s entry to keep it visible.', 'mf-event' ) ); ?></li>
				<li><?php echo wp_kses_post( __( 'The list is ordered relative to <strong>today</strong>. Items happening today appear at the top in their own boxes. Make sure your <em>Settings → General → Timezone</em> in WordPress is correct, since “today” uses the site timezone.', 'mf-event' ) ); ?></li>
				<li><?php echo wp_kses_post( __( 'Multi-day events use the optional <strong>End date</strong> (year-spanning ranges like Dec 25 – Jan 3 are supported).', 'mf-event' ) ); ?></li>
			</ul>

			<hr style="margin:28px 0">

			<h2 class="title"><?php esc_html_e( 'Event Types', 'mf-event' ); ?></h2>
			<p style="max-width:920px;color:#444"><?php echo wp_kses_post( __( 'Each type has a colour used as the card’s accent. Add your own, rename them, or change colours. The <strong>slug</strong> is the value used in the <code>type="…"</code> shortcode parameter; it’s generated automatically from the name for new types and can’t be edited afterwards (so existing events keep working).', 'mf-event' ) ); ?></p>

			<form method="post">
				<?php wp_nonce_field( 'mfe_settings', 'mfe_settings_nonce' ); ?>
				<table class="widefat striped" id="mfe-types-table" style="max-width:720px">
					<thead><tr>
						<th style="width:240px"><?php esc_html_e( 'Name', 'mf-event' ); ?></th>
						<th style="width:200px"><?php esc_html_e( 'Slug', 'mf-event' ); ?></th>
						<th style="width:120px"><?php esc_html_e( 'Colour', 'mf-event' ); ?></th>
						<th style="width:90px"><?php esc_html_e( 'Delete', 'mf-event' ); ?></th>
					</tr></thead>
					<tbody>
						<?php $idx = 0; foreach ( $types as $slug => $info ) : ?>
							<tr>
								<td><input type="text" name="mfe_rows[<?php echo (int) $idx; ?>][label]" value="<?php echo esc_attr( $info['label'] ); ?>" class="regular-text" style="width:100%" /></td>
								<td><code><?php echo esc_html( $slug ); ?></code><input type="hidden" name="mfe_rows[<?php echo (int) $idx; ?>][slug]" value="<?php echo esc_attr( $slug ); ?>" /></td>
								<td><input type="color" name="mfe_rows[<?php echo (int) $idx; ?>][color]" value="<?php echo esc_attr( $info['color'] ); ?>" /></td>
								<td style="text-align:center"><input type="checkbox" name="mfe_rows[<?php echo (int) $idx; ?>][delete]" value="1" /></td>
							</tr>
						<?php $idx++; endforeach; ?>
					</tbody>
				</table>
				<p>
					<button type="button" class="button" id="mfe-add-type"><?php esc_html_e( '+ Add type', 'mf-event' ); ?></button>
				</p>
				<p>
					<button type="submit" class="button button-primary"><?php esc_html_e( 'Save settings', 'mf-event' ); ?></button>
					<button type="submit" name="mfe_reset_types" value="1" class="button" style="margin-left:8px"
						onclick="return confirm(<?php echo esc_js( __( 'Reset all types to the defaults? Custom types will be removed.', 'mf-event' ) ); ?>);"><?php esc_html_e( 'Reset to defaults', 'mf-event' ); ?></button>
				</p>
			</form>

			<script>
			(function(){
				var btn = document.getElementById('mfe-add-type');
				var tbody = document.querySelector('#mfe-types-table tbody');
				var i = <?php echo (int) $idx; ?>;
				var L = {
					placeholder: <?php echo wp_json_encode( __( 'e.g. Exam', 'mf-event' ) ); ?>,
					auto: <?php echo wp_json_encode( __( 'auto from name', 'mf-event' ) ); ?>
				};
				if(!btn) return;
				btn.addEventListener('click', function(){
					var tr = document.createElement('tr');
					tr.innerHTML =
						'<td><input type="text" name="mfe_rows['+i+'][label]" placeholder="'+L.placeholder+'" class="regular-text" style="width:100%"></td>'+
						'<td><em style="color:#888">'+L.auto+'</em><input type="hidden" name="mfe_rows['+i+'][slug]" value=""></td>'+
						'<td><input type="color" name="mfe_rows['+i+'][color]" value="#2b6cb0"></td>'+
						'<td style="text-align:center"><input type="checkbox" name="mfe_rows['+i+'][delete]" value="1"></td>';
					tbody.appendChild(tr);
					i++;
				});
			})();
			</script>
		</div>
		<?php
	}

	/* ---------------------------------------------------------------------
	 * Assets
	 * ------------------------------------------------------------------- */
	public function register_assets() {
		wp_register_style( 'mf-event', plugins_url( 'assets/mf-event.css', __FILE__ ), array(), self::VERSION );
	}

	/** Build per-type accent colours as inline CSS so custom types work. */
	private function types_inline_css() {
		$css = '';
		foreach ( self::get_types() as $slug => $info ) {
			$color = sanitize_hex_color( $info['color'] );
			if ( ! $color ) {
				continue;
			}
			$css .= sprintf( '.mf-event .mfe-card[data-type="%s"]{--mfe-accent:%s;}', esc_attr( $slug ), $color );
		}
		return $css;
	}

	/* ---------------------------------------------------------------------
	 * Date resolution
	 * ------------------------------------------------------------------- */
	private function make_date( $year, $month, $day, $tz ) {
		if ( ! checkdate( (int) $month, (int) $day, (int) $year ) ) {
			return false;
		}
		$d = DateTime::createFromFormat( 'Y-n-j H:i:s', sprintf( '%d-%d-%d 00:00:00', $year, $month, $day ), $tz );
		return $d ? $d : false;
	}

	private function resolve( $ev, DateTime $today, DateTime $window_end, $tz ) {
		$sm = (int) $ev['start_month'];
		$sd = (int) $ev['start_day'];
		if ( ! $sm || ! $sd ) {
			return null;
		}
		$has_end = ( $ev['end_month'] && $ev['end_day'] );
		$em      = (int) $ev['end_month'];
		$ed      = (int) $ev['end_day'];
		$year    = $ev['year'] ? (int) $ev['year'] : 0;

		if ( $year ) {
			$start = $this->make_date( $year, $sm, $sd, $tz );
			if ( ! $start ) {
				return null;
			}
			$end_year = ( $has_end && $em < $sm ) ? $year + 1 : $year;
			$end      = $has_end ? $this->make_date( $end_year, $em, $ed, $tz ) : clone $start;
			if ( ! $end ) {
				$end = clone $start;
			}
			if ( $end < $today ) {
				return null;
			}
			if ( $start > $window_end ) {
				return null;
			}
			return array( $start, $end, false );
		}

		$today_year = (int) $today->format( 'Y' );
		$best       = null;
		foreach ( array( -1, 0, 1 ) as $delta ) {
			$base  = $today_year + $delta;
			$start = $this->make_date( $base, $sm, $sd, $tz );
			if ( ! $start ) {
				continue;
			}
			$end_year = ( $has_end && $em < $sm ) ? $base + 1 : $base;
			$end      = $has_end ? $this->make_date( $end_year, $em, $ed, $tz ) : clone $start;
			if ( ! $end ) {
				$end = clone $start;
			}
			if ( $end < $today ) {
				continue;
			}
			if ( null === $best || $start < $best[0] ) {
				$best = array( $start, $end );
			}
		}
		if ( null === $best ) {
			return null;
		}
		if ( $best[0] > $window_end ) {
			return null;
		}
		return array( $best[0], $best[1], true );
	}

	/* ---------------------------------------------------------------------
	 * Shortcode
	 * ------------------------------------------------------------------- */
	public function shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'months' => 12,
				'limit'  => 0,
				'today'  => 'yes',
				'title'  => '',
				'type'   => '',
			),
			$atts,
			'mf_event'
		);

		wp_enqueue_style( 'mf-event' );
		wp_add_inline_style( 'mf-event', $this->types_inline_css() );

		$tz         = wp_timezone();
		$today      = new DateTime( 'today', $tz );
		$window_end = ( clone $today )->modify( '+' . max( 1, (int) $atts['months'] ) . ' months' );

		$query = new WP_Query(
			array(
				'post_type'      => self::CPT,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'no_found_rows'  => true,
			)
		);

		$today_items    = array();
		$upcoming_items = array();
		$type_filter    = sanitize_key( $atts['type'] );

		foreach ( $query->posts as $post ) {
			$ev = array(
				'start_month' => get_post_meta( $post->ID, self::PREFIX . 'start_month', true ),
				'start_day'   => get_post_meta( $post->ID, self::PREFIX . 'start_day', true ),
				'end_month'   => get_post_meta( $post->ID, self::PREFIX . 'end_month', true ),
				'end_day'     => get_post_meta( $post->ID, self::PREFIX . 'end_day', true ),
				'year'        => get_post_meta( $post->ID, self::PREFIX . 'year', true ),
				'type'        => get_post_meta( $post->ID, self::PREFIX . 'type', true ),
			);

			if ( $type_filter && $ev['type'] !== $type_filter ) {
				continue;
			}

			$resolved = $this->resolve( $ev, $today, $window_end, $tz );
			if ( null === $resolved ) {
				continue;
			}
			list( $start, $end ) = $resolved;

			$item = array(
				'title' => get_the_title( $post ),
				'type'  => $ev['type'] ? $ev['type'] : 'other',
				'start' => $start,
				'end'   => $end,
				'sort'  => (int) $start->format( 'Ymd' ),
			);

			if ( $start <= $today && $today <= $end ) {
				$today_items[] = $item;
			} else {
				$upcoming_items[] = $item;
			}
		}
		wp_reset_postdata();

		usort( $today_items, fn( $a, $b ) => $a['sort'] <=> $b['sort'] );
		usort( $upcoming_items, fn( $a, $b ) => $a['sort'] <=> $b['sort'] );

		$limit = (int) $atts['limit'];
		if ( $limit > 0 ) {
			$upcoming_items = array_slice( $upcoming_items, 0, $limit );
		}

		$show_today = ( 'no' !== strtolower( $atts['today'] ) );

		ob_start();
		echo '<div class="mf-event">';

		if ( $atts['title'] ) {
			echo '<h2 class="mfe-heading">' . esc_html( $atts['title'] ) . '</h2>';
		}

		if ( $show_today && $today_items ) {
			echo '<div class="mfe-today">';
			echo '<div class="mfe-today__label">' . esc_html__( 'Today', 'mf-event' ) . '</div>';
			echo '<div class="mfe-today-grid">';
			foreach ( $today_items as $it ) {
				echo $this->card_html( $it, true ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}
			echo '</div></div>';
		}

		if ( $upcoming_items ) {
			echo '<div class="mfe-list">';
			foreach ( $upcoming_items as $it ) {
				echo $this->card_html( $it, false ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}
			echo '</div>';
		} elseif ( ! ( $show_today && $today_items ) ) {
			echo '<p class="mfe-empty">' . esc_html__( 'No upcoming events.', 'mf-event' ) . '</p>';
		}

		echo '</div>';
		return ob_get_clean();
	}

	private function fmt( DateTime $d, $format ) {
		return wp_date( $format, $d->getTimestamp(), $d->getTimezone() );
	}

	private function card_html( $item, $is_today ) {
		$start = $item['start'];
		$end   = $item['end'];

		$day  = $this->fmt( $start, 'j' );
		$mon  = $this->fmt( $start, 'M' );
		$year = $this->fmt( $start, 'Y' );

		$is_range = ( $start->format( 'Ymd' ) !== $end->format( 'Ymd' ) );
		if ( $is_range ) {
			if ( $start->format( 'Y' ) === $end->format( 'Y' ) ) {
				$meta = $this->fmt( $start, 'M j' ) . ' – ' . $this->fmt( $end, 'M j, Y' );
			} else {
				$meta = $this->fmt( $start, 'M j, Y' ) . ' – ' . $this->fmt( $end, 'M j, Y' );
			}
		} else {
			$meta = $this->fmt( $start, 'l, F j, Y' );
		}

		$classes = 'mfe-card' . ( $is_today ? ' mfe-card--today' : '' );

		$html  = '<div class="' . esc_attr( $classes ) . '" data-type="' . esc_attr( $item['type'] ) . '">';
		$html .= '<div class="mfe-date"><span class="mfe-day">' . esc_html( $day ) . '</span><span class="mfe-mon">' . esc_html( $mon ) . '</span><span class="mfe-year">' . esc_html( $year ) . '</span></div>';
		$html .= '<div class="mfe-body">';
		$html .= '<h3 class="mfe-title">' . esc_html( $item['title'] ) . '</h3>';
		$html .= '<div class="mfe-meta">';
		if ( $is_today ) {
			$html .= '<span class="mfe-badge">' . esc_html__( 'Today', 'mf-event' ) . '</span>';
		}
		$html .= '<span class="mfe-when">' . esc_html( $meta ) . '</span>';
		$html .= '</div></div></div>';
		return $html;
	}

	/* ---------------------------------------------------------------------
	 * Optional one-click sample loader
	 * ------------------------------------------------------------------- */
	private function event_count() {
		$c = wp_count_posts( self::CPT );
		return (int) $c->publish + (int) $c->draft;
	}

	public function sample_notice() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || 'edit-' . self::CPT !== $screen->id ) {
			return;
		}
		if ( ! current_user_can( 'manage_options' ) || $this->event_count() > 0 ) {
			return;
		}
		$url = wp_nonce_url( admin_url( 'edit.php?post_type=' . self::CPT . '&mfe_load_sample=1' ), 'mfe_load_sample' );
		printf(
			'<div class="notice notice-info"><p>%1$s <a class="button button-primary" href="%2$s">%3$s</a> %4$s</p></div>',
			esc_html__( 'No events yet.', 'mf-event' ),
			esc_url( $url ),
			esc_html__( 'Load sample events', 'mf-event' ),
			esc_html__( 'to start from a ready-made academic-calendar set you can edit, or add your own.', 'mf-event' )
		);
	}

	public function maybe_load_sample() {
		if ( ! isset( $_GET['mfe_load_sample'] ) ) {
			return;
		}
		if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'mfe_load_sample' ) ) {
			return;
		}

		// title, start_m, start_d, end_m, end_d, year (0 = recurring), type
		$seed = array(
			array( 'Summer Camp Begins', 7, 6, 0, 0, 0, 'academic' ),
			array( 'Summer Camp Ends', 8, 14, 0, 0, 0, 'academic' ),
			array( 'First Day of School', 8, 31, 0, 0, 0, 'academic' ),
			array( 'Labor Day', 9, 7, 0, 0, 0, 'holiday' ),
			array( 'Parent Orientation & Welcome to School', 9, 11, 0, 0, 0, 'academic' ),
			array( 'Columbus Day', 10, 12, 0, 0, 0, 'holiday' ),
			array( 'Turkish Festival at DC', 10, 18, 0, 0, 0, 'festival' ),
			array( 'Parent-Teacher Conference', 10, 31, 0, 0, 0, 'academic' ),
			array( 'Veterans Day', 11, 11, 0, 0, 0, 'holiday' ),
			array( 'Food Festival', 11, 26, 11, 29, 0, 'festival' ),
			array( 'Winter Break', 12, 25, 1, 3, 0, 'break' ),
			array( 'Martin Luther King Jr. Day', 1, 18, 0, 0, 0, 'holiday' ),
			array( 'Memorial Day', 5, 31, 0, 0, 0, 'holiday' ),
			array( 'Last Day of School & Graduation Ceremony', 6, 18, 0, 0, 0, 'academic' ),

			array( "Mawlid al-Nabi (Prophet Muhammad's Birthday)", 8, 24, 0, 0, 2026, 'religious' ),
			array( 'Beginning of the Three Holy Months & Regaib Night', 12, 10, 0, 0, 2026, 'religious' ),
			array( 'Miraj Night', 1, 4, 0, 0, 2027, 'religious' ),
			array( 'Berat Night', 1, 22, 0, 0, 2027, 'religious' ),
			array( 'Beginning of Ramadan', 2, 8, 0, 0, 2027, 'religious' ),
			array( 'Laylat al-Qadr (Night of Power)', 3, 5, 0, 0, 2027, 'religious' ),
			array( 'Eid al-Fitr', 3, 9, 0, 0, 2027, 'religious' ),
			array( 'Eid Break', 3, 5, 3, 14, 2027, 'break' ),
			array( 'Eid al-Adha', 5, 16, 0, 0, 2027, 'religious' ),
			array( 'Eid Break', 5, 15, 5, 23, 2027, 'break' ),
		);

		foreach ( $seed as $s ) {
			$post_id = wp_insert_post(
				array(
					'post_type'   => self::CPT,
					'post_status' => 'publish',
					'post_title'  => $s[0],
				)
			);
			if ( $post_id && ! is_wp_error( $post_id ) ) {
				update_post_meta( $post_id, self::PREFIX . 'start_month', $s[1] );
				update_post_meta( $post_id, self::PREFIX . 'start_day', $s[2] );
				update_post_meta( $post_id, self::PREFIX . 'end_month', $s[3] ? $s[3] : '' );
				update_post_meta( $post_id, self::PREFIX . 'end_day', $s[4] ? $s[4] : '' );
				update_post_meta( $post_id, self::PREFIX . 'year', $s[5] ? $s[5] : '' );
				update_post_meta( $post_id, self::PREFIX . 'type', $s[6] );
			}
		}

		wp_safe_redirect( admin_url( 'edit.php?post_type=' . self::CPT ) );
		exit;
	}

	/* ---------------------------------------------------------------------
	 * One-click import from the original "isabet-events" plugin
	 * ------------------------------------------------------------------- */
	private function legacy_ids() {
		$q = new WP_Query(
			array(
				'post_type'      => self::LEGACY_CPT,
				'post_status'    => array( 'publish', 'draft' ),
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'     => self::PREFIX . 'migrated',
						'compare' => 'NOT EXISTS',
					),
				),
			)
		);
		return $q->posts;
	}

	public function legacy_notice() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || 'edit-' . self::CPT !== $screen->id || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$ids = $this->legacy_ids();
		if ( empty( $ids ) ) {
			return;
		}
		$url = wp_nonce_url( admin_url( 'edit.php?post_type=' . self::CPT . '&mfe_import_legacy=1' ), 'mfe_import_legacy' );
		printf(
			'<div class="notice notice-warning"><p>%1$s <a class="button button-primary" href="%2$s">%3$s</a> %4$s</p></div>',
			sprintf(
				/* translators: %s: number of events found. */
				esc_html( _n( 'Found %s event from the previous isabet-events plugin.', 'Found %s events from the previous isabet-events plugin.', count( $ids ), 'mf-event' ) ),
				'<strong>' . count( $ids ) . '</strong>'
			),
			esc_url( $url ),
			esc_html__( 'Import them into MF Event', 'mf-event' ),
			esc_html__( '— copies titles, dates and types. Runs once; no duplicates.', 'mf-event' )
		);
	}

	public function maybe_import_legacy() {
		if ( ! isset( $_GET['mfe_import_legacy'] ) ) {
			return;
		}
		if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'mfe_import_legacy' ) ) {
			return;
		}

		$keys = array( 'start_month', 'start_day', 'end_month', 'end_day', 'year', 'type' );
		foreach ( $this->legacy_ids() as $old_id ) {
			$new_id = wp_insert_post(
				array(
					'post_type'   => self::CPT,
					'post_status' => ( 'draft' === get_post_status( $old_id ) ) ? 'draft' : 'publish',
					'post_title'  => get_the_title( $old_id ),
				)
			);
			if ( $new_id && ! is_wp_error( $new_id ) ) {
				foreach ( $keys as $k ) {
					update_post_meta( $new_id, self::PREFIX . $k, get_post_meta( $old_id, self::LEGACY_PREFIX . $k, true ) );
				}
				update_post_meta( $old_id, self::PREFIX . 'migrated', 1 ); // mark so we never import it twice
			}
		}

		wp_safe_redirect( admin_url( 'edit.php?post_type=' . self::CPT ) );
		exit;
	}
}

new MF_Event();
