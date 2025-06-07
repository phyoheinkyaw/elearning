$(document).ready(function () {
    // DOM Elements
    const $chatButton = $('#chat-button');
    const $chatWindow = $('#chat-window');
    const $closeBtn = $('#chat-close-btn');
    const $input = $('#chatbot-input');
    const $sendBtn = $('#chatbot-send-btn');
    const $chatbotForm = $('#chatbot-form');
    const $clearBtn = $('#chatbot-clear-btn');
    const $scrollBottomBtn = $('#chatbot-scroll-bottom');
    const $chatBadge = $('#chat-badge');
    const $welcomeBubble = $('#welcome-bubble');
    const $closeWelcome = $('#close-welcome');
    
    // State
    let aiGenerating = false;
    let isWindowVisible = false;
    let debugMode = false; // Disable debug mode in production
    
    // Initialize
    // console.log("Initializing floating chatbot...");
    $chatWindow.removeClass('active'); // Ensure it starts closed
    
    // Welcome message handling
    function handleWelcomeMessage() {
        // console.log("Handling welcome message");
        // Check if welcome message has been shown before
        const welcomeShown = localStorage.getItem('chatbot_welcome_shown');
        const lastShown = localStorage.getItem('chatbot_welcome_last_shown');
        const now = new Date().getTime();
        
        // Show welcome message if it hasn't been shown before
        // or if it's been more than 7 days since last shown
        if (!welcomeShown || (lastShown && now - parseInt(lastShown) > 7 * 24 * 60 * 60 * 1000)) {
            showWelcomeMessage();
        } else {
            hideWelcomeMessage();
        }
    }
    
    function showWelcomeMessage() {
        // console.log("Showing welcome message");
        $welcomeBubble.show();
        $chatBadge.show();
        
        // Auto-hide welcome message after 15 seconds
        setTimeout(function() {
            if ($welcomeBubble.is(':visible')) {
                $welcomeBubble.fadeOut(300);
            }
        }, 15000);
    }
    
    function hideWelcomeMessage() {
        // console.log("Hiding welcome message");
        $welcomeBubble.hide();
        $chatBadge.hide();
        
        // Save that welcome message was shown
        localStorage.setItem('chatbot_welcome_shown', 'true');
        localStorage.setItem('chatbot_welcome_last_shown', new Date().getTime().toString());
    }
    
    // Event listener for closing welcome message
    $closeWelcome.on('click', function(e) {
        // console.log("Welcome message close button clicked");
        e.stopPropagation();
        hideWelcomeMessage();
    });
    
    // Clicking on welcome bubble or chat button with badge opens chat
    $welcomeBubble.on('click', function() {
        // console.log("Welcome bubble clicked");
        hideWelcomeMessage();
        openChatWindow();
    });
    
    // Function to toggle chat window
    function toggleChatWindow() {
        // console.log("Toggle chat window, current state:", isWindowVisible);
        if (isWindowVisible) {
            closeChatWindow();
        } else {
            openChatWindow();
        }
    }
    
    // Function to open chat window with animation
    function openChatWindow() {
        // console.log("Opening chat window");
        isWindowVisible = true;
        $chatWindow.addClass('active');
        hideWelcomeMessage();
        
        // Focus the input when opening
        setTimeout(() => $input.focus(), 300);
        
        // Load messages only when first opened
        if ($('#chatbot-messages').children().length === 0) {
            loadMessages();
        } else {
            // Even if messages are already loaded, ensure we scroll to bottom when opening
            setTimeout(function() {
                scrollToBottom();
            }, 300);
        }
    }
    
    // Utility function to scroll to bottom
    function scrollToBottom() {
        // console.log("Scrolling to bottom");
        $('#chatbot-messages').scrollTop($('#chatbot-messages')[0].scrollHeight);
        showScrollBtnIfNeeded();
    }
    
    // Function to close chat window with animation
    function closeChatWindow() {
        // console.log("Closing chat window");
        isWindowVisible = false;
        $chatWindow.removeClass('active');
    }
    
    // Event listeners for opening/closing
    $chatButton.on('click', function() {
        // console.log("Chat button clicked");
        toggleChatWindow();
    });
    
    $closeBtn.on('click', function(e) {
        // console.log("Close button clicked");
        e.preventDefault();
        closeChatWindow();
    });
    
    // Update send button state
    function updateSendBtn() {
        if (aiGenerating) {
            $sendBtn.html('<i class="fas fa-spinner fa-spin"></i>');
        } else {
            $sendBtn.html('<i class="fas fa-paper-plane"></i>');
        }
        
        // Make sure $input and $input.val() are defined before calling trim()
        if ($input && $input.val() && $input.val().trim().length > 0 && !aiGenerating) {
            $sendBtn.prop('disabled', false);
        } else {
            $sendBtn.prop('disabled', true);
        }
    }
    
    // Character counter
    function updateCharCount() {
        const count = $input.val().length;
        const max = 1000;
        const $charCount = $('#chatbot-char-count');
        
        $charCount.text(`${count}/${max}`);
        
        if (count > max) {
            $charCount.addClass('text-danger');
            $input.val($input.val().substring(0, max));
        } else {
            $charCount.removeClass('text-danger');
        }
    }
    
    // Auto-resize textarea
    function autoResizeTextarea() {
        $input.css('height', 'auto');
        const newHeight = Math.min($input[0].scrollHeight, 100);
        $input.css('height', newHeight + 'px');
    }
    
    // Input handlers
    $input.on('input', function() {
        updateSendBtn();
        updateCharCount();
        autoResizeTextarea();
    });
    
    // Handle Enter key
    $input.on('keydown', function(e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            if (!$sendBtn.prop('disabled')) {
                e.preventDefault();
                $chatbotForm.submit();
            }
        }
    });
    
    // Load chat history
    function loadMessages() {
        // console.log("Loading messages");
        $.ajax({
            url: 'ajax/chatbot-handler.php',
            method: 'GET',
            dataType: 'json',
            success: function(res) {
                // console.log("Messages loaded", res);
                $('#chatbot-messages').empty();
                if (res && res.history && res.history.length > 0) {
                    res.history.forEach(function(msg) {
                        if (msg.sender === 1) {
                            appendAIMessageStatic(msg.message, 'ELearning AI', msg.created_at ? new Date(msg.created_at) : null);
                        } else {
                            appendMessage('user', msg.message, 'You', msg.created_at ? new Date(msg.created_at) : null);
                        }
                    });
                } else {
                    // If no messages, show a greeting
                    appendAIMessageStatic('Hello! How can I help you with your English learning today?', 'ELearning AI', new Date());
                }
                
                // Always scroll to bottom after loading messages
                setTimeout(function() {
                    scrollToBottom();
                }, 300);
            },
            error: function(xhr, status, error) {
                // console.error("Error loading messages:", error);
                $('#chatbot-messages').empty();
                appendAIMessageStatic('Hello! How can I help you with your English learning today?', 'ELearning AI', new Date());
                
                // Always scroll to bottom after error
                setTimeout(function() {
                    scrollToBottom();
                }, 300);
            }
        });
    }
    
    // Handle form submit
    $chatbotForm.on('submit', function(e) {
        e.preventDefault();
        
        if ($input.val().trim().length === 0 || aiGenerating) return;
        
        const message = $input.val().trim();
        const now = new Date();
        
        appendMessage('user', message, 'You', now);
        $input.val('');
        $input.css('height', 'auto'); // Reset height
        updateCharCount(); // Reset character count to 0/1000
        
        aiGenerating = true;
        updateSendBtn();
        showLoading();
        
        // Force scroll button to show when message is long enough to cause scrolling
        setTimeout(function() {
            showScrollBtnIfNeeded();
        }, 300);
        
        // Check if the message is asking for specific database information
        const courseInfoRegex = /how many courses|number of courses|total courses|course statistics|platform statistics|available courses/i;
        const courseDetailsRegex = /tell me about course|details of course|course details|what is course/i;
        const courseLevelRegex = /courses for level|level .* courses|.* level courses/i;
        // New regex for all course info
        const allCoursesRegex = /all courses|list all courses|tell me about all courses|show me all courses|what courses are available|what courses do you have|show all courses|access course info|access all course info|all course information/i;
        
        // New regex for specific course by ID
        const specificCourseRegex = /course\s+(\d+)|course\s+id\s+(\d+)|tell me about course\s+(\d+)|information about course\s+(\d+)|details for course\s+(\d+)/i;
        
        // New regex for resources
        const resourcesRegex = /resources|learning materials|study materials|practice materials|what resources|available resources/i;
        
        // First try to see if we need specific database information
        let needsDbInfo = false;
        let dbInfoQuery = "";
        
        // Check if the user is asking about a specific course by ID
        const specificCourseMatch = message.match(specificCourseRegex);
        if (specificCourseMatch) {
            // Extract the course ID from the matched groups
            const courseId = specificCourseMatch[1] || specificCourseMatch[2] || specificCourseMatch[3] || specificCourseMatch[4] || specificCourseMatch[5];
            if (courseId) {
                needsDbInfo = true;
                dbInfoQuery = "course_with_materials&course_id=" + courseId;
            }
        } else if (resourcesRegex.test(message)) {
            // If user is asking about resources
            needsDbInfo = true;
            dbInfoQuery = "resources";
        } else if (allCoursesRegex.test(message)) {
            // If user wants all courses info
            needsDbInfo = true;
            dbInfoQuery = "all_courses";
        } else if (courseInfoRegex.test(message)) {
            needsDbInfo = true;
            dbInfoQuery = "platform_stats";
        } else if (courseDetailsRegex.test(message)) {
            // For now, we'll just get a list of all courses
            needsDbInfo = true;
            dbInfoQuery = "all_courses";
        } else if (courseLevelRegex.test(message)) {
            // Extract the level from the message
            const levelMatch = message.match(/[A-C][1-2]/i);
            if (levelMatch) {
                needsDbInfo = true;
                const level = levelMatch[0].toUpperCase();
                dbInfoQuery = "courses_by_level&level=" + level;
            }
        }
        
        // If we need specific database information, fetch it first
        if (needsDbInfo) {
            $.ajax({
                url: 'ajax/chatbot-ai-data.php?action=' + dbInfoQuery,
                method: 'GET',
                dataType: 'json',
                success: function(dbRes) {
                    // Now send the original message along with the database information
                    sendToAI(message, dbRes);
                },
                error: function() {
                    // If the database request fails, just proceed with the normal message
                    sendToAI(message);
                }
            });
        } else {
            // Otherwise just send the message directly
            sendToAI(message);
        }
    });
    
    // Function to send the message to the AI with optional database info
    function sendToAI(message, dbInfo = null, retryCount = 0) {
        const maxRetries = 2; // Max number of retries on failure
        let ajaxData = { message: message };
        
        // If we have database info, add it to the request
        if (dbInfo) {
            ajaxData.db_info = JSON.stringify(dbInfo);
        }
        
        // Log request details for debugging
        // console.log("Sending request to chatbot-handler.php:", {
        //     method: 'POST',
        //     data: ajaxData,
        // });
        
        $.ajax({
            url: 'ajax/chatbot-handler.php',
            method: 'POST',
            data: ajaxData,
            dataType: 'json',
            timeout: 60000, // 60 second timeout to match PHP timeout
            crossDomain: false, // Ensure it's treated as same-domain
            headers: {
                'X-Requested-With': 'XMLHttpRequest' // Add standard AJAX header
            },
            success: function(res) {
                hideLoading();
                if (res && res.reply) {
                    appendAIMessageAnimated(res.reply, 'ELearning AI', res.created_at ? new Date(res.created_at) : new Date(), function() {
                        aiGenerating = false;
                        updateSendBtn();
                        showScrollBtnIfNeeded();
                    });
                } else if (res && res.error) {
                    // Handle specific error from server
                    appendAIMessageAnimated('Sorry, there was a problem: ' + res.error, 'ELearning AI', new Date(), function() {
                        aiGenerating = false;
                        updateSendBtn();
                        showScrollBtnIfNeeded();
                    });
                } else {
                    appendAIMessageAnimated('Sorry, there was a problem with the response format.', 'ELearning AI', new Date(), function() {
                        aiGenerating = false;
                        updateSendBtn();
                        showScrollBtnIfNeeded();
                    });
                }
            },
            error: function(xhr, status, error) {
                // console.error("Error sending message:", error, "Status:", status);
                // console.error("Response details:", {
                //     status: xhr.status,
                //     statusText: xhr.statusText,
                //     responseText: xhr.responseText,
                //     responseType: xhr.responseType,
                //     responseURL: xhr.responseURL,
                //     headers: xhr.getAllResponseHeaders()
                // });
                
                hideLoading();
                
                // If we haven't exceeded retries and it's a timeout or server error, try again
                if (retryCount < maxRetries && (status === 'timeout' || (xhr.status >= 500 && xhr.status < 600))) {
                    // console.log(`Retrying request (${retryCount + 1}/${maxRetries})...`);
                    
                    // Show retry message
                    appendAIMessageStatic(`The server is taking longer than expected to respond. Retrying... (${retryCount + 1}/${maxRetries})`, 'ELearning AI', new Date());
                    
                    // Wait 2 seconds before retry
                    setTimeout(function() {
                        sendToAI(message, dbInfo, retryCount + 1);
                    }, 2000);
                } else {
                    // No more retries or not a retryable error
                    let errorMessage = 'Sorry, there was a problem connecting to the server.';
                    
                    // If error message contains specific details about the API issue, display it
                    if (status === 'timeout') {
                        errorMessage = 'The server took too long to respond. Please try a shorter message or try again later.';
                    } else if (xhr.status === 0) {
                        errorMessage = 'Could not connect to the server. Please check your internet connection and try again.';
                    } else if (xhr.responseJSON && xhr.responseJSON.error) {
                        errorMessage = 'Error: ' + xhr.responseJSON.error;
                        if (xhr.responseJSON.message) {
                            errorMessage += ' - ' + xhr.responseJSON.message;
                        }
                        if (xhr.responseJSON.current_method) {
                            errorMessage += ' (Method: ' + xhr.responseJSON.current_method + ')';
                        }
                    } else if (debugMode && xhr.responseText) {
                        // In debug mode, try to show more detailed error information
                        try {
                            const response = JSON.parse(xhr.responseText);
                            if (response.error) {
                                errorMessage = 'API Error: ' + response.error;
                                if (response.message) {
                                    errorMessage += ' - ' + response.message;
                                }
                            }
                        } catch (e) {
                            // If not valid JSON, show error code
                            errorMessage = `Server Error (HTTP ${xhr.status}). Please try again later.`;
                        }
                    }
                    
                    appendAIMessageAnimated(errorMessage, 'ELearning AI', new Date(), function() {
                        aiGenerating = false;
                        updateSendBtn();
                        showScrollBtnIfNeeded();
                    });
                }
            }
        });
    }
    
    // Clear chat
    $clearBtn.on('click', function() {
        showConfirmDialog({
            title: 'Clear Conversation',
            message: 'Are you sure you want to clear this conversation? This cannot be undone.',
            confirmText: 'Yes, clear all',
            onConfirm: function() {
                $.ajax({
                    url: 'ajax/clear-chat.php',
                    method: 'POST',
                    dataType: 'json',
                    success: function(res) {
                        if (res.success) {
                            $('#chatbot-messages').empty();
                            // Add a fresh welcome message after clearing
                            appendAIMessageStatic('Conversation cleared. How can I help you now?', 'ELearning AI', new Date());
                            showSuccessToast('Conversation cleared');
                        } else {
                            showErrorToast('Failed to clear conversation');
                        }
                    },
                    error: function(xhr, status, error) {
                        // console.error("Error clearing chat:", error);
                        showErrorToast('Server error');
                    }
                });
            }
        });
    });
    
    // Check if at bottom of chat
    function isAtBottom() {
        const $messages = $('#chatbot-messages');
        const scrollHeight = $messages[0].scrollHeight;
        const scrollTop = $messages.scrollTop();
        const clientHeight = $messages.innerHeight();
        
        // console.log("Scroll position:", {
        //     scrollHeight: scrollHeight,
        //     scrollTop: scrollTop,
        //     clientHeight: clientHeight,
        //     isAtBottom: (scrollTop + clientHeight + 50) >= scrollHeight
        // });
        
        // Be more lenient with what's considered "at bottom"
        return (scrollTop + clientHeight + 50) >= scrollHeight;
    }
    
    // Show scroll button if needed
    function showScrollBtnIfNeeded() {
        if ($chatWindow.hasClass('active')) {
            const needsScrolling = !isAtBottom() && $('#chatbot-messages')[0].scrollHeight > $('#chatbot-messages').innerHeight();
            
            if (needsScrolling) {
                // console.log("Showing scroll button");
                $scrollBottomBtn.show();
            } else {
                // console.log("Hiding scroll button");
                $scrollBottomBtn.hide();
            }
        } else {
            $scrollBottomBtn.hide();
        }
    }
    
    // Monitor scrolling
    $('#chatbot-messages').on('scroll', showScrollBtnIfNeeded);
    
    // Scroll to bottom button click
    $scrollBottomBtn.on('click', function() {
        // console.log("Scroll button clicked");
        $('#chatbot-messages').animate({
            scrollTop: $('#chatbot-messages')[0].scrollHeight
        }, 300, function() {
            // Check again after animation completes
            showScrollBtnIfNeeded();
        });
    });
    
    // Append user message
    function appendMessage(sender, message, label, dateObj) {
        let timeString = '';
        if (dateObj) {
            timeString = formatDateTime(dateObj);
        }
        
        const msgDiv = $('<div></div>')
            .addClass('chatbot-message ' + sender)
            .html('<span>' + escapeHtml(message) + '</span>');
            
        const metaDiv = $('<div></div>')
            .addClass('chatbot-message-meta')
            .html('<span class="chatbot-message-label">' + label + '</span>' + 
                  (timeString ? ' <span class="chatbot-message-time">' + timeString + '</span>' : ''));
                  
        $('#chatbot-messages').append(msgDiv, metaDiv);
        
        // For user messages, always scroll to bottom
        if (sender === 'user') {
            scrollToBottom();
        } else if (isAtBottom()) {
            scrollToBottom();
        } else {
            showScrollBtnIfNeeded();
        }
    }
    
    // Animate AI message like ChatGPT (typing effect, with Markdown)
    function appendAIMessageAnimated(markdown, label, dateObj, onDone) {
        let timeString = dateObj ? formatDateTime(dateObj) : '';
        
        const msgDiv = $('<div></div>')
            .addClass('chatbot-message ai')
            .html('<span></span>');
            
        const metaDiv = $('<div></div>')
            .addClass('chatbot-message-meta')
            .html('<span class="chatbot-message-label">' + label + '</span>' + 
                 (timeString ? ' <span class="chatbot-message-time">' + timeString + '</span>' : ''));
                 
        $('#chatbot-messages').append(msgDiv, metaDiv);
        
        // Initial scroll to bottom
        scrollToBottom();
        
        // Typing effect with live markdown rendering
        let i = 0;
        let interval = 12; // ms per character
        let typing = setInterval(function() {
            if (i > markdown.length) {
                clearInterval(typing);
                // After animation, set full HTML with formatting
                msgDiv.find('span').html(marked.parse(markdown));
                // Add copy button
                let tempDiv = document.createElement('div');
                tempDiv.innerHTML = marked.parse(markdown);
                addCopyButton(msgDiv, tempDiv.innerText);
                
                // Final scroll to bottom when animation completes
                scrollToBottom();
                
                if (typeof onDone === 'function') onDone();
                return;
            }
            
            // Render markdown up to i chars
            let partial = markdown.substring(0, i);
            let html = marked.parse(partial + '<span class="chatbot-cursor">|</span>');
            msgDiv.find('span').html(html);
            i++;
            
            // Keep scrolling during animation if at bottom
            if (isAtBottom()) {
                $('#chatbot-messages').scrollTop($('#chatbot-messages')[0].scrollHeight);
            }
        }, interval);
    }
    
    // For loading old AI messages (no animation)
    function appendAIMessageStatic(markdown, label, dateObj) {
        let timeString = dateObj ? formatDateTime(dateObj) : '';
        
        const msgDiv = $('<div></div>')
            .addClass('chatbot-message ai')
            .html('<span>' + marked.parse(markdown) + '</span>');
            
        const metaDiv = $('<div></div>')
            .addClass('chatbot-message-meta')
            .html('<span class="chatbot-message-label">' + label + '</span>' + 
                 (timeString ? ' <span class="chatbot-message-time">' + timeString + '</span>' : ''));
                 
        $('#chatbot-messages').append(msgDiv, metaDiv);
        
        // Add copy button
        let tempDiv = document.createElement('div');
        tempDiv.innerHTML = marked.parse(markdown);
        addCopyButton(msgDiv, tempDiv.innerText);
        
        // Always scroll to bottom for static messages
        scrollToBottom();
    }
    
    // Add copy button to AI messages
    function addCopyButton($msgDiv, plainText) {
        if ($msgDiv.hasClass('ai')) {
            const $copyBtn = $('<button></button>')
                .addClass('chatbot-copy-btn')
                .html('<i class="fas fa-copy"></i>')
                .attr('title', 'Copy to clipboard')
                .on('click', function(e) {
                    e.stopPropagation();
                    navigator.clipboard.writeText(plainText).then(function() {
                        $(this).text('Copied!');
                        setTimeout(() => {
                            $(this).html('<i class="fas fa-copy"></i>');
                        }, 2000);
                    }.bind(this));
                });
            $msgDiv.append($copyBtn);
        }
    }
    
    // Show loading
    function showLoading() {
        const loadingDiv = $('<div></div>')
            .addClass('chatbot-message ai chatbot-loading')
            .attr('id', 'chatbot-loading')
            .html('<span><i class="fas fa-spinner fa-spin chatbot-spinner"></i> ELearning AI is thinking…</span>');
            
        const metaDiv = $('<div></div>')
            .addClass('chatbot-message-meta')
            .html('<span class="chatbot-message-label">ELearning AI</span>');
            
        $('#chatbot-messages').append(loadingDiv, metaDiv);
        scrollToBottom();
    }
    
    // Hide loading
    function hideLoading() {
        $('#chatbot-loading').next('.chatbot-message-meta').remove();
        $('#chatbot-loading').remove();
    }
    
    // Display confirmation modal
    function showConfirmDialog(opts) {
        // Remove existing dialog if any
        $('#chatbot-confirm-modal').remove();
        
        const html = `
        <div id="chatbot-confirm-modal" class="modal fade" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">${opts.title || 'Are you sure?'}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p>${opts.message || 'This action cannot be undone.'}</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-danger confirm-button">${opts.confirmText || 'Confirm'}</button>
                    </div>
                </div>
            </div>
        </div>`;
        
        $('body').append(html);
        
        const modal = new bootstrap.Modal(document.getElementById('chatbot-confirm-modal'));
        modal.show();
        
        $('.confirm-button').on('click', function() {
            if (typeof opts.onConfirm === 'function') {
                opts.onConfirm();
            }
            modal.hide();
        });
    }
    
    // Show a success toast
    function showSuccessToast(message) {
        const toastId = 'toast-' + Date.now();
        const html = `
        <div id="${toastId}" class="toast align-items-center text-white bg-success border-0" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="d-flex">
                <div class="toast-body">
                    <i class="fas fa-check-circle me-2"></i> ${message}
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
        </div>`;
        
        if (!$('#toast-container').length) {
            $('body').append('<div id="toast-container" class="toast-container position-fixed bottom-0 start-0 p-3"></div>');
        }
        
        $('#toast-container').append(html);
        const toastEl = document.getElementById(toastId);
        const toast = new bootstrap.Toast(toastEl, { delay: 3000 });
        toast.show();
        
        $(toastEl).on('hidden.bs.toast', function() {
            $(this).remove();
        });
    }
    
    // Show an error toast
    function showErrorToast(message) {
        const toastId = 'toast-' + Date.now();
        const html = `
        <div id="${toastId}" class="toast align-items-center text-white bg-danger border-0" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="d-flex">
                <div class="toast-body">
                    <i class="fas fa-exclamation-circle me-2"></i> ${message}
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
        </div>`;
        
        if (!$('#toast-container').length) {
            $('body').append('<div id="toast-container" class="toast-container position-fixed bottom-0 start-0 p-3"></div>');
        }
        
        $('#toast-container').append(html);
        const toastEl = document.getElementById(toastId);
        const toast = new bootstrap.Toast(toastEl, { delay: 5000 });
        toast.show();
        
        $(toastEl).on('hidden.bs.toast', function() {
            $(this).remove();
        });
    }
    
    // Helper: Escape HTML to prevent XSS
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.innerText = text;
        return div.innerHTML;
    }
    
    // Helper: Format date for message timestamp
    function formatDateTime(dateObj) {
        const now = new Date();
        const today = new Date(now.getFullYear(), now.getMonth(), now.getDate());
        const yesterday = new Date(today);
        yesterday.setDate(yesterday.getDate() - 1);
        
        // Format time
        const hours = dateObj.getHours().toString().padStart(2, '0');
        const minutes = dateObj.getMinutes().toString().padStart(2, '0');
        const timeString = `${hours}:${minutes}`;
        
        // Check if today/yesterday
        if (dateObj >= today) {
            return `Today, ${timeString}`;
        } else if (dateObj >= yesterday) {
            return `Yesterday, ${timeString}`;
        } else {
            // Format date
            const day = dateObj.getDate().toString().padStart(2, '0');
            const month = (dateObj.getMonth() + 1).toString().padStart(2, '0');
            const year = dateObj.getFullYear();
            return `${day}/${month}/${year}, ${timeString}`;
        }
    }
    
    // Init
    updateSendBtn();
    // Hide scroll button initially
    $scrollBottomBtn.hide();
    // Initialize welcome message
    handleWelcomeMessage();
    
    // Periodically check if scroll button should be shown (for dynamic content loading)
    setInterval(function() {
        if ($chatWindow.hasClass('active')) {
            showScrollBtnIfNeeded();
        }
    }, 1000);
    
    // Debug event listeners
    // console.log("Event listeners initialized:", {
    //     'Chat button exists': $chatButton.length > 0,
    //     'Close button exists': $closeBtn.length > 0,
    //     'Welcome bubble exists': $welcomeBubble.length > 0,
    //     'Close welcome exists': $closeWelcome.length > 0,
    //     'Scroll button exists': $scrollBottomBtn.length > 0
    // });
}); 