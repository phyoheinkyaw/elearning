document.addEventListener('DOMContentLoaded', () => {
    // Get level_id from PHP
    const gameContainer = document.getElementById('gameContainer');
    if (!gameContainer) {
        console.error('Game container not found');
        return;
    }

    // Get the help button
    const helpButton = document.getElementById('helpButton');

    const level_id = parseInt(gameContainer.dataset.levelId);
    const total_levels = parseInt(gameContainer.dataset.totalLevels);
    // Get the current level number from the game container or default to level_id
    const current_level_number = gameContainer.dataset.levelNumber ? 
        parseInt(gameContainer.dataset.levelNumber) : level_id;
    
    console.log('Starting level:', level_id, 'Level number:', current_level_number, 'Total levels:', total_levels);

    // Help button functionality for tooltip only (modal handled in index.php)
    if (helpButton) {
        // Add tooltip behavior
        let tooltipTimeout;
        
        // Show a tooltip for new users on first page load
        if (localStorage.getItem('wordscapes_help_shown') !== 'true') {
            tooltipTimeout = setTimeout(() => {
                helpButton.setAttribute('title', 'Click for game instructions!');
                // Use bootstrap tooltip if available
                if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
                    const tooltip = new bootstrap.Tooltip(helpButton, {
                        trigger: 'manual'
                    });
                    tooltip.show();
                    
                    // Hide tooltip after 5 seconds
                    setTimeout(() => {
                        tooltip.hide();
                    }, 5000);
                }
            }, 3000);
            
            // Mark that we've shown the help tooltip
            localStorage.setItem('wordscapes_help_shown', 'true');
        }
    }

    // Game state
    let gameState = {
        score: 0,
        hintsUsed: 0,
        hintsAvailable: 0,
        foundWords: [],
        levelCompleted: false
    };

    // Load initial game state
    loadGameProgress();
    
    // Load leaderboard data immediately
    loadLeaderboard();

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
    const validWords = [];
    answerBoxes.forEach(box => {
        const word = box.dataset.word;
        if (word) {
            validWords.push(word.toLowerCase());
            console.log('Valid word:', word);
        }
    });

    const fillBoxes = document.querySelectorAll('.fill-box');
    const availableLetters = document.querySelectorAll('.letter');
    const submitBtn = document.getElementById('submitWord');
    const resetWordBtn = document.getElementById('resetWord');
    const shuffleBtn = document.getElementById('shuffleLetters');
    
    // Create score display
    const scoreDisplay = document.createElement('div');
    scoreDisplay.className = 'score-display';
    scoreDisplay.innerHTML = `
        <div class="score-item">
            <i class="fas fa-star"></i>
            <span class="score-value">0</span>
            <span class="score-label">total</span>
        </div>
        <div class="score-item">
            <i class="fas fa-star-half-alt"></i>
            <span class="level-score-value">0</span>
            <span class="score-label">this level</span>
        </div>
        <div class="score-item">
            <i class="fas fa-lightbulb"></i>
            <span class="hints-value">0</span>
            <span class="hints-label">hints</span>
        </div>
    `;
    gameContainer.insertBefore(scoreDisplay, gameContainer.firstChild);

    // Initialize empty fill boxes
    fillBoxes.forEach(box => box.textContent = '');

    // Add keyboard support for typing words
    document.addEventListener('keydown', (e) => {
        // Allow only letter keys
        if (/^[a-zA-Z]$/.test(e.key)) {
            const key = e.key.toUpperCase();
            // Find the matching letter element
            const letterElem = Array.from(availableLetters).find(elem => 
                elem.textContent.toUpperCase() === key && !elem.classList.contains('used')
            );
            
            if (letterElem) {
                // Simulate clicking the letter
                const currentBox = Array.from(fillBoxes).find(box => !box.textContent);
                if (currentBox) {
                    currentBox.textContent = key;
                    currentBox.classList.add('filled');
                    letterElem.classList.add('used');
                    letterElem.style.opacity = '0.5';
                }
            }
        } else if (e.key === 'Backspace') {
            // Handle backspace to remove the last entered letter
            const filledBoxes = Array.from(fillBoxes).filter(box => box.textContent);
            if (filledBoxes.length > 0) {
                const lastBox = filledBoxes[filledBoxes.length - 1];
                const letter = lastBox.textContent;
                
                // Find the corresponding letter element and reactivate it
                const letterElem = Array.from(availableLetters).find(elem => 
                    elem.textContent === letter && elem.classList.contains('used')
                );
                
                if (letterElem) {
                    letterElem.classList.remove('used');
                    letterElem.style.opacity = '1';
                }
                
                // Clear the box
                lastBox.textContent = '';
                lastBox.classList.remove('filled');
            }
        } else if (e.key === 'Enter') {
            // Submit the word when Enter is pressed
            submitWord();
        }
    });

    // Load found words from game state
    function loadGameProgress() {
        // Make AJAX request to get current progress
        fetch(`ajax/game_actions.php?action=get_progress&level_id=${level_id}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    console.log('Loaded game progress:', data);
                    gameState.score = data.score || 0;
                    gameState.currentLevelScore = data.current_level_score || 0;
                    gameState.hintsUsed = data.hints_used || 0;
                    gameState.hintsReceived = data.hints_received || 0;
                    gameState.foundWords = data.found_words || [];
                    gameState.completedLevels = data.completed_levels || [];
                    gameState.revealedHints = data.revealed_hints || {};
                    
                    // Use available_hints if provided, otherwise calculate
                    gameState.hintsAvailable = data.available_hints !== undefined 
                        ? data.available_hints 
                        : (gameState.hintsReceived - gameState.hintsUsed);
                    
                    if (gameState.hintsAvailable < 0) gameState.hintsAvailable = 0;
                    
                    // Update score display
                    updateScoreDisplay();
                    
                    // Mark already found words
                    gameState.foundWords.forEach(word => {
                        updateAnswerBoxes(word);
                    });
                    
                    // Apply revealed hints from database
                    applyRevealedHints();
                }
            })
            .catch(error => {
                console.error('Error loading game progress:', error);
            });
    }
    
    // Function to apply revealed hints from the database
    function applyRevealedHints() {
        if (!gameState.revealedHints || Object.keys(gameState.revealedHints).length === 0) {
            return;
        }
        
        console.log('Applying revealed hints:', gameState.revealedHints);
        
        // Go through each answer group
        document.querySelectorAll('.answer-group').forEach(group => {
            const wordElem = group.querySelector('.answer-word');
            if (!wordElem) return;
            
            const word = wordElem.dataset.word.toLowerCase();
            
            // Check if this word has revealed hints
            if (gameState.revealedHints[word]) {
                const positions = gameState.revealedHints[word];
                const boxes = group.querySelectorAll('.answer-box');
                
                // Apply each revealed hint
                positions.forEach(position => {
                    if (position >= 0 && position < boxes.length) {
                        boxes[position].textContent = word[position].toUpperCase();
                        boxes[position].classList.add('success');
                    }
                });
            }
        });
    }

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

    // Function to update score display
    function updateScoreDisplay() {
        const scoreValueEl = scoreDisplay.querySelector('.score-value');
        const levelScoreValueEl = scoreDisplay.querySelector('.level-score-value');
        const hintsValueEl = scoreDisplay.querySelector('.hints-value');
        
        if (scoreValueEl) {
            scoreValueEl.textContent = gameState.score;
        }
        if (levelScoreValueEl) {
            levelScoreValueEl.textContent = gameState.currentLevelScore;
        }
        if (hintsValueEl) {
            hintsValueEl.textContent = gameState.hintsAvailable;
        }
    }

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
                
                // Add green check mark icon
                if (!box.querySelector('.fa-check-circle')) {
                    const checkIcon = document.createElement('i');
                    checkIcon.className = 'fas fa-check-circle text-success ms-2';
                    box.appendChild(checkIcon);
                }
                
                // Explicitly set display style to block to ensure it's visible
                box.style.display = 'block';
                
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
        // Check if all words are found
        const allWordsFound = validWords.every(word => 
            gameState.foundWords.includes(word.toLowerCase())
        );
        
        if (allWordsFound) {
            console.log('All words completed! Total words:', validWords.length);
            gameState.levelCompleted = true;
            
            // Show notification
            showCompletionNotification();
        }
    }

    // Function to show completion notification
    function showCompletionNotification() {
        // Create modal element
        const modalOverlay = document.createElement('div');
        modalOverlay.className = 'modal-overlay';
        
        const modalContent = document.createElement('div');
        modalContent.className = 'modal-content';
        
        if (level_id === total_levels) {
            // Special message for final level
            modalContent.innerHTML = `
                <div class="modal-header">
                    <h2><i class="fas fa-trophy"></i> Congratulations!</h2>
                </div>
                <div class="modal-body">
                    <p>Amazing! You've completed all levels of Wordscapes!</p>
                    <p>Your total score: <strong>${gameState.score}</strong> points</p>
                    <p>You've mastered all word puzzles in this game.</p>
                </div>
                <div class="modal-footer">
                    <button id="homeButton" class="modal-button home-btn">
                        <i class="fas fa-home"></i> Back to Home
                    </button>
                    <button id="playAgainButton" class="modal-button play-again-btn">
                        <i class="fas fa-redo"></i> Play Again
                    </button>
                </div>
            `;
        } else {
            // Message for regular level completion
            modalContent.innerHTML = `
                <div class="modal-header">
                    <h2><i class="fas fa-check-circle"></i> Level Complete!</h2>
                </div>
                <div class="modal-body">
                    <p>Well done! You've found all words in Level ${level_id}!</p>
                    <p>Your score: <strong>${gameState.score}</strong> points</p>
                    <p>Ready for the next challenge?</p>
                </div>
                <div class="modal-footer">
                    <button id="nextLevelButton" class="modal-button next-btn">
                        <i class="fas fa-arrow-right"></i> Next Level
                    </button>
                </div>
            `;
        }
        
        modalOverlay.appendChild(modalContent);
        document.body.appendChild(modalOverlay);
        
        // Add modal animations
        setTimeout(() => {
            modalOverlay.classList.add('visible');
            modalContent.classList.add('visible');
        }, 100);
        
        // Add event listeners for buttons
        const nextLevelButton = document.getElementById('nextLevelButton');
        const homeButton = document.getElementById('homeButton');
        const playAgainButton = document.getElementById('playAgainButton');
        
        if (nextLevelButton) {
            nextLevelButton.addEventListener('click', () => {
                // Close modal with animation
                modalOverlay.classList.remove('visible');
                modalContent.classList.remove('visible');
                
                // Wait for animation to complete
                setTimeout(() => {
                    modalOverlay.remove();
                    
                    // Save next level to session and redirect
                    saveCurrentLevel(level_id + 1)
                        .then(() => {
                            window.location.href = `index.php?level=${level_id + 1}`;
                        });
                }, 300);
            });
        }
        
        if (homeButton) {
            homeButton.addEventListener('click', () => {
                // Close modal with animation
                modalOverlay.classList.remove('visible');
                modalContent.classList.remove('visible');
                
                // Wait for animation to complete
                setTimeout(() => {
                    modalOverlay.remove();
                    window.location.href = '../../index.php';
                }, 300);
            });
        }
        
        if (playAgainButton) {
            playAgainButton.addEventListener('click', () => {
                // Close modal with animation
                modalOverlay.classList.remove('visible');
                modalContent.classList.remove('visible');
                
                // Wait for animation to complete
                setTimeout(() => {
                    modalOverlay.remove();
                    window.location.href = 'index.php?level=1';
                }, 300);
            });
        }
    }

    // Function to save current level in session
    async function saveCurrentLevel(levelId) {
        try {
            const response = await fetch('ajax/game_actions.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=save_current_level&level_id=${levelId}`
            });
            
            const data = await response.json();
            return data.success;
        } catch (error) {
            console.error('Error saving current level:', error);
            return false;
        }
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

    // Reset word handler
    resetWordBtn.addEventListener('click', () => {
        resetFillBoxes();
    });
    
    // Add click event for hint usage on answer groups
    document.querySelectorAll('.answer-group').forEach(group => {
        group.addEventListener('click', (e) => {
            // Only respond to clicks on the group itself or the answer boxes, not on already revealed letters
            const isAnswerBox = e.target.classList.contains('answer-box') && !e.target.classList.contains('success');
            const isAnswerGroup = e.target === group;
            
            if ((isAnswerBox || isAnswerGroup) && gameState.hintsAvailable > 0) {
                useHint(group);
            }
        });
    });
    
    // Function to use a hint
    function useHint(answerGroup) {
        if (gameState.hintsAvailable <= 0) {
            alert('You need more points to use hints! Earn 10 points for 1 hint.');
            return;
        }
        
        // Get the word for this answer group
        const wordElem = answerGroup.querySelector('.answer-word');
        if (!wordElem) return;
        
        const word = wordElem.dataset.word;
        if (!word) return;
        
        // Check if word is already found
        if (gameState.foundWords.includes(word.toLowerCase())) {
            return;
        }
        
        // Find a letter position that hasn't been revealed yet
        const revealPosition = findUnrevealedPosition(answerGroup, word);
        
        if (revealPosition === -1) {
            console.log('No more letters to reveal in this word');
            return;
        }
        
        // Use a hint via AJAX
        fetch('ajax/game_actions.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=use_hint&level_id=${level_id}&word=${encodeURIComponent(word)}&position=${revealPosition}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Decrease available hints
                gameState.hintsUsed++;
                gameState.hintsAvailable--;
                updateScoreDisplay();
                
                // Store the revealed hints in the game state
                if (data.revealed_hints) {
                    gameState.revealedHints = data.revealed_hints;
                }
                
                // Reveal the letter in the word
                revealLetterInWord(answerGroup, word, revealPosition);
            }
        })
        .catch(error => {
            console.error('Error using hint:', error);
        });
    }
    
    // Function to find a position in the word that hasn't been revealed yet
    function findUnrevealedPosition(answerGroup, word) {
        const boxes = Array.from(answerGroup.querySelectorAll('.answer-box'));
        
        // Find all positions that aren't already revealed
        const unrevealedPositions = boxes
            .map((box, index) => ({ index, revealed: box.classList.contains('success') }))
            .filter(item => !item.revealed)
            .map(item => item.index);
        
        if (unrevealedPositions.length === 0) {
            return -1;
        }
        
        // Choose a random unrevealed position
        const randomIndex = Math.floor(Math.random() * unrevealedPositions.length);
        return unrevealedPositions[randomIndex];
    }
    
    // Function to reveal a specific letter in a word
    function revealLetterInWord(answerGroup, word, position) {
        const boxes = answerGroup.querySelectorAll('.answer-box');
        if (position < 0 || position >= boxes.length) return;
        
        // Reveal the specific letter
        boxes[position].textContent = word[position].toUpperCase();
        boxes[position].classList.add('success');
        
        // Play sound
        playSound('success');
    }
    
    // Reset UI function for fill boxes only
    function resetFillBoxes() {
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
    }

    // Function to submit a word
    async function submitWord() {
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
                    box.classList.remove('warning');
                });
            }, 1000);
            return;
        }

        console.log('Submitting word:', word);
        
        // Check if word is already found
        if (gameState.foundWords.includes(word.toLowerCase())) {
            console.log('Word already found:', word);
            // Show already found state
            fillBoxes.forEach(box => {
                if (box.textContent) {
                    box.classList.add('warning');
                }
            });
            
            // Wait 1 second then reset
            setTimeout(() => {
                fillBoxes.forEach(box => {
                    box.classList.remove('warning');
                });
                
                // Reset fill boxes
                resetFillBoxes();
            }, 1000);
            return;
        }
        
        // Show loading state
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
        
        // Send AJAX request to check word
        try {
            const response = await fetch('ajax/game_actions.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=check_word&level_id=${level_id}&word=${encodeURIComponent(word)}`
            });
            
            const data = await response.json();
            console.log('Word check response:', data);
            
            // Reset button state
            submitBtn.disabled = false;
            submitBtn.innerHTML = 'Submit Word';
            
            if (data.success) {
                // Update game state
                gameState.score = data.score;
                gameState.currentLevelScore = data.current_level_score || 0;
                gameState.foundWords = data.found_words;
                gameState.levelCompleted = data.level_completed;
                
                // Update hintsAvailable if provided, otherwise calculate
                if (data.hints_available !== undefined) {
                    gameState.hintsAvailable = data.hints_available;
                } else if (data.hints_used !== undefined && data.hints_received !== undefined) {
                    gameState.hintsUsed = data.hints_used;
                    gameState.hintsReceived = data.hints_received;
                    gameState.hintsAvailable = gameState.hintsReceived - gameState.hintsUsed;
                }
                
                if (gameState.hintsAvailable < 0) gameState.hintsAvailable = 0;
                
                // Update UI
                updateScoreDisplay();
                updateAnswerBoxes(word);
                
                // Update leaderboard and level info
                loadLeaderboard(); // This also calls updateLevelInfo
                
                // Play success sound
                playSound('success');
                
                // Success animation
                fillBoxes.forEach(box => {
                    if (box.textContent) {
                        box.classList.add('success');
                    }
                });
                
                // Wait 0.5 second then reset
                setTimeout(() => {
                    resetFillBoxes();
                }, 500);
            } else {
                console.log('Invalid word:', word);
                
                // Play error sound
                playSound('error');
                
                // Error animation
                fillBoxes.forEach(box => {
                    if (box.textContent) {
                        box.classList.add('danger');
                    }
                });
                
                // Wait 1 second then reset
                setTimeout(() => {
                    fillBoxes.forEach(box => {
                        box.classList.remove('danger');
                    });
                    
                    // Reset fill boxes
                    resetFillBoxes();
                }, 1000);
            }
        } catch (error) {
            console.error('Error checking word:', error);
            // Reset button state
            submitBtn.disabled = false;
            submitBtn.innerHTML = 'Submit Word';
        }
    }

    // Shuffle letters handler
    shuffleBtn.addEventListener('click', () => {
        console.log('Shuffling letters');
        
        // Get mapping of used letters to fill boxes
        const fillBoxToLetterMap = new Map();
        const usedLetterIndices = new Map();
        
        // Map used letters to their fill box positions
        fillBoxes.forEach((box, index) => {
            if (box.textContent) {
                const letter = box.textContent.toUpperCase();
                fillBoxToLetterMap.set(index, letter);
                
                // Find which available letter element corresponds to this letter
                for (let i = 0; i < availableLetters.length; i++) {
                    if (availableLetters[i].textContent === letter && 
                        availableLetters[i].classList.contains('used')) {
                        usedLetterIndices.set(letter, i);
                        break;
                    }
                }
            }
        });
        
        // Collect all letters (both used and unused)
        const allLetters = Array.from(availableLetters).map(el => el.textContent);
        
        // Shuffle all letters
        const shuffledLetters = shuffle([...allLetters]);
        
        // Update UI for all letter elements
        availableLetters.forEach((el, i) => {
            el.textContent = shuffledLetters[i];
            el.dataset.letter = shuffledLetters[i];
            
            // Reset the used status for all letters
            el.classList.remove('used');
            el.style.opacity = '1';
        });
        
        // Reapply the used status to letters in the fill boxes
        fillBoxToLetterMap.forEach((letter, boxIndex) => {
            // Find the new position of this letter after shuffle
            for (let i = 0; i < availableLetters.length; i++) {
                if (availableLetters[i].textContent === letter && 
                    !availableLetters[i].classList.contains('used')) {
                    // Mark this letter as used
                    availableLetters[i].classList.add('used');
                    availableLetters[i].style.opacity = '0.5';
                    break;
                }
            }
        });
        
        console.log('All letters shuffled, maintained fill box mapping');
    });

    // Function to shuffle an array
    function shuffle(array) {
        for (let i = array.length - 1; i > 0; i--) {
            const j = Math.floor(Math.random() * (i + 1));
            [array[i], array[j]] = [array[j], array[i]];
        }
        return array;
    }

    // Play sound effect
    function playSound(type) {
        // Create sound effect only if needed
        let sound;
        if (type === 'success') {
            sound = new Audio('data:audio/mp3;base64,SUQzBAAAAAABEVRYWFgAAAAtAAADY29tbWVudABCaWdTb3VuZEJhbmsuY29tIC8gTGFTb25vdGhlcXVlLm9yZwBURU5DAAAAHQAAA0xhdmY1Ny40MS4xMDAAAAAAAAAAAAAAA//tAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAGluZm8AAAAPAAAAAwAABVgAVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVQ==');
        } else {
            sound = new Audio('data:audio/mp3;base64,SUQzBAAAAAABEVRYWFgAAAAtAAADY29tbWVudABCaWdTb3VuZEJhbmsuY29tIC8gTGFTb25vdGhlcXVlLm9yZwBURU5DAAAAHQAAA0xhdmY1Ni40MC4xMDEAAAAAAAAAAAAAA//tAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAFdpbmcAAAAPAAAADQAABNgAk5OTk5OTk5OTk5OTk5OTk5OTk5OTk5OTk5OTk5OTyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyPf39/f39/f39/f39/f39/f39/f39/f39/f39/f39/cAAABQTEFNRTMuMTAwA8MAAAAAAAAAABSAJAVRFQAAgAAABNhSxL7PAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA//sQRAAP8AAAf4AAAAgAAA/wAAABAAAB/gAAACAAAD/AAAAEJsQFAkAAAgQJAAAABAAEeqGYyKjGlHKwTEVAOE9zioOOFIQyEIZCEMh2IMFIDP5e/KvMYv/xh/if//8eH//D8IQlDun/8IQhCf//CEIT///+H4fh+H4fh+AwGAgDPAYDAYDgQBgMBAGAPZQnMAUBgIAwwFAoDAkCgL');
        }
        
        // Play the sound
        sound.volume = 0.5;
        sound.play().catch(e => console.log('Sound playback prevented:', e));
    }

    // Submit button click handler
    submitBtn.addEventListener('click', submitWord);
    
    // Function to load leaderboard data
    function loadLeaderboard() {
        fetch(`ajax/game_actions.php?action=get_leaderboard&level_id=${level_id}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    console.log('Loaded leaderboard:', data);
                    renderPersistentLeaderboard(data.leaderboard);
                    
                    // Also update level info whenever we load the leaderboard
                    updateLevelInfo();
                }
            })
            .catch(error => {
                console.error('Error loading leaderboard:', error);
                // Show empty state with error message
                const leaderboardContainer = document.getElementById('persistentLeaderboard');
                if (leaderboardContainer) {
                    leaderboardContainer.innerHTML = '<p class="text-center text-danger">Error loading leaderboard</p>';
                }
            });
    }
    
    // Function to update level information in sidebar
    function updateLevelInfo() {
        fetch(`ajax/game_actions.php?action=get_progress&level_id=${level_id}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update level completion percentage
                    const levelProgressBar = document.querySelector('.level-progress-bar');
                    const foundCount = document.querySelector('.found-count');
                    const totalCount = document.querySelector('.total-count');
                    const percentageDisplay = document.querySelector('.percentage-display');
                    
                    if (levelProgressBar && foundCount && totalCount && percentageDisplay) {
                        const found = data.found_words ? data.found_words.length : 0;
                        const total = validWords.length;
                        const percentage = total > 0 ? Math.round((found / total) * 100) : 0;
                        
                        levelProgressBar.style.width = `${percentage}%`;
                        foundCount.textContent = found;
                        percentageDisplay.textContent = percentage;
                    }
                }
            })
            .catch(error => {
                console.error('Error updating level info:', error);
            });
    }
    
    // Set up auto-refresh for leaderboard and level info
    let refreshInterval;
    
    function startAutoRefresh() {
        // Clear any existing interval
        if (refreshInterval) clearInterval(refreshInterval);
        
        // Set up new interval - refresh every 30 seconds
        refreshInterval = setInterval(() => {
            loadLeaderboard(); // This also calls updateLevelInfo
        }, 30000); // 30 seconds
    }
    
    // Start auto-refresh when page loads
    startAutoRefresh();
    
    // Restart auto-refresh when visibility changes (user comes back to tab)
    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible') {
            loadLeaderboard(); // Immediate refresh when returning to tab
            startAutoRefresh(); // Restart interval
        } else {
            // Clear interval when tab is not visible to save resources
            if (refreshInterval) clearInterval(refreshInterval);
        }
    });
    
    // Function to render the persistent leaderboard in the sidebar
    function renderPersistentLeaderboard(leaderboard) {
        const leaderboardContainer = document.getElementById('persistentLeaderboard');
        if (!leaderboardContainer) return;
        
        // Handle empty or undefined leaderboard data
        if (!leaderboard || Object.keys(leaderboard).length === 0) {
            leaderboardContainer.innerHTML = '<p class="text-center">No leaderboard data available</p>';
            return;
        }
        
        // Create leaderboard table
        const tableHTML = `
            <table class="leaderboard-table">
                <thead>
                    <tr>
                        <th>Rank</th>
                        <th>Player</th>
                        <th>Score</th>
                    </tr>
                </thead>
                <tbody>
                    ${renderPersistentLeaderboardRows(leaderboard)}
                </tbody>
            </table>
        `;
        
        // Update the container
        leaderboardContainer.innerHTML = tableHTML;
    }
    
    // Function to render persistent leaderboard rows
    function renderPersistentLeaderboardRows(leaderboard) {
        let rows = '';
        
        // Check if there's no data available
        if (leaderboard && leaderboard.no_data === true) {
            return `<tr>
                <td colspan="3" class="text-center py-3">
                    <i class="fas fa-trophy text-muted mb-2" style="font-size: 24px;"></i>
                    <p class="mb-0">Be the first to play and get on the leaderboard!</p>
                </td>
            </tr>`;
        }
        
        // Add top users
        if (leaderboard && leaderboard.top_users && leaderboard.top_users.length > 0) {
            const topUsers = leaderboard.top_users.slice(0, 5); // Limit to 5 for sidebar
            topUsers.forEach((user, index) => {
                const rank = index + 1;
                const isCurrentUser = gameContainer.dataset.userId && user.user_id == gameContainer.dataset.userId;
                const rankClass = rank <= 3 ? `rank-${rank}` : '';
                
                rows += `
                    <tr${isCurrentUser ? ' class="my-score"' : ''}>
                        <td class="rank ${rankClass}">${rank}</td>
                        <td>${user.display_name}${isCurrentUser ? ' (You)' : ''}</td>
                        <td class="score">${user.score}</td>
                    </tr>
                `;
            });
        } else {
            rows += `<tr>
                <td colspan="3" class="text-center py-3">
                    <i class="fas fa-users text-muted mb-2" style="font-size: 24px;"></i>
                    <p class="mb-0">No players have scores yet</p>
                </td>
            </tr>`;
        }
        
        // Add user's row if not in top 5 and we have user data
        if (leaderboard && leaderboard.user_data && !leaderboard.user_in_top) {
            rows += `
                <tr class="my-score" style="border-top: 2px dashed #3f51b5;">
                    <td class="rank">${leaderboard.user_data.rank}</td>
                    <td>${leaderboard.user_data.display_name} (You)</td>
                    <td class="score">${leaderboard.user_data.score}</td>
                </tr>
            `;
        }
        
        return rows;
    }
});
