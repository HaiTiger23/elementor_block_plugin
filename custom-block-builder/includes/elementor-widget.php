<?php
/**
 * Elementor Widget: Custom Block
 *
 * Integrates custom blocks as a native Elementor widget with dynamic controls.
 *
 * @package CustomBlockBuilder
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class CBB_Elementor_Widget_Base
 *
 * Abstract base class for Custom Block Builder widgets.
 */
abstract class CBB_Elementor_Widget_Base extends \Elementor\Widget_Base {

    /**
     * Get widget categories.
     *
     * @return array Widget categories.
     */
    public function get_categories() {
        return array( 'general' );
    }

    /**
     * Get widget keywords.
     *
     * @return array Widget keywords.
     */
    public function get_keywords() {
        return array( 'custom', 'block', 'component', 'builder', 'schema' );
    }

    /**
     * Get schema for a specific block.
     *
     * @param int $block_id Block post ID.
     * @return array|null Decoded schema array or null.
     */
    protected function get_block_schema( $block_id ) {
        if ( ! $block_id ) {
            return null;
        }

        $schema_raw = get_post_meta( $block_id, '_cbb_schema', true );
        return json_decode( $schema_raw, true );
    }

    /**
     * Register a single Elementor control based on a schema field definition.
     *
     * @param array  $field      Schema field definition.
     * @param int    $block_id   Block post ID (used for unique control names).
     * @param string $name_prefix Optional prefix for the control name.
     * @param object $container  Optional. The container to add the control to (Widget or Repeater).
     */
    protected function register_single_field_control( $field, $block_id, $name_prefix = '', $container = null ) {
        if ( ! $container ) {
            $container = $this;
        }

        $type = $field['type'] ?? 'text';
        if ( $type === 'section' ) {
            if ( ! empty( $field['fields'] ) && is_array( $field['fields'] ) ) {
                foreach ( $field['fields'] as $sub_field ) {
                    $this->register_single_field_control( $sub_field, $block_id, $name_prefix, $container );
                }
            }
            return;
        }

        if ( empty( $field['name'] ) ) {
            return;
        }

        $field_name = $name_prefix . sanitize_key( $field['name'] );
        $label      = $field['label'] ?? ucfirst( $field['name'] );
        $default    = $field['default'] ?? '';

        switch ( $type ) {
            case 'group':
                if ( ! empty( $field['fields'] ) && is_array( $field['fields'] ) ) {
                    foreach ( $field['fields'] as $sub_field ) {
                        $this->register_single_field_control( $sub_field, $block_id, $field_name . '_', $container );
                    }
                }
                break;

            case 'text':
                $container->add_control( $field_name, array(
                    'label'   => $label,
                    'type'    => \Elementor\Controls_Manager::TEXT,
                    'default' => $default,
                ) );
                break;

            case 'textarea':
                $container->add_control( $field_name, array(
                    'label'   => $label,
                    'type'    => \Elementor\Controls_Manager::TEXTAREA,
                    'default' => $default,
                ) );
                break;

            case 'number':
                $container->add_control( $field_name, array(
                    'label'   => $label,
                    'type'    => \Elementor\Controls_Manager::NUMBER,
                    'default' => $default,
                ) );
                break;

            case 'image':
                $default_url = is_array( $default ) ? ( $default['url'] ?? '' ) : $default;
                $container->add_control( $field_name, array(
                    'label'   => $label,
                    'type'    => \Elementor\Controls_Manager::MEDIA,
                    'default' => array(
                        'url' => $default_url,
                    ),
                ) );
                break;

            case 'select':
                $options = array();
                if ( ! empty( $field['options'] ) && is_array( $field['options'] ) ) {
                    foreach ( $field['options'] as $opt ) {
                        $options[ $opt ] = ucfirst( $opt );
                    }
                }
                $container->add_control( $field_name, array(
                    'label'   => $label,
                    'type'    => \Elementor\Controls_Manager::SELECT,
                    'options' => $options,
                    'default' => $default,
                ) );
                break;

            case 'color':
                $container->add_control( $field_name, array(
                    'label'   => $label,
                    'type'    => \Elementor\Controls_Manager::COLOR,
                    'default' => $default,
                ) );
                break;

            case 'boolean':
                $container->add_control( $field_name, array(
                    'label'        => $label,
                    'type'         => \Elementor\Controls_Manager::SWITCHER,
                    'label_on'     => __( 'Yes', 'custom-block-builder' ),
                    'label_off'    => __( 'No', 'custom-block-builder' ),
                    'return_value' => '1',
                    'default'      => $default ? '1' : '',
                ) );
                break;

            case 'repeater':
                $repeater = new \Elementor\Repeater();
                if ( ! empty( $field['fields'] ) && is_array( $field['fields'] ) ) {
                    foreach ( $field['fields'] as $sub_field ) {
                        $this->register_single_field_control( $sub_field, $block_id, '', $repeater );
                    }
                }

                $title_field = '';
                if ( ! empty( $field['fields'] ) && is_array( $field['fields'] ) ) {
                    $title_field = '{{{' . sanitize_key( $field['fields'][0]['name'] ) . '}}}';
                }

                $container->add_control( $field_name, array(
                    'label'       => $label,
                    'type'        => \Elementor\Controls_Manager::REPEATER,
                    'fields'      => $repeater->get_controls(),
                    'title_field' => $title_field,
                ) );
                break;
        }
    }

