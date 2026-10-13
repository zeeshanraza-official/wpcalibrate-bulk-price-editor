# WPCalibrate Bulk Price Editor for WooCommerce

[![WordPress](https://img.shields.io/badge/WordPress-6.4%2B-blue.svg)](https://wordpress.org)
[![WooCommerce](https://img.shields.io/badge/WooCommerce-8.0%2B-purple.svg)](https://woocommerce.com)
[![PHP](https://img.shields.io/badge/PHP-8.2%2B-indigo.svg)](https://php.net)
[![HPOS](https://img.shields.io/badge/WooCommerce-HPOS%20Ready-success.svg)](https://woocommerce.com)
[![License: GPL v2](https://img.shields.io/badge/License-GPLv2-green.svg)](https://www.gnu.org/licenses/gpl-2.0.html)

> High-performance, safe, and scheduled bulk price management for WooCommerce products and variations with instant preview and full undo history.

---

## 📌 About

**WPCalibrate Bulk Price Editor for WooCommerce** is an enterprise-grade price management suite engineered specifically for high-volume WooCommerce stores. It enables store owners and merchandising managers to safely update regular prices and sale prices across simple products, variable products, and individual variations with mathematical precision.

Unlike traditional bulk editing tools that execute destructive direct SQL updates, WPCalibrate Bulk Price Editor routes all changes through WooCommerce's native CRUD layer, ensures database idempotency via Action Scheduler background workers, prevents race conditions with atomic state checks, and maintains an immutable audit trail with one-click rollback.

### Key Architectural Highlights
- **100% Native WooCommerce CRUD**: Maintains complete metadata integrity, respects tax and cache layers, and triggers all relevant WooCommerce lifecycle hooks (`woocommerce_update_product`, `woocommerce_product_set_stock`, etc.).
- **High-Performance Order Storage (HPOS) & Cart/Checkout Blocks**: Fully declared compatibility with WooCommerce High-Performance Order Storage (custom order tables) and Gutenberg Cart/Checkout blocks.
- **Zero Third-Party Vendor Bloat**: Pure vanilla JavaScript admin interface and PSR-4 PHP architecture without heavyweight external dependencies.
- **Dedicated Custom Tables**: Employs indexed custom tables (`wpcalibrate_price_jobs` and `wpcalibrate_price_job_items`) for queue dispatching and audit tracking without polluting `wp_options` or `wp_postmeta`.
- **In-Dashboard One-Click GitHub Updates**: Built-in GitHub updater notifies store managers of new releases directly inside the WordPress dashboard with one-click updating.

---

## ✨ Features

### 🎯 Precision Filtering Engine
- **Multi-Taxonomy Filtering**: Filter catalog items by product category, tag, and attribute taxonomy terms.
- **Price & Stock Range Filters**: Narrow targets by regular price ranges, sale price presence, and stock status (`instock`, `outofstock`, `onbackorder`).
- **Product Type Coverage**: Seamlessly filter and edit Simple products, Variable parent products, and individual Product Variations.
- **Live Search**: Instant SKU and product title search with debounced server-side querying.

### ⚡ 10 Price Action Operations
- **Exact Regular Price**: Set regular price to an exact fixed monetary amount.
- **Exact Sale Price**: Set sale price to an exact fixed monetary amount.
- **Increase Regular Price (Fixed)**: Add a fixed monetary amount to regular price.
- **Decrease Regular Price (Fixed)**: Deduct a fixed monetary amount from regular price (floored safely at `0.00`).
- **Increase Regular Price (Percent)**: Increase regular price by a percentage.
- **Decrease Regular Price (Percent)**: Decrease regular price by a percentage.
- **Discount Sale from Regular (Percent)**: Calculate and set new sale price as a percentage discount directly from regular price.
- **Discount Sale from Regular (Fixed)**: Calculate and set new sale price by subtracting a fixed discount directly from regular price.
- **Clear Sale Price**: Remove active sale prices and return products to standard regular pricing.
- **Intelligent Fallback**: Relative sale adjustments automatically fall back to regular price baseline when products do not yet have an existing sale price.

### 🏷️ Smart Psychological Rounding
- **No Additional Rounding**: Preserves standard two-decimal mathematical output.
- **Round to Nearest Integer (`.00`)**: Rounds calculations to whole currency units.
- **Round Up (`ceil`) / Round Down (`floor`)**: Mathematical directional rounding.
- **Charm Pricing `.99` (`END_99`)**: Floors integer and appends `.99` (e.g., `$14.35` → `$14.99`).
- **Charm Pricing `.95` (`END_95`)**: Floors integer and appends `.95` (e.g., `$14.35` → `$14.95`).
- **Charm Pricing `.49` (`END_49`)**: Floors integer and appends `.49` (e.g., `$14.80` → `$14.49`).

### 🛡️ Safety & Concurrency Architecture
- **Interactive Dry-Run Preview**: Live visual calculation table displays before-price, proposed price, currency diff, percentage change, and smart warnings (e.g., sale price exceeding regular price).
- **Transient Worker Locks**: Prevents overlapping queue runners via unique 120-second TTL job locks (`wpcalibrate_bpe_lock_job_{id}`).
- **Pre-Execution Checksums**: Captures SHA-256 target fingerprints at preview time. If another administrator edits a product concurrently, the worker detects the discrepancy, safely skips the target, logs a warning, and continues.
- **Immediate + Background Execution**: Processes initial batch (50 items) synchronously for instant feedback on small catalogs, and seamlessly offloads larger batches to WooCommerce Action Scheduler.

### ⏪ One-Click Non-Destructive Undo
- **Immutable Historical Logs**: Records exact timestamp, initiated administrator, target scope, and item-by-item price mutations.
- **Race-Safe Rollback**: Before reverting a price, the undo engine verifies that current product price still matches the value written by the job, preventing accidental overwriting of intentional newer modifications.

### ⏰ Scheduling & Maintenance
- **Future Date Scheduling**: Schedule price promotions, flash sales, or price revisions to execute automatically at a future UTC timestamp.
- **Automated Retention Cleanup**: Daily cron cleans historical jobs and logs older than the configured threshold (default 30 days).
- **Theme-Adaptive Admin Sizing**: Clean 20x20 dashboard icon styled with CSS masks and `currentColor` to dynamically match all WordPress admin color palettes.

---

## 📖 Documentation & How to Use

### Installation

#### Method 1: WordPress Dashboard Upload (Recommended)
1. Download the latest `wpcalibrate-bulk-price-editor-1.0.0.zip` from the [Releases](https://github.com/zeeshanraza-official/wpcalibrate-bulk-price-editor/releases) page.
2. In your WordPress admin dashboard, navigate to **Plugins → Add New Plugin → Upload Plugin**.
3. Choose the downloaded ZIP file and click **Install Now**.
4. Click **Activate Plugin**.

#### Method 2: Manual FTP / SFTP Installation
1. Extract the `wpcalibrate-bulk-price-editor` folder from the ZIP archive.
2. Upload the folder to `/wp-content/plugins/wpcalibrate-bulk-price-editor/` on your server.
3. In WordPress admin, go to **Plugins → Installed Plugins** and activate **WPCalibrate Bulk Price Editor for WooCommerce**.

---

### Step-by-Step Guided Workflow

```
[ Filter Catalog ] ➔ [ Select Products ] ➔ [ Configure Rule ] ➔ [ Preview Changes ] ➔ [ Apply / Schedule ]
```

1. **Navigate to the Editor**: In the WordPress admin sidebar, click **WPCalibrate → Bulk Price Editor**.
2. **Filter Your Products**:
   - Select categories, stock status, or product types in the filter toolbar.
   - Click **Apply Filters** to refresh the target table.
3. **Select Products**:
   - Check individual products or use the header checkbox to select all visible items.
   - *(Note: If you click preview without checking individual items, the UI will prompt you with the option to select all matching items automatically).*
4. **Choose Price Operation**:
   - Select your desired operation (e.g., *Discount sale price from regular price (%)*).
   - Enter your value (e.g., `20` for 20% off).
   - Select a rounding mode if desired (e.g., *Charm Pricing (.99)*).
5. **Calculate & Preview Changes**:
   - Click **Calculate & Preview Changes** to generate the real-time simulation table.
   - Review each row’s before price, proposed price, difference, and any warning badges.
6. **Execute or Schedule**:
   - Click **Apply Price Changes Now** for immediate execution.
   - Or toggle **Schedule for future date/time** and specify the execution timestamp.
7. **Monitor & Rollback**:
   - View execution status, processed count, and elapsed time in the progress banner.
   - Navigate to the **History** tab at any time to review past runs or execute a 1-click **Undo**.

---

### 🔄 In-Dashboard Automatic Updates

This plugin includes native GitHub Release update integration:
- When a new version is tagged and released on GitHub, WordPress automatically flags an update notification under **Dashboard → Updates** and **Plugins**.
- Store administrators can click **Update Now** to pull and install the latest release automatically without manual FTP uploads.
- Click **View version details** in the plugins list to view the full changelog directly inside the native WordPress modal.

---

## 📋 Changelog

### [1.0.2] - 2026-10-13
- **Sidebar Admin Icon Styling**: Implemented CSS mask technique (`-webkit-mask-image` with `currentColor`) on `#adminmenu #toplevel_page_wpcalibrate div.wp-menu-image:before` to dynamically match standard WordPress admin color schemes (`#a7aaad` default inactive, `#fff` active/hover).
- **Price Action Regular Fallback**: Added explicit actions `DISCOUNT_SALE_FROM_REGULAR_PERCENT` and `DISCOUNT_SALE_FROM_REGULAR_FIXED`. Added regular price fallback in `PricingService` for relative sale operations on products without previous sale prices.
- **SQL Decimal Null Handling**: Fixed `JobItemRepository` where null values prepared with `%s` inserted `''` into MySQL `decimal(18,4)` columns as `0.0000`, resolving false race-conflict skips in `BatchWorker` and `UndoService`.
- **Immediate Batch Execution**: Non-scheduled jobs now trigger synchronous processing of the initial batch immediately upon creation for instant feedback.
- **Selection UX Prompt**: Added confirmation modal in `bulk-editor.js` to automatically select all matching items if preview is clicked without manually ticked checkboxes.
- **In-Dashboard GitHub Updater**: Added native `GitHubUpdater` service and WordPress plugin headers for 1-click updates from GitHub releases.

### [1.0.1] - 2026-10-13
- **Admin Layout Critical Error**: Fixed missing `use` statement for `AdminMenu` in `templates/admin/layout.php`.
- **Sidebar Menu Icon Sizing**: Constrained dimensions to standard 20x20 Dashicon bounding box.
- **Overview Tab**: Added dedicated Overview tab explaining plugin purpose, 9-step guided workflow, core calculation methods, and safety architecture.

### [1.0.0] - 2026-10-13
- **Initial Production Architecture**:
  - Full product coverage: Simple, Variable, and Variations.
  - 10 price modification operations with 7 psychological rounding modes.
  - Real-time dry-run preview simulation with race-conflict detection.
  - Action Scheduler background batch worker with transient locking.
  - Conflict-safe non-destructive undo engine with audit logging.
  - HPOS and Cart/Checkout Blocks compatibility declared.
  - 73 automated unit and integration tests passing with 100% success rate.

---

## 💬 Support & Inquiries

- **Author**: [WPCalibrate](https://wpcalibrate.com)
- **Marketplace**: [marketplace.wpcalibrate.com](https://marketplace.wpcalibrate.com/)
- **Direct Support Email**: [support@wpcalibrate.com](mailto:support@wpcalibrate.com)
- **WhatsApp Support**: [+447474795976](https://wa.me/447474795976)
- **GitHub Issues**: [Issues](https://github.com/zeeshanraza-official/wpcalibrate-bulk-price-editor/issues)

---

## 📄 License

This plugin is free software, licensed under the terms of the [GNU General Public License v2.0 or later](LICENSE.txt).  
Copyright © 2026 WPCalibrate.
