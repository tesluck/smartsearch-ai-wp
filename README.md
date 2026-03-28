# SmartSearch AI — Intelligent Search Plugin for WordPress

[![WordPress Version](https://img.shields.io/badge/WordPress-5.8%2B-blue.svg)](https://wordpress.org/)
[![PHP Version](https://img.shields.io/badge/PHP-7.4%2B-purple.svg)](https://php.net/)
[![License](https://img.shields.io/badge/License-GPL%20v2-green.svg)](LICENSE)

**Your visitors describe their problem in plain English. SmartSearch AI understands what they need and shows the right results.**

No more keyword guessing. No more empty search results. SmartSearch AI transforms basic WordPress search into an intelligent, intent-aware experience.

---

## The Problem

Default WordPress search matches keywords. But real people don't search with keywords — they describe problems:

| What users type | What they actually need |
|---|---|
| "water leaking from wall" | Pipe Repair |
| "AC blowing warm air" | AC Repair |
| "faucet broke off" | Faucet Repair & Installation |
| "hear something in the attic" | Wildlife Removal |
| "install a spigot outside" | Outdoor Plumbing |

SmartSearch AI bridges this gap with a curated intent dictionary + fuzzy matching.

## How It Works

```
User types: "water not coming out of faucet"
    ↓
Fuse.js fuzzy matches against 300+ intent phrases
    ↓
Matches: Faucet Repair & Installation (Plumbing)
    ↓
WP_Query finds companies offering faucet repair in user's area
    ↓
User sees: relevant services + companies, instantly
```

## Features

### Free
- Natural language intent matching with synonyms, intents, and keywords
- Instant client-side autocomplete via Fuse.js (zero server round-trips)
- 40+ home service categories with 300+ intents out of the box
- Fully configurable JSON dictionaries for any industry
- Location-aware search (service + city filtering)
- `[smartsearch]` shortcode with customization attributes
- Developer hooks and filters
- Responsive, theme-agnostic design
- Translation-ready (i18n)

### Pro
- AI-powered fallback via OpenAI for unmatched queries
- Search analytics dashboard
- Premium industry dictionaries
- Visual dictionary builder
- White-label option
- Priority support

## Quick Start

### Install

**From WordPress.org:**
1. Go to Plugins -> Add New
2. Search "SmartSearch AI"
3. Install and Activate

**Manual:**
1. Download the [latest release](https://github.com/mrtask/smartsearch-ai-wp/releases)
2. Upload to `/wp-content/plugins/`
3. Activate in WordPress admin

### Configure

1. Go to **Settings -> SmartSearch AI**
2. Select which post types and taxonomies to search
3. Add `[smartsearch]` to any page

### Shortcode Examples

```
[smartsearch]
[smartsearch location="true"]
[smartsearch placeholder="What do you need fixed?" button_text="Find Help"]
[smartsearch location="true" placeholder="Describe your problem..." button_text="Search"]
```

### PHP Template

```php
<?php echo do_shortcode('[smartsearch location="true"]'); ?>
```

## Dictionary Format

Dictionaries are JSON files in `smartsearch-ai/dictionaries/`. Each service has four matching layers:

```json
{
  "id": "pipe-repair",
  "name": "Pipe Repair",
  "category": "Plumbing",
  "synonyms": ["broken pipe", "burst pipe", "pipe replacement"],
  "intents": [
    "water leaking from wall",
    "water coming through ceiling",
    "pipe froze and burst",
    "hear water running but nothing is on"
  ],
  "keywords": ["pipe", "leak", "burst", "water damage"]
}
```

**Synonyms** = alternate names for the service.
**Intents** = how real people describe the problem (the key differentiator).
**Keywords** = individual terms that relate to the service.

### Creating Custom Dictionaries

1. Export current dictionary from Settings -> SmartSearch AI -> Dictionary
2. Edit the JSON for your industry
3. Import the modified dictionary
4. Or: drop a new `.json` file into `smartsearch-ai/dictionaries/`

## Developer Hooks

| Hook | Type | Description |
|---|---|---|
| `ssai_dictionary` | Filter | Modify the dictionary array before search |
| `ssai_search_index` | Filter | Modify the flat index sent to the frontend |
| `ssai_query_args` | Filter | Modify WP_Query args for post retrieval |
| `ssai_search_performed` | Action | Fires after each search (for analytics) |
| `ssai_is_pro` | Filter | Override Pro status check |

## Contributing

We welcome contributions! See [CONTRIBUTING.md](CONTRIBUTING.md) for guidelines.

The most impactful contribution: **adding intent phrases to the dictionaries.** Think about how real people describe problems, and add those phrases.

## License

GPL v2 or later. See [LICENSE](LICENSE) for the full text.

## Credits

Built and maintained by the [MrTask](https://mrtask.com) team.

**Open source libraries:**
- [Fuse.js](https://www.fusejs.io/) — Lightweight fuzzy-search (Apache 2.0)

## Links

- [WordPress.org Plugin Page](https://wordpress.org/plugins/smartsearch-ai/) (coming soon)
- [Upgrade to Pro](https://smartsearchai.dev/pro/)
- [Documentation](https://smartsearchai.dev/docs/)
- [Buy Us a Coffee](https://buymeacoffee.com/mrtask)
- [Report a Bug](https://github.com/mrtask/smartsearch-ai-wp/issues)
