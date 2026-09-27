<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Registers the in-house "Leagueapps Hero Banner" Beaver Builder module
 * (modules/ds-hero/). Blueprint generation 6+.
 */
class DS_Hero {

	private $settings;

	public function __construct( $settings = array() ) {
		$this->settings = $settings;
	}

	public function init() {
		add_action( 'init', array( $this, 'register_module' ), 20 );
		// Right after Beaver Themer prints the header (fl_before_header, 999), before any content is parsed.
		add_action( 'fl_after_header', array( $this, 'overlay_header_clearance' ), 1 );
	}

	/**
	 * Page banners clear a Themer header set to Overlay (Alipio 2026-09-27: design must come from settings, not
	 * template CSS). The space reserved is the header's own measured height, so it follows whatever the header layout's
	 * settings make it: a taller header, a top bar, an edit in Beaver Builder. A header that does not overlay (Home 1)
	 * gets nothing. The page banner reserves it as top padding, so its background still runs under the header; any other
	 * first row of a Themer layout (the 404) gets it as a transparent top border, which keeps the row's own padding
	 * setting and lets its background run under the header too. Measured here, before the content exists, so nothing
	 * moves after load; re-measured on resize, never while the header is in its smaller scrolled state.
	 */
	public function overlay_header_clearance() {
		echo "<style id=\"ds-overlay-header-css\">html.ds-overlay-header .ds-hero--banner{padding-top:var(--ds-overlay-header,0px)}"
			. "html.ds-overlay-header .fl-page-content>.fl-builder-content:first-child>.fl-row:first-child:not(:has(.ds-hero))>.fl-row-content-wrap{border-top:var(--ds-overlay-header,0px) solid transparent}</style>\n";
		echo "<script id=\"ds-overlay-header-js\">(function(){var h=document.querySelector('.fl-builder-content[data-type=\"header\"][data-overlay=\"1\"]');if(!h){return;}var r=document.documentElement;"
			. "function m(){if(h.classList.contains('fl-theme-builder-header-scrolled')){return;}var v=h.offsetHeight;if(v>0){r.style.setProperty('--ds-overlay-header',v+'px');r.classList.add('ds-overlay-header');}}"
			. "m();window.addEventListener('resize',m);window.addEventListener('load',m);})();</script>\n";
	}

	public function register_module() {
		if ( class_exists( 'FLBuilder' ) && class_exists( 'FLBuilderModule' ) ) {
			require_once DS_TOOLKIT_PATH . 'modules/ds-hero/ds-hero.php';
		}
	}
}
