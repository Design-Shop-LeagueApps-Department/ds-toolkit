<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Theme Setting: the form controls the screen is drawn with (rows, selects, colour, font, typography and background groups).
 *
 * One part of DS_Theme_Setting, which was a single 1,620-line class until the 2026-11 audit. The methods
 * were moved here as they were: a trait, so they are still methods of the feature (same visibility, same
 * $this). The hooks, the front-end CSS output and the button shapes stay in class-ds-theme-setting.php.
 */
trait DS_Theme_Setting_Fields {

    /* -------------------------------------------------------- Field helpers */

    private $fid = 0;
    private function fid() { return 'dsts-f' . ( ++$this->fid ); }

    private function label_html( $label, $for = '', $hint = '' ) {
        if ( '' === $label ) { return ''; }
        $h = '<div class="dsts-field-label"><label' . ( $for ? ' for="' . esc_attr( $for ) . '"' : '' ) . '>' . esc_html( $label ) . '</label>';
        if ( $hint ) { $h .= '<span class="dsts-hint" tabindex="0" data-tip="' . esc_attr( $hint ) . '"><span class="dashicons dashicons-info-outline"></span></span>'; }
        return $h . '</div>';
    }

    /** A labelled row: label on the left, control on the right. */
    private function row( $label, $control_html, $args = array() ) {
        $cls = 'dsts-field' . ( ! empty( $args['class'] ) ? ' ' . $args['class'] : '' );
        echo '<div class="' . esc_attr( $cls ) . '"' . ( ! empty( $args['attrs'] ) ? ' ' . $args['attrs'] : '' ) . '>' . $this->label_html( $label, $args['for'] ?? '', $args['hint'] ?? '' ) . '<div class="dsts-field-control">' . $control_html . '</div>';
        if ( ! empty( $args['help'] ) ) { echo '<p class="dsts-help">' . wp_kses_post( $args['help'] ) . '</p>'; }
        echo '</div>';
    }

    /** A compact labelled cell for grids (label above the control). */
    private function cell( $label, $control_html, $args = array() ) {
        return '<div class="dsts-cell' . ( ! empty( $args['class'] ) ? ' ' . esc_attr( $args['class'] ) : '' ) . '"><span class="dsts-cell-label">' . esc_html( $label ) . '</span>' . $control_html . '</div>';
    }

    private function card_open( $title, $desc = '', $args = array() ) {
        echo '<div class="dsts-card' . ( ! empty( $args['class'] ) ? ' ' . esc_attr( $args['class'] ) : '' ) . '"' . ( ! empty( $args['id'] ) ? ' id="' . esc_attr( $args['id'] ) . '"' : '' ) . '>';
        if ( $title ) {
            echo '<div class="dsts-card-head"><h3>' . esc_html( $title ) . '</h3>' . ( ! empty( $args['aside'] ) ? $args['aside'] : '' ) . '</div>';
        }
        if ( $desc ) { echo '<p class="dsts-card-desc">' . wp_kses_post( $desc ) . '</p>'; }
    }
    private function card_close() { echo '</div>'; }

    private function select_html( $name, $val, $choices, $args = array() ) {
        $id  = $args['id'] ?? '';
        $out = '<select name="' . esc_attr( $name ) . '"' . ( $id ? ' id="' . esc_attr( $id ) . '"' : '' ) . ( ! empty( $args['class'] ) ? ' class="' . esc_attr( $args['class'] ) . '"' : '' ) . '>';
        $found = false;
        foreach ( $choices as $v => $l ) {
            $sel    = ( (string) $val === (string) $v );
            $found  = $found || $sel;
            $out   .= '<option value="' . esc_attr( $v ) . '"' . ( $sel ? ' selected' : '' ) . '>' . esc_html( $l ) . '</option>';
        }
        // Never silently change a stored value the list does not know.
        if ( ! $found && '' !== (string) $val ) {
            $out .= '<option value="' . esc_attr( $val ) . '" selected>' . esc_html( $val ) . '</option>';
        }
        return $out . '</select>';
    }

