/**
 * Search autocomplete functionality
 * For use on search bars throughout the ELearning site
 */
$(document).ready(function() {
    // Target all search inputs with the data-autosuggest attribute
    const $searchInputs = $('input[data-autosuggest="true"]');
    
    if ($searchInputs.length === 0) return;
    
    // Initialize each search input with autocomplete
    $searchInputs.each(function() {
        const $input = $(this);
        const $form = $input.closest('form');
        let $suggestionsContainer;
        let debounceTimer;
        let selectedIndex = -1;
        let suggestions = [];
        
        // Create suggestions container if it doesn't exist
        if (!$input.next('.search-suggestions').length) {
            $suggestionsContainer = $('<div class="search-suggestions"></div>');
            $input.after($suggestionsContainer);
            
            // Adjust position for special cases like the header search
            if ($input.closest('.navbar').length) {
                // For navbar search, ensure suggestions appear below the input
                const inputHeight = $input.outerHeight();
                const navbarHeight = $input.closest('.navbar').outerHeight();
                $suggestionsContainer.css('z-index', 1050); // Higher z-index for navbar
            }
        } else {
            $suggestionsContainer = $input.next('.search-suggestions');
        }
        
        // Handle input changes with debounce
        $input.on('input', function() {
            const query = $input.val().trim();
            
            // Clear previous timer
            clearTimeout(debounceTimer);
            
            // Reset selection
            selectedIndex = -1;
            
            // Hide suggestions if query is empty
            if (query.length < 2) {
                $suggestionsContainer.hide();
                return;
            }
            
            // Set new timer for 300ms
            debounceTimer = setTimeout(function() {
                fetchSuggestions(query);
            }, 300);
        });
        
        // Handle keyboard navigation
        $input.on('keydown', function(e) {
            // If no suggestions are shown, do nothing special
            if (!$suggestionsContainer.is(':visible')) return;
            
            switch (e.key) {
                case 'ArrowDown':
                    e.preventDefault();
                    selectedIndex = Math.min(selectedIndex + 1, suggestions.length - 1);
                    highlightSuggestion();
                    break;
                case 'ArrowUp':
                    e.preventDefault();
                    selectedIndex = Math.max(selectedIndex - 1, -1);
                    highlightSuggestion();
                    break;
                case 'Enter':
                    if (selectedIndex >= 0) {
                        e.preventDefault();
                        selectSuggestion(suggestions[selectedIndex]);
                    }
                    break;
                case 'Escape':
                    e.preventDefault();
                    $suggestionsContainer.hide();
                    break;
            }
        });
        
        // Hide suggestions when clicking outside
        $(document).on('click', function(e) {
            if (!$input.is(e.target) && !$suggestionsContainer.is(e.target) && $suggestionsContainer.has(e.target).length === 0) {
                $suggestionsContainer.hide();
            }
        });
        
        // Hide suggestions when focus is lost
        $input.on('blur', function(e) {
            // Delayed to allow click events on suggestions to fire first
            setTimeout(function() {
                if (!$suggestionsContainer.is(':hover')) {
                    $suggestionsContainer.hide();
                }
            }, 200);
        });
        
        // Fetch suggestions via AJAX
        function fetchSuggestions(query) {
            // Try to determine the base path
            let ajaxUrl = 'ajax/search-suggestions.php';
            
            // Check if we're in a subdirectory (like games/wordscapes)
            if (window.location.pathname.includes('/games/')) {
                ajaxUrl = '../../ajax/search-suggestions.php';
            }
            
            $.ajax({
                url: ajaxUrl,
                data: { query: query },
                dataType: 'json',
                success: function(response) {
                    if (response.success && response.suggestions.length > 0) {
                        suggestions = response.suggestions;
                        renderSuggestions(suggestions);
                        $suggestionsContainer.show();
                    } else {
                        $suggestionsContainer.hide();
                    }
                },
                error: function() {
                    $suggestionsContainer.hide();
                }
            });
        }
        
        // Render suggestions in the container
        function renderSuggestions(suggestions) {
            $suggestionsContainer.empty();
            
            suggestions.forEach(function(suggestion, index) {
                const $item = $('<div class="suggestion-item"></div>');
                
                // Add badge for suggestion type
                let badgeClass = 'bg-secondary';
                let typeText = suggestion.type;
                
                if (suggestion.type === 'course') {
                    badgeClass = 'bg-primary';
                    typeText = 'Course';
                } else if (suggestion.type === 'material') {
                    badgeClass = 'bg-success';
                    typeText = 'Material';
                } else if (suggestion.type === 'term') {
                    badgeClass = 'bg-info';
                    typeText = 'Term';
                }
                
                const $badge = $(`<span class="badge ${badgeClass} suggestion-badge">${typeText}</span>`);
                
                // Title and truncated description
                const $title = $('<div class="suggestion-title"></div>').text(suggestion.title);
                
                // For materials, show the course it belongs to
                let $subtitle = '';
                if (suggestion.type === 'material' && suggestion.course_title) {
                    $subtitle = $('<div class="suggestion-subtitle"></div>').text('From: ' + suggestion.course_title);
                }
                
                $item.append($badge).append($title);
                
                if ($subtitle) {
                    $item.append($subtitle);
                }
                
                // Attach click handler
                $item.on('click', function() {
                    selectSuggestion(suggestion);
                });
                
                $suggestionsContainer.append($item);
            });
        }
        
        // Highlight the currently selected suggestion
        function highlightSuggestion() {
            $suggestionsContainer.find('.suggestion-item').removeClass('active');
            
            if (selectedIndex >= 0) {
                $suggestionsContainer.find('.suggestion-item').eq(selectedIndex).addClass('active');
            }
        }
        
        // Select a suggestion
        function selectSuggestion(suggestion) {
            // Set input value to suggestion title
            $input.val(suggestion.title);
            
            // Get base path prefix
            let pathPrefix = '';
            if (window.location.pathname.includes('/games/')) {
                pathPrefix = '../../';
            }
            
            // Navigate based on suggestion type
            if (suggestion.type === 'course') {
                window.location.href = pathPrefix + 'course-details.php?id=' + suggestion.id;
            } else if (suggestion.type === 'material') {
                window.location.href = pathPrefix + 'material-details.php?id=' + suggestion.id;
            } else if (suggestion.type === 'term') {
                window.location.href = pathPrefix + 'dictionary.php?term=' + encodeURIComponent(suggestion.title);
            } else {
                // Default behavior - submit the form
                $form.submit();
            }
            
            // Hide suggestions
            $suggestionsContainer.hide();
        }
    });
}); 