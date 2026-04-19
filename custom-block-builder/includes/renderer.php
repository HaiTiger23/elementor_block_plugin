<?php
/**
 * Block Rendering Engine
 *
 * Core rendering function that processes block templates with data.
 *
 * @package CustomBlockBuilder
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Render a block by its post ID with provided data.
 *
 * @param int    $block_id    The block post ID.
 * @param array  $data        Key-value pairs of field data to inject into the template.
 * @param string $instance_id Optional. A unique ID for this instance of the block.
 * @return string Rendered HTML output.
 */
function cbb_render_block( $block_id, $data = array(), $instance_id = null ) {
    $block = get_post( $block_id );

    if ( ! $block || 'custom_block' !== $block->post_type ) {
        return '<!-- CBB: Block not found -->';
    }

    $schema_raw = get_post_meta( $block_id, '_cbb_schema', true );
    $view       = get_post_meta( $block_id, '_cbb_view', true );
    $css        = get_post_meta( $block_id, '_cbb_css', true );
    $js         = get_post_meta( $block_id, '_cbb_js', true );

    $schema = json_decode( $schema_raw, true );

    if ( empty( $view ) ) {
        return '<!-- CBB: Block has no view template -->';
    }

    // Merge defaults from schema with provided data.
    $merged_data = cbb_merge_defaults( $schema, $data );

    return cbb_render_template( $view, $css, $js, $merged_data, $block_id, $instance_id );
}

/**
 * Render a block preview (from raw data, not from DB).
 * Used for live preview in admin editor.
 *
 * @param string $view      The view template (PHP/HTML).
 * @param string $css       The block CSS.
 * @param string $js        The block JS.
 * @param string $schema    The schema JSON string.
 * @param array  $test_data Test data key-value pairs.
 * @return string Rendered HTML output.
 */
function cbb_render_block_preview( $view, $css, $js, $schema, $test_data = array() ) {
    $schema_decoded = json_decode( $schema, true );

    if ( empty( $view ) ) {
        return '<p style="color:#999;text-align:center;padding:40px;">No view template defined.</p>';
    }

    // Merge defaults with test data.
    $merged_data = cbb_merge_defaults( $schema_decoded, $test_data );

    return cbb_render_template( $view, $css, $js, $merged_data, 'preview', 'preview' );
}

/**
 * Core template rendering function.
 *
 * @param string     $view        The view template string.
 * @param string     $css         The CSS string.
 * @param string     $js          The JS string.
 * @param array      $data        Merged data (defaults + overrides).
 * @param int|string $block_id    Block ID or 'preview'.
 * @param string     $instance_id Optional. A unique ID for this instance.
 * @return string Rendered HTML.
 */