    /** Segmented control: native radios styled as a button group (posts like a select). */
    private function seg_html( $name, $val, $choices, $args = array() ) {
        $out = '<div class="dsts-seg' . ( ! empty( $args['class'] ) ? ' ' . esc_attr( $args['class'] ) : '' ) . '" role="radiogroup">';
        $found = false;
        foreach ( $choices as $v => $c ) {
            $c      = is_array( $c ) ? $c : array( 'label' => $c );
            $sel    = ( (string) $val === (string) $v );
            $found  = $found || $sel;
            $inner  = ! empty( $c['icon'] ) ? '<span class="dashicons ' . esc_attr( $c['icon'] ) . '" aria-hidden="true"></span><span class="screen-reader-text">' . esc_html( $c['label'] ) . '</span>' : esc_html( $c['label'] );
            $out   .= '<label title="' . esc_attr( $c['title'] ?? $c['label'] ) . '"><input type="radio" name="' . esc_attr( $name ) . '" value="' . esc_attr( $v ) . '"' . ( $sel ? ' checked' : '' ) . '><span>' . $inner . '</span></label>';
        }
        if ( ! $found && '' !== (string) $val ) {
            $out .= '<label title="' . esc_attr( $val ) . '"><input type="radio" name="' . esc_attr( $name ) . '" value="' . esc_attr( $val ) . '" checked><span>' . esc_html( $val ) . '</span></label>';
        }
        return $out . '</div>';
    }

    private function num_html( $name, $val, $args = array() ) {
        return '<input type="number" class="dsts-num" name="' . esc_attr( $name ) . '" value="' . esc_attr( $val ) . '"'
            . ( isset( $args['step'] ) ? ' step="' . esc_attr( $args['step'] ) . '"' : '' )
            . ( isset( $args['min'] ) ? ' min="' . esc_attr( $args['min'] ) . '"' : '' )
            . ( isset( $args['max'] ) ? ' max="' . esc_attr( $args['max'] ) . '"' : '' )
            . ( isset( $args['placeholder'] ) ? ' placeholder="' . esc_attr( $args['placeholder'] ) . '"' : '' )
            . ( isset( $args['id'] ) ? ' id="' . esc_attr( $args['id'] ) . '"' : '' )
            . ( isset( $args['title'] ) ? ' title="' . esc_attr( $args['title'] ) . '" aria-label="' . esc_attr( $args['title'] ) . '"' : '' ) . '>';
    }

    /** Number + unit in one control. */
    private function num_unit_html( $len_name, $len, $unit_name, $unit, $units, $args = array() ) {
        return '<div class="dsts-nu">' . $this->num_html( $len_name, $len, $args ) . $this->select_html( $unit_name, $unit, $units, array( 'class' => 'dsts-nu-unit' ) ) . '</div>';
    }

    private function color_html( $name, $value, $args = array() ) {
        $value   = trim( (string) $value );
        $info    = $this->color_info( $value );
        $globals = ! isset( $args['globals'] ) || $args['globals'];
        $fill    = $info['fill'] ? ' style="background:' . esc_attr( $info['fill'] ) . '"' : '';
        return '<div class="dsts-color is-' . esc_attr( $info['state'] ) . '" data-globals="' . ( $globals ? '1' : '0' ) . '">'
            . '<button type="button" class="dsts-color-btn"><span class="dsts-color-chip"><span class="dsts-color-fill"' . $fill . '></span></span>'
            . '<span class="dsts-color-text">' . esc_html( $info['text'] ) . '</span>'
            . ( 'global' === $info['state'] ? '<span class="dsts-color-tag">Global</span>' : '' ) . '</button>'
            . '<input type="hidden" class="dsts-color-input" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '"' . ( ! empty( $args['input_class'] ) ? ' data-role="' . esc_attr( $args['input_class'] ) . '"' : '' ) . '>'
            . '</div>';
    }

    private function color_row( $label, $name, $value, $args = array() ) {
        $this->row( $label, $this->color_html( $name, $value, $args ), array( 'class' => 'dsts-field--color', 'hint' => $args['hint'] ?? '', 'help' => $args['help'] ?? '' ) );
    }

    private function font_html( $name, $value ) {
        $value = '' !== trim( (string) $value ) ? $value : 'Default';
        return '<div class="dsts-font" data-value="' . esc_attr( $value ) . '">'
            . '<input type="text" class="dsts-font-input" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '" autocomplete="off" spellcheck="false" role="combobox" aria-expanded="false" aria-autocomplete="list">'
            . '<span class="dashicons dashicons-arrow-down-alt2" aria-hidden="true"></span></div>';
    }

