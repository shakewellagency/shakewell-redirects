=== Shakewell Redirects ===
Contributors: shakewellagency
Tags: redirects, 301, seo, headless, rest-api
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: 0.1.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Manage 301 and 302 redirects in wp-admin, serve them on WordPress, and publish them over REST for headless front ends.

== Description ==

Editors manage redirects under Tools > Redirects. Each redirect has a source path, a target, a status code, a match type and an enabled switch. Redirects are stored as a non-public post type with registered post meta, so there are no custom tables and no other plugins are needed.

Changing page URLs costs search ranking, so the plugin makes accidental changes hard. On every save it refuses:

* a source that is not a path starting with a single "/" (no spaces, "?" or "#")
* a target that is not a site path or a full http(s) URL (no `javascript:`, no `//host`)
* a redirect to itself, including a prefix rule whose target sits under its own source
* a loop or a double hop through another enabled redirect, in either direction
* a duplicate source with the same match type

It warns, without blocking, when the source is the URL of a published post or page, because the redirect would hide that live page.

**Matching rules**

* The path is compared case-insensitively. A trailing slash is optional on both sides. The query string and fragment are ignored.
* Exact: matches that one path only.
* Prefix: matches the source path and everything under it. `/shop/sale/` matches `/shop/sale/` and `/shop/sale/january/`, but not `/shop/salex/`.
* A prefix rule sends everything to the target. It does not keep the rest of the path, and the visitor's query string is not added to the target.
* An exact rule beats a prefix rule. Between prefix rules the longest source wins.
* A prefix rule on "/" is refused.
* Status codes: 301, 302, 307 and 308.
* Targets are used exactly as written: a site path stays a path, an absolute URL stays absolute. External targets are allowed because they are entered by editors and checked when saved.

Only redirects that are published and enabled are served. Draft, disabled and trashed redirects are ignored.

**Capabilities**

* Managing redirects: `edit_others_posts` (Editors and above). Change it with the `shakewell_redirects_manage_capability` filter.
* Importing from the admin screen: `manage_options`. Change it with the `shakewell_redirects_import_capability` filter.

**Import and export**

CSV columns are `source,target,code,match,enabled`. Only `source` and `target` are required; a blank code means 301, a blank match means exact and a blank enabled means yes. Every row is checked with the same rules as the edit screen. If any row is invalid, nothing is imported and each problem is listed with its line number. A dry run reports the same without creating anything.

`wp shakewell-redirects import redirects.csv --dry-run`
`wp shakewell-redirects import redirects.csv`
`wp shakewell-redirects export > redirects.csv`

The same import, a dry-run option and an export button are on Tools > Redirect import.

**Headless sites**

A headless front end can read the redirects and do the redirecting itself. Turn off redirecting in WordPress with `define( 'SHAKEWELL_REDIRECTS_DISABLE', true );` in wp-config.php, or `add_filter( 'shakewell_redirects_serve', '__return_false' );`.

The list is public, read-only and cacheable:

`GET /wp-json/shakewell-redirects/v1/redirects`

It returns `[{"source":"/newsletter/","target":"/newsletter-signup/","code":301,"match":"exact"}]` for enabled, published redirects only. No post IDs, authors or drafts are exposed. The response has an ETag and `Cache-Control: public, max-age=60, stale-while-revalidate=300`, and answers 304 to a matching `If-None-Match`.

To refresh a front-end cache when redirects change, use the action. It fires after a redirect is created, updated, enabled, disabled, trashed, restored or deleted, with the source paths affected (including a source that was replaced). An import fires it once for the whole file.

`add_action( 'shakewell_redirects_changed', function ( array $source_paths ) { /* purge or revalidate */ } );`

**Removing data**

Deleting the plugin keeps your redirects. To remove them on delete, set `define( 'SHAKEWELL_REDIRECTS_DELETE_DATA', true );` in wp-config.php, or run `wp option update shakewell_redirects_delete_data 1`, before deleting the plugin.

= External services =

None. The plugin makes no outside requests and sends no data anywhere.

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/`, or install it with Composer.
2. Activate it from the Plugins screen.
3. Add redirects under Tools > Redirects.

== Frequently Asked Questions ==

= Does a prefix rule keep the rest of the path? =

No. Everything under the source goes to the target. This matches rules such as `/shop/sale/` sending `/shop/sale/january/` to `/shop/offers/`.

= Is the query string passed on? =

No. The query string is ignored for matching and is not added to the target.

= Why was my redirect saved as a draft? =

It failed a check. The reason is shown at the top of the screen and nothing was stored. Fix the fields and publish again.

= Will it redirect a page that exists? =

Yes, redirects run before WordPress loads the page. You get a warning when you save a source that is a published page.

= Can the redirects be used without WordPress redirecting? =

Yes. See Headless sites above.

= Where do I report a security problem? =

Please do not open a public issue. Follow SECURITY.md in the plugin's repository.

== Changelog ==

= 0.1.1 =
* Site-neutral examples and test data, and the readme contributor field.

= 0.1.0 =
* First release.
