<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * LeagueApps Menu — an in-house Beaver Builder navigation module.
 *
 * Renders a WordPress nav menu with:
 *  - a horizontal desktop bar with hover dropdowns,
 *  - an optional submenu indicator (arrow icon or custom text),
 *  - optional per-item MEGA panels whose child links flow into N columns
 *    (module default, overridable per menu item via a `ds-cols-N` CSS class;
 *     `ds-no-mega` forces a plain dropdown),
 *  - an optional "CTA button" treatment on the last top-level item,
 *  - a full-screen hamburger overlay (animated hamburger→X, optional label)
 *    below a configurable breakpoint.
 *
 * Fully owned/SSH-editable (markup here, styles in css/ + includes/frontend.css.php,
 * behavior in js/frontend.js). No UABB dependency.
 *
 * @class DS_Menu_Module
 */
class DS_Menu_Module extends FLBuilderModule {

	/** map of top-level menu-item ID => per-item mega column count (0 = use default). */
	private $mega_map = array();
	/** true when the picker has explicit selections (only those items are mega). */
	private $mega_use_picker = false;

	public function __construct() {
		parent::__construct( array(
			'name'            => __( 'Menu', 'ds-toolkit' ),
			'description'     => __( 'In-house responsive nav menu with mega-menu columns.', 'ds-toolkit' ),
			'category'        => __( 'LeagueApps', 'ds-toolkit' ),
			'dir'             => DS_TOOLKIT_PATH . 'modules/ds-menu/',
			'url'             => DS_TOOLKIT_URL . 'modules/ds-menu/',
			'partial_refresh' => true,
			'editor_export'   => false,
		) );
	}

	/** Options for the menu-picker field (built at registration time). */
	public static function menu_options() {
		$opts = array( '' => __( '— Select a menu —', 'ds-toolkit' ) );
		foreach ( wp_get_nav_menus() as $menu ) {
			$opts[ $menu->term_id ] = $menu->name;
		}
		return $opts;
	}

	/** Entry point called by includes/frontend.php. */
	public function render_nav() {
		$s       = $this->settings;
		$menu_id = isset( $s->menu ) ? $s->menu : '';
		if ( empty( $menu_id ) ) {
			if ( FLBuilderModel::is_builder_active() ) {
				echo '<p style="padding:14px">' . esc_html__( 'Select a menu in the module settings.', 'ds-toolkit' ) . '</p>';
			}
			return;
		}
		$items = wp_get_nav_menu_items( $menu_id );
		if ( empty( $items ) ) {
			return;
		}

		// Build a parent => children[] tree, keeping menu order.
		$children = array();
		foreach ( $items as $item ) {
			$children[ (int) $item->menu_item_parent ][] = $item;
		}

		$mega  = ( isset( $s->mega_enabled ) && 'yes' === $s->mega_enabled );
		$cols  = isset( $s->mega_columns ) ? max( 1, (int) $s->mega_columns ) : 3;
		$label = isset( $s->toggle_label ) ? trim( (string) $s->toggle_label ) : '';

				// Parse the picker selection from the hidden mega_items field. Stored as a
		// plain string of "id:cols" pairs joined by commas, e.g. "56410:0,56415:4"
		// (cols 0 = use the default). A plain string is used, not JSON, because Beaver's
		// save path json_decode_deep's any JSON-looking value, which a text input then
		// cannot restore. Empty = original behaviour (every top-level item with children
		// is mega). Legacy JSON / decoded-array values are also read.
		$this->mega_map        = array();
		$this->mega_use_picker = false;
		$mi = isset( $s->mega_items ) ? $s->mega_items : '';
		if ( is_array( $mi ) ) {
			foreach ( $mi as $row ) {
				if ( is_array( $row ) && isset( $row['id'] ) ) {
					$this->mega_map[ (int) $row['id'] ] = isset( $row['cols'] ) ? (int) $row['cols'] : 0;
				}
			}
		} elseif ( is_string( $mi ) && '' !== trim( $mi ) ) {
			$mi = trim( $mi );
			if ( '[' === $mi[0] ) {
				$decoded = json_decode( $mi, true );
				if ( is_array( $decoded ) ) {
					foreach ( $decoded as $row ) {
						if ( is_array( $row ) && isset( $row['id'] ) ) {
							$this->mega_map[ (int) $row['id'] ] = isset( $row['cols'] ) ? (int) $row['cols'] : 0;
						}
					}
				}
			} else {
				foreach ( explode( ',', $mi ) as $tok ) {
					$pair = explode( ':', $tok );
					$id   = (int) trim( $pair[0] );
					if ( $id ) { $this->mega_map[ $id ] = isset( $pair[1] ) ? (int) $pair[1] : 0; }
				}
			}
		}
		$this->mega_use_picker = ! empty( $this->mega_map );

		$mstyle = ( ( $s->mobile_menu_style ?? 'drill' ) === 'overlay' ) ? 'overlay' : 'drill';

		// Drawer logo (drill style): module image → Partner Logo option → site logo.
		$drill_logo = '';
		if ( 'drill' === $mstyle && ( $s->drill_logo_show ?? 'show' ) === 'show' ) {
			$drill_logo = ! empty( $s->drill_logo ) ? DS_Card::photo_url( $s->drill_logo, 'medium' ) : '';
			if ( '' === $drill_logo && function_exists( 'get_field' ) ) {
				$pl = get_field( 'partner_logo', 'option' );
				if ( $pl ) { $drill_logo = DS_Card::photo_url( $pl, 'medium' ); }
			}
			if ( '' === $drill_logo ) {
				$cl = (int) get_theme_mod( 'custom_logo' );
				if ( $cl ) { $drill_logo = (string) wp_get_attachment_image_url( $cl, 'medium' ); }
			}
		}
		$drill_overview = ( $s->drill_overview ?? 'show' ) === 'hide' ? 'hide' : 'show';
		$drill_attrs    = ' data-drill-overview="' . esc_attr( $drill_overview ) . '"';
		if ( '' !== $drill_logo ) {
			$drill_attrs .= ' data-drill-logo="' . esc_url( $drill_logo ) . '"';
			$drill_attrs .= ' data-drill-logo-align="' . ( ( $s->drill_logo_align ?? 'right' ) === 'left' ? 'left' : 'right' ) . '"';
		}

		echo '<nav class="ds-menu-wrap' . ( 'drill' === $mstyle ? ' ds-menu-wrap--drill' : '' ) . '"' . $drill_attrs . ' aria-label="' . esc_attr__( 'Primary', 'ds-toolkit' ) . '">';

		// Hamburger toggle (animates into an X via CSS when the overlay opens).
		echo '<button type="button" class="ds-menu-toggle" aria-expanded="false" aria-label="' . esc_attr__( 'Toggle menu', 'ds-toolkit' ) . '">';
		echo '<span class="ds-bars" aria-hidden="true"><span></span><span></span><span></span></span>';
		if ( '' !== $label ) {
			echo '<span class="ds-menu-toggle-label">' . esc_html( $label ) . '</span>';
		}
		echo '</button>';

		$this->render_branch( 0, $children, $mega, $cols, true );

		// Fallback close control (hidden by default — the hamburger-X closes the overlay).
		echo '<button type="button" class="ds-menu-close" aria-label="' . esc_attr__( 'Close menu', 'ds-toolkit' ) . '">&times;</button>';
		echo '</nav>';
	}

