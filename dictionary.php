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
</head>

<body>
    <?php include 'includes/nav.php'; ?>

    <div class="container py-5">
        <div class="search-container">
            <h1 class="display-4 text-center mb-4">Dictionary</h1>
            <form id="dictionaryForm" class="mb-5">
                <div class="input-group">
                    <input type="text" class="form-control form-control-lg" id="wordInput" placeholder="Enter a word..." required>
                    <button class="btn btn-primary btn-lg" type="submit">
                        <i class="fas fa-search me-2"></i>Search
                    </button>
                </div>
            </form>

            <div id="dictionaryResults" class="mt-4"></div>
        </div>
    </div>

    <?php include 'includes/footer.php'; ?>

    <!-- Bootstrap JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('dictionaryForm').addEventListener('submit', function(event) {
            event.preventDefault();
            const word = document.getElementById('wordInput').value.trim();
            const resultsContainer = document.getElementById('dictionaryResults');
            resultsContainer.innerHTML = '';

            if (!word) {
                resultsContainer.innerHTML = '<div class="alert alert-warning">Please enter a word to search.</div>';
                return;
            }

            fetch(`https://api.dictionaryapi.dev/api/v2/entries/en/${word}`)
                .then(response => response.json())
                .then(data => {
                    if (data.title && data.title === 'No Definitions Found') {
                        resultsContainer.innerHTML = `<div class="alert alert-warning">No definitions found for "${word}".</div>`;
                    } else {
                        data.forEach(entry => {
                            const resultItem = document.createElement('div');
                            resultItem.className = 'result-item';

                            // Word Title
                            const wordHeading = document.createElement('h2');
                            wordHeading.className = 'word-title';
                            wordHeading.textContent = entry.word;
                            resultItem.appendChild(wordHeading);

                            // Phonetic
                            if (entry.phonetic) {
                                const phonetic = document.createElement('p');
                                phonetic.className = 'phonetic';
                                phonetic.textContent = 'Phonetic: ' + entry.phonetic;
                                resultItem.appendChild(phonetic);
                            }

                            // Pronunciations Section
                            const pronunciations = {
                                us: entry.phonetics.find(p => p.audio && p.audio.includes('-us.mp3')),
                                uk: entry.phonetics.find(p => p.audio && p.audio.includes('-uk.mp3')),
                                au: entry.phonetics.find(p => p.audio && p.audio.includes('-au.mp3'))
                            };

                            if (Object.values(pronunciations).some(p => p)) {
                                const pronunciationSection = document.createElement('div');
                                pronunciationSection.className = 'pronunciation-section';

                                Object.entries(pronunciations).forEach(([region, phonetic]) => {
                                    if (phonetic && phonetic.audio) {
                                        const audioBox = document.createElement('div');
                                        audioBox.className = 'pronunciation-box';
                                        audioBox.innerHTML = `
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
                                            </div>`;
                                        pronunciationSection.appendChild(audioBox);
                                    }
                                });

                                resultItem.appendChild(pronunciationSection);
                            }

                            // Meanings
                            entry.meanings.forEach(meaning => {
                                const partOfSpeechDiv = document.createElement('div');
                                partOfSpeechDiv.className = 'part-of-speech';
                                partOfSpeechDiv.textContent = meaning.partOfSpeech;
                                resultItem.appendChild(partOfSpeechDiv);

                                meaning.definitions.forEach((definition, index) => {
                                    const definitionDiv = document.createElement('div');
                                    definitionDiv.className = 'definition';
                                    
                                    const definitionText = document.createElement('p');
                                    definitionText.innerHTML = `<strong>${index + 1}.</strong> ${definition.definition}`;
                                    definitionDiv.appendChild(definitionText);

                                    if (definition.example) {
                                        const exampleText = document.createElement('div');
                                        exampleText.className = 'example';
                                        exampleText.innerHTML = `<i class="fas fa-quote-left me-2"></i>${definition.example}`;
                                        definitionDiv.appendChild(exampleText);
                                    }

                                    resultItem.appendChild(definitionDiv);
                                });
                            });

                            resultsContainer.appendChild(resultItem);
                        });
                    }
                })
                .catch(error => {
                    resultsContainer.innerHTML = `<div class="alert alert-danger">Error fetching data. Please try again later.</div>`;
                });
        });
    </script>
</body>

</html> 