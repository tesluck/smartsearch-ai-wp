=== SmartSearch AI — Intelligent Search Plugin for WordPress ===
Contributors: mrtask, ttesluck
Tags: search, AI search, smart search, natural language, autocomplete, service directory, fuzzy search
Requires at least: 5.8
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Your visitors describe their problem in plain English — SmartSearch AI understands what they need and shows the right results. No more keyword guessing.

== Description ==

**SmartSearch AI** transforms your WordPress search from basic keyword matching into an intelligent, intent-aware search experience.

Instead of forcing visitors to guess the exact terms in your database, SmartSearch AI understands natural language. When someone types *"water leaking from wall"*, it knows they need **Pipe Repair**. When they type *"AC blowing warm air"*, it surfaces **AC Repair** services.

= How It Works =

1. **Dictionary-Based Intent Matching** — Ships with a curated synonym dictionary that maps hundreds of natural language phrases to service categories. When a visitor types their problem, the plugin fuzzy-matches against synonyms, intents, and keywords to find the right service.

2. **Instant Client-Side Autocomplete** — Powered by Fuse.js, suggestions appear instantly as the user types — no server round-trips required.

3. **Service + Listing Results** — Returns both the matched service category AND the actual companies/posts that offer it.

4. **Optional AI Fallback (Pro)** — For queries the dictionary can't match, an AI-powered fallback interprets the query and maps it to the right service.

= Perfect For =

* **Service directories** (home services, contractors, local business)
* **Job boards** (match job seekers to positions by skills/needs)
* **Product catalogs** (find products by describing what you need)
* **Knowledge bases** (find articles by describing your problem)
* **Any WordPress site** with content that users struggle to find

= Key Features (Free) =

* 🔍 Natural language search — visitors describe problems, get solutions
* ⚡ Instant autocomplete — zero-latency fuzzy matching via Fuse.js
* 📖 40+ home service categories with 300+ intents out of the box
* 🏗️ Fully configurable — import/export JSON dictionaries for any industry
* 📍 Location-aware search — combine service + city filtering
* 🎨 Theme-agnostic styling — minimal CSS, easily customizable
* 🔧 Developer-friendly — hooks, filters, shortcode attributes
* 🌍 Translation-ready — full i18n support
* 📱 Fully responsive design

= Pro Features =

* 🤖 AI-powered fallback (OpenAI) for unmatched queries
* 📊 Search analytics dashboard — see what visitors are searching for
* 🏭 Premium industry dictionaries (auto, legal, medical, and more)
* ✏️ Visual dictionary builder — add services and intents without editing JSON
* 🏷️ White-label option — remove SmartSearch AI branding
* 📧 Priority email support

[Upgrade to Pro →](https://smartsearchai.dev/pro/)

= Quick Start =

1. Install and activate the plugin
2. Go to **Settings → SmartSearch AI**
3. Choose your post types and taxonomies
4. Add `[smartsearch]` to any page

That's it! Your visitors can now search by describing their problem.

== Installation ==

= Automatic Installation =

1. Go to **Plugins → Add New** in your WordPress dashboard
2. Search for "SmartSearch AI"
3. Click **Install Now**, then **Activate**

= Manual Installation =

1. Download the plugin zip file
2. Go to **Plugins → Add New → Upload Plugin**
3. Upload the zip file and click **Install Now**
4. Activate the plugin

= Configuration =

1. Navigate to **Settings → SmartSearch AI**
2. **General tab**: Set your placeholder text, post types, and taxonomies
3. **Dictionary tab**: View/manage service categories, import/export dictionaries
4. **AI Fallback tab** (Pro): Configure OpenAI API key for unmatched queries
5. **Usage tab**: Copy shortcode examples for your pages

== Frequently Asked Questions ==

= Does this replace the default WordPress search? =

No. SmartSearch AI adds a new search component via shortcode. Your existing WordPress search continues to work. You can replace it if you want, but it's not required.

= How does the dictionary work? =

The dictionary is a JSON file that maps service names to synonyms (alternative names), intents (natural language problem descriptions), and keywords. When a user types something, the plugin fuzzy-matches their query against all of these. The home-services dictionary ships with 40+ categories covering plumbing, HVAC, electrical, roofing, and more.

= Can I use this for industries other than home services? =

Absolutely. Export the dictionary JSON, modify it for your industry, and import it back. Or start from scratch. The dictionary format is simple and documented.

= Do I need the Pro version? =

The free version is fully functional with dictionary-based search. Pro adds AI fallback for edge cases, analytics, premium dictionaries, and a visual editor. Most sites work great with free.

= Does the AI fallback send data to OpenAI? =

Only if you enable it (Pro feature) and provide your own API key. Only the search query is sent — no personal data or site content.

= How do I customize the styling? =

All CSS classes use the `ssai-` prefix. Override them in your theme's CSS. The plugin uses minimal styling that adapts to most themes.

= Is it GDPR compliant? =

The free version processes everything locally — no external requests. The Pro AI fallback sends search queries to OpenAI; you should note this in your privacy policy if enabled.

== Screenshots ==

1. Search bar with instant autocomplete suggestions
2. Natural language query matched to the right service
3. Full search results showing services and companies
4. Admin settings — General configuration
5. Admin settings — Dictionary management
6. Admin settings — AI fallback configuration

== Changelog ==

= 1.0.0 — 2026-03-27 =
* 🎉 Initial release
* Dictionary-based intent matching with synonyms, intents, and keywords
* Client-side Fuse.js autocomplete
* Server-side AJAX search with WP_Query integration
* Home services dictionary (40+ categories, 300+ intents)
* Admin settings panel with tabbed interface
* Dictionary import/export (JSON)
* Shortcode with location, placeholder, and button customization
* OpenAI integration for AI-powered fallback (Pro)
* Full i18n support
* Responsive design

== Upgrade Notice ==

= 1.0.0 =
Initial release. Install to give your visitors intelligent, intent-aware search.

== Credits ==

SmartSearch AI is built and maintained by [MrTask](https://mrtask.com).

**Open Source Libraries:**

* [Fuse.js](https://www.fusejs.io/) — Lightweight fuzzy-search library (Apache 2.0)

**Like this plugin?**

* ⭐ [Rate it on WordPress.org](https://wordpress.org/support/plugin/smartsearch-ai/reviews/#new-post)
* ☕ [Buy us a coffee](https://buymeacoffee.com/mrtask)
* 🐛 [Report a bug](https://github.com/mrtask/smartsearch-ai-wp/issues)
