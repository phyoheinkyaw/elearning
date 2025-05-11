document.addEventListener('DOMContentLoaded', () => {
    // Get level_id from PHP
    const gameContainer = document.getElementById('gameContainer');
    if (!gameContainer) {
        console.error('Game container not found');
        return;
    }

    const level_id = parseInt(gameContainer.dataset.levelId);
    const total_levels = parseInt(gameContainer.dataset.totalLevels);
    // Get the current level number from the game container or default to level_id
    const current_level_number = gameContainer.dataset.levelNumber ? 
        parseInt(gameContainer.dataset.levelNumber) : level_id;
    
    console.log('Starting level:', level_id, 'Level number:', current_level_number, 'Total levels:', total_levels);

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
            <span class="score-label">points</span>
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
                    gameState.hintsUsed = data.hints_used || 0;
                    gameState.foundWords = data.found_words || [];
                    
                    // Calculate available hints (1 hint per 10 points)
                    gameState.hintsAvailable = Math.floor(gameState.score / 10) - gameState.hintsUsed;
                    if (gameState.hintsAvailable < 0) gameState.hintsAvailable = 0;
                    
                    // Update score display
                    updateScoreDisplay();
                    
                    // Mark already found words
                    gameState.foundWords.forEach(word => {
                        updateAnswerBoxes(word);
                    });
                }
            })
            .catch(error => {
                console.error('Error loading game progress:', error);
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
        const hintsValueEl = scoreDisplay.querySelector('.hints-value');
        
        if (scoreValueEl) {
            scoreValueEl.textContent = gameState.score;
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
        
        // Use a hint via AJAX
        fetch('ajax/game_actions.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=use_hint&level_id=${level_id}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Decrease available hints
                gameState.hintsUsed++;
                gameState.hintsAvailable--;
                updateScoreDisplay();
                
                // Reveal a random letter in the word
                revealRandomLetterInWord(answerGroup, word);
            }
        })
        .catch(error => {
            console.error('Error using hint:', error);
        });
    }
    
    // Function to reveal a random letter in a word
    function revealRandomLetterInWord(answerGroup, word) {
        const boxes = answerGroup.querySelectorAll('.answer-box:not(.success)');
        if (boxes.length === 0) return;
        
        // Choose a random box
        const randomIndex = Math.floor(Math.random() * boxes.length);
        const randomBox = boxes[randomIndex];
        const boxIndex = Array.from(answerGroup.querySelectorAll('.answer-box')).indexOf(randomBox);
        
        // Reveal the letter
        randomBox.textContent = word[boxIndex].toUpperCase();
        randomBox.classList.add('success');
        
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
                gameState.foundWords = data.found_words;
                gameState.levelCompleted = data.level_completed;
                
                // Calculate available hints (1 hint per 10 points)
                gameState.hintsAvailable = Math.floor(gameState.score / 10) - gameState.hintsUsed;
                if (gameState.hintsAvailable < 0) gameState.hintsAvailable = 0;
                
                // Update UI
                updateScoreDisplay();
                updateAnswerBoxes(word);
                
                // Update leaderboard
                loadLeaderboard();
                
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
        
        // Get all letters
        const letters = Array.from(availableLetters).map(el => el.textContent);
        
        // Shuffle
        const shuffled = shuffle([...letters]);
        
        // Update UI
        availableLetters.forEach((el, i) => {
            el.textContent = shuffled[i];
            el.dataset.letter = shuffled[i];
        });
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
            sound = new Audio('data:audio/mp3;base64,SUQzBAAAAAABEVRYWFgAAAAtAAADY29tbWVudABCaWdTb3VuZEJhbmsuY29tIC8gTGFTb25vdGhlcXVlLm9yZwBURU5DAAAAHQAAA0xhdmY1Ny40MS4xMDAAAAAAAAAAAAAAA//tAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAGluZm8AAAAPAAAAAwAABVgAVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVQ==');
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
                    renderLeaderboard(data.leaderboard);
                }
            })
            .catch(error => {
                console.error('Error loading leaderboard:', error);
            });
    }
    
    // Function to render leaderboard
    function renderLeaderboard(leaderboard) {
        // Create leaderboard section if it doesn't exist
        let leaderboardSection = document.querySelector('.leaderboard-section');
        
        if (!leaderboardSection) {
            leaderboardSection = document.createElement('div');
            leaderboardSection.className = 'leaderboard-section';
            leaderboardSection.innerHTML = '<h3>Leaderboard</h3>';
            
            // Append leaderboard section to game container
            const answerSection = document.querySelector('.answer-section');
            if (answerSection) {
                gameContainer.insertBefore(leaderboardSection, answerSection.nextSibling);
            } else {
                gameContainer.appendChild(leaderboardSection);
            }
        }
        
        // Create leaderboard table
        const tableHTML = `
            <table class="leaderboard-table">
                <thead>
                    <tr>
                        <th>Rank</th>
                        <th>Player</th>
                        <th>Score</th>
                        <th>Hints</th>
                    </tr>
                </thead>
                <tbody>
                    ${renderLeaderboardRows(leaderboard)}
                </tbody>
            </table>
        `;
        
        // Append or replace existing table
        const existingTable = leaderboardSection.querySelector('.leaderboard-table');
        if (existingTable) {
            existingTable.outerHTML = tableHTML;
        } else {
            leaderboardSection.innerHTML += tableHTML;
        }
    }
    
    // Function to render leaderboard rows
    function renderLeaderboardRows(leaderboard) {
        let rows = '';
        
        // Add top users
        if (leaderboard.top_users && leaderboard.top_users.length > 0) {
            leaderboard.top_users.forEach((user, index) => {
                const rank = index + 1;
                const isCurrentUser = user.user_id == gameContainer.dataset.userId;
                const rankClass = rank <= 3 ? `rank-${rank}` : '';
                
                rows += `
                    <tr${isCurrentUser ? ' class="my-score"' : ''}>
                        <td class="rank ${rankClass}">${rank}</td>
                        <td>${user.username}${isCurrentUser ? ' (You)' : ''}</td>
                        <td class="score">${user.score} points</td>
                        <td>${user.hints_used}</td>
                    </tr>
                `;
            });
        } else {
            rows += '<tr><td colspan="4" style="text-align:center;">No data available</td></tr>';
        }
        
        // Add user's row if not in top 10
        if (!leaderboard.user_in_top && leaderboard.user_data) {
            rows += `
                <tr class="my-score" style="border-top: 2px dashed #3f51b5;">
                    <td class="rank">${leaderboard.user_data.rank}</td>
                    <td>${leaderboard.user_data.username} (You)</td>
                    <td class="score">${leaderboard.user_data.score} points</td>
                    <td>${leaderboard.user_data.hints_used}</td>
                </tr>
            `;
        }
        
        return rows;
    }
});