    /**
     * Extract block data from Elementor settings based on schema.
     *
     * @param array  $schema      Decoded schema.
     * @param array  $settings    Elementor widget settings.
     * @param string $name_prefix Optional prefix for control names.
     * @return array Extracted data.
     */
    protected function extract_block_data( $schema, $settings, $name_prefix = '' ) {
        $data = array();
        if ( empty( $schema['fields'] ) || ! is_array( $schema['fields'] ) ) {
            return $data;
        }

        foreach ( $schema['fields'] as $field ) {
            if ( empty( $field['name'] ) && ( empty( $field['type'] ) || $field['type'] !== 'section' ) ) {
                continue;
            }

            if ( isset( $field['type'] ) && $field['type'] === 'section' ) {
                if ( ! empty( $field['fields'] ) && is_array( $field['fields'] ) ) {
                    $section_data = $this->extract_block_data( array( 'fields' => $field['fields'] ), $settings, $name_prefix );
                    $data = array_merge( $data, $section_data );
                }
                continue;
            }

            $control_name = $name_prefix . sanitize_key( $field['name'] ?? '' );
            if ( isset( $field['type'] ) && $field['type'] === 'group' ) {
                $group_data = array();
                if ( ! empty( $field['fields'] ) && is_array( $field['fields'] ) ) {
                    $group_data = $this->extract_block_data( array( 'fields' => $field['fields'] ), $settings, $control_name . '_' );
                }
                $data[ $field['name'] ] = $group_data;
                continue;
            }

            if ( isset( $settings[ $control_name ] ) ) {
                $value = $settings[ $control_name ];

                if ( $field['type'] === 'image' && is_array( $value ) ) {
                    $value = $value['url'] ?? '';
                } elseif ( $field['type'] === 'boolean' ) {
                    $value = ( $value === '1' || $value === 'yes' || $value === true );
                } elseif ( $field['type'] === 'repeater' && is_array( $value ) ) {
                    $repeater_data = array();
                    foreach ( $value as $item ) {
                        // For repeaters, sub-fields don't have prefix as they are scoped within the item array.
                        $repeater_data[] = $this->extract_block_data( array( 'fields' => $field['fields'] ?? [] ), $item, '' );
                    }
                    $value = $repeater_data;
                }

                $data[ $field['name'] ] = $value;
            }
        }
        return $data;
    }

    /**
     * Common content template placeholder.
     */
    protected function render_editor_placeholder( $title ) {
        ?>
        <div style="padding:40px;text-align:center;color:#999;background:#f9f9f9;border:2px dashed #ddd;border-radius:8px;">
            <p style="font-size:16px;margin:0;">🧩 <?php echo esc_html( $title ); ?></p>
        </div>
        <?php
    }
}

