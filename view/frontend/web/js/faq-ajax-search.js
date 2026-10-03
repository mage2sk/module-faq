/**
 * FAQ Accordion with AJAX Search JavaScript
 *
 * @category  Panth
 * @package   Panth_Faq
 * @author    Panth
 * @copyright Copyright (c) 2025 Panth
 */
define([
    'jquery',
    'mage/url'
], function ($, urlBuilder) {
    'use strict';

    return function (config, element) {
        var defaultOpen = config.defaultOpen || false;
        var $element = $(element);
        var searchTimeout;

        // Initialize accordion
        function initAccordion() {
            $element.find('.faq-item-header').off('click').on('click', function () {
                var $header = $(this);
                var $item = $header.closest('.faq-item');
                var $content = $item.find('.faq-item-content');
                var isOpen = $content.is(':visible');

                // Toggle current item
                if (isOpen) {
                    $content.slideUp(300);
                    $item.removeClass('active');
                } else {
                    $content.slideDown(300);
                    $item.addClass('active');
                }
            });

            // Handle keyboard navigation
            $element.find('.faq-item-header').off('keypress').on('keypress', function (e) {
                if (e.which === 13 || e.which === 32) {
                    e.preventDefault();
                    $(this).trigger('click');
                }
            });
        }

        // Handle helpful voting
        $element.on('click', '.helpful-btn', function (e) {
            e.preventDefault();
            var $btn = $(this);
            var itemId = $btn.data('item-id');
            var voteType = $btn.hasClass('helpful-yes') ? 'yes' : 'no';

            // Check if already voted
            var storageKey = 'faq_voted_' + itemId;
            if (localStorage.getItem(storageKey)) {
                alert('You have already voted on this FAQ item.');
                return;
            }

            // Send vote via AJAX
            $.ajax({
                url: urlBuilder.build('faq/vote/submit'),
                type: 'POST',
                data: {
                    item_id: itemId,
                    vote: voteType,
                    form_key: $.mage.cookies.get('form_key')
                },
                dataType: 'json',
                showLoader: true,
                success: function (response) {
                    if (response.success) {
                        // Update count
                        var $count = $btn.find('.count');
                        var currentCount = parseInt($count.text().replace(/[()]/g, '')) || 0;
                        $count.text('(' + (currentCount + 1) + ')');

                        // Mark as voted
                        localStorage.setItem(storageKey, voteType);

                        // Disable both buttons
                        $btn.closest('.faq-helpful').find('.helpful-btn').prop('disabled', true);
                    }
                },
                error: function () {
                    console.error('Error submitting vote');
                }
            });
        });

        // Handle AJAX search
        var $searchInput = $('#faq-search-input');
        var $searchForm = $('#faq-search-form');

        if ($searchInput.length) {
            // Debounced search on input
            $searchInput.on('input', function () {
                clearTimeout(searchTimeout);
                var query = $(this).val().trim();

                if (query.length < 2) {
                    return;
                }

                searchTimeout = setTimeout(function () {
                    performSearch(query);
                }, 500);
            });

            // Search on form submit
            $searchForm.on('submit', function (e) {
                e.preventDefault();
                var query = $searchInput.val().trim();
                if (query.length >= 2) {
                    performSearch(query);
                }
            });
        }

        function performSearch(query) {
            var categoryId = config.categoryId || 0;

            $.ajax({
                url: urlBuilder.build('faq/ajax/search'),
                type: 'POST',
                data: {
                    q: query,
                    category: categoryId,
                    form_key: $.mage.cookies.get('form_key')
                },
                dataType: 'json',
                showLoader: true,
                success: function (response) {
                    if (response.success) {
                        renderSearchResults(response.results, query, response.count);
                    }
                },
                error: function () {
                    console.error('Search error');
                }
            });
        }

        function renderSearchResults(results, query, count) {
            var $contentDiv = $element.find('.faq-content');

            if (!results || results.length === 0) {
                $contentDiv.html(
                    '<div class="message info empty">' +
                    '<div>No FAQs found matching your search.</div>' +
                    '</div>'
                );
                return;
            }

            var html = '<div class="search-results-info"><p>' +
                'Search results for: "' + escapeHtml(query) + '" ' +
                '<span class="results-count">(' + count + ' results)</span>' +
                '</p></div>';

            html += '<div class="faq-items-list">';

            results.forEach(function (item) {
                html += renderFaqItem(item);
            });

            html += '</div>';

            $contentDiv.html(html);

            // Re-initialize accordion for new items
            initAccordion();
        }

        function renderFaqItem(item) {
            var displayStyle = defaultOpen ? 'block' : 'none';
            var activeClass = defaultOpen ? 'active' : '';

            return '<div class="faq-item ' + activeClass + '" data-faq-id="' + item.id + '">' +
                '<div class="faq-item-header" role="button" tabindex="0">' +
                '<h3 class="faq-question">' + escapeHtml(item.question) + '</h3>' +
                '<span class="faq-toggle-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg></span>' +
                '</div>' +
                '<div class="faq-item-content" style="display: ' + displayStyle + ';">' +
                '<div class="faq-answer">' + escapeHtml(item.answer) + '</div>' +
                '</div>' +
                '</div>';
        }

        function escapeHtml(text) {
            var map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return text.replace(/[&<>"']/g, function (m) {
                return map[m];
            });
        }

        // Set initial state based on defaultOpen
        if (defaultOpen) {
            $element.find('.faq-item').addClass('active');
        }

        // Initialize accordion
        initAccordion();

        // URL hash handling for direct linking to FAQ items
        if (window.location.hash) {
            var hash = window.location.hash;
            var $targetItem = $element.find(hash);
            if ($targetItem.length) {
                $targetItem.find('.faq-item-header').trigger('click');
                $('html, body').animate({
                    scrollTop: $targetItem.offset().top - 100
                }, 500);
            }
        }
    };
});
