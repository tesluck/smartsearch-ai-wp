/**
 * SmartSearch AI - Search Widget JavaScript
 *
 * Production-quality jQuery-based search functionality with client-side Fuse.js
 * fuzzy searching and server-side full search with AJAX.
 *
 * Global config: ssaiConfig (not sssConfig)
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
      // Cache jQuery selectors
      this.$wrapper = $('.ssai-search-wrapper');
      this.$searchInput = $('.ssai-search-input', this.$wrapper);
      this.$locationInput = $('.ssai-location-input', this.$wrapper);
      this.$searchBtn = $('.ssai-search-btn', this.$wrapper);
      this.$suggestionsBox = $('.ssai-suggestions', this.$wrapper);
      this.$resultsContainer = $('.ssai-results', this.$wrapper);

      // Fetch search index on load
      this.fetchSearchIndex();

      // Attach event listeners
      this.attachEventListeners();
    },

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
            self.searchIndex = response.data;
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
          { name: 'name', weight: 0.7 },
          { name: 'intent_phrases', weight: 0.5 },
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

      // Search button click
      this.$searchBtn.on('click', function (e) {
        e.preventDefault();
        self.performSearch();
      });

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
        self.performSearch();
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
        if (e.keyCode === 13) {
          // Enter key - perform search
          e.preventDefault();
          this.performSearch();
        }
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
          e.preventDefault();
          if (this.selectedSuggestionIndex >= 0) {
            const $selected = $items.eq(this.selectedSuggestionIndex);
            const name = $selected.data('name');
            this.$searchInput.val(name);
            this.closeSuggestions();
          }
          this.performSearch();
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
      const grouped = this.groupResultsByCategory(results);

      this.renderSuggestions(query, grouped);
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
    renderSuggestions: function (query, grouped) {
      const self = this;
      let html = '';

      // Sort categories alphabetically (but keep 'Other' last)
      const categories = Object.keys(grouped).sort(function (a, b) {
        if (a === 'Other') return 1;
        if (b === 'Other') return -1;
        return a.localeCompare(b);
      });

      categories.forEach(function (category) {
        const items = grouped[category];
        html += `<div class="ssai-category-label">${self.escapeHtml(category)}</div>`;

        items.slice(0, 5).forEach(function (item) {
          // Highlight matching text
          const highlightedName = self.highlightMatches(item.name, query);
          const intentPhrase = item.intent_phrases
            ? item.intent_phrases[0]
            : '';

          html += `
            <div class="ssai-suggestion-item" data-name="${self.escapeHtml(item.name)}" data-service-id="${self.escapeHtml(item.service_id)}">
              <div class="ssai-suggestion-name">${highlightedName}</div>
              ${
                intentPhrase
                  ? `<div class="ssai-suggestion-intent">"${self.escapeHtml(intentPhrase)}"</div>`
                  : ''
              }
            </div>
          `;
        });
      });

      this.$suggestionsBox.html(html);
      this.openSuggestions();
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
      const regex = new RegExp(`(${escapedQuery})`, 'gi');

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
      this.$suggestionsBox.addClass('visible');
    },

    /**
     * Close the suggestions dropdown
     */
    closeSuggestions: function () {
      this.$suggestionsBox.removeClass('visible');
      this.selectedSuggestionIndex = -1;
    },

    /**
     * Perform server-side search via AJAX
     */
    performSearch: function () {
      const query = this.$searchInput.val().trim();
      const location = this.$locationInput.val().trim();

      if (!query) {
        return;
      }

      // Show loading state
      this.$resultsContainer.html(
        '<div class="ssai-loading">Searching...</div>'
      );

      this.currentQuery = query;
      this.currentLocation = location;

      const self = this;

      $.ajax({
        url: this.config.ajaxUrl,
        type: 'POST',
        dataType: 'json',
        data: {
          action: 'ssai_search',
          nonce: this.config.nonce,
          query: query,
          location: location,
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

      // Interpreted intent banner
      if (data.interpreted_intent) {
        html += `
          <div class="ssai-interpreted">
            <strong>Searching for:</strong> ${self.escapeHtml(data.interpreted_intent)}
          </div>
        `;
      }

      // Matched services as chips
      if (data.matched_services && data.matched_services.length > 0) {
        html += '<div class="ssai-matched-services">';
        data.matched_services.forEach(function (service) {
          html += `
            <div class="ssai-service-chip">
              ${self.escapeHtml(service.category)}: ${self.escapeHtml(service.name)}
            </div>
          `;
        });
        html += '</div>';
      }

      // Results posts
      if (data.posts && data.posts.length > 0) {
        html += '<div class="ssai-posts">';
        data.posts.forEach(function (post) {
          const thumbnail = post.thumbnail
            ? `<img src="${post.thumbnail}" alt="${self.escapeHtml(post.title)}" />`
            : '';

          html += `
            <div class="ssai-post-item">
              ${thumbnail}
              <div>
                <a href="${post.url}" class="ssai-post-title" target="_blank" rel="noopener noreferrer">
                  ${self.escapeHtml(post.title)}
                </a>
                <p class="ssai-post-excerpt">${self.escapeHtml(post.excerpt)}</p>
              </div>
            </div>
          `;
        });
        html += '</div>';
      } else {
        html += '<div class="ssai-no-results">No results found. Try a different search.</div>';
      }

      // Powered by footer
      html += '<div class="ssai-powered-by">Powered by SmartSearch AI</div>';

      this.$resultsContainer.html(html);
    },

    /**
     * Render error message
     */
    renderError: function (message) {
      const html = `
        <div class="ssai-no-results">
          <strong>Error:</strong> ${this.escapeHtml(message)}
        </div>
      `;
      this.$resultsContainer.html(html);
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