	/**
	 * Recursively render a menu branch.
	 *
	 * @param int   $parent   Parent menu-item ID (0 for top level).
	 * @param array $children parent => items map.
	 * @param bool  $mega     Mega menu enabled (module setting).
	 * @param int   $cols     Default mega column count.
	 * @param bool  $is_top   Whether this is the top-level list.
	 */
	private function render_branch( $parent, $children, $mega, $cols, $is_top ) {
		if ( empty( $children[ $parent ] ) ) {
			return;
		}

		// "Style last item as button" applies only to the last TOP-LEVEL item.
		$button_on = $is_top && isset( $this->settings->last_item_button ) && 'yes' === $this->settings->last_item_button;
		$last_id   = ( $button_on && ! empty( $children[ $parent ] ) ) ? (int) end( $children[ $parent ] )->ID : 0;

		echo '<ul class="' . ( $is_top ? 'ds-menu' : 'ds-submenu' ) . '">';		
		$sep_on = $is_top && ( $this->settings->bar_divider_style ?? 'none' ) !== 'none';
		$sep_first = true;
		foreach ( $children[ $parent ] as $item ) {
			$has_children = ! empty( $children[ $item->ID ] );
			$classes      = is_array( $item->classes ) ? $item->classes : array();
			$no_mega      = in_array( 'ds-no-mega', $classes, true );
			$item_cols    = $cols;
			foreach ( $classes as $c ) {
				if ( preg_match( '/^ds-cols-(\d+)$/', $c, $m ) ) {
					$item_cols = max( 1, (int) $m[1] );
				}
			}
			// Picker selection (when used) decides which items are mega; a per-item
				// column count set in the picker wins over a legacy ds-cols-N class.
				$picked = $is_top && isset( $this->mega_map[ (int) $item->ID ] );
				if ( $picked && (int) $this->mega_map[ (int) $item->ID ] > 0 ) {
					$item_cols = max( 1, (int) $this->mega_map[ (int) $item->ID ] );
				}
				$is_mega   = $is_top && $has_children && $mega && ! $no_mega
					&& ( $this->mega_use_picker ? $picked : true );
			$is_button = $button_on && (int) $item->ID === $last_id;

			$li_class = 'ds-menu-item';
			if ( $has_children ) { $li_class .= ' has-children'; }
			if ( $is_mega ) { $li_class .= ' is-mega'; }
			if ( $is_button ) { $li_class .= ' is-button'; }
			$extra = array_filter( $classes, function( $c ) { return $c && 0 !== strpos( $c, 'ds-cols-' ) && 'ds-no-mega' !== $c; } );
			if ( $extra ) { $li_class .= ' ' . implode( ' ', array_map( 'sanitize_html_class', $extra ) ); }

			// Bar divider: a real flex-item separator between top-level items (centres
			// correctly for every alignment incl. Justify). Skipped before the CTA button.
			if ( $sep_on ) {
				if ( ! $sep_first && ! $is_button ) { echo '<li class="ds-menu-sep" aria-hidden="true"><span></span></li>'; }
				$sep_first = false;
			}
			echo '<li class="' . esc_attr( $li_class ) . '">';
			$target = $item->target ? ' target="' . esc_attr( $item->target ) . '" rel="noopener"' : '';
			// CTA buttons never get the submenu indicator.
			$ind = $is_button ? '' : $this->indicator_markup( $has_children );
			echo '<a href="' . esc_url( $item->url ) . '"' . $target . '><span data-title="' . esc_attr( $item->title ) . '">' . esc_html( $item->title ) . '</span>' . $ind . '</a>';

			if ( $has_children ) {
				// Per-item expand control (used by the mobile overlay accordion).
				echo '<button type="button" class="ds-sub-toggle" aria-expanded="false" aria-label="' . esc_attr__( 'Expand', 'ds-toolkit' ) . '"></button>';
				if ( $is_mega ) {
					echo '<div class="ds-mega" style="--ds-cols:' . (int) $item_cols . '">';
					foreach ( $children[ $item->ID ] as $child ) {
						$ctarget = $child->target ? ' target="' . esc_attr( $child->target ) . '" rel="noopener"' : '';
						echo '<div class="ds-mega-col">';
						echo '<a class="ds-mega-head" href="' . esc_url( $child->url ) . '"' . $ctarget . '>' . esc_html( $child->title ) . '</a>';
						$this->render_branch( $child->ID, $children, false, $cols, false );
						echo '</div>';
					}
					echo '</div>';
				} else {
					$this->render_branch( $item->ID, $children, false, $cols, false );
				}
			}
			echo '</li>';
		}
		echo '</ul>';
	}

	/**
	 * Marker (arrow icon or custom text) appended inside the link of any item
	 * that has a submenu. Returns '' when the item has no children or the
	 * indicator is set to "none".
	 */
	private function indicator_markup( $has_children ) {
		if ( ! $has_children ) {
			return '';
		}
		$mode = isset( $this->settings->submenu_indicator ) ? $this->settings->submenu_indicator : 'icon';
		if ( 'none' === $mode ) {
			return '';
		}
		if ( 'text' === $mode ) {
			$t = isset( $this->settings->submenu_indicator_text ) ? trim( (string) $this->settings->submenu_indicator_text ) : '';
			return '' === $t ? '' : '<span class="ds-sub-ind ds-sub-ind-text" aria-hidden="true">' . esc_html( $t ) . '</span>';
		}
		// Arrow icon (chevron).
		return '<span class="ds-sub-ind ds-sub-ind-icon" aria-hidden="true"><svg viewBox="0 0 12 8" width="11" height="8" fill="none"><path d="M1 1.5 6 6.5 11 1.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg></span>';
	}
}