    /** Four linked numeric inputs (border width, radius), with a link toggle that sets all four together. */
    private function quad_html( $base, $keys, $vals, $args = array() ) {
        $vals   = (array) $vals;
        $values = array();
        foreach ( $keys as $k => $l ) { $values[] = (string) ( $vals[ $k ] ?? '' ); }
        $linked = count( array_unique( $values ) ) === 1;
        $out    = '<div class="dsts-quad' . ( $linked ? ' is-linked' : '' ) . '">';
        foreach ( $keys as $k => $l ) {
            $out .= '<label class="dsts-quad-i"><input type="number" class="dsts-num" name="' . esc_attr( $base . '[' . ( $args['field'] ?? '' ) . $k . ']' ) . '" value="' . esc_attr( $vals[ $k ] ?? '' ) . '" min="0"><span>' . esc_html( $l ) . '</span></label>';
        }
        return $out . '<button type="button" class="dsts-quad-link" aria-pressed="' . ( $linked ? 'true' : 'false' ) . '" title="Link values"><span class="dashicons dashicons-admin-links"></span></button></div>';
    }

    /** Image picker tile (URL, and optionally the attachment id). */
    private function image_html( $url_name, $url, $id_name = '', $id = 0, $args = array() ) {
        $url  = (string) $url;
        $cls  = 'dsts-img' . ( $url ? ' has-img' : '' ) . ( ! empty( $args['shape'] ) ? ' dsts-img--' . $args['shape'] : '' );
        $out  = '<div class="' . esc_attr( $cls ) . '"' . ( ! empty( $args['title'] ) ? ' data-title="' . esc_attr( $args['title'] ) . '"' : '' ) . '>';
        $out .= '<button type="button" class="dsts-img-thumb" aria-label="Select image">' . ( $url ? '<img src="' . esc_url( $url ) . '" alt="">' : '' ) . '<span class="dsts-img-empty"><span class="dashicons dashicons-format-image"></span>Select image</span></button>';
        $out .= '<div class="dsts-img-actions"><button type="button" class="button dsts-img-select">' . ( $url ? 'Replace' : 'Select' ) . '</button><button type="button" class="button-link dsts-img-remove"' . ( $url ? '' : ' hidden' ) . '>Remove</button></div>';
        $out .= '<input type="hidden" class="dsts-img-url" name="' . esc_attr( $url_name ) . '" value="' . esc_attr( $url ) . '">';
        if ( $id_name ) { $out .= '<input type="hidden" class="dsts-img-id" name="' . esc_attr( $id_name ) . '" value="' . esc_attr( $id ) . '">'; }
        return $out . '</div>';
    }

    private function position_html( $name, $val ) {
        $out = '<div class="dsts-pos" role="radiogroup">';
        foreach ( array( 'top', 'center', 'bottom' ) as $y ) {
            foreach ( array( 'left', 'center', 'right' ) as $x ) {
                $v    = $x . ' ' . $y;
                $out .= '<label title="' . esc_attr( ucwords( $v ) ) . '"><input type="radio" name="' . esc_attr( $name ) . '" value="' . esc_attr( $v ) . '"' . checked( $val, $v, false ) . '><span></span></label>';
            }
        }
        return $out . '</div>';
    }

    private function blend_modes() {
        return array( 'normal' => 'Normal', 'multiply' => 'Multiply', 'screen' => 'Screen', 'overlay' => 'Overlay', 'darken' => 'Darken', 'lighten' => 'Lighten', 'color-dodge' => 'Color Dodge', 'color-burn' => 'Color Burn', 'hard-light' => 'Hard Light', 'soft-light' => 'Soft Light', 'difference' => 'Difference', 'exclusion' => 'Exclusion', 'hue' => 'Hue', 'saturation' => 'Saturation', 'color' => 'Color', 'luminosity' => 'Luminosity' );
    }