function cbb_render_template( $view, $css, $js, $data, $block_id, $instance_id = null ) {
    static $instance_counter = 0;
    
    if ( ! $instance_id ) {
        $instance_counter++;
        $instance_id = $block_id . '-' . $instance_counter;
    }

    $block_type_class = 'cbb-block-' . sanitize_html_class( $block_id );
    $instance_class   = 'cbb-i-' . sanitize_html_class( $instance_id );

    // Make helper functions available in templates.
    // These are simple wrappers the user can call in their view templates.
    $esc_html = 'esc_html';
    $esc_attr = 'esc_attr';
    $esc_url  = 'esc_url';

    // Extract data into variables for use in the template.
    // Each field name becomes a PHP variable.
    extract( $data, EXTR_SKIP );

    // Render the view template.
    ob_start();
    try {
        if ( strpos( $view, '<?php' ) !== false ) {
            eval( '?>' . $view );
        } else {
            echo cbb_render_mustache( $view, $data );
        }
    } catch ( \Throwable $e ) {
        ob_end_clean();
        $error_message = sprintf(
            /* translators: 1: error message, 2: line number */
            __( 'Template Error: %1$s on line %2$d', 'custom-block-builder' ),
            $e->getMessage(),
            $e->getLine()
        );
        return '<div class="cbb-block ' . esc_attr( $block_type_class ) . ' ' . esc_attr( $instance_class ) . '">'
             . '<p style="color:red;padding:20px;border:1px solid red;background:#fff5f5;">' . esc_html( $error_message ) . '</p>'
             . '</div>';
    }
    $html = ob_get_clean();

    // Build the complete output.
    $output = '<div class="cbb-block ' . esc_attr( $block_type_class ) . ' ' . esc_attr( $instance_class ) . '">';
    $output .= $html;
    $output .= '</div>';

    // Append scoped CSS — only once per block type per request.
    static $rendered_css = array();
    if ( ! empty( $css ) && ! in_array( $block_id, $rendered_css ) ) {
        $minified_css = ( $block_id !== 'preview' ) ? get_post_meta( $block_id, '_cbb_css_minified', true ) : '';
        if ( empty( $minified_css ) ) {
            $scoped_css = cbb_scope_css( $css, '.' . $block_type_class );
            $minified_css = cbb_minify_css( $scoped_css );
        }
        $output .= '<style id="cbb-style-' . esc_attr( $block_id ) . '">' . $minified_css . '</style>';
        $rendered_css[] = $block_id;
    }

    // Append isolated JS.
    if ( ! empty( $js ) ) {
        $minified_js = ( $block_id !== 'preview' ) ? get_post_meta( $block_id, '_cbb_js_minified', true ) : '';
        if ( empty( $minified_js ) ) {
            $minified_js = cbb_minify_js( $js );
        }
        $output .= '<script>(function(){ var blockEl = document.querySelector(".' . esc_js( $instance_class ) . '");' . "\n" . $minified_js . "\n" . '})();</script>';
    }

    return $output;
}

/**
 * Simple Mustache-like rendering engine.
 *
 * @param string $template Template string.
 * @param array  $data     Data to inject.
 * @return string Rendered output.
 */
function cbb_render_mustache( $template, $data ) {
    // 1. Handle repeaters {{#each list}}...{{/each}}
    $template = preg_replace_callback( '/\{\{\s*#each\s+([a-zA-Z0-9_-]+)\s*\}\}(.*?)\{\{\s*\/each\s*\}\}/s', function( $matches ) use ( $data ) {
        $var_name = $matches[1];
        $inner_tpl = $matches[2];
        $output = '';
        if ( isset( $data[$var_name] ) && is_array( $data[$var_name] ) ) {
            foreach ( $data[$var_name] as $item ) {
                $output .= cbb_render_mustache( $inner_tpl, $item );
            }
        }
        return $output;
    }, $template );

    // 2. Handle conditionals {{#if var}}...{{/if}}
    $template = preg_replace_callback( '/\{\{\s*#if\s+([a-zA-Z0-9_-]+)\s*\}\}(.*?)\{\{\s*\/if\s*\}\}/s', function( $matches ) use ( $data ) {
        $var_name = $matches[1];
        $inner_tpl = $matches[2];
        if ( ! empty( $data[$var_name] ) ) {
            return cbb_render_mustache( $inner_tpl, $data );
        }
        return '';
    }, $template );

    // 3. Handle object properties {{ obj.prop }}
    $template = preg_replace_callback( '/\{\{\s*([a-zA-Z0-9_-]+)\.([a-zA-Z0-9_-]+)\s*\}\}/', function( $matches ) use ( $data ) {
        $obj_name = $matches[1];
        $prop_name = $matches[2];
        if ( isset( $data[$obj_name] ) && is_array( $data[$obj_name] ) && isset( $data[$obj_name][$prop_name] ) ) {
            return esc_html( $data[$obj_name][$prop_name] );
        }
        return '';
    }, $template );

    // 4. Handle raw variables {{{ var }}}
    $template = preg_replace_callback( '/\{\{\{\s*([a-zA-Z0-9_-]+)\s*\}\}\}/', function( $matches ) use ( $data ) {
        $var_name = $matches[1];
        return isset( $data[$var_name] ) && is_scalar( $data[$var_name] ) ? $data[$var_name] : '';
    }, $template );

    // 5. Handle escaped variables {{ var }}
    $template = preg_replace_callback( '/\{\{\s*([a-zA-Z0-9_-]+)\s*\}\}/', function( $matches ) use ( $data ) {
        $var_name = $matches[1];
        return isset( $data[$var_name] ) && is_scalar( $data[$var_name] ) ? esc_html( $data[$var_name] ) : '';
    }, $template );

    return $template;
}

