<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Image Optimization on Upload.
 *
 * Stops oversized, heavy media at the door: every JPEG/PNG that comes in
 * through the Media Library (or a sideload / WP-CLI import) is downscaled to a
 * web-friendly bounding box and re-encoded as WebP before WordPress generates
 * its thumbnails — so a partner dropping a 12 MB phone photo ends up with a
 * lightweight ~100-300 KB WebP instead, and every sub-size is WebP too.
 *
 * Hooks `wp_handle_upload`, which fires for BOTH admin uploads and sideloads
 * (core passes the original file's path/url/type and we hand back the WebP's),
 * so the conversion happens once, up front, before metadata + sub-sizes are
 * built from the file. The original heavy upload is removed.
 *
 * Safe by design:
 *   - Only touches `image/jpeg` and `image/png` (and oversized `image/webp`,
 *     which it resizes in place). GIFs are skipped (would lose animation),
 *     SVGs and every non-image upload pass straight through.
 *   - No-ops gracefully if the active image editor can't encode WebP, if the
 *     editor errors, or if the WebP would be no smaller than a source that
 *     didn't need resizing (an already-optimized image is left alone).
 *
 * Tunable via filters:
 *   - `ds_image_optimization_max_dim`  (int, default 2048) — longest-edge cap.
 *   - `ds_image_optimization_quality`  (int, default 82)   — WebP quality.
 *   - `ds_image_optimization_mime_types` (array)           — source mimes to convert.
 *
 * Blueprint generation 6+ only; auto-enabled on DSLP6 builds.
 */
class DS_Image_Optimization {

    /** Longest-edge cap, in pixels, applied to oversized uploads. */
    const MAX_DIM = 2048;

    /** WebP encode quality (0-100). 82 is a strong size/quality balance. */
    const QUALITY = 82;

    private $settings;

    public function __construct( $settings = array() ) {
        $this->settings = $settings;
    }

    public function init() {
        // Priority 20 so it runs after WordPress' own upload handling and any
        // earlier mime/path massaging has settled. $context is 'upload' or
        // 'sideload' — we treat both the same.
        add_filter( 'wp_handle_upload', array( $this, 'optimize_upload' ), 20 );
    }

    /**
     * @param array $upload { 'file' => abs path, 'url' => url, 'type' => mime }
     * @return array Possibly rewritten to point at the new WebP file.
     */
    public function optimize_upload( $upload ) {
        if ( ! is_array( $upload ) || empty( $upload['file'] ) || empty( $upload['type'] ) ) {
            return $upload;
        }

        $file = $upload['file'];
        $mime = strtolower( $upload['type'] );

        if ( ! file_exists( $file ) ) {
            return $upload;
        }

        $convertible = apply_filters(
            'ds_image_optimization_mime_types',
            array( 'image/jpeg', 'image/jpg', 'image/pjpeg', 'image/png' )
        );
        $is_convertible = in_array( $mime, $convertible, true );
        $is_webp        = ( 'image/webp' === $mime );

        // Only JPEG/PNG → WebP, or an oversized WebP we can shrink in place.
        if ( ! $is_convertible && ! $is_webp ) {
            return $upload;
        }

        // Bail cleanly if the active editor (GD/Imagick) can't write WebP.
        if ( ! wp_image_editor_supports( array( 'mime_type' => 'image/webp' ) ) ) {
            return $upload;
        }

        $editor = wp_get_image_editor( $file );
        if ( is_wp_error( $editor ) ) {
            return $upload;
        }

        // Downscale anything larger than the cap to a bounding box (aspect
        // preserved, never upscaled).
        $max     = (int) apply_filters( 'ds_image_optimization_max_dim', self::MAX_DIM );
        $size    = $editor->get_size();
        $resized = false;
        if ( $max > 0 && is_array( $size ) && ! empty( $size['width'] ) && ! empty( $size['height'] ) ) {
            if ( $size['width'] > $max || $size['height'] > $max ) {
                $resized = ! is_wp_error( $editor->resize( $max, $max, false ) );
            }
        }

        // An already-WebP upload only needs re-saving if we actually resized it;
        // otherwise leave the original untouched.
        if ( $is_webp && ! $resized ) {
            return $upload;
        }

        $quality = (int) apply_filters( 'ds_image_optimization_quality', self::QUALITY );
        if ( $quality > 0 && $quality <= 100 ) {
            $editor->set_quality( $quality );
        }

        $dir = pathinfo( $file, PATHINFO_DIRNAME );

        if ( $is_webp ) {
            // Resize-in-place: overwrite the same .webp file, keep file/url/type.
            $saved = $editor->save( $file, 'image/webp' );
            if ( is_wp_error( $saved ) ) {
                return $upload;
            }
            return $upload;
        }

        // JPEG/PNG → WebP under a collision-safe sibling filename.
        $webp_name = wp_unique_filename(
            $dir,
            preg_replace( '/\.(jpe?g|png)$/i', '.webp', wp_basename( $file ) )
        );
        $webp_path = trailingslashit( $dir ) . $webp_name;

        $saved = $editor->save( $webp_path, 'image/webp' );
        if ( is_wp_error( $saved ) || empty( $saved['path'] ) || ! file_exists( $saved['path'] ) ) {
            return $upload;
        }

        // Quality bar: if we didn't need to resize and the WebP isn't actually
        // smaller, the source was already well-optimized — discard the WebP and
        // keep the original as-is.
        if ( ! $resized ) {
            $orig_bytes = (int) filesize( $file );
            $webp_bytes = (int) filesize( $saved['path'] );
            if ( $orig_bytes > 0 && $webp_bytes >= $orig_bytes ) {
                wp_delete_file( $saved['path'] );
                return $upload;
            }
        }

        // Point the upload at the WebP and drop the heavy original.
        if ( $saved['path'] !== $file ) {
            wp_delete_file( $file );
        }

        $upload['file'] = $saved['path'];
        $upload['url']  = trailingslashit( dirname( $upload['url'] ) ) . $webp_name;
        $upload['type'] = 'image/webp';

        return $upload;
    }
}