    /** Colour + pattern/image background group; the image options only show once an image is set. */
    private function background_group( $key, $mods, $defaults ) {
        $mod = function( $k, $d = '' ) { $v = get_theme_mod( $k, $d ); return ( false === $v ) ? $d : $v; };
        $n   = function( $f ) use ( $key ) { return 'general[' . $key . '_' . $f . ']'; };
        $img = $mod( $mods . '-image' );

        $this->color_row( 'Background colour', $n( 'color' ), $mod( $mods . '-color', $defaults['color'] ?? '' ) );
        $this->row( 'Pattern / image', $this->image_html( $n( 'image' ), $img, '', 0, array( 'title' => 'Select background image' ) ), array( 'class' => 'dsts-field--image' ) );

        echo '<div class="dsts-bg-opts"' . ( $img ? '' : ' hidden' ) . '>';
        $this->row( 'Repeat', $this->seg_html( $n( 'repeat' ), $mod( $mods . '-repeat', $defaults['repeat'] ), array( 'no-repeat' => 'None', 'repeat' => 'Tile', 'repeat-x' => array( 'label' => 'X', 'title' => 'Tile horizontally' ), 'repeat-y' => array( 'label' => 'Y', 'title' => 'Tile vertically' ) ) ) );
        $this->row( 'Size', $this->seg_html( $n( 'size' ), $mod( $mods . '-size', 'auto' ), array( 'auto' => 'Auto', 'cover' => 'Cover', 'contain' => 'Contain' ) ) );
        $this->row( 'Position', $this->position_html( $n( 'position' ), $mod( $mods . '-position', $defaults['position'] ) ) );
        $this->row( 'Attachment', $this->seg_html( $n( 'attachment' ), $mod( $mods . '-attachment', 'scroll' ), array( 'scroll' => 'Scroll', 'fixed' => array( 'label' => 'Fixed', 'title' => 'Parallax: the image holds still while the page scrolls' ) ) ), array( 'hint' => 'Fixed is the parallax effect. It switches back to Scroll on touch devices (iOS renders it zoomed) and for visitors who ask for reduced motion.' ) );
        $this->row( 'Blend mode', $this->select_html( $n( 'blend' ), $mod( $mods . '-blend', 'normal' ), $this->blend_modes() ) );
        $this->color_row( 'Image overlay', $n( 'overlay' ), $mod( $mods . '-overlay' ), array( 'hint' => 'Tints the image. Use a colour with transparency: a solid colour hides the image entirely.' ) );
        echo '</div>';
    }

    /** Typography controls for one group (Font, size, spacing, style, shadow). */
    private function typo_block( $group, $typo ) {
        $n       = "typo[$group]";
        $weights = array( '' => 'Default', '100' => '100 Thin', '200' => '200 Extra Light', '300' => '300 Light', '400' => '400 Regular', '500' => '500 Medium', '600' => '600 Semi Bold', '700' => '700 Bold', '800' => '800 Extra Bold', '900' => '900 Black' );
        $sh      = (array) $this->tv( $typo, 'text_shadow' );

        echo '<div class="dsts-typo" data-group="' . esc_attr( $group ) . '">';
        echo '<div class="dsts-grid dsts-grid--font">';
        echo $this->cell( 'Font', $this->font_html( "{$n}[font_family]", $this->tv( $typo, 'font_family' ) ) );
        echo $this->cell( 'Weight', $this->select_html( "{$n}[font_weight]", $this->tv( $typo, 'font_weight' ), $weights, array( 'class' => 'dsts-weight' ) ) );
        echo '</div><div class="dsts-grid dsts-grid--3">';
        echo $this->cell( 'Size', $this->num_unit_html( "{$n}[font_size_length]", $this->tv( $typo, 'font_size', 'length' ), "{$n}[font_size_unit]", $this->tv( $typo, 'font_size', 'unit' ) ?: 'px', $this->units(), array( 'placeholder' => '—', 'step' => 'any', 'title' => 'Font size' ) ) );
        echo $this->cell( 'Line height', $this->num_unit_html( "{$n}[line_height_length]", $this->tv( $typo, 'line_height', 'length' ), "{$n}[line_height_unit]", $this->tv( $typo, 'line_height', 'unit' ), array( '' => '—', 'em' => 'em', 'px' => 'px', '%' => '%' ), array( 'placeholder' => '—', 'step' => 'any', 'title' => 'Line height' ) ) );
        echo $this->cell( 'Letter spacing', $this->num_unit_html( "{$n}[letter_spacing_length]", $this->tv( $typo, 'letter_spacing', 'length' ), "{$n}[letter_spacing_unit]", $this->tv( $typo, 'letter_spacing', 'unit' ) ?: 'px', array( 'px' => 'px', 'em' => 'em' ), array( 'placeholder' => '—', 'step' => 'any', 'title' => 'Letter spacing' ) ) );
        echo '</div><div class="dsts-grid dsts-grid--2">';
        echo $this->cell( 'Case', $this->seg_html( "{$n}[text_transform]", $this->tv( $typo, 'text_transform' ), array( '' => array( 'label' => 'Auto', 'title' => 'Default' ), 'none' => array( 'label' => 'Ab', 'title' => 'Normal' ), 'capitalize' => array( 'label' => 'Aa', 'title' => 'Capitalize' ), 'uppercase' => array( 'label' => 'AB', 'title' => 'UPPERCASE' ), 'lowercase' => array( 'label' => 'ab', 'title' => 'lowercase' ) ), array( 'class' => 'dsts-seg--sm' ) ) );
        echo $this->cell( 'Align', $this->seg_html( "{$n}[text_align]", $this->tv( $typo, 'text_align' ), array( '' => array( 'label' => 'Auto', 'title' => 'Default' ), 'left' => array( 'label' => 'Left', 'icon' => 'dashicons-editor-alignleft' ), 'center' => array( 'label' => 'Center', 'icon' => 'dashicons-editor-aligncenter' ), 'right' => array( 'label' => 'Right', 'icon' => 'dashicons-editor-alignright' ) ), array( 'class' => 'dsts-seg--sm' ) ) );
        echo '</div>';

        $has_more = '' !== (string) $this->tv( $typo, 'font_style' ) || '' !== (string) $this->tv( $typo, 'text_decoration' ) || '' !== (string) $this->tv( $typo, 'font_variant' ) || ! empty( $sh['color'] );
        echo '<details class="dsts-more"' . ( $has_more ? ' open' : '' ) . '><summary>Style, decoration &amp; shadow</summary>';
        echo '<div class="dsts-grid dsts-grid--3">';
        echo $this->cell( 'Style', $this->select_html( "{$n}[font_style]", $this->tv( $typo, 'font_style' ), array( '' => 'Default', 'normal' => 'Normal', 'italic' => 'Italic' ) ) );
        echo $this->cell( 'Decoration', $this->select_html( "{$n}[text_decoration]", $this->tv( $typo, 'text_decoration' ), array( '' => 'Default', 'none' => 'None', 'underline' => 'Underline', 'line-through' => 'Line Through', 'overline' => 'Overline' ) ) );
        echo $this->cell( 'Variant', $this->select_html( "{$n}[font_variant]", $this->tv( $typo, 'font_variant' ), array( '' => 'Default', 'normal' => 'Normal', 'small-caps' => 'Small Caps' ) ) );
        echo '</div><div class="dsts-grid dsts-grid--shadow">';
        echo $this->cell( 'Text shadow', $this->color_html( $n . '[shadow_color]', $sh['color'] ?? '' ) );
        echo $this->cell( 'X', $this->num_html( "{$n}[shadow_x]", $sh['horizontal'] ?? '', array( 'placeholder' => '0', 'title' => 'Shadow X' ) ) );
        echo $this->cell( 'Y', $this->num_html( "{$n}[shadow_y]", $sh['vertical'] ?? '', array( 'placeholder' => '0', 'title' => 'Shadow Y' ) ) );
        echo $this->cell( 'Blur', $this->num_html( "{$n}[shadow_blur]", $sh['blur'] ?? '', array( 'placeholder' => '0', 'title' => 'Shadow blur' ) ) );
        echo '</div></details>';
        echo '<div class="dsts-typo-foot"><button type="button" class="button-link dsts-typo-reset">Reset to theme default</button></div>';
        echo '</div>';
    }

