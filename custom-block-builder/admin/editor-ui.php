<?php
/**
 * Admin Editor UI
 *
 * Custom meta boxes for editing block schema, view, CSS, JS, and live preview.
 *
 * @package CustomBlockBuilder
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Register meta boxes for the custom_block editor.
 */
function cbb_register_meta_boxes() {
    // Schema Editor.
    add_meta_box(
        'cbb_schema_editor',
        __( '📋 Schema (JSON)', 'custom-block-builder' ),
        'cbb_render_schema_meta_box',
        'custom_block',
        'normal',
        'high'
    );

    // View Template Editor.
    add_meta_box(
        'cbb_view_editor',
        __( '🖼️ View Template (PHP/HTML)', 'custom-block-builder' ),
        'cbb_render_view_meta_box',
        'custom_block',
        'normal',
        'high'
    );

    // CSS Editor.
    add_meta_box(
        'cbb_css_editor',
        __( '🎨 CSS', 'custom-block-builder' ),
        'cbb_render_css_meta_box',
        'custom_block',
        'normal',
        'default'
    );

    // JS Editor.
    add_meta_box(
        'cbb_js_editor',
        __( '⚡ JavaScript', 'custom-block-builder' ),
        'cbb_render_js_meta_box',
        'custom_block',
        'normal',
        'default'
    );

    // Live Preview.
    add_meta_box(
        'cbb_live_preview',
        __( '👁️ Live Preview', 'custom-block-builder' ),
        'cbb_render_preview_meta_box',
        'custom_block',
        'normal',
        'default'
    );

    // Block Info (side).
    add_meta_box(
        'cbb_block_info',
        __( 'ℹ️ Block Info', 'custom-block-builder' ),
        'cbb_render_info_meta_box',
        'custom_block',
        'side',
        'high'
    );

    // Schema Helper (side).
    add_meta_box(
        'cbb_schema_helper',
        __( '📖 Field Types Reference', 'custom-block-builder' ),
        'cbb_render_schema_helper_meta_box',
        'custom_block',
        'side',
        'default'
    );
}
add_action( 'add_meta_boxes', 'cbb_register_meta_boxes' );

/**
 * Render the Schema meta box.
 *
 * @param WP_Post $post Current post object.
 */
function cbb_render_schema_meta_box( $post ) {
    $schema = get_post_meta( $post->ID, '_cbb_schema', true );
    if ( empty( $schema ) ) {
        $schema = "{\n  \"fields\": [\n    {\n      \"type\": \"text\",\n      \"name\": \"title\",\n      \"label\": \"Title\",\n      \"default\": \"Hello World\"\n    }\n  ]\n}";
    } else {
        // Pretty print the stored JSON.
        $decoded = json_decode( $schema, true );
        if ( $decoded ) {
            $schema = wp_json_encode( $decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );
        }
    }

    wp_nonce_field( 'cbb_save_meta', 'cbb_meta_nonce' );
    ?>
    <div class="cbb-editor-wrapper">
        <textarea id="cbb-schema-editor" name="cbb_schema" class="cbb-code-textarea"><?php echo esc_textarea( $schema ); ?></textarea>
        <div class="cbb-editor-hint">
            <span class="dashicons dashicons-info-outline"></span>
            <?php esc_html_e( 'Define block input fields using JSON schema. Changes auto-update the preview form below.', 'custom-block-builder' ); ?>
        </div>
    </div>
    <?php
}

/**
 * Render the View Template meta box.
 *
 * @param WP_Post $post Current post object.
 */
function cbb_render_view_meta_box( $post ) {
    $view = get_post_meta( $post->ID, '_cbb_view', true );
    if ( empty( $view ) ) {
        $view = "<div class=\"my-block\">\n  <h2><?php echo esc_html(\$title); ?></h2>\n</div>";
    }
    ?>
    <div class="cbb-editor-wrapper">
        <textarea id="cbb-view-editor" name="cbb_view" class="cbb-code-textarea"><?php echo esc_textarea( $view ); ?></textarea>
        <div class="cbb-editor-hint">
            <span class="dashicons dashicons-info-outline"></span>
            <?php esc_html_e( 'Write PHP/HTML template. Schema field names are available as PHP variables (e.g. $title, $image).', 'custom-block-builder' ); ?>
        </div>
        <div id="cbb-available-vars" class="cbb-available-vars"></div>
    </div>
    <?php
}

