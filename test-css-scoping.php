<?php
define( 'ABSPATH', __DIR__ . '/' );
// Mock WP functions.
if ( ! function_exists( 'sanitize_hex_color' ) ) {
    function sanitize_hex_color( $color ) {
        if ( '' === $color ) return '';
        if ( preg_match( '|^#([A-Fa-f0-9]{3}){1,2}$|', $color ) ) return $color;
        return '';
    }
}
if ( ! function_exists( 'sanitize_text_field' ) ) {
    function sanitize_text_field( $str ) { return trim( $str ); }
}
if ( ! function_exists( 'esc_url_raw' ) ) {
    function esc_url_raw( $url ) { return $url; }
}

require_once 'custom-block-builder/includes/renderer.php';

function test_cbb_scope_css() {
    $prefix = '.cbb-block-123';
    $tests = [
        'simple' => [
            'input' => 'h1 { color: red; } p { color: blue; }',
            'expected' => ".cbb-block-123 h1 {\ncolor: red;\n} .cbb-block-123 p {\ncolor: blue;\n}"
        ],
        'comma' => [
            'input' => 'h1, h2 { color: red; }',
            'expected' => ".cbb-block-123 h1, .cbb-block-123 h2 {\ncolor: red;\n}"
        ],
        'ampersand' => [
            'input' => '& { border: 1px solid; } &:hover { color: green; }',
            'expected' => ".cbb-block-123 {\nborder: 1px solid;\n} .cbb-block-123:hover {\ncolor: green;\n}"
        ],
        'host' => [
            'input' => ':host { border: 1px solid; } :host(.active) { color: red; }',
            'expected' => ".cbb-block-123 {\nborder: 1px solid;\n} .cbb-block-123(.active) {\ncolor: red;\n}"
        ],
        'media' => [
            'input' => '@media (max-width: 600px) { h1 { font-size: 20px; } }',
            'expected' => "@media (max-width: 600px) {\n.cbb-block-123 h1 {\nfont-size: 20px;\n}\n}"
        ],
        'root_selectors' => [
            'input' => 'html, body, :root { background: #fff; }',
            'expected' => ".cbb-block-123, .cbb-block-123, .cbb-block-123 {\nbackground: #fff;\n}"
        ],
        'root_nested' => [
            'input' => 'html h1 { font-size: 2rem; } body .content { padding: 10px; }',
            'expected' => ".cbb-block-123 h1 {\nfont-size: 2rem;\n} .cbb-block-123 .content {\npadding: 10px;\n}"
        ],
        'nested_media' => [
            'input' => '@media screen { @media (min-width: 100px) { div { color: red; } } }',
            'expected' => "@media screen {\n@media (min-width: 100px) {\n.cbb-block-123 div {\ncolor: red;\n}\n}\n}"
        ]
    ];

    foreach ($tests as $name => $data) {
        $output = cbb_scope_css($data['input'], $prefix);
        // Normalize spaces for comparison.
        $output_compact = preg_replace('/\s+/', ' ', trim($output));
        $output_compact = str_replace('} .', '} .', $output_compact); // ensure there is a space
        $expected_compact = preg_replace('/\s+/', ' ', trim($data['expected']));
        
        // Remove spaces around { and } and , for even more lenient comparison
        $clean_output = preg_replace('/\s*([{},])\s*/', '$1', $output_compact);
        $clean_expected = preg_replace('/\s*([{},])\s*/', '$1', $expected_compact);

        if ($clean_output === $clean_expected) {
            echo "PASS: $name\n";
        } else {
            echo "FAIL: $name\n";
            echo "  Expected: $expected_compact\n";
            echo "  Actual:   $output_compact\n";
        }
    }
}

test_cbb_scope_css();