/**
 * Basic CSS minification.
 */
function cbb_minify_css( $css ) {
    $css = preg_replace( '/\s+/', ' ', $css );
    $css = preg_replace( '/\/\*.*?\*\//', '', $css );
    return trim( $css );
}

/**
 * Basic JS minification.
 */
function cbb_minify_js( $js ) {
    $js = preg_replace( '/\/\/.*/', '', $js );
    $js = preg_replace( '/\s+/', ' ', $js );
    return trim( $js );
}


/**
 * Merge schema default values with provided data.
 *
 * @param array|null $schema The decoded schema array.
 * @param array      $data   User-provided data.
 * @return array Merged data array.
 */
function cbb_merge_defaults( $schema, $data ) {
    $merged = array();

    if ( ! empty( $schema['fields'] ) && is_array( $schema['fields'] ) ) {
        foreach ( $schema['fields'] as $field ) {
            if ( isset( $field['type'] ) && $field['type'] === 'section' ) {
                if ( ! empty( $field['fields'] ) && is_array( $field['fields'] ) ) {
                    $section_merged = cbb_merge_defaults( array( 'fields' => $field['fields'] ), $data );
                    $merged = array_merge( $merged, $section_merged );
                }
                continue;
            }

            $name = $field['name'] ?? '';
            if ( empty( $name ) ) {
                continue;
            }

            $type = $field['type'] ?? 'text';

            if ( $type === 'group' ) {
                $group_data = array();
                if ( isset( $data[ $name ] ) && is_array( $data[ $name ] ) ) {
                    $group_data = $data[ $name ];
                }
                $merged[ $name ] = cbb_merge_defaults( array( 'fields' => $field['fields'] ?? array() ), $group_data );
                continue;
            }

            // Get default value based on field type.
            $default = $field['default'] ?? cbb_get_type_default( $type );

            // Use provided data or fall back to default.
            if ( isset( $data[ $name ] ) ) {
                if ( $type === 'repeater' && is_array( $data[ $name ] ) && ! empty( $field['fields'] ) ) {
                    $repeater_items = array();
                    foreach ( $data[ $name ] as $item ) {
                        $repeater_items[] = cbb_merge_defaults( array( 'fields' => $field['fields'] ), $item );
                    }
                    $merged[ $name ] = $repeater_items;
                } else {
                    $merged[ $name ] = cbb_sanitize_field_value( $data[ $name ], $type );
                }
            } else {
                $merged[ $name ] = $default;
            }
        }
    }

    return $merged;
}

/**
 * Get the default value for a field type.
 *
 * @param string $type Field type.
 * @return mixed Default value.
 */
function cbb_get_type_default( $type ) {
    switch ( $type ) {
        case 'text':
        case 'textarea':
        case 'select':
        case 'color':
            return '';
        case 'number':
            return 0;
        case 'image':
            return '';
        case 'boolean':
            return false;
        case 'repeater':
            return array();
        case 'group':
            return array();
        default:
            return '';
    }
}

/**
 * Sanitize a field value based on its type.
 *
 * @param mixed  $value The value to sanitize.
 * @param string $type  The field type.
 * @return mixed Sanitized value.
 */
