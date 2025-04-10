document.addEventListener('DOMContentLoaded', () => {
    // Get level_id from PHP
    const gameContainer = document.getElementById('gameContainer');
    if (!gameContainer) {
        console.error('Game container not found');
        return;
    }

    const level_id = parseInt(gameContainer.dataset.levelId);
    const total_levels = parseInt(gameContainer.dataset.totalLevels);
    console.log('Starting level:', level_id, 'Total levels:', total_levels);

    // Log hints from PHP
    if (window.gameHints) {
        console.log('Hint Words and Positions:');
        for (const [word, positions] of Object.entries(window.gameHints)) {
            console.log(`Word: ${word}, Hint Positions: ${positions.join(', ')}`);
        }
    }

    // Get all answer boxes
    const answerBoxes = document.querySelectorAll('.answer-word');
    console.log('Total answer boxes:', answerBoxes.length);

    // Log all valid words from the HTML
    answerBoxes.forEach(box => {
        const word = box.dataset.word;
        if (word) {
            console.log('Valid word:', word);
        }
    });

    const fillBoxes = document.querySelectorAll('.fill-box');
    const availableLetters = document.querySelectorAll('.letter');
    const submitBtn = document.getElementById('submitWord');
    const resetBtn = document.getElementById('resetGame');
    const shuffleBtn = document.getElementById('shuffleLetters');

    // Initialize empty fill boxes
    fillBoxes.forEach(box => box.textContent = '');

    // Track completed words
    let completedWords = [];

    // Letter click handler
    availableLetters.forEach(letter => {
        letter.addEventListener('click', () => {
            if (!letter.classList.contains('used')) {
                // Find the first empty box
                const currentBox = Array.from(fillBoxes).find(box => !box.textContent);
                if (currentBox) {
                    currentBox.textContent = letter.textContent.toUpperCase();
                    currentBox.classList.add('filled');
                    letter.classList.add('used');
                    letter.style.opacity = '0.5';
                    console.log('Letter selected:', letter.textContent.toUpperCase());
                }
            }
        });
    });

    // Function to update answer boxes
    function updateAnswerBoxes(word) {
        console.log('Updating answer boxes for word:', word);
        const answerBoxes = document.querySelectorAll('.answer-word');
        let found = false;
        
        // Convert word to uppercase for display
        const upperWord = word.toUpperCase();
        
        answerBoxes.forEach(box => {
            const boxWord = box.dataset.word.toUpperCase();
            if (boxWord === upperWord) {
                found = true;
                const group = box.closest('.answer-group');
                const boxes = group.querySelectorAll('.answer-box');
                const wordLetters = upperWord.split('');
                
                boxes.forEach((box, index) => {
                    box.classList.add('success');
                    box.textContent = wordLetters[index];
                });
                
                box.style.display = 'block';
                
                // Add to completed words if not already there
                if (!completedWords.includes(word)) {
                    completedWords.push(word);
                    console.log('Word completed:', word, 'Total completed:', completedWords.length);
                    
                    // Reset the fill boxes
                    fillBoxes.forEach(box => {
                        box.textContent = '';
                        box.classList.remove('filled', 'success');
                    });
                    
                    // Reset letter elements
                    availableLetters.forEach(letter => {
                        letter.classList.remove('used');
                        letter.style.opacity = '1';
                    });
                }
                
                // Check level completion after each word
                checkLevelCompletion();
            }
        });

        if (!found) {
            console.log('Word not found in answer boxes:', word);
        }
    }

    // Function to check if all words are completed
    function checkLevelCompletion() {
        const answerBoxes = document.querySelectorAll('.answer-word');
        const totalWords = answerBoxes.length;
        const completedCount = completedWords.length;
        
        // Check if all words are unique and completed
        const uniqueCompletedWords = new Set(completedWords.map(word => word.toLowerCase()));
        const allUniqueCompleted = uniqueCompletedWords.size === completedWords.length;
        
        if (completedCount === totalWords && allUniqueCompleted) {
            console.log('All words completed! Total words:', totalWords);
            console.log('Completed words:', completedWords);
            
            // Show notification
            showCompletionNotification();
        }
    }

    // Function to show completion notification
    function showCompletionNotification() {
        // Create notification element
        const notification = document.createElement('div');
        notification.className = 'notification';
        
        if (level_id === total_levels) {
            // Special message for final level
            notification.innerHTML = `
                <div class="notification-content">
                    <i class="fas fa-trophy"></i>
                    <span>You've completed all levels!</span>
                </div>
            `;
            notification.style.background = '#2196F3';
            notification.style.boxShadow = '0 2px 5px rgba(33, 150, 243, 0.2)';
        } else {
            notification.innerHTML = `
                <div class="notification-content">
                    <i class="fas fa-check-circle"></i>
                    <span>All words found!</span>
                </div>
            `;
        }
        
        // Add to body
        document.body.appendChild(notification);
        
        // Animate in
        notification.style.opacity = '1';
        notification.style.transform = 'translateX(0)';
        
        // Wait for animation, then handle completion
        setTimeout(() => {
            // Animate out
            notification.style.opacity = '0';
            notification.style.transform = 'translateX(100%)';
            
            // Remove after animation
            setTimeout(() => {
                notification.remove();
                
                // Only redirect if not on last level
                if (level_id < total_levels) {
                    // Redirect to next level
                    window.location.href = `index.php?level=${level_id + 1}`;
                }
            }, 300); // Match the fade-out duration
        }, 2000); // Total time notification is visible
    }

    // Initialize game with hints
    function initializeGame() {
        const hints = window.gameHints;
        
        // Display hints in answer boxes
        answerBoxes.forEach(box => {
            const word = box.dataset.word;
            if (word && hints[word]) {
                const hintPositions = hints[word];
                const group = box.closest('.answer-group');
                const boxes = group.querySelectorAll('.answer-box');
                
                // Place hints in their correct positions
                hintPositions.forEach(position => {
                    if (position < boxes.length) {
                        boxes[position].textContent = word[position];
                        boxes[position].classList.add('filled');
                    }
                });
            }
        });
    }

    // Initialize the game
    initializeGame();

    // Reset game handler
    resetBtn.addEventListener('click', () => {
        console.log('Resetting game');
        // Reset fill boxes
        fillBoxes.forEach(box => {
            box.textContent = '';
            box.classList.remove('filled', 'success', 'danger', 'warning');
        });
        
        // Reset letter elements
        availableLetters.forEach(letter => {
            letter.classList.remove('used');
            letter.style.opacity = '1';
        });

        // Reset any completed words
        const answerWords = document.querySelectorAll('.answer-word');
        answerWords.forEach(word => {
            word.textContent = '';
            word.classList.remove('completed');
        });
    });

    // Submit word handler
    submitBtn.addEventListener('click', async () => {
        // Get all non-empty letters and join without spaces
        const word = Array.from(fillBoxes)
            .map(box => box.textContent.trim())
            .filter(text => text)
            .join('');

        if (!word) {
            console.log('Word submission failed: Empty word');
            // Show warning state for empty word
            fillBoxes.forEach(box => {
                box.classList.add('warning');
            });
            
            // Wait 1 second then reset
            setTimeout(() => {
                fillBoxes.forEach(box => {
                    box.classList.remove('filled', 'warning');
                });
            }, 1000);
            return;
        }

        // Make uppercase
        const cleanWord = word.toUpperCase();
        console.log('Submitting word:', cleanWord);

        try {
            const response = await fetch('ajax/check-word.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `word=${encodeURIComponent(cleanWord)}&level_id=${level_id}`
            });

            const result = await response.json();
            console.log('Word validation result:', result);
            
            if (result.valid) {
                // Only update answer boxes if word hasn't been completed yet
                if (!completedWords.includes(cleanWord)) {
                    updateAnswerBoxes(cleanWord);
                } else {
                    console.log('Word already completed:', cleanWord);
                    alert('This word has already been completed!');
                }
            } else {
                console.log('Word validation failed:', result.message);
                // Mark boxes as danger
                fillBoxes.forEach(box => {
                    if (box.textContent) {
                        box.classList.add('danger');
                    }
                });
                
                // Wait 1 second then reset
                setTimeout(() => {
                    fillBoxes.forEach(box => {
                        box.textContent = '';
                        box.classList.remove('filled', 'danger');
                    });
                    
                    // Reset letter elements
                    availableLetters.forEach(letter => {
                        letter.classList.remove('used');
                        letter.style.opacity = '1';
                    });
                }, 1000);
            }
        } catch (error) {
            console.error('Error checking word:', error);
            
            // Show error state
            fillBoxes.forEach(box => {
                box.classList.add('danger');
            });
            
            // Wait 1 second then reset
            setTimeout(() => {
                fillBoxes.forEach(box => {
                    box.textContent = '';
                    box.classList.remove('filled', 'danger');
                });
                
                availableLetters.forEach(letter => {
                    letter.classList.remove('used');
                    letter.style.opacity = '1';
                });
            }, 1000);
        }
    });

    // Shuffle letters handler
    shuffleBtn.addEventListener('click', () => {
        console.log('Shuffling letters');
        const letters = Array.from(availableLetters);
        const shuffledLetters = shuffle(letters);
        const letterContainer = document.querySelector('.letter-container');
        letterContainer.innerHTML = '';
        shuffledLetters.forEach(letter => letterContainer.appendChild(letter));
    });

    // Shuffle function
    function shuffle(array) {
        for (let i = array.length - 1; i > 0; i--) {
            const j = Math.floor(Math.random() * (i + 1));
            [array[i], array[j]] = [array[j], array[i]];
        }
        return array;
    }
});