    private function units() {
        return array( 'px' => 'px', 'em' => 'em', 'rem' => 'rem', '%' => '%', 'vw' => 'vw' );
    }

    /** Read a typography sub-value from an object/array. */
    private function tv( $typo, $key, $sub = null ) {
        $t = (array) $typo;
        if ( ! isset( $t[ $key ] ) ) { return ''; }
        if ( null === $sub ) { return $t[ $key ]; }
        $v = (array) $t[ $key ];
        return isset( $v[ $sub ] ) ? $v[ $sub ] : '';
    }

    /** Button-shape selector: radio cards each showing a live preview of its clip-path. */
    private function button_shape_html( $current, $prev_bg, $prev_fg ) {
        $prev_bg = $prev_bg ?: '#1cb0f6';
        $prev_fg = $prev_fg ?: '#ffffff';
        $out = '<div class="dsts-shapes" style="--dsts-btn-bg:' . esc_attr( $prev_bg ) . ';--dsts-btn-fg:' . esc_attr( $prev_fg ) . '">';
        foreach ( self::button_styles() as $key => $st ) {
            $checked = ( (string) $current === (string) $key );
            $clip    = '' !== $st['clip'] ? '-webkit-clip-path:' . $st['clip'] . ';clip-path:' . $st['clip'] . ';border-radius:0;' : '';
            $out    .= '<label class="dsts-shape' . ( $checked ? ' is-active' : '' ) . '">'
                . '<input type="radio" name="button_style" value="' . esc_attr( $key ) . '"' . checked( $checked, true, false ) . '>'
                . '<span class="dsts-shape-demo"><span class="dsts-shape-btn" style="' . esc_attr( $clip ) . '">Register</span></span>'
                . '<span class="dsts-shape-name">' . esc_html( $st['label'] ) . '</span>'
                . '<span class="dsts-shape-desc">' . esc_html( $st['desc'] ) . '</span></label>';
        }
        return $out . '</div>';
    }
}