/**
 * Render the CSS meta box.
 *
 * @param WP_Post $post Current post object.
 */
function cbb_render_css_meta_box( $post ) {
    $css = get_post_meta( $post->ID, '_cbb_css', true );
    ?>
    <div class="cbb-editor-wrapper">
        <textarea id="cbb-css-editor" name="cbb_css" class="cbb-code-textarea"><?php echo esc_textarea( $css ); ?></textarea>
        <div class="cbb-editor-hint">
            <span class="dashicons dashicons-info-outline"></span>
            <?php esc_html_e( 'CSS is automatically scoped to this block. No need to worry about conflicts.', 'custom-block-builder' ); ?>
        </div>
    </div>
    <?php
}

/**
 * Render the JS meta box.
 *
 * @param WP_Post $post Current post object.
 */
function cbb_render_js_meta_box( $post ) {
    $js = get_post_meta( $post->ID, '_cbb_js', true );
    ?>
    <div class="cbb-editor-wrapper">
        <textarea id="cbb-js-editor" name="cbb_js" class="cbb-code-textarea"><?php echo esc_textarea( $js ); ?></textarea>
        <div class="cbb-editor-hint">
            <span class="dashicons dashicons-info-outline"></span>
            <?php esc_html_e( 'JS is wrapped in an IIFE. Use "blockEl" to reference the block DOM element.', 'custom-block-builder' ); ?>
        </div>
    </div>
    <?php
}

/**
 * Render the Live Preview meta box.
 *
 * @param WP_Post $post Current post object.
 */
function cbb_render_preview_meta_box( $post ) {
    ?>
    <div class="cbb-preview-container">
        <div class="cbb-preview-toolbar">
            <div class="cbb-preview-toolbar-left">
                <button type="button" id="cbb-preview-btn" class="button button-primary">
                    <span class="dashicons dashicons-visibility"></span>
                    <?php esc_html_e( 'Update Preview', 'custom-block-builder' ); ?>
                </button>
                <label class="cbb-auto-preview-label">
                    <input type="checkbox" id="cbb-auto-preview" checked />
                    <?php esc_html_e( 'Auto-preview', 'custom-block-builder' ); ?>
                </label>
            </div>
            <div class="cbb-preview-status" id="cbb-preview-status"></div>
        </div>

        <div class="cbb-preview-split">
            <!-- Test Data Form -->
            <div class="cbb-preview-form-panel">
                <h4 class="cbb-panel-title">
                    <span class="dashicons dashicons-forms"></span>
                    <?php esc_html_e( 'Test Data', 'custom-block-builder' ); ?>
                </h4>
                <div id="cbb-test-data-form" class="cbb-test-data-form">
                    <p class="cbb-placeholder-text"><?php esc_html_e( 'Define a schema above to see test fields here.', 'custom-block-builder' ); ?></p>
                </div>
            </div>

            <!-- Preview iframe -->
            <div class="cbb-preview-iframe-panel">
                <h4 class="cbb-panel-title">
                    <span class="dashicons dashicons-desktop"></span>
                    <?php esc_html_e( 'Preview Output', 'custom-block-builder' ); ?>
                </h4>
                <div class="cbb-preview-iframe-wrapper">
                    <iframe id="cbb-preview-iframe" sandbox="allow-scripts allow-same-origin"></iframe>
                </div>
            </div>
        </div>
    </div>
    <?php
}

/**
 * Render the Block Info meta box.
 *
 * @param WP_Post $post Current post object.
 */
