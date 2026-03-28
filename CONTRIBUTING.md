# Contributing to SmartSearch AI

Thank you for your interest in contributing to SmartSearch AI! This document provides guidelines and instructions for contributing.

## Getting Started

1. Fork the repository on GitHub
2. Clone your fork locally: `git clone https://github.com/YOUR-USERNAME/smartsearch-ai-wp.git`
3. Create a feature branch: `git checkout -b feature/your-feature-name`
4. Make your changes
5. Test thoroughly (see Testing section below)
6. Commit with a clear message: `git commit -m "Add: description of your change"`
7. Push to your fork: `git push origin feature/your-feature-name`
8. Open a Pull Request against the `main` branch

## Code Standards

This project follows the [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/):

- PHP files follow WordPress PHP Coding Standards
- JavaScript follows WordPress JavaScript Coding Standards
- CSS follows WordPress CSS Coding Standards
- All strings must be translatable using the `smartsearch-ai` text domain
- All output must be properly escaped (`esc_html`, `esc_attr`, `esc_url`, etc.)
- All input must be sanitized
- AJAX handlers must verify nonces and check capabilities

## Adding Dictionary Entries

The most impactful contribution you can make is improving the synonym dictionaries. To add or improve service mappings:

1. Open the relevant dictionary file in `smartsearch-ai/dictionaries/`
2. Each service entry has this structure:

```json
{
  "id": "unique-slug",
  "name": "Display Name",
  "category": "Category Name",
  "synonyms": ["alternate names for this service"],
  "intents": ["natural language phrases people use to describe this problem"],
  "keywords": ["individual relevant terms"]
}
```

3. **Intents are the most valuable** - these are the natural language phrases real people use. Think about how a homeowner would describe the problem to a friend, not how a contractor would describe the service.
4. Test your additions by searching for them in the plugin's autocomplete.

### Creating a New Industry Dictionary

1. Copy `home-services.json` as a template
2. Update the `meta.industry` field
3. Replace all services with your industry's services
4. Submit a PR with the new dictionary file

## Testing

Before submitting a PR:

1. Activate the plugin on a clean WordPress install (5.8+, PHP 7.4+)
2. Verify the shortcode renders correctly: `[smartsearch]`
3. Test autocomplete with various natural language queries
4. Test the full search flow (autocomplete -> select -> results)
5. Verify the admin settings page loads and saves correctly
6. Test dictionary import and export
7. Check for PHP errors in debug mode (`WP_DEBUG = true`)
8. Test on mobile viewport sizes
9. Verify keyboard navigation works in the suggestions dropdown

## Reporting Bugs

Use the [GitHub Issues](https://github.com/mrtask/smartsearch-ai-wp/issues) page with the bug report template. Include:

- WordPress version
- PHP version
- Theme name
- Steps to reproduce
- Expected vs actual behavior
- Browser and device info

## Feature Requests

Open a GitHub Issue using the feature request template. Describe the use case, not just the solution.

## Commit Message Convention

- `Add:` for new features
- `Fix:` for bug fixes
- `Update:` for improvements to existing features
- `Remove:` for removed features
- `Docs:` for documentation changes
- `Dict:` for dictionary additions/improvements

## License

By contributing, you agree that your contributions will be licensed under the GPL v2 or later license.