FLBuilder::register_module( 'DS_Menu_Module', array(
	'general' => array(
		'title'    => __( 'General', 'ds-toolkit' ),
		'sections' => array(
			'menu'   => array(
				'title'  => __( 'Menu', 'ds-toolkit' ),
				'fields' => array(
					'menu'      => array(
						'type'    => 'select',
						'label'   => __( 'WordPress Menu', 'ds-toolkit' ),
						'options' => DS_Menu_Module::menu_options(),
					),
					'alignment' => array(
						'type'    => 'select',
						'label'   => __( 'Alignment', 'ds-toolkit' ),
						'default' => 'right',
						'options' => array(
							'left'   => __( 'Left', 'ds-toolkit' ),
							'center' => __( 'Center', 'ds-toolkit' ),
							'right'  => __( 'Right', 'ds-toolkit' ),
							'justify' => __( 'Justify (even spacing)', 'ds-toolkit' ),
						),
					),
				),
			),
			'subind' => array(
				'title'  => __( 'Submenu Indicator', 'ds-toolkit' ),
				'fields' => array(
					'submenu_indicator'      => array(
						'type'    => 'select',
						'label'   => __( 'Indicator', 'ds-toolkit' ),
						'default' => 'icon',
						'options' => array(
							'none' => __( 'None', 'ds-toolkit' ),
							'icon' => __( 'Arrow Icon', 'ds-toolkit' ),
							'text' => __( 'Text', 'ds-toolkit' ),
						),
						'help'    => __( 'Marker shown next to items that have a submenu.', 'ds-toolkit' ),
						'toggle'  => array( 'text' => array( 'fields' => array( 'submenu_indicator_text' ) ) ),
					),
					'submenu_indicator_text' => array(
						'type'    => 'text',
						'label'   => __( 'Indicator Text', 'ds-toolkit' ),
						'default' => '+',
					),
				),
			),
			'mega'   => array(
				'title'  => __( 'Mega Menu', 'ds-toolkit' ),
				'fields' => array(
					'mega_enabled'   => array(
						'type'    => 'select',
						'label'   => __( 'Mega Menu', 'ds-toolkit' ),
						'default' => 'no',
						'options' => array( 'no' => __( 'No', 'ds-toolkit' ), 'yes' => __( 'Yes', 'ds-toolkit' ) ),
						'help'    => __( 'Lets top-level items open as a wide multi-column panel. Pick which items below; leave the picker empty to make every item that has sub-items a mega panel.', 'ds-toolkit' ),
						'toggle'  => array( 'yes' => array( 'fields' => array( 'mega_picker', 'mega_items', 'mega_columns', 'mega_width', 'mega_align', 'mega_max_width' ) ) ),
					),
					// In-builder picker: lists this menu's top-level items as clickable pills.
					// The pill UI is drawn by js/admin.js, which writes the chosen items
					// (and their per-item column counts) into the hidden mega_items field.
					'mega_picker'    => array(
						'type'    => 'raw',
						'label'   => __( 'Mega Items', 'ds-toolkit' ),
						'content' => '<style>'
							. '#fl-field-mega_items{display:none!important}'
							. '.ds-mega-picker-search{width:100%;box-sizing:border-box;margin:0 0 8px;padding:7px 10px;border:1px solid #d6d6d6;border-radius:4px;font:inherit}'
							. '.ds-mega-picker-pills{display:flex;flex-wrap:wrap;gap:6px}'
							. '.ds-mega-pill{display:inline-flex;align-items:center;gap:6px;padding:5px 10px;border:1px solid #c9d1da;border-radius:999px;background:#f3f5f7;color:#23282d;font-size:12px;line-height:1;cursor:pointer;user-select:none;transition:background .12s,border-color .12s,color .12s}'
							. '.ds-mega-pill:hover{border-color:#8c9bab}'
							. '.ds-mega-pill.is-on{background:#1f6feb;border-color:#1f6feb;color:#fff}'
							. '.ds-mega-pill.is-disabled{opacity:.45;cursor:not-allowed}'
							. '.ds-mega-pill-cols{display:none;align-items:center;gap:4px;margin-left:2px;padding-left:8px;border-left:1px solid rgba(255,255,255,.45)}'
							. '.ds-mega-pill.is-on .ds-mega-pill-cols{display:inline-flex}'
							. '.ds-mega-pill-cols button{width:16px;height:16px;line-height:14px;text-align:center;padding:0;border:0;border-radius:3px;background:rgba(255,255,255,.25);color:#fff;cursor:pointer;font-size:12px}'
							. '.ds-mega-pill-cols span{min-width:10px;text-align:center;font-variant-numeric:tabular-nums}'
							. '.ds-mega-picker-empty,.ds-mega-picker-note{color:#72777c;font-size:11px;margin:8px 0 0}'
							. '</style>'
							. '<div class="ds-mega-picker" data-loading="1">'
							. '<input type="text" class="ds-mega-picker-search" placeholder="' . esc_attr__( 'Search menu items…', 'ds-toolkit' ) . '" autocomplete="off" />'
							. '<div class="ds-mega-picker-pills"></div>'
							. '<p class="ds-mega-picker-note">' . esc_html__( 'Click an item to make it a mega panel. Use −/+ to set its columns. Only top-level items that have sub-items can be mega.', 'ds-toolkit' ) . '</p>'
							. '</div>',
					),
					// Hidden store: JSON array of { "id": <menu item id>, "cols": <n> }.
					'mega_items'     => array(
						'type'      => 'text',
						'label'     => '',
						'default'   => '',
						'className' => 'ds-mega-items-store',
					),
					'mega_columns'   => array(
						'type'    => 'unit',
						'label'   => __( 'Default Columns', 'ds-toolkit' ),
						'default' => '3',
						'help'    => __( 'Column count for mega items that do not set their own in the picker above.', 'ds-toolkit' ),
						'slider'  => array( 'min' => 1, 'max' => 6, 'step' => 1 ),
					),
					'mega_width'     => array(
						'type'    => 'select',
						'label'   => __( 'Panel Width', 'ds-toolkit' ),
						'default' => 'auto',
						'options' => array(
							'auto'     => __( 'Auto (sized to columns)', 'ds-toolkit' ),
							'bar'      => __( 'Full menu width', 'ds-toolkit' ),
							'viewport' => __( 'Full screen width', 'ds-toolkit' ),
						),
						'help'    => __( 'Auto sizes the panel under its item. Full menu width spans the menu bar. Full screen width goes edge to edge.', 'ds-toolkit' ),
						'toggle'  => array( 'auto' => array( 'fields' => array( 'mega_align', 'mega_max_width' ) ) ),
					),
					'mega_align'     => array(
						'type'    => 'select',
						'label'   => __( 'Panel Alignment', 'ds-toolkit' ),
						'default' => 'left',
						'options' => array(
							'left'   => __( 'Left edge of item', 'ds-toolkit' ),
							'center' => __( 'Centered on item', 'ds-toolkit' ),
							'right'  => __( 'Right edge of item', 'ds-toolkit' ),
						),
						'help'    => __( 'Where the auto-width panel sits relative to its menu item.', 'ds-toolkit' ),
					),
					'mega_max_width' => array(
						'type'        => 'unit',
						'label'       => __( 'Max Width', 'ds-toolkit' ),
						'default'     => '',
						'description' => 'px',
						'help'        => __( 'Optional cap on the auto-width panel. Blank keeps the default.', 'ds-toolkit' ),
						'slider'      => array( 'min' => 300, 'max' => 1200, 'step' => 10 ),
					),
				),
			),
			'resp'   => array(
				'title'  => __( 'Responsive', 'ds-toolkit' ),
				'fields' => array(
					'mobile_menu_style' => array(
						'type'    => 'select',
						'label'   => __( 'Mobile Menu Style', 'ds-toolkit' ),
						'default' => 'drill',
						'options' => array(
							'drill'   => __( 'Drill-down drawer (one level per panel)', 'ds-toolkit' ),
							'overlay' => __( 'Overlay with accordions (legacy)', 'ds-toolkit' ),
						),
						'help'    => __( 'Drill-down: submenus slide in one level at a time with a back control (Stripe-style); parents with their own URL get an "Overview" row. Desktop is unaffected. Menu content stays editable in Appearance → Menus.', 'ds-toolkit' ),
						'toggle'  => array( 'drill' => array( 'fields' => array( 'drill_overview' ) ) ),
					),
					'drill_overview' => array(
						'type'    => 'select',
						'label'   => __( 'Parent Overview Row', 'ds-toolkit' ),
						'default' => 'show',
						'options' => array(
							'show' => __( 'Show', 'ds-toolkit' ),
							'hide' => __( 'Hide', 'ds-toolkit' ),
						),
						'help'    => __( 'Show a link to the parent item above its child links in drilled panels. Hide to list child links only. Desktop is unaffected.', 'ds-toolkit' ),
					),
					'breakpoint'   => array(
						'type'        => 'unit',
						'label'       => __( 'Mobile Breakpoint', 'ds-toolkit' ),
						'default'     => '1000',
						'description' => 'px',
						'help'        => __( 'At or below this width the menu collapses to the hamburger menu.', 'ds-toolkit' ),
					),
					'toggle_label' => array(
						'type'    => 'text',
						'label'   => __( 'Hamburger Label', 'ds-toolkit' ),
						'default' => '',
						'help'    => __( 'Optional text to the right of the hamburger icon (e.g. "Menu"). Blank = icon only.', 'ds-toolkit' ),
					),
				),
			),
		),
	),
	'style'   => array(
		'title'    => __( 'Style', 'ds-toolkit' ),
		'sections' => array(
			'bar'  => array(
				'title'  => __( 'Menu Bar', 'ds-toolkit' ),
				'fields' => array(
					'text_color'   => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Text Color', 'ds-toolkit' ), 'show_reset' => true, 'show_alpha' => true ),
					'hover_color'  => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Text Hover / Active Color', 'ds-toolkit' ), 'show_reset' => true, 'show_alpha' => true ),
					'bar_divider_style'  => array(
						'type'    => 'select',
						'label'   => __( 'Item Divider', 'ds-toolkit' ),
						'default' => 'none',
						'options' => array(
							'none'   => __( 'None', 'ds-toolkit' ),
							'solid'  => __( 'Solid', 'ds-toolkit' ),
							'dashed' => __( 'Dashed', 'ds-toolkit' ),
							'dotted' => __( 'Dotted', 'ds-toolkit' ),
						),
						'help'    => __( 'A small vertical divider between top-level items on the desktop bar — pairs nicely with the Justify alignment. Hidden in the mobile overlay and never shown before the CTA button.', 'ds-toolkit' ),
						'toggle'  => array(
							'solid'  => array( 'fields' => array( 'bar_divider_color', 'bar_divider_height', 'bar_divider_width' ) ),
							'dashed' => array( 'fields' => array( 'bar_divider_color', 'bar_divider_height', 'bar_divider_width' ) ),
							'dotted' => array( 'fields' => array( 'bar_divider_color', 'bar_divider_height', 'bar_divider_width' ) ),
						),
					),
					'bar_divider_color'  => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Divider Color', 'ds-toolkit' ), 'default' => 'var(--fl-global-line-color)', 'show_reset' => true, 'show_alpha' => true ),
					'bar_divider_height' => array( 'type' => 'unit', 'label' => __( 'Divider Height', 'ds-toolkit' ), 'default' => '18', 'description' => 'px', 'slider' => array( 'min' => 6, 'max' => 60, 'step' => 1 ) ),
					'bar_divider_width'  => array( 'type' => 'unit', 'label' => __( 'Divider Width', 'ds-toolkit' ), 'default' => '1', 'description' => 'px', 'slider' => array( 'min' => 1, 'max' => 6, 'step' => 1 ) ),
					'hover_weight' => array(
						'type'    => 'select',
						'label'   => __( 'Hover Font Weight', 'ds-toolkit' ),
						'default' => '',
						'options' => array(
							''    => __( 'No change', 'ds-toolkit' ),
							'400' => __( 'Normal (400)', 'ds-toolkit' ),
							'500' => __( 'Medium (500)', 'ds-toolkit' ),
							'600' => __( 'Semi-bold (600)', 'ds-toolkit' ),
							'700' => __( 'Bold (700)', 'ds-toolkit' ),
							'800' => __( 'Extra-bold (800)', 'ds-toolkit' ),
							'900' => __( 'Black (900)', 'ds-toolkit' ),
						),
						'help'    => __( 'Make top-level links heavier on hover and for the current page. Their width is pre-reserved so the bar never shifts when the weight changes.', 'ds-toolkit' ),
					),
					'typography'   => array( 'type' => 'typography', 'label' => __( 'Typography', 'ds-toolkit' ), 'responsive' => true, 'preview' => array( 'type' => 'css', 'selector' => '.ds-menu > .ds-menu-item > a' ) ),
				),
			),
			'hover' => array(
				'title'  => __( 'Hover Effect', 'ds-toolkit' ),
				'fields' => array(
					'hover_anim'   => array(
						'type'    => 'select',
						'label'   => __( 'Hover Animation', 'ds-toolkit' ),
						'default' => 'none',
						'options' => array(
							'none'             => __( 'None (color only)', 'ds-toolkit' ),
							'underline'        => __( 'Underline — slide in', 'ds-toolkit' ),
							'underline_center' => __( 'Underline — grow from center', 'ds-toolkit' ),
							'underline_fade'   => __( 'Underline — fade in', 'ds-toolkit' ),
							'raise'            => __( 'Raise (nudge up)', 'ds-toolkit' ),
							'grow'             => __( 'Grow (scale up)', 'ds-toolkit' ),
						),
						'help'    => __( 'Animated effect on top-level links on hover, also shown on the current page.', 'ds-toolkit' ),
						'toggle'  => array(
							'underline'        => array( 'fields' => array( 'hover_anim_color', 'hover_anim_size' ) ),
							'underline_center' => array( 'fields' => array( 'hover_anim_color', 'hover_anim_size' ) ),
							'underline_fade'   => array( 'fields' => array( 'hover_anim_color', 'hover_anim_size' ) ),
						),
					),
					'hover_anim_color' => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Underline Color', 'ds-toolkit' ), 'show_reset' => true, 'show_alpha' => true, 'help' => __( 'Blank uses the Text Hover / Active Color.', 'ds-toolkit' ) ),
					'hover_anim_size'  => array( 'type' => 'unit', 'label' => __( 'Underline Thickness', 'ds-toolkit' ), 'default' => '2', 'description' => 'px', 'slider' => array( 'min' => 1, 'max' => 6, 'step' => 1 ) ),
				),
			),
			'space' => array(
				'title'  => __( 'Item Size & Spacing', 'ds-toolkit' ),
				'fields' => array(
					'item_spacing' => array( 'type' => 'unit', 'label' => __( 'Item Spacing', 'ds-toolkit' ), 'default' => '20', 'description' => 'px', 'help' => __( 'Gap between top-level items (sets the default left / right link padding).', 'ds-toolkit' ), 'slider' => array( 'min' => 0, 'max' => 80, 'step' => 1 ) ),
					'bar_pad_v'    => array( 'type' => 'unit', 'label' => __( 'Item Vertical Padding', 'ds-toolkit' ), 'default' => '10', 'description' => 'px', 'help' => __( 'Top / bottom padding inside each top-level link — sets the compact item height. The link is vertically centred in the bar; the dropdown still opens from the bar bottom.', 'ds-toolkit' ), 'slider' => array( 'min' => 0, 'max' => 30, 'step' => 1 ) ),
					'bar_pad_h'    => array( 'type' => 'unit', 'label' => __( 'Item Horizontal Padding', 'ds-toolkit' ), 'default' => '', 'description' => 'px', 'help' => __( 'Left / right padding inside each top-level link. Blank = derived from Item Spacing.', 'ds-toolkit' ), 'slider' => array( 'min' => 0, 'max' => 40, 'step' => 1 ) ),
				),
			),
			'pill' => array(
				'title'  => __( 'Item Background (Pill)', 'ds-toolkit' ),
				'fields' => array(
					'pill_enabled' => array(
						'type'    => 'select',
						'label'   => __( 'Item Background', 'ds-toolkit' ),
						'default' => 'no',
						'options' => array( 'no' => __( 'No', 'ds-toolkit' ), 'yes' => __( 'Yes', 'ds-toolkit' ) ),
						'help'    => __( 'Show a rounded background (pill) behind top-level items on hover and for the current page. The CTA button item is never given a pill.', 'ds-toolkit' ),
						'toggle'  => array( 'yes' => array( 'fields' => array( 'pill_bg', 'pill_text', 'pill_bg_active', 'pill_text_active', 'pill_radius', 'pill_pad_v', 'pill_pad_h' ) ) ),
					),
					'pill_bg'          => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Hover Background', 'ds-toolkit' ), 'show_reset' => true, 'show_alpha' => true, 'help' => __( 'Background of the pill on hover.', 'ds-toolkit' ) ),
					'pill_text'        => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Hover Text Color', 'ds-toolkit' ), 'show_reset' => true, 'show_alpha' => true, 'help' => __( 'Blank uses the Text Hover / Active Color.', 'ds-toolkit' ) ),
					'pill_bg_active'   => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Active / Current Background', 'ds-toolkit' ), 'show_reset' => true, 'show_alpha' => true, 'help' => __( 'Pill background for the current-page item. Blank uses the hover background.', 'ds-toolkit' ) ),
					'pill_text_active' => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Active / Current Text', 'ds-toolkit' ), 'show_reset' => true, 'show_alpha' => true, 'help' => __( 'Blank uses the Hover Text Color.', 'ds-toolkit' ) ),
					'pill_radius'      => array( 'type' => 'unit', 'label' => __( 'Corner Radius', 'ds-toolkit' ), 'default' => '999', 'description' => 'px', 'help' => __( '999 = full stadium pill. Lower for a rounded rectangle.', 'ds-toolkit' ), 'slider' => array( 'min' => 0, 'max' => 40, 'step' => 1 ) ),
					'pill_pad_v'       => array( 'type' => 'unit', 'label' => __( 'Padding — Vertical', 'ds-toolkit' ), 'default' => '8', 'description' => 'px', 'slider' => array( 'min' => 0, 'max' => 24, 'step' => 1 ) ),
					'pill_pad_h'       => array( 'type' => 'unit', 'label' => __( 'Padding — Horizontal', 'ds-toolkit' ), 'default' => '16', 'description' => 'px', 'slider' => array( 'min' => 0, 'max' => 40, 'step' => 1 ) ),
				),
			),
			'drop' => array(
				'title'  => __( 'Dropdown / Mega', 'ds-toolkit' ),
				'fields' => array(
					'dropdown_bg'            => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Background', 'ds-toolkit' ), 'default' => 'var(--fl-global-white)', 'show_reset' => true, 'show_alpha' => true ),
					'dropdown_text'          => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Text Color', 'ds-toolkit' ), 'show_reset' => true, 'show_alpha' => true ),
					'dropdown_text_hover'    => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Text Hover Color', 'ds-toolkit' ), 'show_reset' => true, 'show_alpha' => true ),
					'dropdown_hover_bg'      => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Item Hover Background', 'ds-toolkit' ), 'show_reset' => true, 'show_alpha' => true, 'help' => __( 'Background highlight behind a dropdown / mega link on hover (desktop).', 'ds-toolkit' ) ),
					'dropdown_hover_radius'  => array( 'type' => 'unit', 'label' => __( 'Item Hover Radius', 'ds-toolkit' ), 'default' => '', 'description' => 'px', 'help' => __( 'Corner rounding of the hover highlight. Blank = square.', 'ds-toolkit' ), 'slider' => array( 'min' => 0, 'max' => 20, 'step' => 1 ) ),
					'dropdown_radius'        => array( 'type' => 'unit', 'label' => __( 'Corner Radius', 'ds-toolkit' ), 'description' => 'px', 'help' => __( 'Rounding of the dropdown & mega panel corners. Blank keeps the default.', 'ds-toolkit' ), 'slider' => array( 'min' => 0, 'max' => 30, 'step' => 1 ) ),
					'dropdown_gap'           => array( 'type' => 'unit', 'label' => __( 'Gap From Menu Bar', 'ds-toolkit' ), 'default' => '0', 'description' => 'px', 'help' => __( 'Vertical space between the menu bar and the dropdown / mega panel (desktop). A transparent bridge keeps it open while moving the cursor across the gap.', 'ds-toolkit' ), 'slider' => array( 'min' => 0, 'max' => 40, 'step' => 1 ) ),
					'dropdown_pad_v'         => array( 'type' => 'unit', 'label' => __( 'Item Padding — Vertical', 'ds-toolkit' ), 'description' => 'px', 'help' => __( 'Top/bottom padding of dropdown & mega links (desktop). Blank keeps the default.', 'ds-toolkit' ), 'slider' => array( 'min' => 0, 'max' => 30, 'step' => 1 ) ),
					'dropdown_pad_h'         => array( 'type' => 'unit', 'label' => __( 'Item Padding — Horizontal', 'ds-toolkit' ), 'description' => 'px', 'slider' => array( 'min' => 0, 'max' => 40, 'step' => 1 ) ),
					'dropdown_typography'    => array( 'type' => 'typography', 'label' => __( 'Submenu Label Typography', 'ds-toolkit' ), 'responsive' => true, 'preview' => array( 'type' => 'css', 'selector' => '.ds-submenu a, .ds-mega a' ) ),
					'dropdown_divider_style' => array(
						'type'    => 'select',
						'label'   => __( 'Divider Style', 'ds-toolkit' ),
						'default' => 'none',
						'options' => array(
							'none'   => __( 'None', 'ds-toolkit' ),
							'solid'  => __( 'Solid', 'ds-toolkit' ),
							'dashed' => __( 'Dashed', 'ds-toolkit' ),
							'dotted' => __( 'Dotted', 'ds-toolkit' ),
						),
						'help'    => __( 'Line between dropdown / mega submenu items.', 'ds-toolkit' ),
						'toggle'  => array(
							'solid'  => array( 'fields' => array( 'dropdown_divider_color', 'dropdown_divider_width' ) ),
							'dashed' => array( 'fields' => array( 'dropdown_divider_color', 'dropdown_divider_width' ) ),
							'dotted' => array( 'fields' => array( 'dropdown_divider_color', 'dropdown_divider_width' ) ),
						),
					),
					'dropdown_divider_color' => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Divider Color', 'ds-toolkit' ), 'default' => 'eeeeee', 'show_reset' => true, 'show_alpha' => true ),
					'dropdown_divider_width' => array( 'type' => 'unit', 'label' => __( 'Divider Width', 'ds-toolkit' ), 'default' => '1', 'description' => 'px', 'slider' => array( 'min' => 1, 'max' => 6, 'step' => 1 ) ),
						'mega_head_color'         => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Mega Heading Color', 'ds-toolkit' ), 'show_reset' => true, 'show_alpha' => true, 'help' => __( 'Color of the bold column-heading links inside a mega panel.', 'ds-toolkit' ) ),
						'mega_head_hover_color'   => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Mega Heading Hover Color', 'ds-toolkit' ), 'show_reset' => true, 'show_alpha' => true ),
						'mega_head_typography'    => array( 'type' => 'typography', 'label' => __( 'Mega Heading Typography', 'ds-toolkit' ), 'responsive' => true, 'preview' => array( 'type' => 'css', 'selector' => '.ds-mega-col .ds-mega-head' ) ),
						'mega_head_divider'       => array(
							'type'    => 'select',
							'label'   => __( 'Mega Heading Underline', 'ds-toolkit' ),
							'default' => 'none',
							'options' => array(
								'none'   => __( 'None', 'ds-toolkit' ),
								'solid'  => __( 'Solid', 'ds-toolkit' ),
								'dashed' => __( 'Dashed', 'ds-toolkit' ),
								'dotted' => __( 'Dotted', 'ds-toolkit' ),
							),
							'help'    => __( 'Optional underline below each mega column heading.', 'ds-toolkit' ),
							'toggle'  => array(
								'solid'  => array( 'fields' => array( 'mega_head_divider_color' ) ),
								'dashed' => array( 'fields' => array( 'mega_head_divider_color' ) ),
								'dotted' => array( 'fields' => array( 'mega_head_divider_color' ) ),
							),
						),
						'mega_head_divider_color' => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Underline Color', 'ds-toolkit' ), 'show_reset' => true, 'show_alpha' => true ),
						'mega_sub_indent'         => array( 'type' => 'unit', 'label' => __( 'Mega Sub-item Indent', 'ds-toolkit' ), 'default' => '', 'description' => 'px', 'help' => __( 'Left indent for the links under each mega column heading (e.g. the teams under a "U16" heading). Applies to mega panels only.', 'ds-toolkit' ), 'slider' => array( 'min' => 0, 'max' => 40, 'step' => 1 ) ),
				),
			),
			'btn'  => array(
				'title'  => __( 'CTA Button (Last Item)', 'ds-toolkit' ),
				'fields' => array(
					'last_item_button'  => array(
						'type'    => 'select',
						'label'   => __( 'Style Last Item as Button', 'ds-toolkit' ),
						'default' => 'no',
						'options' => array( 'no' => __( 'No', 'ds-toolkit' ), 'yes' => __( 'Yes', 'ds-toolkit' ) ),
						'help'    => __( 'Turns the last top-level menu item into a button so it pops (e.g. "Join Now").', 'ds-toolkit' ),
						'toggle'  => array( 'yes' => array( 'fields' => array( 'button_global', 'button_bg', 'button_text', 'button_bg_hover', 'button_text_hover', 'button_radius', 'button_padding', 'button_typography', 'button_full_mobile', 'button_mobile_bg', 'button_mobile_text', 'button_mobile_bg_hover', 'button_mobile_text_hover' ) ) ),
					),
					'button_global'     => array( 'type' => 'select', 'label' => __( 'Use Global Button Style', 'ds-toolkit' ), 'default' => 'yes', 'options' => array( 'yes' => __( 'Yes (match site Button)', 'ds-toolkit' ), 'no' => __( 'No (custom below)', 'ds-toolkit' ) ), 'help' => __( 'On by default: matches the site Button style from Theme Setting → Elements → Button (background, hover, border radius, typography), kept in sync. Switch to "No" to use the custom desktop colors / radius below instead.', 'ds-toolkit' ) ),
					'button_bg'         => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Background', 'ds-toolkit' ), 'default' => 'var(--fl-global-button)', 'show_reset' => true, 'show_alpha' => true ),
					'button_text'       => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Text Color', 'ds-toolkit' ), 'default' => 'var(--fl-global-white)', 'show_reset' => true, 'show_alpha' => true ),
					'button_bg_hover'   => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Background Hover', 'ds-toolkit' ), 'show_reset' => true, 'show_alpha' => true ),
					'button_text_hover' => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Text Hover', 'ds-toolkit' ), 'show_reset' => true, 'show_alpha' => true ),
					'button_radius'     => array( 'type' => 'unit', 'label' => __( 'Border Radius', 'ds-toolkit' ), 'default' => '', 'description' => 'px', 'help' => __( 'Blank = sync from the global Button radius (Theme Setting → Elements → Button). Set a value to override.', 'ds-toolkit' ), 'slider' => array( 'min' => 0, 'max' => 50, 'step' => 1 ) ),
					'button_padding'    => array( 'type' => 'unit', 'label' => __( 'Horizontal Padding', 'ds-toolkit' ), 'default' => '24', 'description' => 'px', 'slider' => array( 'min' => 8, 'max' => 60, 'step' => 1 ) ),
					'button_typography' => array( 'type' => 'typography', 'label' => __( 'Typography', 'ds-toolkit' ), 'responsive' => true, 'preview' => array( 'type' => 'css', 'selector' => '.ds-menu > .ds-menu-item.is-button > a' ) ),
					'button_full_mobile'       => array( 'type' => 'select', 'label' => __( 'Full Width in Mobile Overlay', 'ds-toolkit' ), 'default' => 'yes', 'options' => array( 'yes' => __( 'Yes', 'ds-toolkit' ), 'no' => __( 'No', 'ds-toolkit' ) ) ),
					'button_mobile_bg'         => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Mobile Background', 'ds-toolkit' ), 'show_reset' => true, 'show_alpha' => true, 'help' => __( 'Overlay CTA colors. Blank = inverted (overlay text-colour fill, overlay-bg text).', 'ds-toolkit' ) ),
					'button_mobile_text'       => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Mobile Text', 'ds-toolkit' ), 'show_reset' => true, 'show_alpha' => true ),
					'button_mobile_bg_hover'   => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Mobile Background Hover', 'ds-toolkit' ), 'show_reset' => true, 'show_alpha' => true ),
					'button_mobile_text_hover' => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Mobile Text Hover', 'ds-toolkit' ), 'show_reset' => true, 'show_alpha' => true ),
				),
			),
			'ham'  => array(
				'title'  => __( 'Hamburger Button', 'ds-toolkit' ),
				'fields' => array(
					'toggle_color'        => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Icon / Label Color', 'ds-toolkit' ), 'show_reset' => true, 'show_alpha' => true ),
					'toggle_bg'           => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Background', 'ds-toolkit' ), 'show_reset' => true, 'show_alpha' => true, 'help' => __( 'Leave blank for no background.', 'ds-toolkit' ) ),
					'toggle_border_color' => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Border Color', 'ds-toolkit' ), 'show_reset' => true, 'show_alpha' => true ),
					'toggle_border_width' => array( 'type' => 'unit', 'label' => __( 'Border Width', 'ds-toolkit' ), 'default' => '0', 'description' => 'px', 'help' => __( 'Set to 0 for no border.', 'ds-toolkit' ), 'slider' => array( 'min' => 0, 'max' => 6, 'step' => 1 ) ),
					'toggle_radius'       => array( 'type' => 'unit', 'label' => __( 'Border Radius', 'ds-toolkit' ), 'default' => '0', 'description' => 'px', 'slider' => array( 'min' => 0, 'max' => 40, 'step' => 1 ) ),
					'toggle_icon_size'        => array( 'type' => 'unit', 'label' => __( 'Icon Size', 'ds-toolkit' ), 'default' => '26', 'description' => 'px', 'slider' => array( 'min' => 16, 'max' => 44, 'step' => 1 ) ),
					'toggle_label_typography' => array( 'type' => 'typography', 'label' => __( 'Label Typography', 'ds-toolkit' ), 'responsive' => true, 'preview' => array( 'type' => 'css', 'selector' => '.ds-menu-toggle-label' ) ),
				),
			),
			'mob'  => array(
				'title'  => __( 'Mobile Overlay', 'ds-toolkit' ),
				'fields' => array(
					'drill_logo_show'   => array(
						'type'    => 'select',
						'label'   => __( 'Drawer Logo', 'ds-toolkit' ),
						'default' => 'show',
						'options' => array( 'show' => __( 'Show', 'ds-toolkit' ), 'hide' => __( 'Hide', 'ds-toolkit' ) ),
						'toggle'  => array( 'show' => array( 'fields' => array( 'drill_logo', 'drill_logo_align', 'drill_logo_height' ) ) ),
						'help'    => __( 'Shows the site logo at the top of the drill-down drawer (drill style only). Blank image = the Partner Logo from Partner Setting, then the site logo.', 'ds-toolkit' ),
					),
					'drill_logo'        => array( 'type' => 'photo', 'label' => __( 'Drawer Logo Image', 'ds-toolkit' ), 'show_remove' => true, 'connections' => array( 'photo' ), 'help' => __( 'Override image. Blank = Partner Logo → site logo.', 'ds-toolkit' ) ),
					'drill_logo_align'  => array( 'type' => 'select', 'label' => __( 'Drawer Logo Position', 'ds-toolkit' ), 'default' => 'right', 'options' => array( 'right' => __( 'Top Right (beside the close X)', 'ds-toolkit' ), 'left' => __( 'Top Left', 'ds-toolkit' ) ) ),
					'drill_logo_height' => array( 'type' => 'unit', 'label' => __( 'Drawer Logo Height', 'ds-toolkit' ), 'default' => '30', 'description' => 'px', 'slider' => array( 'min' => 16, 'max' => 64, 'step' => 1 ), 'preview' => array( 'type' => 'css', 'selector' => '.ds-drill-brand img', 'property' => 'height', 'unit' => 'px' ) ),
					'overlay_bg'           => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Overlay Background', 'ds-toolkit' ), 'default' => 'var(--fl-global-dark-background)', 'show_reset' => true, 'show_alpha' => true ),
					// --- Menu items (top level) in the overlay ---
					'overlay_text'          => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Menu Item Color', 'ds-toolkit' ), 'default' => 'var(--fl-global-white)', 'show_reset' => true, 'show_alpha' => true ),
					'overlay_text_hover'    => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Menu Item Hover / Active Color', 'ds-toolkit' ), 'show_reset' => true, 'show_alpha' => true ),
					'overlay_item_bg'       => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Menu Item Background', 'ds-toolkit' ), 'show_reset' => true, 'show_alpha' => true ),
					'mobile_typography'     => array( 'type' => 'typography', 'label' => __( 'Menu Label Typography', 'ds-toolkit' ), 'responsive' => true, 'preview' => array( 'type' => 'css', 'selector' => '.ds-menu-wrap.ds-menu-open .ds-menu > .ds-menu-item > a' ) ),
					// --- Submenu / mega items in the overlay ---
					'overlay_subtext'       => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Submenu Item Color', 'ds-toolkit' ), 'show_reset' => true, 'show_alpha' => true, 'help' => __( 'Sub-item / mega link color in the overlay. Defaults to the menu item color.', 'ds-toolkit' ) ),
					'overlay_subtext_hover' => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Submenu Item Hover Color', 'ds-toolkit' ), 'show_reset' => true, 'show_alpha' => true ),
					'overlay_sub_bg'        => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Submenu Item Background', 'ds-toolkit' ), 'show_reset' => true, 'show_alpha' => true ),
					'mobile_subtypography'  => array( 'type' => 'typography', 'label' => __( 'Submenu Label Typography', 'ds-toolkit' ), 'responsive' => true, 'help' => __( 'Font for sub / mega links in the overlay. Falls back to the desktop Submenu Label Typography.', 'ds-toolkit' ), 'preview' => array( 'type' => 'css', 'selector' => '.ds-menu-wrap.ds-menu-open .ds-submenu a, .ds-menu-wrap.ds-menu-open .ds-mega a' ) ),
					'mobile_divider_style' => array(
						'type'    => 'select',
						'label'   => __( 'Divider Style', 'ds-toolkit' ),
						'default' => 'solid',
						'options' => array(
							'none'   => __( 'None', 'ds-toolkit' ),
							'solid'  => __( 'Solid', 'ds-toolkit' ),
							'dashed' => __( 'Dashed', 'ds-toolkit' ),
							'dotted' => __( 'Dotted', 'ds-toolkit' ),
						),
						'help'    => __( 'Line between items in the mobile overlay.', 'ds-toolkit' ),
						'toggle'  => array(
							'solid'  => array( 'fields' => array( 'mobile_divider_color', 'mobile_divider_width' ) ),
							'dashed' => array( 'fields' => array( 'mobile_divider_color', 'mobile_divider_width' ) ),
							'dotted' => array( 'fields' => array( 'mobile_divider_color', 'mobile_divider_width' ) ),
						),
					),
					'mobile_divider_color' => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Divider Color', 'ds-toolkit' ), 'show_reset' => true, 'show_alpha' => true ),
					'mobile_divider_width' => array( 'type' => 'unit', 'label' => __( 'Divider Width', 'ds-toolkit' ), 'default' => '1', 'description' => 'px', 'slider' => array( 'min' => 1, 'max' => 6, 'step' => 1 ) ),
				),
			),
		),
	),
) );

