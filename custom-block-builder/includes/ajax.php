<?php
/**
 * AJAX Handlers
 *
 * Handles AJAX requests for block preview and schema retrieval.
 *
 * @package CustomBlockBuilder
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * AJAX handler: Preview block (admin editor).
 *
 * Receives raw block data and returns rendered HTML for the iframe preview.
 */
function cbb_ajax_preview_block() {
    // Verify nonce.
    check_ajax_referer( 'cbb_preview_nonce', 'nonce' );

    // Verify capabilities.
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( array( 'message' => 'Insufficient permissions.' ), 403 );
    }

    $view      = isset( $_POST['view'] ) ? wp_unslash( $_POST['view'] ) : '';
    $css       = isset( $_POST['css'] ) ? wp_unslash( $_POST['css'] ) : '';
    $js        = isset( $_POST['js'] ) ? wp_unslash( $_POST['js'] ) : '';
    $schema    = isset( $_POST['schema'] ) ? wp_unslash( $_POST['schema'] ) : '{"fields":[]}';
    $test_data = isset( $_POST['test_data'] ) ? json_decode( wp_unslash( $_POST['test_data'] ), true ) : array();

    if ( ! is_array( $test_data ) ) {
        $test_data = array();
    }

    $html = cbb_render_block_preview( $view, $css, $js, $schema, $test_data );

    wp_send_json_success( array( 'html' => $html ) );
}
add_action( 'wp_ajax_cbb_preview_block', 'cbb_ajax_preview_block' );

/**
 * AJAX handler: Get block schema (for Elementor widget).
 *
 * Returns the schema JSON for a specific block.
 */
function cbb_ajax_get_block_schema() {
    // Verify nonce.
    check_ajax_referer( 'cbb_elementor_nonce', 'nonce' );

    // Verify capabilities.
    if ( ! current_user_can( 'edit_posts' ) ) {
        wp_send_json_error( array( 'message' => 'Insufficient permissions.' ), 403 );
    }

    $block_id = isset( $_POST['block_id'] ) ? absint( $_POST['block_id'] ) : 0;

    if ( ! $block_id ) {
        wp_send_json_error( array( 'message' => 'No block ID provided.' ) );
    }

    $block = get_post( $block_id );
    if ( ! $block || 'custom_block' !== $block->post_type ) {
        wp_send_json_error( array( 'message' => 'Block not found.' ) );
    }

    $schema_raw = get_post_meta( $block_id, '_cbb_schema', true );
    $schema     = json_decode( $schema_raw, true );

    if ( ! $schema ) {
        $schema = array( 'fields' => array() );
    }

    wp_send_json_success( array(
        'schema'  => $schema,
        'name'    => $block->post_title,
        'slug'    => $block->post_name,
        'version' => get_post_meta( $block_id, '_cbb_version', true ),
    ) );
}
add_action( 'wp_ajax_cbb_get_block_schema', 'cbb_ajax_get_block_schema' );

/**
 * AJAX handler: Render block for Elementor preview.
 *
 * Called from the Elementor editor to get rendered HTML for the widget preview.
 */
function cbb_ajax_render_elementor_preview() {
    // Verify nonce.
    check_ajax_referer( 'cbb_elementor_nonce', 'nonce' );

    if ( ! current_user_can( 'edit_posts' ) ) {
        wp_send_json_error( array( 'message' => 'Insufficient permissions.' ), 403 );
    }

    $block_id = isset( $_POST['block_id'] ) ? absint( $_POST['block_id'] ) : 0;
    $data     = isset( $_POST['data'] ) ? json_decode( wp_unslash( $_POST['data'] ), true ) : array();

    if ( ! $block_id ) {
        wp_send_json_error( array( 'message' => 'No block ID provided.' ) );
    }

    if ( ! is_array( $data ) ) {
        $data = array();
    }

    $html = cbb_render_block( $block_id, $data, 'elementor-preview-' . $block_id );

    wp_send_json_success( array( 'html' => $html ) );
}
add_action( 'wp_ajax_cbb_render_elementor_preview', 'cbb_ajax_render_elementor_preview' );
