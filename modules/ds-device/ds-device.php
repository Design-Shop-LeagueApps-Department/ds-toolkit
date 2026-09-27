<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * LeagueApps Device: shows images and videos inside a device frame (phone, tablet or desktop).
 *
 * Media: one image, several images, one video, or a mixed list of images and videos (the same slide model as the
 * Hero Banner's Mixed Media). Several items either slide or fade on a timer, or stay static and move only when the
 * visitor uses the dots or swipes. The frame is drawn in CSS (no device photos), so its colours follow the site's
 * global colours and it stays sharp at any size.
 *
 * Accessibility: anything that moves gets a pause/play button (WCAG 2.2.2), reduced-motion visitors get no autoplay,
 * dots are 24px targets with aria-current, images keep their Media Library alt text, and a video with no description
 * is hidden from screen readers as decoration.
 *
 * Markup here, styles in css/frontend.css + includes/frontend.css.php, behaviour in js/frontend.js.
 *
 * @class DS_Device_Module
 */
class DS_Device_Module extends FLBuilderModule {

	public function __construct() {
		parent::__construct( array(
			'name'            => __( 'Device', 'ds-toolkit' ),
			'description'     => __( 'Images and videos inside a phone, tablet or desktop frame; single, slideshow or mixed media.', 'ds-toolkit' ),
			'category'        => __( 'LeagueApps', 'ds-toolkit' ),
			'dir'             => DS_TOOLKIT_PATH . 'modules/ds-device/',
			'url'             => DS_TOOLKIT_URL . 'modules/ds-device/',
			'partial_refresh' => true,
			'editor_export'   => false,
		) );
	}

	/** Device keys: phone | tablet | desktop. */
	public function device() {
		$d = $this->settings->device ?? 'phone';
		return in_array( $d, array( 'phone', 'tablet', 'desktop' ), true ) ? $d : 'phone';
	}

	/** Resolve a Media Library video (id / {id} array|object) with a URL fallback. */
	private function video_src( $media, $url ) {
		if ( ! empty( $media ) ) {
			$id = is_object( $media ) ? ( $media->id ?? 0 ) : ( is_array( $media ) ? ( $media['id'] ?? 0 ) : $media );
			if ( is_numeric( $id ) ) {
				$u = (string) wp_get_attachment_url( (int) $id );
				if ( '' !== $u ) { return $u; }
			}
		}
		return trim( (string) $url );
	}

	/** Ids from a multiple-photos field (array of ids, or a comma list). */
	private function photo_ids( $raw ) {
		if ( is_object( $raw ) ) { $raw = (array) $raw; }
		$ids = is_array( $raw ) ? $raw : explode( ',', (string) $raw );
		return array_values( array_filter( array_map( 'absint', $ids ) ) );
	}

	/**
	 * Normalised media items: [{type: image|video, id, url, poster, label, advance}].
	 * Items with no usable media are skipped, so the markup, the dots and the script always agree.
	 */
	public function items() {
		$s    = $this->settings;
		$type = $s->media_type ?? 'image';
		$out  = array();
		$img  = function ( $val, $label = '' ) use ( &$out ) {
			$id  = is_numeric( $val ) ? (int) $val : 0;
			$url = DS_Card::photo_url( $val, 'full' );
			if ( '' !== $url ) { $out[] = array( 'type' => 'image', 'id' => $id, 'url' => $url, 'poster' => '', 'label' => (string) $label, 'advance' => 'interval' ); }
		};
		$vid  = function ( $media, $url, $poster, $label, $advance ) use ( &$out ) {
			$src = $this->video_src( $media, $url );
			if ( '' === $src ) { return; }
			$out[] = array( 'type' => 'video', 'id' => 0, 'url' => $src, 'poster' => $poster ? DS_Card::photo_url( $poster, 'large' ) : '', 'label' => (string) $label, 'advance' => 'end' === $advance ? 'end' : 'interval' );
		};

		if ( 'slideshow' === $type ) {
			foreach ( $this->photo_ids( $s->photos ?? array() ) as $id ) { $img( $id ); }
		} elseif ( 'video' === $type ) {
			$vid( $s->video_media ?? '', $s->video_url ?? '', $s->video_poster ?? '', $s->video_label ?? '', 'interval' );
		} elseif ( 'mixed' === $type ) {
			foreach ( (array) ( $s->mixed_slides ?? array() ) as $slide ) {
				$slide = (object) $slide;
				if ( ( $slide->slide_type ?? 'image' ) === 'video' ) {
					$vid( $slide->video_media ?? '', $slide->video_url ?? '', $slide->video_poster ?? '', $slide->video_label ?? '', $slide->video_advance ?? 'end' );
				} elseif ( ! empty( $slide->photo ) ) {
					$img( $slide->photo );
				}
			}
		} elseif ( ! empty( $s->photo ) ) {
			$img( $s->photo );
		}
		return $out;
	}

	/** Motion for a multi-item device: slide | fade | static. */
	private function motion() {
		$m = $this->settings->motion ?? 'slide';
		return in_array( $m, array( 'slide', 'fade', 'static' ), true ) ? $m : 'slide';
	}

	/** The site's own address for the browser bar, unless the editor typed one. */
	private function browser_url() {
		$u = trim( (string) ( $this->settings->browser_url ?? '' ) );
		if ( '' === $u ) { $u = (string) wp_parse_url( home_url(), PHP_URL_HOST ); }
		return preg_replace( '#^https?://#i', '', $u );
	}

	/** One media item's inner markup. */
	private function render_media( $it, $index, $autoplay_first ) {
		$fit_cls = 'ds-device-media';
		if ( 'video' === $it['type'] ) {
			$loop    = 'interval' === $it['advance'] ? ' loop' : '';
			$auto    = ( 0 === $index && $autoplay_first ) ? ' autoplay' : '';
			$preload = 0 === $index ? 'auto' : 'metadata';
			$a11y    = '' !== $it['label'] ? ' aria-label="' . esc_attr( $it['label'] ) . '"' : ' aria-hidden="true"';
			printf(
				'<video class="%s" muted playsinline preload="%s"%s%s%s%s><source src="%s" type="video/mp4"></video>',
				esc_attr( $fit_cls ), esc_attr( $preload ), $loop, $auto,
				$it['poster'] ? ' poster="' . esc_url( $it['poster'] ) . '"' : '',
				$a11y, esc_url( $it['url'] )
			);
			return;
		}
		$size    = 'phone' === $this->device() ? 'large' : 'full';
		$loading = 0 === $index ? 'eager' : 'lazy';
		if ( $it['id'] ) {
			echo wp_get_attachment_image( $it['id'], $size, false, array( 'class' => $fit_cls, 'loading' => $loading, 'decoding' => 'async', 'sizes' => '(max-width: 768px) 90vw, 720px' ) );
		} else {
			printf( '<img class="%s" src="%s" alt="%s" loading="%s" decoding="async">', esc_attr( $fit_cls ), esc_url( $it['url'] ), esc_attr( $it['label'] ), esc_attr( $loading ) );
		}
	}

	/** Entry point called by includes/frontend.php. */
	public function render_device() {
		$s      = $this->settings;
		$items  = $this->items();
		$device = $this->device();
		$count  = count( $items );
		$multi  = $count > 1;
		$motion = $multi ? $this->motion() : 'static';
		$has_v  = in_array( 'video', array_column( $items, 'type' ), true );
		$auto   = $multi && 'static' !== $motion;
		$orient = ( 'desktop' !== $device && 'landscape' === ( $s->orientation ?? 'portrait' ) ) ? 'landscape' : 'portrait';
		$label  = trim( (string) ( $s->label ?? '' ) );

		$cls = array( 'ds-device', 'ds-device--' . $device, 'ds-device--' . $orient, 'ds-device--' . ( 'fade' === $motion ? 'fade' : 'slide' ) );
		if ( 'phone' === $device ) { $cls[] = 'ds-device--cam-' . ( in_array( $s->phone_camera ?? 'island', array( 'island', 'notch', 'none' ), true ) ? ( $s->phone_camera ?? 'island' ) : 'island' ); }
		if ( 'desktop' === $device ) { $cls[] = 'ds-device--' . ( 'monitor' === ( $s->desktop_style ?? 'browser' ) ? 'monitor' : 'browser' ); $cls[] = 'ds-device--bar-' . ( 'dark' === ( $s->bar_theme ?? 'light' ) ? 'dark' : 'light' ); }
		$cls[] = 'ds-device--shadow-' . ( in_array( $s->shadow ?? 'soft', array( 'none', 'soft', 'strong' ), true ) ? ( $s->shadow ?? 'soft' ) : 'soft' );
		if ( 'yes' === ( $s->float ?? 'no' ) ) { $cls[] = 'ds-device--float'; }
		if ( 'contain' === ( $s->fit ?? 'cover' ) ) { $cls[] = 'ds-device--contain'; }

		$attrs = sprintf( ' data-autoplay="%d" data-interval="%d"', $auto ? 1 : 0, max( 2, (int) ( $s->interval ?? 4 ) ) );
		if ( $multi ) {
			$attrs .= ' role="region" aria-roledescription="' . esc_attr__( 'carousel', 'ds-toolkit' ) . '" aria-label="' . esc_attr( '' !== $label ? $label : __( 'Media', 'ds-toolkit' ) ) . '"';
		}

		echo '<div class="' . esc_attr( implode( ' ', $cls ) ) . '"' . $attrs . '><div class="ds-device-stage"><div class="ds-device-body"><div class="ds-device-frame">';

		if ( 'desktop' === $device && 'browser' === ( $s->desktop_style ?? 'browser' ) ) {
			echo '<div class="ds-device-bar" aria-hidden="true"><span class="ds-device-lights"><i></i><i></i><i></i></span><span class="ds-device-url">' . esc_html( $this->browser_url() ) . '</span></div>';
		}
		if ( 'phone' === $device ) { echo '<span class="ds-device-cam" aria-hidden="true"></span>'; }

		echo '<div class="ds-device-screen">';
		if ( ! $count ) {
			if ( class_exists( 'FLBuilderModel' ) && FLBuilderModel::is_builder_active() ) {
				echo '<div class="ds-device-empty">' . esc_html__( 'Add an image or a video in the module settings.', 'ds-toolkit' ) . '</div>';
			}
		}
		foreach ( $items as $i => $it ) {
			$item_attrs = $multi ? ' role="group" aria-roledescription="' . esc_attr__( 'slide', 'ds-toolkit' ) . '" aria-label="' . esc_attr( sprintf( __( '%1$d of %2$d', 'ds-toolkit' ), $i + 1, $count ) ) . '"' : '';
			if ( $multi && $i > 0 ) { $item_attrs .= ' aria-hidden="true"'; }
			printf( '<div class="ds-device-item%s" data-type="%s" data-advance="%s"%s>', 0 === $i ? ' is-active' : '', esc_attr( $it['type'] ), esc_attr( $it['advance'] ), $item_attrs );
			$this->render_media( $it, $i, true );
			echo '</div>';
		}
		if ( $auto || $has_v ) {
			echo '<button type="button" class="ds-device-toggle" aria-label="' . esc_attr__( 'Pause', 'ds-toolkit' ) . '"><svg class="ds-device-ico-pause" viewBox="0 0 24 24" aria-hidden="true"><rect x="6" y="5" width="4" height="14" rx="1"/><rect x="14" y="5" width="4" height="14" rx="1"/></svg><svg class="ds-device-ico-play" viewBox="0 0 24 24" aria-hidden="true"><path d="M8 5.5v13a1 1 0 0 0 1.5.86l10.2-6.5a1 1 0 0 0 0-1.72L9.5 4.64A1 1 0 0 0 8 5.5z"/></svg></button>';
		}
		echo '</div>'; // screen

		echo '</div>'; // frame
		if ( 'desktop' === $device && 'monitor' === ( $s->desktop_style ?? 'browser' ) ) { echo '<div class="ds-device-stand" aria-hidden="true"><i></i></div>'; }
		echo '</div>'; // body

		if ( $multi && 'none' !== ( $s->nav ?? 'dots' ) ) {
			echo '<div class="ds-device-dots">';
			for ( $d = 0; $d < $count; $d++ ) {
				printf( '<button type="button" class="ds-device-dot%s" aria-label="%s"%s><span></span></button>', 0 === $d ? ' is-active' : '', esc_attr( sprintf( __( 'Show item %d', 'ds-toolkit' ), $d + 1 ) ), 0 === $d ? ' aria-current="true"' : '' );
			}
			echo '</div>';
		}
		echo '</div></div>'; // stage, device
	}
}

FLBuilder::register_settings_form( 'ds_device_slide', array(
	'title' => __( 'Item', 'ds-toolkit' ),
	'tabs'  => array(
		'general' => array(
			'title'    => __( 'Item', 'ds-toolkit' ),
			'sections' => array(
				'general' => array(
					'title'  => '',
					'fields' => array(
						'slide_type'    => array(
							'type'    => 'select',
							'label'   => __( 'Type', 'ds-toolkit' ),
							'default' => 'image',
							'options' => array( 'image' => __( 'Image', 'ds-toolkit' ), 'video' => __( 'Video', 'ds-toolkit' ) ),
							'toggle'  => array(
								'image' => array( 'fields' => array( 'photo' ) ),
								'video' => array( 'fields' => array( 'video_media', 'video_url', 'video_poster', 'video_label', 'video_advance' ) ),
							),
						),
						'photo'         => array( 'type' => 'photo', 'label' => __( 'Image', 'ds-toolkit' ), 'show_remove' => true, 'connections' => array( 'photo' ), 'help' => __( 'Its Media Library alt text is what screen readers announce.', 'ds-toolkit' ) ),
						'video_media'   => array( 'type' => 'video', 'label' => __( 'Video (Media Library)', 'ds-toolkit' ), 'help' => __( 'An MP4 from the Media Library. Takes priority over the Video URL.', 'ds-toolkit' ) ),
						'video_url'     => array( 'type' => 'text', 'label' => __( 'Video URL (MP4)', 'ds-toolkit' ), 'connections' => array( 'url' ) ),
						'video_poster'  => array( 'type' => 'photo', 'label' => __( 'Poster Image', 'ds-toolkit' ), 'show_remove' => true, 'connections' => array( 'photo' ), 'help' => __( 'Shown while the video loads, and instead of it for visitors who ask for reduced motion.', 'ds-toolkit' ) ),
						'video_label'   => array( 'type' => 'text', 'label' => __( 'Video Description', 'ds-toolkit' ), 'help' => __( 'Optional. Describe the video for screen readers; leave blank if it is decoration.', 'ds-toolkit' ) ),
						'video_advance' => array(
							'type'    => 'select',
							'label'   => __( 'Next Item', 'ds-toolkit' ),
							'default' => 'end',
							'options' => array( 'end' => __( 'When the video ends', 'ds-toolkit' ), 'interval' => __( 'After the interval (video loops)', 'ds-toolkit' ) ),
						),
					),
				),
			),
		),
	),
) );

FLBuilder::register_module( 'DS_Device_Module', array(
	'general' => array(
		'title'    => __( 'General', 'ds-toolkit' ),
		'sections' => array(
			'device' => array(
				'title'  => __( 'Device', 'ds-toolkit' ),
				'fields' => array(
					'device'        => array(
						'type'    => 'select',
						'label'   => __( 'Device', 'ds-toolkit' ),
						'default' => 'phone',
						'options' => array( 'phone' => __( 'Phone', 'ds-toolkit' ), 'tablet' => __( 'Tablet', 'ds-toolkit' ), 'desktop' => __( 'Desktop', 'ds-toolkit' ) ),
						'toggle'  => array(
							'phone'   => array( 'fields' => array( 'orientation', 'phone_camera' ) ),
							'tablet'  => array( 'fields' => array( 'orientation' ) ),
							'desktop' => array( 'fields' => array( 'desktop_style' ) ),
						),
					),
					'orientation'   => array( 'type' => 'select', 'label' => __( 'Orientation', 'ds-toolkit' ), 'default' => 'portrait', 'options' => array( 'portrait' => __( 'Portrait', 'ds-toolkit' ), 'landscape' => __( 'Landscape', 'ds-toolkit' ) ) ),
					'phone_camera'  => array( 'type' => 'select', 'label' => __( 'Camera', 'ds-toolkit' ), 'default' => 'island', 'options' => array( 'island' => __( 'Dynamic island', 'ds-toolkit' ), 'notch' => __( 'Notch', 'ds-toolkit' ), 'none' => __( 'None (full screen)', 'ds-toolkit' ) ) ),
					'desktop_style' => array(
						'type'    => 'select',
						'label'   => __( 'Desktop Style', 'ds-toolkit' ),
						'default' => 'browser',
						'options' => array( 'browser' => __( 'Browser window', 'ds-toolkit' ), 'monitor' => __( 'Monitor with stand', 'ds-toolkit' ) ),
						'toggle'  => array( 'browser' => array( 'fields' => array( 'browser_url', 'bar_theme' ) ) ),
					),
					'browser_url'   => array( 'type' => 'text', 'label' => __( 'Address Bar Text', 'ds-toolkit' ), 'placeholder' => __( 'This site\'s address', 'ds-toolkit' ), 'help' => __( 'Blank shows this site\'s own address.', 'ds-toolkit' ) ),
				),
			),
			'media'  => array(
				'title'  => __( 'Media', 'ds-toolkit' ),
				'fields' => array(
					'media_type'   => array(
						'type'    => 'select',
						'label'   => __( 'Show', 'ds-toolkit' ),
						'default' => 'image',
						'options' => array(
							'image'     => __( 'One image', 'ds-toolkit' ),
							'slideshow' => __( 'Several images', 'ds-toolkit' ),
							'video'     => __( 'One video', 'ds-toolkit' ),
							'mixed'     => __( 'Images and videos', 'ds-toolkit' ),
						),
						'toggle'  => array(
							'image'     => array( 'fields' => array( 'photo' ) ),
							'slideshow' => array( 'fields' => array( 'photos' ), 'sections' => array( 'motion' ) ),
							'video'     => array( 'fields' => array( 'video_media', 'video_url', 'video_poster', 'video_label' ) ),
							'mixed'     => array( 'fields' => array( 'mixed_slides' ), 'sections' => array( 'motion' ) ),
						),
					),
					'photo'        => array( 'type' => 'photo', 'label' => __( 'Image', 'ds-toolkit' ), 'show_remove' => true, 'connections' => array( 'photo' ), 'help' => __( 'Its Media Library alt text is what screen readers announce.', 'ds-toolkit' ) ),
					'photos'       => array( 'type' => 'multiple-photos', 'label' => __( 'Images', 'ds-toolkit' ), 'connections' => array( 'multiple-photos' ) ),
					'video_media'  => array( 'type' => 'video', 'label' => __( 'Video (Media Library)', 'ds-toolkit' ), 'help' => __( 'An MP4 from the Media Library; plays muted and loops. Takes priority over the Video URL.', 'ds-toolkit' ) ),
					'video_url'    => array( 'type' => 'text', 'label' => __( 'Video URL (MP4)', 'ds-toolkit' ), 'connections' => array( 'url' ) ),
					'video_poster' => array( 'type' => 'photo', 'label' => __( 'Poster Image', 'ds-toolkit' ), 'show_remove' => true, 'connections' => array( 'photo' ), 'help' => __( 'Shown while the video loads, and instead of it for visitors who ask for reduced motion.', 'ds-toolkit' ) ),
					'video_label'  => array( 'type' => 'text', 'label' => __( 'Video Description', 'ds-toolkit' ), 'help' => __( 'Optional. Describe the video for screen readers; leave blank if it is decoration.', 'ds-toolkit' ) ),
					'mixed_slides' => array( 'type' => 'form', 'label' => __( 'Item', 'ds-toolkit' ), 'form' => 'ds_device_slide', 'preview_text' => 'slide_type', 'multiple' => true ),
					'fit'          => array( 'type' => 'select', 'label' => __( 'Fit', 'ds-toolkit' ), 'default' => 'cover', 'options' => array( 'cover' => __( 'Fill the screen (crop)', 'ds-toolkit' ), 'contain' => __( 'Show all of it', 'ds-toolkit' ) ) ),
					'focus'        => array( 'type' => 'select', 'label' => __( 'Crop Focus', 'ds-toolkit' ), 'default' => 'center', 'options' => array( 'center' => __( 'Center', 'ds-toolkit' ), 'top' => __( 'Top', 'ds-toolkit' ), 'bottom' => __( 'Bottom', 'ds-toolkit' ), 'left' => __( 'Left', 'ds-toolkit' ), 'right' => __( 'Right', 'ds-toolkit' ) ), 'help' => __( 'Which part stays in view when a photo is cropped to the screen.', 'ds-toolkit' ) ),
					'label'        => array( 'type' => 'text', 'label' => __( 'Accessible Name', 'ds-toolkit' ), 'placeholder' => __( 'Media', 'ds-toolkit' ), 'help' => __( 'What screen readers call this group of images, e.g. "Club highlights".', 'ds-toolkit' ) ),
				),
			),
			'motion' => array(
				'title'  => __( 'Motion', 'ds-toolkit' ),
				'fields' => array(
					'motion'   => array(
						'type'    => 'select',
						'label'   => __( 'Motion', 'ds-toolkit' ),
						'default' => 'slide',
						'options' => array( 'slide' => __( 'Slide', 'ds-toolkit' ), 'fade' => __( 'Fade', 'ds-toolkit' ), 'static' => __( 'Static (visitor changes it)', 'ds-toolkit' ) ),
						'toggle'  => array( 'slide' => array( 'fields' => array( 'interval' ) ), 'fade' => array( 'fields' => array( 'interval' ) ) ),
						'help'    => __( 'Slide and Fade move on by themselves and show a pause button. Static changes only with the dots or a swipe.', 'ds-toolkit' ),
					),
					'interval' => array( 'type' => 'unit', 'label' => __( 'Time per Item', 'ds-toolkit' ), 'default' => '4', 'units' => array( 's' ), 'slider' => array( 'min' => 2, 'max' => 15, 'step' => 1 ) ),
					'nav'      => array( 'type' => 'select', 'label' => __( 'Dots', 'ds-toolkit' ), 'default' => 'dots', 'options' => array( 'dots' => __( 'Show', 'ds-toolkit' ), 'none' => __( 'Hide', 'ds-toolkit' ) ) ),
				),
			),
		),
	),
	'style'   => array(
		'title'    => __( 'Style', 'ds-toolkit' ),
		'sections' => array(
			'size'  => array(
				'title'  => __( 'Size & Position', 'ds-toolkit' ),
				'fields' => array(
					'width' => array( 'type' => 'unit', 'label' => __( 'Width', 'ds-toolkit' ), 'units' => array( 'px' ), 'responsive' => true, 'slider' => array( 'min' => 160, 'max' => 1400, 'step' => 10 ), 'placeholder' => '', 'help' => __( 'Blank uses a size that suits the device (phone 320, tablet 520, desktop 760). It never grows past its column.', 'ds-toolkit' ) ),
					'align' => array( 'type' => 'select', 'label' => __( 'Alignment', 'ds-toolkit' ), 'default' => 'center', 'responsive' => true, 'options' => array( 'left' => __( 'Left', 'ds-toolkit' ), 'center' => __( 'Center', 'ds-toolkit' ), 'right' => __( 'Right', 'ds-toolkit' ) ) ),
					'tilt'  => array( 'type' => 'unit', 'label' => __( 'Tilt', 'ds-toolkit' ), 'units' => array( 'deg' ), 'responsive' => true, 'slider' => array( 'min' => -15, 'max' => 15, 'step' => 0.5 ), 'placeholder' => '0' ),
					'float' => array( 'type' => 'select', 'label' => __( 'Gentle Float', 'ds-toolkit' ), 'default' => 'no', 'options' => array( 'no' => __( 'No', 'ds-toolkit' ), 'yes' => __( 'Yes', 'ds-toolkit' ) ), 'help' => __( 'A slow up-and-down drift. Off for visitors who ask for reduced motion.', 'ds-toolkit' ) ),
				),
			),
			'frame' => array(
				'title'  => __( 'Frame', 'ds-toolkit' ),
				'fields' => array(
					'frame_color'  => array( 'type' => 'color', 'label' => __( 'Frame Colour', 'ds-toolkit' ), 'default' => '111111', 'show_reset' => true, 'show_alpha' => true, 'connections' => array( 'color' ) ),
					'screen_color' => array( 'type' => 'color', 'label' => __( 'Screen Background', 'ds-toolkit' ), 'default' => '000000', 'show_reset' => true, 'connections' => array( 'color' ), 'help' => __( 'Shows around an image set to "Show all of it", and before media loads.', 'ds-toolkit' ) ),
					'bezel'        => array( 'type' => 'unit', 'label' => __( 'Bezel', 'ds-toolkit' ), 'units' => array( 'px' ), 'slider' => array( 'min' => 0, 'max' => 40, 'step' => 1 ), 'placeholder' => '', 'help' => __( 'Frame thickness. Blank scales with the device width.', 'ds-toolkit' ) ),
					'bar_theme'    => array( 'type' => 'select', 'label' => __( 'Browser Bar', 'ds-toolkit' ), 'default' => 'light', 'options' => array( 'light' => __( 'Light', 'ds-toolkit' ), 'dark' => __( 'Dark', 'ds-toolkit' ) ) ),
					'shadow'       => array( 'type' => 'select', 'label' => __( 'Shadow', 'ds-toolkit' ), 'default' => 'soft', 'options' => array( 'none' => __( 'None', 'ds-toolkit' ), 'soft' => __( 'Soft', 'ds-toolkit' ), 'strong' => __( 'Strong', 'ds-toolkit' ) ) ),
				),
			),
			'dots'  => array(
				'title'  => __( 'Dots & Button', 'ds-toolkit' ),
				'fields' => array(
					'dot_color'        => array( 'type' => 'color', 'label' => __( 'Dot Colour', 'ds-toolkit' ), 'show_reset' => true, 'show_alpha' => true, 'connections' => array( 'color' ), 'help' => __( 'Blank: the text colour at 55%.', 'ds-toolkit' ) ),
					'dot_active_color' => array( 'type' => 'color', 'label' => __( 'Current Dot Colour', 'ds-toolkit' ), 'show_reset' => true, 'connections' => array( 'color' ), 'help' => __( 'Blank: the site Primary colour.', 'ds-toolkit' ) ),
				),
			),
		),
	),
) );
