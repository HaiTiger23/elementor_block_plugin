<?php
/**
 * Custom Post Type: custom_block
 *
 * Registers the CPT and associated meta fields for block storage.
 *
 * @package CustomBlockBuilder
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Register the Custom Block post type.
 */
function cbb_register_post_type() {
    $labels = array(
        'name'               => __( 'Custom Blocks', 'custom-block-builder' ),
        'singular_name'      => __( 'Custom Block', 'custom-block-builder' ),
        'add_new'            => __( 'Add New Block', 'custom-block-builder' ),
        'add_new_item'       => __( 'Add New Custom Block', 'custom-block-builder' ),
        'edit_item'          => __( 'Edit Custom Block', 'custom-block-builder' ),
        'new_item'           => __( 'New Custom Block', 'custom-block-builder' ),
        'view_item'          => __( 'View Custom Block', 'custom-block-builder' ),
        'search_items'       => __( 'Search Custom Blocks', 'custom-block-builder' ),
        'not_found'          => __( 'No custom blocks found', 'custom-block-builder' ),
        'not_found_in_trash' => __( 'No custom blocks found in Trash', 'custom-block-builder' ),
        'all_items'          => __( 'All Blocks', 'custom-block-builder' ),
        'menu_name'          => __( 'Custom Blocks', 'custom-block-builder' ),
    );

    $args = array(
        'labels'              => $labels,
        'public'              => false,
        'show_ui'             => true,
        'show_in_menu'        => true,
        'menu_position'       => 58,
        'menu_icon'           => 'dashicons-layout',
        'supports'            => array( 'title' ),
        'has_archive'         => false,
        'exclude_from_search' => true,
        'publicly_queryable'  => false,
        'capability_type'     => 'post',
        'map_meta_cap'        => true,
        'rewrite'             => false,
    );

    register_post_type( 'custom_block', $args );
}
add_action( 'init', 'cbb_register_post_type' );

/**
 * Register meta fields for the custom_block post type.
 */
function cbb_register_meta_fields() {
    $meta_fields = array(
        '_cbb_schema'  => array(
            'type'              => 'string',
            'single'            => true,
            'sanitize_callback' => 'cbb_sanitize_json',
            'default'           => '{"fields":[]}',
        ),
        '_cbb_view'    => array(
            'type'              => 'string',
            'single'            => true,
            'sanitize_callback' => 'wp_kses_post',
            'default'           => '',
        ),
        '_cbb_css'     => array(
            'type'              => 'string',
            'single'            => true,
            'sanitize_callback' => 'sanitize_textarea_field',
            'default'           => '',
        ),
        '_cbb_js'      => array(
            'type'              => 'string',
            'single'            => true,
            'sanitize_callback' => 'sanitize_textarea_field',
            'default'           => '',
        ),
        '_cbb_icon'    => array(
            'type'              => 'string',
            'single'            => true,
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => 'eicon-code',
        ),
        '_cbb_version' => array(
            'type'              => 'integer',
            'single'            => true,
            'sanitize_callback' => 'absint',
            'default'           => 1,
        ),
    );

    foreach ( $meta_fields as $meta_key => $args ) {
        register_post_meta( 'custom_block', $meta_key, $args );
    }
}
add_action( 'init', 'cbb_register_meta_fields' );

/**
 * Sanitize JSON string.
 *
 * @param string $value Raw JSON string.
 * @return string Sanitized JSON string.
 */
function cbb_sanitize_json( $value ) {
    $decoded = json_decode( $value, true );

    if ( json_last_error() !== JSON_ERROR_NONE ) {
        return '{"fields":[]}';
    }

    return wp_json_encode( $decoded );
}

/**
 * Save block meta data when post is saved.
 *
 * @param int     $post_id Post ID.
 * @param WP_Post $post    Post object.
 */
