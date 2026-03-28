# Changelog

All notable changes to SmartSearch AI will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] - 2026-03-27

### Added
- Dictionary-based intent matching with synonyms, intents, and keywords
- Client-side Fuse.js autocomplete with zero-latency fuzzy matching
- Server-side AJAX search with WP_Query integration
- Home services default dictionary with 40+ categories and 300+ natural language intents
- Admin settings panel with tabbed interface (General, Dictionary, AI Fallback, Usage)
- Dictionary import/export in JSON format
- `[smartsearch]` shortcode with location, placeholder, and button customization
- Optional OpenAI integration for AI-powered fallback on unmatched queries (Pro)
- Bigram matching for better phrase-level intent detection
- Service result grouping by category in autocomplete dropdown
- Location-aware search combining service type + city/area filtering
- Transient caching for search index and autocomplete results
- Developer hooks: `ssai_dictionary`, `ssai_search_index`, `ssai_query_args`
- Action hook: `ssai_search_performed` for analytics integration
- Full i18n support with `smartsearch-ai` text domain
- Responsive design with mobile-first approach
- Keyboard navigation (arrow keys, Enter, Escape) in suggestions
- ARIA attributes for accessibility
- "Powered by SmartSearch AI" branding (removable in Pro)

[1.0.0]: https://github.com/mrtask/smartsearch-ai-wp/releases/tag/v1.0.0
