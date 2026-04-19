<?php
/**
 * Plugin Name: Custom Block Builder
 * Plugin URI: https://example.com/custom-block-builder
 * Description: A low-code component builder for WordPress + Elementor. Create reusable, schema-driven blocks with live preview.
 * Version: 1.0.0
 * Author: Developer
 * Author URI: https://example.com
 * Text Domain: custom-block-builder
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

// Plugin constants
define( 'CBB_VERSION', '1.0.0' );
define( 'CBB_PLUGIN_FILE', __FILE__ );
define( 'CBB_PATH', plugin_dir_path( __FILE__ ) );
define( 'CBB_URL', plugin_dir_url( __FILE__ ) );

/**
 * Include core files.
 */
require_once CBB_PATH . 'includes/renderer.php';
require_once CBB_PATH . 'includes/post-type.php';
require_once CBB_PATH . 'includes/ajax.php';
require_once CBB_PATH . 'admin/editor-ui.php';

/**
 * Register Elementor Widget.
 * Hooks into Elementor after it is loaded.
 */
function cbb_register_elementor_widget( $widgets_manager ) {
    require_once CBB_PATH . 'includes/elementor-widget.php';

    // 1. Register the legacy generic widget.
    $widgets_manager->register( new \CBB_Elementor_Widget() );

    // 2. Register individual widgets for each block for better scalability and UX.
    $blocks = cbb_get_active_blocks();

    foreach ( $blocks as $block ) {
        $widgets_manager->register( new \CBB_Single_Block_Widget( [], [ 'block_id' => $block->ID ] ) );
    }
}
add_action( 'elementor/widgets/register', 'cbb_register_elementor_widget' );

/**
 * Get all active custom blocks, with transient caching.
 *
 * @return array Array of block post objects.
 */
function cbb_get_active_blocks() {
    $blocks = get_transient( 'cbb_active_blocks' );

    if ( false === $blocks ) {
        $blocks = get_posts( array(
            'post_type'      => 'custom_block',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
        ) );
        set_transient( 'cbb_active_blocks', $blocks, HOUR_IN_SECONDS );
    }

    return $blocks;
}

/**
 * Clear block cache when a block is saved or deleted.
 */
function cbb_clear_block_cache() {
    delete_transient( 'cbb_active_blocks' );
}
add_action( 'save_post_custom_block', 'cbb_clear_block_cache' );
add_action( 'deleted_post', 'cbb_clear_block_cache' );

/**
 * Enqueue admin assets on the block editor screens.
 *
 * @param string $hook The current admin page hook.
 */
function cbb_admin_enqueue_scripts( $hook ) {
    global $post_type;

    if ( 'custom_block' !== $post_type ) {
        return;
    }

    if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
        return;
    }

    // WordPress ships CodeMirror — use the bundled version.
    $cm_settings = wp_enqueue_code_editor( array( 'type' => 'application/json' ) );
    wp_enqueue_media();

    // Also enqueue CSS & JS modes for CodeMirror.
    wp_enqueue_script( 'wp-theme-plugin-editor' );
    wp_enqueue_style( 'wp-codemirror' );

    // Admin editor styles.
    wp_enqueue_style(
        'cbb-admin-editor',
        CBB_URL . 'admin/assets/admin-editor.css',
        array( 'wp-codemirror' ),
        CBB_VERSION
    );

    // Admin editor scripts.
    wp_enqueue_script(
        'cbb-admin-editor',
        CBB_URL . 'admin/assets/admin-editor.js',
        array( 'jquery', 'wp-theme-plugin-editor' ),
        CBB_VERSION,
        true
    );

    wp_localize_script( 'cbb-admin-editor', 'cbbAdmin', array(
        'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
        'nonce'    => wp_create_nonce( 'cbb_preview_nonce' ),
        'postId'   => get_the_ID(),
    ) );
}
add_action( 'admin_enqueue_scripts', 'cbb_admin_enqueue_scripts' );

/**
 * Enqueue Elementor editor scripts.
 */
function cbb_elementor_editor_scripts() {
    wp_enqueue_script(
        'cbb-elementor-editor',
        CBB_URL . 'assets/js/cbb-elementor.js',
        array( 'jquery' ),
        CBB_VERSION,
        true
    );

    wp_localize_script( 'cbb-elementor-editor', 'cbbElementor', array(
        'ajaxUrl' => admin_url( 'admin-ajax.php' ),
        'nonce'   => wp_create_nonce( 'cbb_elementor_nonce' ),
    ) );
}
add_action( 'elementor/editor/before_enqueue_scripts', 'cbb_elementor_editor_scripts' );

/**
 * Enqueue minimal frontend styles.
 */
function cbb_frontend_styles() {
    wp_enqueue_style(
        'cbb-frontend',
        CBB_URL . 'assets/css/cbb-frontend.css',
        array(),
        CBB_VERSION
    );
}
add_action( 'wp_enqueue_scripts', 'cbb_frontend_styles' );

/**
 * Check Elementor dependency on activation.
 */
function cbb_activation_check() {
    if ( ! did_action( 'elementor/loaded' ) ) {
        // Elementor might not be active yet during activation, just set a transient.
        set_transient( 'cbb_activation_notice', true, 5 );
    }
}
register_activation_hook( __FILE__, 'cbb_activation_check' );

/**
 * Show admin notice if Elementor is not active.
 */
function cbb_admin_notice_elementor_missing() {
    if ( did_action( 'elementor/loaded' ) ) {
        delete_transient( 'cbb_activation_notice' );
        return;
    }

    echo '<div class="notice notice-warning is-dismissible">';
    echo '<p><strong>Custom Block Builder</strong> requires <strong>Elementor</strong> to be installed and activated for full functionality.</p>';
    echo '</div>';
}
add_action( 'admin_notices', 'cbb_admin_notice_elementor_missing' );
