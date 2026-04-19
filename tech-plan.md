# Technical Implementation Plan: Custom Block Builder

## 1. Executive Summary
The Custom Block Builder is a low-code solution for creating schema-driven, reusable components in WordPress and Elementor. This plan outlines the final development phases to move from the current functional prototype to a robust, scalable production-ready plugin.

## 2. Current Architecture Review
### 2.1 Core Components
- **Storage:** Custom Post Type `custom_block` with JSON-based schema and raw template storage in post meta.
- **Rendering:** PHP `eval()` based engine with data injection via `extract()`.
- **Editor:** Integrated CodeMirror editors for Schema (JSON), View (PHP/HTML), CSS, and JS.
- **Preview:** Real-time iframe-based preview with auto-generated test data forms.
- **Integration:** Native Elementor widget that maps block schema fields to Elementor controls.

### 2.2 Identified Limitations
- **Elementor Scalability:** Current widget registers controls for ALL blocks simultaneously, which will degrade performance as the number of blocks grows.
- **Field Types:** Limited to basic types (text, number, select, boolean, image). Lacks complex types like `repeater`.
- **Security:** Reliance on `eval()` requires strict capability checks (`manage_options`).
- **Asset Loading:** CSS/JS are enqueued inline; no support for external dependencies per block.

---

## 3. Implementation Phases

### Phase 1: Core Refinement & Stability
**Goal:** Polishing the existing experience and ensuring reliability.
- **CSS Scoping Logic:** Improve the `cbb_scope_css` parser to handle complex selectors (media queries, pseudo-elements) more robustly.
- **JS Isolation:** Enhance the JS wrapper to provide a clearer API for the `blockEl` reference.
- **Error Handling:** Implement better PHP error catching during `eval()` to prevent Whitescreen of Death (WSOD) in the editor/frontend.

### Phase 2: Elementor Optimization (High Priority)
**Goal:** Ensure the plugin remains fast even with dozens of custom blocks.
- **Lazy Loading Controls:** Modify `CBB_Elementor_Widget` to only register controls for the *active* block ID using AJAX-based control injection if possible, or optimizing the current conditional logic to reduce memory footprint.
- **Dynamic Icons:** Allow users to specify a Dashicon or SVG icon in the block schema for better recognition in the Elementor panel.

### Phase 3: Advanced Field Types
**Goal:** Support more complex UI components.
- **Repeater Field:** Implement a repeater field type in the schema and map it to Elementor's `REPEATER` control.
- **Nested Fields:** Allow grouping fields for better organization in the Elementor panel.
- **Media Library Integration:** Improve the image field to use the WordPress Media Library instead of just a URL string.

### Phase 4: Security & Performance
**Goal:** Enterprise-grade readiness.
- **Template Sandboxing:** Research and implement a safer alternative to `eval()`, such as a simplified Mustache-like engine or a restricted PHP sandbox.
- **Asset Minification:** Automatically minify and cache the generated scoped CSS and JS.
- **Version Management:** Implement a "Migration" flow when a block schema changes significantly to prevent layout breaks in existing Elementor pages.

---

## 4. Technical Specifications

### 4.1 Repeater Field Schema Example
```json
{
  "type": "repeater",
  "name": "features",
  "label": "Feature List",
  "fields": [
    { "type": "text", "name": "title", "label": "Feature Title" },
    { "type": "image", "name": "icon", "label": "Icon" }
  ]
}
```

### 4.2 Optimized Elementor Control Registration
Instead of:
```php
foreach ($all_blocks as $block) {
    // Register dozens of controls...
}
```
We will explore using a single dynamic control section that reloads via AJAX when the `block_id` changes, significantly reducing the initial widget registration overhead.

---

## 5. Success Metrics
- **Performance:** Elementor editor load time increase < 500ms with 50+ custom blocks.
- **Compatibility:** Full support for Elementor 3.x (Free & Pro).
- **Usability:** A non-developer can create a "Testimonial Slider" block within 15 minutes using the builder.