/* ------------------------------------------------------------------ Builder UI */

/**
 * Builder-UI script: draws the "Mega Items" pill picker inside the module
 * settings and writes the selection into the hidden mega_items field.
 */
add_action( 'fl_builder_ui_enqueue_scripts', function () {
	wp_enqueue_script(
		'ds-menu-admin',
		DS_TOOLKIT_URL . 'modules/ds-menu/js/admin.js',
		array( 'jquery' ),
		DS_TOOLKIT_VERSION,
		true
	);
	wp_localize_script( 'ds-menu-admin', 'DSMenuPicker', array(
		'ajaxurl' => admin_url( 'admin-ajax.php' ),
		'nonce'   => wp_create_nonce( 'ds_menu_picker' ),
	) );
} );

/**
 * AJAX: return the TOP-LEVEL items of a nav menu for the picker.
 * Each item: { id, title, has_children }. Only items with children can be mega.
 */
add_action( 'wp_ajax_ds_menu_top_items', function () {
	check_ajax_referer( 'ds_menu_picker', 'nonce' );
	if ( ! current_user_can( 'edit_theme_options' ) && ! current_user_can( 'edit_posts' ) ) {
		wp_send_json_error( array(), 403 );
	}
	$menu_id = isset( $_REQUEST['menu'] ) ? absint( $_REQUEST['menu'] ) : 0;
	$out     = array();
	if ( $menu_id ) {
		$items = wp_get_nav_menu_items( $menu_id );
		if ( $items ) {
			$has_kids = array();
			foreach ( $items as $it ) {
				$pid = (int) $it->menu_item_parent;
				if ( $pid ) { $has_kids[ $pid ] = true; }
			}
			foreach ( $items as $it ) {
				if ( 0 !== (int) $it->menu_item_parent ) { continue; }
				$out[] = array(
					'id'           => (int) $it->ID,
					'title'        => wp_strip_all_tags( $it->title ),
					'has_children' => ! empty( $has_kids[ (int) $it->ID ] ),
				);
			}
		}
	}
	wp_send_json_success( $out );
} );