function cbb_sanitize_field_value( $value, $type ) {
    switch ( $type ) {
        case 'text':
            return sanitize_text_field( $value );
        case 'textarea':
            return sanitize_textarea_field( $value );
        case 'number':
            return floatval( $value );
        case 'image':
            if ( is_array( $value ) ) {
                return esc_url_raw( $value['url'] ?? '' );
            }
            return esc_url_raw( $value );
        case 'select':
            return sanitize_text_field( $value );
        case 'color':
            return cbb_sanitize_color_value( $value );
        case 'boolean':
            return (bool) $value;
        case 'repeater':
            return is_array( $value ) ? $value : array();
        default:
            return sanitize_text_field( $value );
    }
}

/**
 * Sanitize color values to keep only safe CSS color formats.
 *
 * @param mixed $value Raw color value.
 * @return string Sanitized color value.
 */
function cbb_sanitize_color_value( $value ) {
    if ( ! is_scalar( $value ) ) {
        return '';
    }

    $value = trim( (string) $value );
    if ( $value === '' ) {
        return '';
    }

    $hex = sanitize_hex_color( $value );
    if ( $hex ) {
        return $hex;
    }

    if ( preg_match( '/^rgba?\(\s*[\d.]+%?\s*,\s*[\d.]+%?\s*,\s*[\d.]+%?(?:\s*,\s*(?:0|1|0?\.\d+))?\s*\)$/i', $value ) ) {
        return $value;
    }

    if ( preg_match( '/^hsla?\(\s*[\d.]+(?:deg|grad|rad|turn)?\s*,\s*[\d.]+%\s*,\s*[\d.]+%(?:\s*,\s*(?:0|1|0?\.\d+))?\s*\)$/i', $value ) ) {
        return $value;
    }

    if ( preg_match( '/^var\(\s*--[a-z0-9_-]+\s*(?:,\s*[^()]+)?\s*\)$/i', $value ) ) {
        return $value;
    }

    return '';
}

/**
 * Scope CSS by prefixing all selectors with a parent selector.
 *
 * Simple implementation: handles basic selectors.
 *
 * @param string $css      Raw CSS string.
 * @param string $prefix   Selector prefix (e.g., '.cbb-block-123').
 * @return string Scoped CSS.
 */
function cbb_scope_css( $css, $prefix ) {
    // Remove comments.
    $css = preg_replace( '/\/\*.*?\*\//s', '', $css );

    $callback = function( $matches ) use ( &$callback, $prefix ) {
        $selectors = trim( $matches[1] );
        $content   = trim( $matches[2] );

        if ( strpos( $selectors, '@media' ) === 0 || strpos( $selectors, '@supports' ) === 0 ) {
            // Recurse into the content of @media or @supports.
            $scoped_content = preg_replace_callback( '/([^{]+)\{((?:[^{}]++|(?R))*)\}/s', $callback, $content );
            return $selectors . " {\n" . $scoped_content . "\n}";
        } elseif ( strpos( $selectors, '@' ) === 0 ) {
            // Other @-rules (keyframes, font-face) - don't prefix.
            return $matches[0];
        } else {
            // Normal selectors.
            $selector_list = array_map( 'trim', explode( ',', $selectors ) );
            $prefixed      = array();
            foreach ( $selector_list as $s ) {
                $s = trim( $s );
                if ( empty( $s ) ) {
                    continue;
                }

                // Handle & or :host as the prefix itself.
                if ( $s === '&' || $s === ':host' ) {
                    $prefixed[] = $prefix;
                } elseif ( strpos( $s, '&' ) === 0 ) {
                    $prefixed[] = $prefix . substr( $s, 1 );
                } elseif ( strpos( $s, ':host' ) === 0 ) {
                    $prefixed[] = $prefix . substr( $s, 5 );
                } else {
                    $prefixed[] = $prefix . ' ' . $s;
                }
            }
            return implode( ', ', $prefixed ) . " {\n" . $content . "\n}";
        }
    };

    return preg_replace_callback( '/([^{]+)\{((?:[^{}]++|(?R))*)\}/s', $callback, $css );
}