function cbb_save_block_meta( $post_id, $post ) {
    // Verify this is our post type.
    if ( 'custom_block' !== $post->post_type ) {
        return;
    }

    // Check permissions.
    if ( ! current_user_can( 'edit_post', $post_id ) ) {
        return;
    }

    // Verify nonce.
    if ( ! isset( $_POST['cbb_meta_nonce'] ) || ! wp_verify_nonce( $_POST['cbb_meta_nonce'], 'cbb_save_meta' ) ) {
        return;
    }

    // Don't save on autosave.
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
        return;
    }

    // Save schema.
    if ( isset( $_POST['cbb_schema'] ) ) {
        update_post_meta( $post_id, '_cbb_schema', cbb_sanitize_json( wp_unslash( $_POST['cbb_schema'] ) ) );
    }

    // Save view template — allow full HTML/PHP for admins.
    if ( isset( $_POST['cbb_view'] ) ) {
        // For administrators, store the raw template without kses filtering.
        if ( current_user_can( 'manage_options' ) ) {
            update_post_meta( $post_id, '_cbb_view', wp_unslash( $_POST['cbb_view'] ) );
        } else {
            update_post_meta( $post_id, '_cbb_view', wp_kses_post( wp_unslash( $_POST['cbb_view'] ) ) );
        }
    }

    // Save CSS.
    if ( isset( $_POST['cbb_css'] ) ) {
        $css = wp_unslash( $_POST['cbb_css'] );
        update_post_meta( $post_id, '_cbb_css', sanitize_textarea_field( $css ) );
        
        // Pre-minify and store.
        $scoped_css = cbb_scope_css( $css, '.cbb-block-' . $post_id );
        update_post_meta( $post_id, '_cbb_css_minified', cbb_minify_css( $scoped_css ) );
    }

    // Save JS.
    if ( isset( $_POST['cbb_js'] ) ) {
        $js = wp_unslash( $_POST['cbb_js'] );
        update_post_meta( $post_id, '_cbb_js', sanitize_textarea_field( $js ) );
        
        // Pre-minify and store.
        update_post_meta( $post_id, '_cbb_js_minified', cbb_minify_js( $js ) );
    }

    // Increment version.
    $current_version = (int) get_post_meta( $post_id, '_cbb_version', true );
    update_post_meta( $post_id, '_cbb_version', $current_version + 1 );
}
add_action( 'save_post', 'cbb_save_block_meta', 10, 2 );

/**
 * Add custom columns to the blocks list table.
 *
 * @param array $columns Existing columns.
 * @return array Modified columns.
 */
function cbb_custom_columns( $columns ) {
    $new_columns = array();

    foreach ( $columns as $key => $value ) {
        $new_columns[ $key ] = $value;
        if ( 'title' === $key ) {
            $new_columns['cbb_slug']    = __( 'Slug', 'custom-block-builder' );
            $new_columns['cbb_version'] = __( 'Version', 'custom-block-builder' );
            $new_columns['cbb_fields']  = __( 'Fields', 'custom-block-builder' );
        }
    }

    return $new_columns;
}
add_filter( 'manage_custom_block_posts_columns', 'cbb_custom_columns' );

/**
 * Render custom column content.
 *
 * @param string $column  Column name.
 * @param int    $post_id Post ID.
 */
function cbb_custom_column_content( $column, $post_id ) {
    switch ( $column ) {
        case 'cbb_slug':
            echo esc_html( get_post_field( 'post_name', $post_id ) );
            break;

        case 'cbb_version':
            $version = get_post_meta( $post_id, '_cbb_version', true );
            echo esc_html( $version ? 'v' . $version : 'v1' );
            break;

        case 'cbb_fields':
            $schema = json_decode( get_post_meta( $post_id, '_cbb_schema', true ), true );
            if ( $schema && ! empty( $schema['fields'] ) ) {
                $count = count( $schema['fields'] );
                /* translators: %d: number of fields */
                printf( esc_html( _n( '%d field', '%d fields', $count, 'custom-block-builder' ) ), $count );
            } else {
                echo '<em>' . esc_html__( 'No fields', 'custom-block-builder' ) . '</em>';
            }
            break;
    }
}
add_action( 'manage_custom_block_posts_custom_column', 'cbb_custom_column_content', 10, 2 );
