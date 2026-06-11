=== PhilDesigns Floating Labels for Forms ===
Contributors: phildesigns
Tags: contact-form-7, gravity-forms, floating-labels, forms, css
Requires at least: 6.7
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

CSS floating labels for Contact Form 7 and Gravity Forms. Three styles, per-form overrides, and no external dependencies.

== Description ==

Floating Labels for Forms transforms standard form labels and legends into accessible CSS floating labels — the kind that start inside the field as a placeholder and glide above the input when the user focuses or fills it.

Labels remain in the DOM at all times; only their visual position changes. This keeps the form fully accessible and compatible with screen readers without any additional markup.

= Features =

* Three built-in styles: Underline, Outlined Box, and Padded Box
* Set one style globally and override it per individual form
* Colour palette editor — customise accent, border, background, and label colours with a live colour picker
* Supports Contact Form 7 and Gravity Forms
* No external JavaScript or CSS dependencies

= Usage =

1. Activate the plugin.
2. Go to **Settings → Floating Labels** and choose a default style.
3. Optionally override the style for individual CF7 or Gravity Forms forms.
4. Save settings — done.

== Installation ==

1. Upload the `floating-labels-forms` folder to `/wp-content/plugins/`
2. Activate the plugin through the **Plugins** menu in WordPress
3. Go to **Settings → Floating Labels** to configure styles

== Frequently Asked Questions ==

= Does this work without Contact Form 7 or Gravity Forms? =

No. The plugin applies styles specifically to CF7 and Gravity Forms markup. At least one of them must be active.

= Will this break my form's accessibility? =

No. Labels are never removed or hidden — they stay in the DOM and are always readable by screen readers. Only their visual position changes via CSS.

= Can I use a different style for each form? =

Yes. The settings page shows a table of all your forms and lets you assign a style (or "Use Global") to each one individually.

== Screenshots ==

1. Settings page showing global style selector, per-form overrides, and colour palette editor.

== Changelog ==

= 1.0.0 =
* Initial release

== Upgrade Notice ==

= 1.0.0 =
Initial release.
