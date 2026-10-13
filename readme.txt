=== WPCalibrate Bulk Price Editor for WooCommerce ===
Contributors: WPCalibrate
Tags: woocommerce, bulk price, price editor, bulk pricing, scheduled prices
Requires at least: 6.4
Tested up to: 7.1.3
Requires PHP: 8.2
WC requires at least: 8.0
WC tested up to: 11.2.1
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Professional, safe, and scheduled bulk price management for WooCommerce simple products and variations with instant preview and full undo history.

== Description ==

**WPCalibrate Bulk Price Editor for WooCommerce** is a high-performance, focused catalog pricing management plugin engineered for store administrators and authorized shop managers.

Unlike generic editors that risk corrupting product catalogs or timing out during massive operations, WPCalibrate Bulk Price Editor uses a deterministic 9-step safety workflow, WooCommerce Action Scheduler background queueing, real-time live preview with race conflict detection, and non-destructive undo history.

### Key Capabilities

* **Full Variation Support:** Treat individual variations as independently selectable pricing targets with parent-product context.
* **Granular Product Filtering:** Filter server-side by search keyword, SKU, categories, tags, product type, regular price range, sale price range, and stock status.
* **Precise Price Operations:**
  * Exact value assignment for regular and sale prices.
  * Fixed-amount increases and decreases.
  * Percentage-based increases and decreases.
  * Clear sale price (removes sale pricing while preserving regular price).
* **Retail & Psychological Rounding Rules:**
  * No additional rounding / store decimal precision.
  * Nearest whole amount.
  * Round up (ceil) or round down (floor).
  * Psychological price endings (.99, .95, .49).
* **Pre-Execution Preview with Race Conflict Detection:** Preview exact before-and-after prices, difference amounts, percentage changes, and WooCommerce validity warnings before touching the database. A deterministic fingerprint prevents tampering.
* **Idempotent Background Processing:** Large catalog operations execute safely in bounded Action Scheduler batches. Process locks and per-item status tracking guarantee that worker retries never apply relative percentage or fixed calculations twice.
* **Timezone-Aware Scheduling:** Schedule future price changes using your WordPress site's configured timezone.
* **Automated Price Restore:** Optionally define a future date/time to automatically revert prices back to the exact captured pre-change values.
* **One-Click Undo:** Complete historical record of all bulk operations with safety checks. If a product's price was manually modified after a bulk job ran, the conflict is flagged and skipped rather than silently overwriting newer data.
* **High-Performance Order Storage (HPOS) & Cart/Checkout Blocks Compatible:** Certified compatible with WooCommerce HPOS and modern block-based storefronts.

== Installation ==

1. Upload the `wpcalibrate-bulk-price-editor` folder to the `/wp-content/plugins/` directory, or install the ZIP file via **Plugins > Add New > Upload Plugin** in your WordPress dashboard.
2. Activate the plugin through the **Plugins** menu in WordPress.
3. Ensure WooCommerce is active and meets the minimum version requirement (8.0+).
4. Navigate to **WPCalibrate > Bulk Price Editor** in the admin sidebar.

== Frequently Asked Questions ==

= How does background processing execute? =
Bulk pricing jobs are queued and processed asynchronously using WooCommerce Action Scheduler in bounded batches (default 50 items per batch). Execution timing depends on Action Scheduler and site traffic unless an automated server-level cron runner (`wp-cron.php` via crontab) is configured on your hosting environment.

= What happens if a price changes between preview and processing? =
The plugin calculates a deterministic checksum for each product target during preview. If another administrator, API call, or automated system alters a target product's price before the bulk job processes it, the worker detects the conflict, safely skips that target to protect the newer price, records a clear diagnostic notice, and continues processing the rest of the job.

= How does the Undo / Restore feature work? =
For every completed operation, exact pre-change regular and sale prices are stored in dedicated indexed database tables. When an authorized user triggers an Undo, the plugin verifies that the current price matches the value written by that job. It restores only eligible, non-conflicting products and flags any items that received newer external price changes.

= What happens when the plugin is uninstalled? =
By default, plugin settings and job history are safely preserved in case you re-install later. An explicit administrator setting ("Delete plugin settings and history when uninstalling") is available in **Settings** if you wish to drop custom tables upon deletion. Product catalog prices and WooCommerce orders are strictly **never** altered or deleted during uninstallation.

= Which user roles have access? =
Administrators have full access to all features, settings, and licensing. Shop Managers possessing the `manage_woocommerce` capability may perform product filtering, preview calculations, and execute bulk price jobs. Settings, diagnostics, and licensing remain strictly restricted to administrators (`manage_options`).

= Why does the License tab display "License integration not configured"? =
WPCalibrate Bulk Price Editor incorporates a clean, decoupled licensing architecture. The external commercial licensing provider adapter is left explicitly pending live provider documentation and endpoints. As required by professional engineering standards, the plugin never simulates fake validation.

== Hooks & Extensibility ==

The plugin provides developer action and filter hooks for custom extensions:

* `do_action( 'wpcalibrate_bpe_job_completed', int $job_id, JobStatus $status )`: Fired when an Action Scheduler bulk price job finishes all batches.

== Changelog ==

= 1.0.0 =
* Initial commercial release.
* Complete WooCommerce HPOS and Cart/Checkout Blocks compatibility.
* 9-step bulk editing workflow with live preview and race conflict detection.
* Action Scheduler idempotent batch worker architecture.
* Timezone-aware scheduling and automatic restore.
* Bounded history retention cleanup.
