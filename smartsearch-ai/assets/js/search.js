/**
 * SmartSearch AI - Search Widget JavaScript
 *
 * Production-quality jQuery-based search functionality with client-side Fuse.js
 * fuzzy searching and server-side full search with AJAX.
 *
 * Global config: ssaiConfig
 * AJAX actions: ssai_get_index, ssai_autocomplete, ssai_search
 * Nonce field: nonce
 */

(function ($) {
  'use strict';

  /**
   * SmartSearchAI - Main widget controller
   */
  const SmartSearchAI = {
    // Configuration from WordPress
    config: window.ssaiConfig || {},

    // Fuse.js instance for client-side fuzzy search
    fuse: null,

    // Search index data
    searchIndex: null,

    // jQuery selectors
    $wrapper: null,
    $searchInput: null,
    $locationInput: null,
    $searchBtn: null,
    $suggestionsBox: null,
    $resultsContainer: null,

    // State
    selectedSuggestionIndex: -1,
    debounceTimer: null,
    currentQuery: '',
    currentLocation: '',

    /**
     * Initialize the widget
     */
    init: function () {
      // Try plugin shortcode first, then fall back to theme search
      this.$wrapper = $('.ssai-search-wrapper');

      if (this.$wrapper.length) {
        // Plugin shortcode mode
        this.$searchInput = $('.ssai-search-input', this.$wrapper);
        this.$locationInput = $('.ssai-location-input', this.$wrapper);
        this.$searchBtn = $('.ssai-search-btn', this.$wrapper);
        this.$suggestionsBox = $('.ssai-suggestions', this.$wrapper);
        this.$resultsContainer = $('.ssai-results', this.$wrapper);
      } else {
        // Theme integration mode — hook into existing theme search
        var $themeForm = $('.wp-search-form');
        if (!$themeForm.length) {
          // Still inject search page banner if on search results page
          this.renderSearchPageBanner();
          return;
        }
        this.$wrapper = $themeForm.closest('form').length ? $themeForm.closest('form') : $themeForm;
        this.$searchInput = $themeForm.find('.search-field');
        this.$locationInput = $(); // no location in theme
        this.$searchBtn = $themeForm.find('.search-submit');
        this.$suggestionsBox = $themeForm.find('.search-results-live');
        this.$resultsContainer = $themeForm.find('.search-results-live');
        this.themeMode = true;

        // Mark the dropdown container for SSAI-specific styling
        this.$suggestionsBox.addClass('ssai-dropdown-active');

        // Disable theme's live search handler (uses delegated keyup, can't unbind directly)
        this.$searchInput.on('keyup', function (e) {
          e.stopImmediatePropagation();
        });

        // In theme mode, let the form submit normally (navigates to search results page).
        // The search page banner (renderSearchPageBanner) handles the UX there.
      }

      // Fetch search index on load
      this.fetchSearchIndex();

      // Attach event listeners
      this.attachEventListeners();

      // Render search page banner if on search results page
      this.renderSearchPageBanner();
    },

    // Flag for theme integration mode
    themeMode: false,

    /**
     * Fetch the search index from the server via AJAX
     */
    fetchSearchIndex: function () {
      const self = this;

      $.ajax({
        url: this.config.ajaxUrl,
        type: 'POST',
        dataType: 'json',
        data: {
          action: 'ssai_get_index',
          nonce: this.config.nonce,
        },
        success: function (response) {
          if (response.success && response.data) {
            self.searchIndex = response.data.index || response.data;
            self.initializeFuse();
          }
        },
        error: function (xhr, status, error) {
          console.error('SmartSearch AI: Failed to fetch search index', error);
        },
      });
    },

    /**
     * Initialize Fuse.js with the search index
     */
    initializeFuse: function () {
      const fuseOptions = {
        keys: [
          { name: 'term', weight: 0.7 },
          { name: 'service_name', weight: 0.5 },
          { name: 'category', weight: 0.3 },
        ],
        threshold: 0.4,
        minMatchCharLength: 2,
        includeScore: true,
      };

      this.fuse = new Fuse(this.searchIndex, fuseOptions);
    },

    /**
     * Attach event listeners to DOM elements
     */
    attachEventListeners: function () {
      const self = this;

      // Input events with debouncing
      this.$searchInput.on('input', function () {
        self.handleSearchInput($(this).val());
      });

      // Keyboard navigation in suggestions
      this.$searchInput.on('keydown', function (e) {
        self.handleInputKeydown(e);
      });

      // Search button click — in theme mode, let the form submit normally
      if (!this.themeMode) {
        this.$searchBtn.on('click', function (e) {
          e.preventDefault();
          self.performSearch();
        });
      }

      // Location input can also trigger search on Enter
      this.$locationInput.on('keydown', function (e) {
        if (e.keyCode === 13) {
          e.preventDefault();
          self.performSearch();
        }
      });

      // Close suggestions when clicking outside
      $(document).on('click', function (e) {
        if (!self.$wrapper.has(e.target).length) {
          self.closeSuggestions();
        }
      });

      // Prevent suggestion box from closing when clicking inside it
      this.$suggestionsBox.on('click', function (e) {
        e.stopPropagation();
      });

      // Suggestion item selection
      this.$suggestionsBox.on('click', '.ssai-suggestion-item', function () {
        const name = $(this).data('name');
        self.$searchInput.val(name);
        self.closeSuggestions();
        if (self.themeMode) {
          self.$wrapper[0].submit();
        } else {
          self.performSearch();
        }
      });

      // Chip click — search for that service
      $(document).on('click', '.ssai-chip', function (e) {
        e.preventDefault();
        const serviceName = $(this).data('service');
        if (serviceName) {
          self.$searchInput.val(serviceName);
          self.closeSuggestions();
          self.performSearch();
        }
      });

      // Example query click
      $(document).on('click', '.ssai-example-link, .ssai-example-row', function (e) {
        e.preventDefault();
        const query = $(this).data('query');
        if (query) {
          self.$searchInput.val(query);
          self.handleSearchInput(query);
        }
      });
    },

    /**
     * Handle search input with debouncing
     */
    handleSearchInput: function (query) {
      const self = this;

      // Clear previous timer
      clearTimeout(this.debounceTimer);

      this.currentQuery = query;

      // Don't search if query is too short
      if (query.length < 2) {
        this.closeSuggestions();
        return;
      }

      // Debounce the search
      this.debounceTimer = setTimeout(function () {
        self.performClientSearch(query);
      }, 250);
    },

    /**
     * Handle keyboard navigation in search input
     */
    handleInputKeydown: function (e) {
      const self = this;
      const $items = this.$suggestionsBox.find('.ssai-suggestion-item');
      const itemCount = $items.length;

      // Skip if suggestions box is not visible or empty
      if (!this.$suggestionsBox.hasClass('visible') || itemCount === 0) {
        if (e.keyCode === 13 && !this.themeMode) {
          e.preventDefault();
          this.performSearch();
        }
        // In theme mode, let Enter submit the form naturally
        return;
      }

      switch (e.keyCode) {
        case 38: // Up arrow
          e.preventDefault();
          this.selectedSuggestionIndex =
            this.selectedSuggestionIndex > 0
              ? this.selectedSuggestionIndex - 1
              : itemCount - 1;
          this.updateSelectedSuggestion();
          break;

        case 40: // Down arrow
          e.preventDefault();
          this.selectedSuggestionIndex =
            this.selectedSuggestionIndex < itemCount - 1
              ? this.selectedSuggestionIndex + 1
              : 0;
          this.updateSelectedSuggestion();
          break;

        case 13: // Enter
          if (this.selectedSuggestionIndex >= 0) {
            e.preventDefault();
            const $selected = $items.eq(this.selectedSuggestionIndex);
            const name = $selected.data('name');
            this.$searchInput.val(name);
            this.closeSuggestions();
            // Submit the form (theme mode: native submit, shortcode mode: AJAX)
            if (this.themeMode) {
              this.$wrapper.off('submit').submit();
            } else {
              this.performSearch();
            }
          } else if (!this.themeMode) {
            e.preventDefault();
            this.performSearch();
          }
          // In theme mode with no selection, let Enter submit naturally
          break;

        case 27: // Escape
          e.preventDefault();
          this.closeSuggestions();
          break;

        default:
          break;
      }
    },

    /**
     * Update the visual selection of a suggestion item
     */
    updateSelectedSuggestion: function () {
      const $items = this.$suggestionsBox.find('.ssai-suggestion-item');
      $items.removeClass('selected');

      if (this.selectedSuggestionIndex >= 0) {
        $items.eq(this.selectedSuggestionIndex).addClass('selected');
        // Scroll into view if needed
        const $selected = $items.eq(this.selectedSuggestionIndex);
        const suggestionsTop = this.$suggestionsBox.scrollTop();
        const suggestionsHeight = this.$suggestionsBox.height();
        const itemTop = $selected.position().top;
        const itemHeight = $selected.outerHeight();

        if (itemTop < 0) {
          this.$suggestionsBox.scrollTop(suggestionsTop + itemTop);
        } else if (itemTop + itemHeight > suggestionsHeight) {
          this.$suggestionsBox.scrollTop(
            suggestionsTop + itemTop + itemHeight - suggestionsHeight
          );
        }
      }
    },

    /**
     * Perform client-side fuzzy search using Fuse.js
     */
    performClientSearch: function (query) {
      // Don't search if Fuse.js isn't initialized
      if (!this.fuse) {
        return;
      }

      const results = this.fuse.search(query);
      const bestScore = results.length > 0 ? results[0].score : 1;
      // Fuse.js: 0 = perfect, 1 = no match. Low confidence if best > 0.35
      const confidence = bestScore <= 0.15 ? 'high' : bestScore <= 0.35 ? 'medium' : 'low';
      const grouped = this.groupResultsByCategory(results);

      this.renderSuggestions(query, grouped, confidence);
      this.selectedSuggestionIndex = -1;
    },

    /**
     * Group search results by category and deduplicate by service_id
     */
    groupResultsByCategory: function (results) {
      const grouped = {};
      const seenServiceIds = {};

      results.forEach(function (result) {
        const item = result.item;
        const category = item.category || 'Other';

        // Deduplicate by service_id
        if (seenServiceIds[item.service_id]) {
          return;
        }
        seenServiceIds[item.service_id] = true;

        // Use service_name as display name
        item.name = item.service_name || item.term;

        if (!grouped[category]) {
          grouped[category] = [];
        }
        grouped[category].push(item);
      });

      return grouped;
    },

    /**
     * Render suggestions dropdown with grouped results
     */
    renderSuggestions: function (query, grouped, confidence) {
      const self = this;
      let html = '';

      const categories = Object.keys(grouped).sort(function (a, b) {
        if (a === 'Other') return 1;
        if (b === 'Other') return -1;
        return a.localeCompare(b);
      });

      if (categories.length === 0) {
        html = this.renderEmptyState(query);
        this.$suggestionsBox.html(html);
        this.openSuggestions();
        return;
      }

      // Flatten all services, deduplicated
      const allServices = [];
      const seenIds = {};
      categories.forEach(function (cat) {
        grouped[cat].forEach(function (item) {
          if (!seenIds[item.service_id]) {
            seenIds[item.service_id] = true;
            allServices.push(item);
          }
        });
      });

      // Uniform rows — same structure regardless of confidence
      allServices.slice(0, 7).forEach(function (item) {
        var highlightedName = self.highlightMatches(item.name, query);

        // Subtle context — keep it short
        var context = '';
        if (item.type === 'synonym' && item.term) {
          context = item.term;
        } else if (item.type === 'intent' && item.term) {
          context = item.term;
        } else if (item.category) {
          context = item.category;
        }

        html +=
          '<div class="ssai-suggestion-item" data-name="' + self.escapeHtml(item.name) + '" data-service-id="' + self.escapeHtml(item.service_id) + '">' +
            '<svg class="ssai-sg-icon" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M9 3.5a5.5 5.5 0 100 11 5.5 5.5 0 000-11zM2 9a7 7 0 1112.452 4.391l3.328 3.329a.75.75 0 11-1.06 1.06l-3.329-3.328A7 7 0 012 9z" clip-rule="evenodd"/></svg>' +
            '<div class="ssai-sg-text">' +
              '<span class="ssai-sg-name">' + highlightedName + '</span>' +
              (context ? '<span class="ssai-sg-context"> &mdash; ' + self.escapeHtml(context) + '</span>' : '') +
            '</div>' +
          '</div>';
      });

      this.$suggestionsBox.html(html);
      this.openSuggestions();
    },

    /**
     * Render helpful empty state with example queries
     */
    renderEmptyState: function (query) {
      const self = this;
      const examples = this.config.exampleQueries || [
        'my toilet won\'t stop running',
        'no hot water',
        'pipe is leaking',
      ];

      let html = '<div class="ssai-empty-help">';
      html += '<div class="ssai-empty-title">No results for "' + this.escapeHtml(query) + '"</div>';
      html += '<div class="ssai-empty-subtitle">Try something like:</div>';

      // Render examples as suggestion-style rows (same look as real results)
      examples.forEach(function (ex) {
        html +=
          '<a href="#" class="ssai-example-row" data-query="' + self.escapeHtml(ex) + '">' +
            '<svg class="ssai-sg-icon" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M9 3.5a5.5 5.5 0 100 11 5.5 5.5 0 000-11zM2 9a7 7 0 1112.452 4.391l3.328 3.329a.75.75 0 11-1.06 1.06l-3.329-3.328A7 7 0 012 9z" clip-rule="evenodd"/></svg>' +
            '<span class="ssai-sg-name">' + self.escapeHtml(ex) + '</span>' +
          '</a>';
      });

      html += '</div>';
      return html;
    },

    /**
     * Highlight matching parts of the text
     */
    highlightMatches: function (text, query) {
      if (!query || query.length === 0) {
        return this.escapeHtml(text);
      }

      const escapedText = this.escapeHtml(text);
      const escapedQuery = this.escapeHtml(query);
      const regex = new RegExp('(' + escapedQuery + ')', 'gi');

      return escapedText.replace(regex, '<strong>$1</strong>');
    },

    /**
     * Escape HTML special characters
     */
    escapeHtml: function (text) {
      if (!text) return '';
      return text
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
    },

    /**
     * Open the suggestions dropdown
     */
    openSuggestions: function () {
      this.$suggestionsBox.addClass('visible').show();
    },

    /**
     * Close the suggestions dropdown
     */
    closeSuggestions: function () {
      this.$suggestionsBox.removeClass('visible').hide();
      this.selectedSuggestionIndex = -1;
    },

    /**
     * Perform server-side search via AJAX
     */
    performSearch: function () {
      const query = this.$searchInput.val().trim();
      const location = this.$locationInput ? this.$locationInput.val() : '';
      const locationTrimmed = (location || '').trim();

      if (!query) {
        return;
      }

      // Show loading state
      this.$resultsContainer.html(
        '<div class="ssai-loading">Searching...</div>'
      ).show();

      this.currentQuery = query;
      this.currentLocation = locationTrimmed;

      const self = this;

      $.ajax({
        url: this.config.ajaxUrl,
        type: 'POST',
        dataType: 'json',
        data: {
          action: 'ssai_search',
          nonce: this.config.nonce,
          query: query,
          location: locationTrimmed,
        },
        success: function (response) {
          self.closeSuggestions();
          if (response.success) {
            self.renderResults(response.data);
          } else {
            self.renderError(
              response.data.message || 'Search failed. Please try again.'
            );
          }
        },
        error: function (xhr, status, error) {
          console.error('SmartSearch AI: Search error', error);
          self.renderError(
            'An error occurred during search. Please try again.'
          );
        },
      });
    },

    /**
     * Render search results
     */
    renderResults: function (data) {
      const self = this;
      let html = '';

      // Query → Match banner
      const originalQuery = data.original_query || data.query || '';
      const matchedServices = data.matched_services || [];
      const confidence = data.confidence || 'none';

      if (originalQuery && matchedServices.length > 0) {
        html += '<div class="ssai-query-banner">';

        if (confidence === 'low') {
          html += '<div class="ssai-banner-query">No exact match for "<strong>' + self.escapeHtml(originalQuery) + '</strong>". Did you mean:</div>';
        } else {
          html += '<div class="ssai-banner-query">You searched for "<strong>' + self.escapeHtml(originalQuery) + '</strong>". Showing results for:</div>';
        }

        html += '<div class="ssai-chip-bar">';
        matchedServices.forEach(function (service) {
          html += '<span class="ssai-chip" data-service="' + self.escapeHtml(service.name) + '">'
            + self.escapeHtml(service.name)
            + '</span>';
        });
        html += '</div></div>';
      }

      // Results posts
      if (data.posts && data.posts.length > 0) {
        html += '<div class="ssai-posts">';
        data.posts.forEach(function (post) {
          const thumbnail = post.thumbnail
            ? '<img src="' + post.thumbnail + '" alt="' + self.escapeHtml(post.title) + '" />'
            : '';

          html +=
            '<div class="ssai-post-item">' +
              thumbnail +
              '<div>' +
                '<a href="' + post.url + '" class="ssai-post-title" target="_blank" rel="noopener noreferrer">' +
                  self.escapeHtml(post.title) +
                '</a>' +
                '<p class="ssai-post-excerpt">' + self.escapeHtml(post.excerpt) + '</p>' +
              '</div>' +
            '</div>';
        });
        html += '</div>';
      } else if (matchedServices.length > 0) {
        // Understood query but no company results
        html += '<div class="ssai-no-results">';
        html += 'We found matching services but no companies are listed yet for ';
        html += '<strong>' + self.escapeHtml(matchedServices[0].name) + '</strong>. ';
        html += 'Try a different service.';
        html += '</div>';
      } else {
        // Didn't understand query at all
        html += this.renderEmptyResultsState(originalQuery);
      }

      // Powered by footer
      html += '<div class="ssai-powered-by">Powered by SmartSearch AI</div>';

      this.$resultsContainer.html(html);
    },

    /**
     * Render empty results state with suggestions
     */
    renderEmptyResultsState: function (query) {
      const self = this;
      const examples = this.config.exampleQueries || [];

      let html = '<div class="ssai-empty-help">';
      html += '<div class="ssai-empty-title">No results for "' + this.escapeHtml(query) + '"</div>';
      html += '<div class="ssai-empty-subtitle">Try describing your plumbing problem:</div>';
      html += '<div class="ssai-example-list">';
      examples.slice(0, 4).forEach(function (ex) {
        html += '<a href="#" class="ssai-example-link" data-query="' + self.escapeHtml(ex) + '">'
          + self.escapeHtml(ex)
          + '</a>';
      });
      html += '</div></div>';
      return html;
    },

    /**
     * Render error message
     */
    renderError: function (message) {
      const html =
        '<div class="ssai-no-results">' +
          '<strong>Error:</strong> ' + this.escapeHtml(message) +
        '</div>';
      this.$resultsContainer.html(html);
    },

    /**
     * Render search page banner (for WordPress search.php results page).
     * Reads context from hidden div injected by PHP.
     */
    renderSearchPageBanner: function () {
      const $ctx = $('#ssai-search-context');
      if (!$ctx.length) return;

      var context;
      try {
        context = JSON.parse($ctx.attr('data-context'));
      } catch (e) {
        return;
      }

      if (!context || !context.original_query) return;

      const self = this;
      const originalQuery = context.original_query;
      const matched = context.matched_services || [];
      const confidence = context.confidence || 'none';

      // Build banner HTML
      let html = '<div class="ssai-page-banner">';

      if (matched.length > 0) {
        if (confidence === 'low') {
          html += '<div class="ssai-banner-query">No exact match for "<strong>' + self.escapeHtml(originalQuery) + '</strong>". Showing closest results:</div>';
        } else {
          html += '<div class="ssai-banner-query">You searched for "<strong>' + self.escapeHtml(originalQuery) + '</strong>". Showing results for:</div>';
        }

        html += '<div class="ssai-chip-bar">';
        matched.forEach(function (svc) {
          html += '<a href="/?s=' + encodeURIComponent(svc.name) + '" class="ssai-chip">'
            + self.escapeHtml(svc.name)
            + '</a>';
        });
        html += '</div>';
      } else if (confidence === 'none') {
        html += '<div class="ssai-banner-query">Showing keyword results for "<strong>' + self.escapeHtml(originalQuery) + '</strong>"</div>';
      }

      html += '</div>';

      // Insert before the listing cards
      const $target = $('#search_result .listing_cards, #search_result .container');
      if ($target.length) {
        $target.first().prepend(html);
      }
    },
  };

  /**
   * Initialize on document ready
   */
  $(document).ready(function () {
    SmartSearchAI.init();
  });

  // Expose to global scope for debugging (optional)
  window.SmartSearchAI = SmartSearchAI;
})(jQuery);
