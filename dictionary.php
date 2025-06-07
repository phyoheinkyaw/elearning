<?php
session_start();
require_once 'includes/db.php';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dictionary - ELearning</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="css/custom.css" rel="stylesheet">
    <!-- Search Autocomplete CSS -->
    <link href="css/search-autocomplete.css" rel="stylesheet">
    <!-- Floating Chatbot CSS -->
    <link href="css/floating-chatbot.css" rel="stylesheet">
    <style>
        .search-container {
            margin: 50px auto;
            max-width: 800px;
            padding: 30px;
            background-color: var(--white);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-lg);
        }

        .result-item {
            background-color: var(--white);
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-md);
            padding: 2rem;
            margin-bottom: 2rem;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .result-item:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-lg);
        }

        .word-title {
            color: var(--primary);
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 1rem;
            border-bottom: 3px solid var(--primary);
            padding-bottom: 0.5rem;
        }

        .phonetic {
            font-size: 1.2rem;
            color: var(--accent);
            font-weight: 500;
            margin-bottom: 1.5rem;
            font-style: italic;
        }

        .pronunciation-section {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            margin-bottom: 2rem;
            padding: 1rem;
            background-color: var(--gray-100);
            border-radius: var(--radius-md);
        }

        .pronunciation-box {
            flex: 1;
            min-width: 200px;
            padding: 1rem;
            background-color: var(--white);
            border-radius: var(--radius-sm);
            border: 1px solid var(--gray-300);
        }

        .pronunciation-box h6 {
            color: var(--secondary);
            font-weight: 600;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .audio-player audio {
            width: 100%;
            height: 40px;
        }

        .part-of-speech {
            color: var(--dark);
            font-size: 1.3rem;
            font-weight: 700;
            margin: 2rem 0 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid var(--accent);
        }

        .definition {
            margin: 1.5rem 0;
            padding-left: 1.5rem;
            border-left: 4px solid var(--accent);
        }

        .definition p {
            margin-bottom: 0.5rem;
            color: var(--gray-800);
        }

        .definition strong {
            color: var(--primary);
        }

        .definition em {
            color: var(--secondary);
            font-weight: 500;
        }

        .example {
            margin-top: 0.5rem;
            padding: 0.75rem;
            background-color: var(--gray-100);
            border-radius: var(--radius-sm);
            font-style: italic;
            color: var(--gray-700);
        }
    </style>
    <!-- Floating Chatbot CSS -->
</head>

<body>
    <?php include 'includes/nav.php'; ?>

    <div class="container py-5">
        <div class="search-container">
            <h1 class="display-4 text-center mb-4">Dictionary</h1>
            <form id="dictionaryForm" class="mb-5">
                <div class="input-group search-input-container">
                    <input type="text" class="form-control form-control-lg" id="wordInput" placeholder="Enter a word..." required>
                    <button class="btn btn-primary btn-lg" type="submit">
                        <i class="fas fa-search me-2"></i>Search
                    </button>
                    <div class="search-suggestions" id="wordSuggestions"></div>
                </div>
            </form>

            <div id="dictionaryResults" class="mt-4"></div>
        </div>
    </div>

    <?php include 'includes/footer.php'; ?>

    <!-- Bootstrap JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <!-- jQuery -->
    <script src="js/lib/jquery-3.6.0.min.js"></script>
    
    <script>
        $(document).ready(function() {
            // Word lookup function
            function lookupWord(word) {
                const resultsContainer = $('#dictionaryResults');
                resultsContainer.html('');

                if (!word) {
                    resultsContainer.html('<div class="alert alert-warning">Please enter a word to search.</div>');
                    return;
                }

                // Show loading indicator
                resultsContainer.html('<div class="text-center"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div><p class="mt-2">Searching for definition...</p></div>');

                // Use our own server as a proxy to avoid CORS issues and hide 404 errors from the console
                $.ajax({
                    url: 'ajax/dictionary-proxy.php',
                    method: 'GET',
                    data: { word: word },
                    dataType: 'json',
                    success: function(data) {
                        resultsContainer.html('');
                        
                        if (data.success && data.entries && data.entries.length > 0) {
                            // Display dictionary results
                            data.entries.forEach(function(entry) {
                                const resultItem = $('<div>').addClass('result-item');

                                // Word Title
                                const wordHeading = $('<h2>').addClass('word-title').text(entry.word);
                                resultItem.append(wordHeading);

                                // Phonetic
                                if (entry.phonetic) {
                                    const phonetic = $('<p>').addClass('phonetic').text('Phonetic: ' + entry.phonetic);
                                    resultItem.append(phonetic);
                                }

                                // Pronunciations Section
                                const pronunciations = {
                                    us: entry.phonetics.find(p => p.audio && p.audio.includes('-us.mp3')),
                                    uk: entry.phonetics.find(p => p.audio && p.audio.includes('-uk.mp3')),
                                    au: entry.phonetics.find(p => p.audio && p.audio.includes('-au.mp3'))
                                };

                                if (Object.values(pronunciations).some(p => p)) {
                                    const pronunciationSection = $('<div>').addClass('pronunciation-section');

                                    Object.entries(pronunciations).forEach(([region, phonetic]) => {
                                        if (phonetic && phonetic.audio) {
                                            const audioBox = $('<div>').addClass('pronunciation-box').html(`
                                                <h6>
                                                    <i class="fas fa-volume-up"></i>
                                                    ${region.toUpperCase()} Pronunciation
                                                    ${phonetic.text ? `<small>(${phonetic.text})</small>` : ''}
                                                </h6>
                                                <div class="audio-player">
                                                    <audio controls>
                                                        <source src="${phonetic.audio}" type="audio/mpeg">
                                                        Your browser does not support the audio element.
                                                    </audio>
                                                </div>`);
                                            pronunciationSection.append(audioBox);
                                        }
                                    });

                                    resultItem.append(pronunciationSection);
                                }

                                // Meanings
                                entry.meanings.forEach(function(meaning) {
                                    const partOfSpeechDiv = $('<div>').addClass('part-of-speech').text(meaning.partOfSpeech);
                                    resultItem.append(partOfSpeechDiv);

                                    meaning.definitions.forEach(function(definition, index) {
                                        const definitionDiv = $('<div>').addClass('definition');
                                        
                                        const definitionText = $('<p>').html(`<strong>${index + 1}.</strong> ${definition.definition}`);
                                        definitionDiv.append(definitionText);

                                        if (definition.example) {
                                            const exampleText = $('<div>').addClass('example').html(`<i class="fas fa-quote-left me-2"></i>${definition.example}`);
                                            definitionDiv.append(exampleText);
                                        }

                                        resultItem.append(definitionDiv);
                                    });
                                });

                                resultsContainer.append(resultItem);
                            });
                        } else {
                            // Word not found or error occurred
                            searchWikipedia(word, resultsContainer);
                        }
                    },
                    error: function() {
                        // If our proxy fails, try Wikipedia
                        searchWikipedia(word, resultsContainer);
                    }
                });
            }

            // Wikipedia fallback search
            function searchWikipedia(term, resultsContainer) {
                // Show interim message
                resultsContainer.html(`
                    <div class="alert alert-info">
                        <div class="d-flex align-items-center">
                            <div class="spinner-border spinner-border-sm me-3" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                            <div>
                                <p class="mb-1"><strong>Dictionary definition not found.</strong></p>
                                <p class="mb-0">Searching Wikipedia for "${term}"...</p>
                            </div>
                        </div>
                    </div>
                `);

                // Use Wikipedia API to get content
                $.ajax({
                    url: `https://en.wikipedia.org/api/rest_v1/page/summary/${encodeURIComponent(term)}`,
                    method: 'GET',
                    dataType: 'json',
                    success: function(data) {
                        if (data.title && data.extract) {
                            const resultItem = $('<div>').addClass('result-item');
                            
                            // Title with Wikipedia label
                            const titleSection = $('<div>').addClass('d-flex align-items-center mb-2');
                            const wordHeading = $('<h2>').addClass('word-title me-3 mb-0').text(data.title);
                            const sourceLabel = $('<span>').addClass('badge bg-secondary').text('Wikipedia');
                            titleSection.append(wordHeading, sourceLabel);
                            
                            resultItem.append(titleSection);
                            
                            // Add thumbnail if available
                            if (data.thumbnail) {
                                const imageContainer = $('<div>').addClass('float-end ms-4 mb-3');
                                const image = $('<img>')
                                    .attr('src', data.thumbnail.source)
                                    .attr('alt', data.title)
                                    .addClass('rounded')
                                    .css({
                                        'max-width': '200px',
                                        'max-height': '200px'
                                    });
                                imageContainer.append(image);
                                resultItem.append(imageContainer);
                            }
                            
                            // Description
                            if (data.description) {
                                const description = $('<p>').addClass('text-muted fst-italic mb-3').text(data.description);
                                resultItem.append(description);
                            }
                            
                            // Extract
                            const extractDiv = $('<div>').addClass('mb-3').html(data.extract_html);
                            resultItem.append(extractDiv);
                            
                            // Link to Wikipedia
                            const linkRow = $('<div>').addClass('text-end');
                            const wikiLink = $('<a>')
                                .attr('href', data.content_urls.desktop.page)
                                .attr('target', '_blank')
                                .addClass('btn btn-outline-primary btn-sm')
                                .html('<i class="fas fa-external-link-alt me-2"></i>Read more on Wikipedia');
                            linkRow.append(wikiLink);
                            resultItem.append(linkRow);
                            
                            resultsContainer.html('').append(resultItem);
                            
                            // Add helpful search suggestions below
                            addSearchSuggestions(term, resultsContainer);
                        } else {
                            // If Wikipedia doesn't have a good match either
                            showNotFoundMessage(term, resultsContainer);
                        }
                    },
                    error: function() {
                        // If Wikipedia search fails
                        showNotFoundMessage(term, resultsContainer);
                    }
                });
            }

            // Show improved "not found" message with suggestions
            function showNotFoundMessage(term, resultsContainer) {
                const messageDiv = $('<div>').addClass('alert alert-warning');
                
                const heading = $('<h4>').addClass('alert-heading').text(`No results found for "${term}"`);
                messageDiv.append(heading);
                
                // Get the individual words for a dynamic example
                const words = term.split(/\s+/);
                let exampleText = '';
                
                if (words.length > 1) {
                    // Create example text with the actual words from the search
                    exampleText = `(e.g., search for "${words[0]}" and "${words[1]}"${words.length > 2 ? '...' : ''} separately)`;
                }
                
                const suggestions = $('<div>').html(`
                    <p>We couldn't find this term in our dictionaries. Here are some suggestions:</p>
                    <ul>
                        <li>Check the spelling of your search term</li>
                        <li>Try searching for individual words instead of phrases ${exampleText}</li>
                        <li>Use more common synonyms or related terms</li>
                        <li>Try a more general term</li>
                    </ul>
                `);
                messageDiv.append(suggestions);
                
                resultsContainer.html('').append(messageDiv);
                
                // Add helpful search suggestions below
                addSearchSuggestions(term, resultsContainer);
            }
            
            // Add helpful related search suggestions
            function addSearchSuggestions(term, resultsContainer) {
                // For compound terms, suggest searching the individual words
                const words = term.split(/\s+/);
                
                if (words.length > 1) {
                    const suggestionsDiv = $('<div>').addClass('card mt-3');
                    const cardHeader = $('<div>').addClass('card-header').text('Try searching for individual words:');
                    const cardBody = $('<div>').addClass('card-body');
                    const buttonGroup = $('<div>').addClass('d-flex flex-wrap gap-2');
                    
                    words.forEach(word => {
                        if (word.length > 2) {
                            const btn = $('<button>')
                                .attr('type', 'button')
                                .addClass('btn btn-outline-primary')
                                .text(word)
                                .on('click', function() {
                                    $('#wordInput').val(word);
                                    lookupWord(word);
                                });
                            buttonGroup.append(btn);
                        }
                    });
                    
                    cardBody.append(buttonGroup);
                    suggestionsDiv.append(cardHeader, cardBody);
                    resultsContainer.append(suggestionsDiv);
                }
            }
            
            // Handle form submission
            $('#dictionaryForm').on('submit', function(event) {
                event.preventDefault();
                const word = $('#wordInput').val().trim();
                lookupWord(word);
            });

            // Dictionary autocomplete
            const wordInput = $('#wordInput');
            const suggestionsContainer = $('#wordSuggestions');
            let currentFocus = -1;
            let debounceTimer;

            // Function to fetch suggestions
            function fetchSuggestions(query) {
                if (query.length < 2) {
                    suggestionsContainer.hide();
                    return;
                }

                $.ajax({
                    url: 'ajax/get-word-suggestions.php',
                    method: 'GET',
                    data: { query: query },
                    dataType: 'json',
                    success: function(data) {
                        if (data.suggestions && data.suggestions.length > 0) {
                            displaySuggestions(data.suggestions);
                        } else {
                            suggestionsContainer.hide();
                        }
                    },
                    error: function() {
                        suggestionsContainer.hide();
                    }
                });
            }

            // Function to display suggestions
            function displaySuggestions(suggestions) {
                // Clear previous suggestions
                suggestionsContainer.empty();
                
                // Add new suggestions
                suggestions.forEach(function(word) {
                    const item = $('<div>')
                        .addClass('suggestion-item')
                        .html(`<div class="suggestion-title">${word}</div>`)
                        .on('click', function() {
                            wordInput.val(word);
                            suggestionsContainer.hide();
                            lookupWord(word); // Use AJAX lookup instead of form submit
                        });
                    
                    suggestionsContainer.append(item);
                });
                
                // Show suggestions container
                suggestionsContainer.show();
            }

            // Input event with debounce to avoid excessive requests
            wordInput.on('input', function() {
                const query = $(this).val().trim();
                
                clearTimeout(debounceTimer);
                debounceTimer = setTimeout(function() {
                    fetchSuggestions(query);
                }, 300);
                
                currentFocus = -1;
            });

            // Handle keyboard navigation
            wordInput.on('keydown', function(e) {
                const items = suggestionsContainer.find('.suggestion-item');
                
                // Down arrow
                if (e.keyCode === 40) {
                    currentFocus++;
                    setActive(items);
                    e.preventDefault();
                } 
                // Up arrow
                else if (e.keyCode === 38) {
                    currentFocus--;
                    setActive(items);
                    e.preventDefault();
                } 
                // Enter key
                else if (e.keyCode === 13) {
                    if (items.length > 0 && currentFocus > -1) {
                        // If a suggestion is selected/active
                        e.preventDefault();
                        items.eq(currentFocus).click();
                    } else {
                        // If no suggestion is selected, just hide the suggestions container
                        suggestionsContainer.hide();
                    }
                }
            });

            // Set active suggestion
            function setActive(items) {
                if (!items.length) return;
                
                // Reset all items
                items.removeClass('active');
                
                // Adjust focus if out of bounds
                if (currentFocus >= items.length) currentFocus = 0;
                if (currentFocus < 0) currentFocus = items.length - 1;
                
                // Set active class
                items.eq(currentFocus).addClass('active');
                
                // Update input with the active suggestion text
                const activeText = items.eq(currentFocus).find('.suggestion-title').text();
                wordInput.val(activeText);
            }

            // Close suggestions when clicking outside
            $(document).on('click', function(e) {
                if (!$(e.target).closest('.search-input-container').length) {
                    suggestionsContainer.hide();
                }
            });
        });
    </script>
    
    <!-- Include Floating Chatbot -->
    <?php include 'includes/floating-chatbot.php'; ?>
    
    <!-- Marked.js for Markdown -->
    <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
    
    <!-- Floating Chatbot JS -->
    <script src="js/floating-chatbot.js"></script>
</body>

</html>