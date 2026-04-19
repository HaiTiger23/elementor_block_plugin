# REQUIREMENT: Custom Elementor Block Builder Plugin

## 1. Overview

Xây dựng một WordPress plugin cho phép người dùng tạo các block (component) có thể tái sử dụng trong Elementor, với khả năng:

* Định nghĩa input (giống props) bằng JSON schema
* Viết template render (PHP/HTML)
* Thêm CSS và JS riêng cho từng block
* Preview realtime khi tạo block
* Sử dụng block trong Elementor thông qua widget custom

Mục tiêu: tạo hệ thống tương tự Shopify Section nhưng trong WordPress + Elementor.

---

## 2. Core Features

### 2.1 Block Management (Admin Plugin)

User có thể:

* Tạo block mới
* Chỉnh sửa block
* Xoá block
* Xem preview block

Mỗi block bao gồm:

* name (string)
* slug (string, unique)
* schema (JSON)
* view (PHP/HTML)
* css (string)
* js (string)
* version (int)

---

### 2.2 Schema Definition (Dynamic Input)

Schema định nghĩa các input field cho block.

Ví dụ:

```json
{
  "fields": [
    {
      "type": "text",
      "name": "title",
      "label": "Tiêu đề",
      "default": "Hello"
    },
    {
      "type": "image",
      "name": "image",
      "label": "Ảnh"
    },
    {
      "type": "select",
      "name": "align",
      "options": ["left", "center", "right"]
    }
  ]
}
```

Supported field types:

* text
* textarea
* number
* color
* image
* select
* repeater (optional - advanced)
* boolean (switch)

---

### 2.3 Block Storage

Option 1 (recommended):

* Custom Post Type: `custom_block`

Each block stored in:

* post_title → name
* post_name → slug
* post_meta:

  * schema
  * view
  * css
  * js
  * version

---

### 2.4 Rendering Engine

Function:

```php
render_block($block_id, $data)
```

Responsibilities:

* Load block data (schema, view, css, js)
* Inject variables from `$data`
* Render HTML output
* Attach CSS & JS (scoped)

Implementation idea:

* extract($data)
* ob_start()
* include/eval template
* return HTML

---

### 2.5 Elementor Integration

Create a custom Elementor Widget:

Name: `Custom Block Widget`

Features:

* Dropdown select block (list from CPT)
* Auto generate controls from schema
* Render block preview in Elementor editor

Dynamic control generation:

* Parse schema
* Map field type → Elementor Control

Example mapping:

* text → TEXT
* image → MEDIA
* select → SELECT

---

### 2.6 Live Preview (Admin)

When creating/editing block:

Flow:

* User nhập data test
* JS gửi AJAX request
* Server gọi render_block()
* Trả về HTML
* Render trong iframe preview

Requirements:

* debounce input
* isolate CSS/JS (iframe)

---

## 3. Technical Architecture

### 3.1 Plugin Structure

```
custom-block-builder/
│
├── custom-block-builder.php
├── includes/
│   ├── post-type.php
│   ├── renderer.php
│   ├── ajax.php
│   ├── elementor-widget.php
│
├── admin/
│   ├── editor-ui.php
│   ├── assets/
│
├── assets/
│   ├── css/
│   ├── js/
```

---

### 3.2 Security

* Sanitize schema JSON
* Escape output (esc_html, esc_attr)
* Restrict PHP execution (avoid arbitrary code)
* Optional:

  * disable dangerous functions
  * use sandbox (future)

---

### 3.3 CSS & JS Isolation

Auto scope CSS:

```css
.block-{id} h1 { color:red }
```

Wrap JS:

```js
(function(){
  // block js
})();
```

---

### 3.4 Versioning

Each block có version:

* Khi update block → tăng version
* Elementor lưu version để tránh breaking layout

---

## 4. Advanced Features (Phase 2)

* Repeater field (array input)
* Conditional logic (show/hide field)
* Export/import block (JSON)
* Global block (shared data)
* Template library

---

## 5. Constraints

* Không phụ thuộc plugin ngoài (trừ Elementor)
* Phải hoạt động với Elementor Free (không bắt buộc Pro)
* Code phải mở rộng được (modular)

---

## 6. Expected Outcome

* User có thể tạo block giống component
* Reuse block nhiều nơi
* Truyền data linh hoạt như props
* Preview realtime khi build block
* Tích hợp mượt với Elementor

---

## 7. Notes

* Không dùng Elementor Template làm core
* Không hardcode field
* Schema-driven là bắt buộc

---

## 8. Summary

Hệ thống này là:

> Low-code Component Builder cho WordPress + Elementor

Nếu implement đúng:

* Tăng tốc build UI
* Giảm lặp code
* Dễ scale project lớn

---
