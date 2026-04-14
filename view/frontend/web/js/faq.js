/**
 * FAQ Accordion JavaScript - Compatible with both Luma and Hyva themes
 *
 * @category  Panth
 * @package   Panth_Faq
 * @author    Panth
 * @copyright Copyright (c) 2025 Panth
 */

// Universal Module Definition (UMD) pattern for compatibility
(function (root, factory) {
    'use strict';

    if (typeof define === 'function' && define.amd) {
        // AMD/RequireJS (Luma theme)
        define(['jquery', 'mage/url'], factory);
    } else {
        // Vanilla JavaScript (Hyva theme)
        root.PanthFaq = factory(root.jQuery || root.$);
    }
}(typeof self !== 'undefined' ? self : this, function ($, urlBuilder) {
    'use strict';

    // Vanilla JS implementation
    function initFaqAccordion(element, config) {
        config = config || {};
        var defaultOpen = config.defaultOpen || false;
        var elementNode = typeof element === 'string' ? document.querySelector(element) : element;

        if (!elementNode) {
            return;
        }

        // Initialize accordion with vanilla JS
        var headers = elementNode.querySelectorAll('.faq-item-header');

        headers.forEach(function(header) {
            header.addEventListener('click', function() {
                var item = header.closest('.faq-item');
                var content = item.querySelector('.faq-item-content');
                var isOpen = content.style.display !== 'none' && content.style.display !== '';

                // Toggle current item
                if (isOpen) {
                    content.style.display = 'none';
                    item.classList.remove('active');
                } else {
                    content.style.display = 'block';
                    item.classList.add('active');
                }
            });

            // Handle keyboard navigation
            header.addEventListener('keypress', function(e) {
                if (e.which === 13 || e.which === 32) { // Enter or Space
                    e.preventDefault();
                    header.click();
                }
            });
        });

        // Set initial state based on defaultOpen
        if (defaultOpen) {
            var items = elementNode.querySelectorAll('.faq-item');
            items.forEach(function(item) {
                item.classList.add('active');
                var content = item.querySelector('.faq-item-content');
                if (content) {
                    content.style.display = 'block';
                }
            });
        }

        // URL hash handling for direct linking to FAQ items
        if (window.location.hash) {
            var hash = window.location.hash;
            var targetItem = elementNode.querySelector(hash);
            if (targetItem) {
                var targetHeader = targetItem.querySelector('.faq-item-header');
                if (targetHeader) {
                    targetHeader.click();
                    setTimeout(function() {
                        targetItem.scrollIntoView({ behavior: 'smooth', block: 'start' });
                        window.scrollBy(0, -100);
                    }, 100);
                }
            }
        }
    }

    // jQuery implementation for Luma theme
    if ($ && $.fn) {
        return function (config, element) {
            var defaultOpen = config.defaultOpen || false;

            // Initialize accordion
            $(element).find('.faq-item-header').on('click', function () {
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
            $(element).find('.faq-item-header').on('keypress', function (e) {
                if (e.which === 13 || e.which === 32) { // Enter or Space
                    e.preventDefault();
                    $(this).trigger('click');
                }
            });

            // Handle helpful voting
            $(element).on('click', '.helpful-btn', function (e) {
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
                    url: urlBuilder ? urlBuilder.build('faq/vote/submit') : '/faq/vote/submit',
                    type: 'POST',
                    data: {
                        item_id: itemId,
                        vote: voteType,
                        form_key: $.mage ? $.mage.cookies.get('form_key') : ''
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

            // Set initial state based on defaultOpen
            if (defaultOpen) {
                $(element).find('.faq-item').addClass('active');
            }

            // URL hash handling for direct linking to FAQ items
            if (window.location.hash) {
                var hash = window.location.hash;
                var $targetItem = $(element).find(hash);
                if ($targetItem.length) {
                    $targetItem.find('.faq-item-header').trigger('click');
                    $('html, body').animate({
                        scrollTop: $targetItem.offset().top - 100
                    }, 500);
                }
            }
        };
    }

    // Return vanilla JS implementation for Hyva
    return {
        init: initFaqAccordion
    };
}));