/**
 * Class CBB_Elementor_Widget
 *
 * Legacy generic widget that allows selecting any block from a dropdown.
 */
class CBB_Elementor_Widget extends CBB_Elementor_Widget_Base {

    public function get_name() {
        return 'cbb_custom_block';
    }

    public function get_title() {
        return __( 'Custom Block (Legacy)', 'custom-block-builder' );
    }

    public function get_icon() {
        return 'eicon-code';
    }

    private function get_block_options() {
        $blocks = get_posts( array(
            'post_type'      => 'custom_block',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'orderby'        => 'title',
            'order'          => 'ASC',
        ) );

        $options = array( '' => __( '— Select Block —', 'custom-block-builder' ) );

        foreach ( $blocks as $block ) {
            $options[ $block->ID ] = $block->post_title;
        }

        return $options;
    }

    protected function register_controls() {
        $this->start_controls_section(
            'section_block_select',
            array(
                'label' => __( 'Block Selection', 'custom-block-builder' ),
                'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
            )
        );

        $this->add_control(
            'block_id',
            array(
                'label'   => __( 'Choose Block', 'custom-block-builder' ),
                'type'    => \Elementor\Controls_Manager::SELECT,
                'options' => $this->get_block_options(),
                'default' => '',
            )
        );

        $this->add_control(
            'block_info',
            array(
                'type'            => \Elementor\Controls_Manager::RAW_HTML,
                'raw'             => '<p style="color:#93003c;font-style:italic;">' . __( 'Select a block above, then save and refresh to see its controls below.', 'custom-block-builder' ) . '</p>',
                'content_classes' => 'elementor-panel-alert elementor-panel-alert-info',
            )
        );

        $this->end_controls_section();

        $this->register_dynamic_field_controls();
    }

    private function register_dynamic_field_controls() {
        // Optimization: Only register controls for the currently selected block if we are in the editor and have the data.
        // However, for simplicity and compatibility with how Elementor loads the panel, we still register all with conditions
        // but we limit it to a reasonable number or use a transient if needed.
        // For Phase 2, we keep this but encourage using the single widgets.
        $blocks = get_posts( array(
            'post_type'      => 'custom_block',
            'posts_per_page' => 20, // Limit legacy widget to first 20 blocks for performance.
            'post_status'    => 'publish',
        ) );

        foreach ( $blocks as $block ) {
            $schema = $this->get_block_schema( $block->ID );

            if ( empty( $schema['fields'] ) ) {
                continue;
            }

            $this->start_controls_section(
                'section_block_fields_' . $block->ID,
                array(
                    'label'     => sprintf( __( '%s — Fields', 'custom-block-builder' ), $block->post_title ),
                    'tab'       => \Elementor\Controls_Manager::TAB_CONTENT,
                    'condition' => array(
                        'block_id' => (string) $block->ID,
                    ),
                )
            );

            foreach ( $schema['fields'] as $field ) {
                $this->register_single_field_control( $field, $block->ID, 'cbb_field_' . $block->ID . '_' );
            }

            $this->end_controls_section();
        }
    }

    protected function render() {
        $settings = $this->get_settings_for_display();
        $block_id = ! empty( $settings['block_id'] ) ? absint( $settings['block_id'] ) : 0;

        if ( ! $block_id ) {
            if ( \Elementor\Plugin::$instance->editor->is_edit_mode() ) {
                $this->render_editor_placeholder( __( 'Select a Custom Block', 'custom-block-builder' ) );
            }
            return;
        }

        $schema = $this->get_block_schema( $block_id );
        $data   = $this->extract_block_data( $schema, $settings, 'cbb_field_' . $block_id . '_' );

        echo cbb_render_block( $block_id, $data, $this->get_id() );
    }