function cbb_render_info_meta_box( $post ) {
    $version = get_post_meta( $post->ID, '_cbb_version', true );
    $icon    = get_post_meta( $post->ID, '_cbb_icon', true ) ?: 'eicon-code';
    $slug    = $post->post_name;
    ?>
    <div class="cbb-info-box">
        <div class="cbb-info-row">
            <label for="cbb-icon-input"><?php esc_html_e( 'Widget Icon:', 'custom-block-builder' ); ?></label>
            <div class="cbb-icon-input-wrapper">
                <input type="text" id="cbb-icon-input" name="cbb_icon" value="<?php echo esc_attr( $icon ); ?>" class="widefat" placeholder="eicon-code" />
                <p class="description"><?php printf( esc_html__( 'Use %1$sElementor icons%2$s (e.g., %3$seicon-code%4$s).', 'custom-block-builder' ), '<a href="https://elementor.github.io/elementor-icons/" target="_blank">', '</a>', '<code>', '</code>' ); ?></p>
            </div>
        </div>
        <div class="cbb-info-row">
            <label><?php esc_html_e( 'Slug:', 'custom-block-builder' ); ?></label>
            <code id="cbb-block-slug"><?php echo esc_html( $slug ?: __( '(auto-generated)', 'custom-block-builder' ) ); ?></code>
        </div>
        <div class="cbb-info-row">
            <label><?php esc_html_e( 'Version:', 'custom-block-builder' ); ?></label>
            <span class="cbb-version-badge">v<?php echo esc_html( $version ?: '1' ); ?></span>
        </div>
        <div class="cbb-info-row">
            <label><?php esc_html_e( 'Block ID:', 'custom-block-builder' ); ?></label>
            <code><?php echo esc_html( $post->ID ); ?></code>
        </div>
        <div class="cbb-info-row">
            <label><?php esc_html_e( 'Shortcode:', 'custom-block-builder' ); ?></label>
            <code class="cbb-shortcode-display">[custom_block id="<?php echo esc_attr( $post->ID ); ?>"]</code>
        </div>
    </div>
    <?php
}

/**
 * Render the Schema Helper meta box.
 *
 * @param WP_Post $post Current post object.
 */
function cbb_render_schema_helper_meta_box( $post ) {
    ?>
    <div class="cbb-helper-box">
        <div class="cbb-field-type-item">
            <strong>text</strong>
            <span class="cbb-type-desc"><?php esc_html_e( 'Single line text input', 'custom-block-builder' ); ?></span>
        </div>
        <div class="cbb-field-type-item">
            <strong>textarea</strong>
            <span class="cbb-type-desc"><?php esc_html_e( 'Multi-line text area', 'custom-block-builder' ); ?></span>
        </div>
        <div class="cbb-field-type-item">
            <strong>number</strong>
            <span class="cbb-type-desc"><?php esc_html_e( 'Numeric input', 'custom-block-builder' ); ?></span>
        </div>
        <div class="cbb-field-type-item">
            <strong>image</strong>
            <span class="cbb-type-desc"><?php esc_html_e( 'Image picker (WordPress Media Library)', 'custom-block-builder' ); ?></span>
        </div>
        <div class="cbb-field-type-item">
            <strong>select</strong>
            <span class="cbb-type-desc"><?php esc_html_e( 'Dropdown with options[]', 'custom-block-builder' ); ?></span>
        </div>
        <div class="cbb-field-type-item">
            <strong>boolean</strong>
            <span class="cbb-type-desc"><?php esc_html_e( 'True/false toggle', 'custom-block-builder' ); ?></span>
        </div>
        <div class="cbb-field-type-item">
            <strong>group</strong>
            <span class="cbb-type-desc"><?php esc_html_e( 'Nested object with child fields[]', 'custom-block-builder' ); ?></span>
        </div>
        <div class="cbb-field-type-item">
            <strong>section</strong>
            <span class="cbb-type-desc"><?php esc_html_e( 'Visual grouping in editor panel', 'custom-block-builder' ); ?></span>
        </div>

        <hr />

        <p class="cbb-helper-example-title"><strong><?php esc_html_e( 'Example field:', 'custom-block-builder' ); ?></strong></p>
        <pre class="cbb-helper-code">{
  "type": "text",
  "name": "title",
  "label": "Title",
  "default": "Hello"
}</pre>
    </div>
    <?php
}

/**
 * Register a simple shortcode for rendering blocks outside Elementor.
 *
 * Usage: [custom_block id="123" title="Hello"]
 *
 * @param array $atts Shortcode attributes.
 * @return string Rendered block HTML.
 */
function cbb_shortcode_handler( $atts ) {
    $atts = shortcode_atts( array(
        'id' => 0,
    ), $atts, 'custom_block' );

    $block_id = absint( $atts['id'] );

    if ( ! $block_id ) {
        return '<!-- CBB: No block ID specified -->';
    }

    // All additional attributes become data.
    $data = $atts;
    unset( $data['id'] );

    return cbb_render_block( $block_id, $data );
}
add_shortcode( 'custom_block', 'cbb_shortcode_handler' );