    protected function content_template() {
        ?>
        <#
        if ( ! settings.block_id ) {
        #>
            <div style="padding:40px;text-align:center;color:#999;background:#f9f9f9;border:2px dashed #ddd;border-radius:8px;">
                <p style="font-size:16px;margin:0;">🧩 <?php echo esc_html__( 'Select a Custom Block', 'custom-block-builder' ); ?></p>
            </div>
        <#
        } else {
        #>
            <div class="cbb-elementor-preview-loading" style="padding:20px;text-align:center;color:#666;">
                <p>⏳ <?php echo esc_html__( 'Loading block preview...', 'custom-block-builder' ); ?></p>
            </div>
        <#
        }
        #>
        <?php
    }
}

/**
 * Class CBB_Single_Block_Widget
 *
 * A dedicated widget for a specific custom block.
 */
class CBB_Single_Block_Widget extends CBB_Elementor_Widget_Base {

    protected $block_id;
    protected $block_title;
    protected $block_slug;
    protected $block_icon;

    public function __construct( $data = [], $args = null ) {
        parent::__construct( $data, $args );

        if ( isset( $args['block_id'] ) ) {
            $this->block_id    = $args['block_id'];
            $this->block_title = get_the_title( $this->block_id );
            $this->block_slug  = get_post_field( 'post_name', $this->block_id );
            $this->block_icon  = get_post_meta( $this->block_id, '_cbb_icon', true ) ?: 'eicon-code';
        }
    }

    public function get_name() {
        return 'cbb_block_' . $this->block_slug;
    }

    public function get_title() {
        return $this->block_title ?: __( 'Custom Block', 'custom-block-builder' );
    }

    public function get_icon() {
        return $this->block_icon;
    }

    protected function register_controls() {
        $schema = $this->get_block_schema( $this->block_id );

        if ( empty( $schema['fields'] ) ) {
            $this->start_controls_section(
                'section_no_fields',
                array(
                    'label' => __( 'Information', 'custom-block-builder' ),
                )
            );
            $this->add_control(
                'no_fields_info',
                array(
                    'type' => \Elementor\Controls_Manager::RAW_HTML,
                    'raw'  => __( 'This block has no configurable fields.', 'custom-block-builder' ),
                )
            );
            $this->end_controls_section();
            return;
        }

        $general_fields = array();
        $sections       = array();

        foreach ( $schema['fields'] as $field ) {
            if ( isset( $field['type'] ) && $field['type'] === 'section' ) {
                $sections[] = $field;
            } else {
                $general_fields[] = $field;
            }
        }

        if ( ! empty( $general_fields ) ) {
            $this->start_controls_section(
                'section_general',
                array(
                    'label' => __( 'General', 'custom-block-builder' ),
                )
            );
            foreach ( $general_fields as $field ) {
                $this->register_single_field_control( $field, $this->block_id );
            }
            $this->end_controls_section();
        }

        foreach ( $sections as $index => $section ) {
            $this->start_controls_section(
                'section_' . $index,
                array(
                    'label' => $section['label'] ?? __( 'Section', 'custom-block-builder' ),
                )
            );

            if ( ! empty( $section['fields'] ) && is_array( $section['fields'] ) ) {
                foreach ( $section['fields'] as $sub_field ) {
                    $this->register_single_field_control( $sub_field, $this->block_id );
                }
            }

            $this->end_controls_section();
        }

        if ( empty( $general_fields ) && empty( $sections ) ) {
            $this->start_controls_section(
                'section_fields',
                array(
                    'label' => __( 'Content', 'custom-block-builder' ),
                )
            );
            foreach ( $schema['fields'] as $field ) {
                $this->register_single_field_control( $field, $this->block_id );
            }
            $this->end_controls_section();
        }
    }

    protected function render() {
        $settings = $this->get_settings_for_display();
        $schema   = $this->get_block_schema( $this->block_id );
        $data     = $this->extract_block_data( $schema, $settings );

        echo cbb_render_block( $this->block_id, $data, $this->get_id() );
    }

    protected function content_template() {
        ?>
        <div class="cbb-elementor-preview-loading" style="padding:20px;text-align:center;color:#666;">
            <p>⏳ <?php echo esc_html__( 'Loading block preview...', 'custom-block-builder' ); ?></p>
        </div>
        <?php
    }
}
