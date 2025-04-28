$(document).ready(function () {
    // Get the username from the data attribute
    const username = $('body').data('username') || 'You';

    // Disable send button if input is empty, or AI is generating
    const $input = $('#chatbot-input');
    const $sendBtn = $('#chatbot-send-btn');
    let aiGenerating = false;
    function updateSendBtn() {
        if (aiGenerating) {
            $sendBtn.html('<i class="fas fa-spinner fa-spin"></i>');
        } else {
            $sendBtn.text('Send');
        }
        if ($input.val().trim().length > 0 && !aiGenerating) {
            $sendBtn.prop('disabled', false);
        } else {
            $sendBtn.prop('disabled', true);
        }
    }
    $input.on('input', updateSendBtn);
    updateSendBtn();

    // Prevent form submit on empty
    $('#chatbot-form').on('submit', function (e) {
        if ($input.val().trim().length === 0 || aiGenerating) {
            e.preventDefault();
            $input.val('');
            updateSendBtn();
            return false;
        }
    });

    // Restore: Load all chat history at once on page load
    function loadMessages() {
        $.ajax({
            url: 'ajax/chatbot-handler.php',
            method: 'GET',
            dataType: 'json',
            success: function (res) {
                $('#chatbot-messages').empty();
                if (res && res.history) {
                    res.history.forEach(function (msg) {
                        if (msg.sender === 1) {
                            appendAIMessageStatic(msg.message, 'ELearning AI', msg.created_at ? new Date(msg.created_at) : null);
                        } else {
                            appendMessage('user', msg.message, 'You', msg.created_at ? new Date(msg.created_at) : null);
                        }
                    });
                }
            }
        });
    }

    // Load all on page load
    loadMessages();

    // Handle form submit
    $('#chatbot-form').on('submit', function (e) {
        e.preventDefault();
        if ($input.val().trim().length === 0 || aiGenerating) return;
        const message = $input.val().trim();
        const now = new Date();
        appendMessage('user', message, 'You', now);
        $input.val('');
        aiGenerating = true;
        updateSendBtn();
        showLoading();
        $.ajax({
            url: 'ajax/chatbot-handler.php',
            method: 'POST',
            data: { message: message },
            dataType: 'json',
            success: function (res) {
                hideLoading();
                if (res && res.reply) {
                    appendAIMessageAnimated(res.reply, 'ELearning AI', res.created_at ? new Date(res.created_at) : new Date(), function() {
                        aiGenerating = false;
                        updateSendBtn();
                    });
                } else {
                    appendAIMessageAnimated('Sorry, there was a problem.', 'ELearning AI', new Date(), function() {
                        aiGenerating = false;
                        updateSendBtn();
                    });
                }
            },
            error: function () {
                hideLoading();
                appendAIMessageAnimated('Sorry, there was a problem.', 'ELearning AI', new Date(), function() {
                    aiGenerating = false;
                    updateSendBtn();
                });
            }
        });
    });

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
            .html('<span class="chatbot-message-label">' + label + '</span>' + (timeString ? ' <span class="chatbot-message-time">' + timeString + '</span>' : ''));
        $('#chatbot-messages').append(msgDiv, metaDiv);
        $('#chatbot-messages').scrollTop($('#chatbot-messages')[0].scrollHeight);
    }

    // Animate AI message like ChatGPT (typing effect, with Markdown)
    function appendAIMessageAnimated(markdown, label, dateObj, onDone) {
        let timeString = dateObj ? formatDateTime(dateObj) : '';
        const msgDiv = $('<div></div>')
            .addClass('chatbot-message ai')
            .html('<span></span>');
        const metaDiv = $('<div></div>')
            .addClass('chatbot-message-meta')
            .html('<span class="chatbot-message-label">' + label + '</span>' + (timeString ? ' <span class="chatbot-message-time">' + timeString + '</span>' : ''));
        $('#chatbot-messages').append(msgDiv, metaDiv);
        $('#chatbot-messages').scrollTop($('#chatbot-messages')[0].scrollHeight);

        // Typing effect with live markdown rendering
        let i = 0;
        let interval = 12; // ms per character
        let typing = setInterval(function () {
            if (i > markdown.length) {
                clearInterval(typing);
                // After animation, set full HTML with formatting (for any missed formatting)
                msgDiv.find('span').html(marked.parse(markdown));
                // Add copy button after animation
                // Extract plain text from markdown
                let tempDiv = document.createElement('div');
                tempDiv.innerHTML = marked.parse(markdown);
                addCopyButton(msgDiv, tempDiv.innerText);
                if (typeof onDone === 'function') onDone();
                return;
            }
            // Render markdown up to i chars
            let partial = markdown.substring(0, i);
            let html = marked.parse(partial + '<span class="chatbot-cursor">|</span>');
            msgDiv.find('span').html(html);
            i++;
            $('#chatbot-messages').scrollTop($('#chatbot-messages')[0].scrollHeight);
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
            .html('<span class="chatbot-message-label">' + label + '</span>' + (timeString ? ' <span class="chatbot-message-time">' + timeString + '</span>' : ''));
        $('#chatbot-messages').append(msgDiv, metaDiv);
        // Add copy button
        let tempDiv = document.createElement('div');
        tempDiv.innerHTML = marked.parse(markdown);
        addCopyButton(msgDiv, tempDiv.innerText);
        $('#chatbot-messages').scrollTop($('#chatbot-messages')[0].scrollHeight);
    }

    function showLoading() {
        const loadingDiv = $('<div></div>')
            .addClass('chatbot-message ai chatbot-loading')
            .attr('id', 'chatbot-loading')
            .html('<span><i class="fas fa-spinner fa-spin chatbot-spinner"></i> ELearning AI is thinking…</span>');
        const metaDiv = $('<div></div>')
            .addClass('chatbot-message-meta')
            .html('<span class="chatbot-message-label">ELearning AI</span>');
        $('#chatbot-messages').append(loadingDiv, metaDiv);
        $('#chatbot-messages').scrollTop($('#chatbot-messages')[0].scrollHeight);
    }

    function hideLoading() {
        $('#chatbot-loading').next('.chatbot-message-meta').remove();
        $('#chatbot-loading').remove();
    }

    // --- Beautiful custom confirmation modal for Clear All ---
    function showConfirmDialog(opts) {
        // Remove existing dialog if any
        $('#chatbot-confirm-modal').remove();
        const html = `
        <div id="chatbot-confirm-modal" class="chatbot-modal-overlay">
            <div class="chatbot-modal">
                <div class="chatbot-modal-icon"><i class="fas fa-exclamation-triangle"></i></div>
                <div class="chatbot-modal-title">${opts.title || 'Are you sure?'}</div>
                <div class="chatbot-modal-msg">${opts.message || 'This action cannot be undone.'}</div>
                <div class="chatbot-modal-actions">
                    <button class="chatbot-modal-cancel">Cancel</button>
                    <button class="chatbot-modal-confirm">${opts.confirmText || 'Yes, clear all'}</button>
                </div>
            </div>
        </div>`;
        $('body').append(html);
        $('#chatbot-confirm-modal').fadeIn(120);
        $('#chatbot-confirm-modal .chatbot-modal-cancel').on('click', function() {
            $('#chatbot-confirm-modal').fadeOut(120, function(){ $(this).remove(); });
            if (opts.onCancel) opts.onCancel();
        });
        $('#chatbot-confirm-modal .chatbot-modal-confirm').on('click', function() {
            $('#chatbot-confirm-modal').fadeOut(120, function(){ $(this).remove(); });
            if (opts.onConfirm) opts.onConfirm();
        });
    }

    // --- Show success toast/alert for clear all ---
    function showSuccessToast(msg) {
        $('#chatbot-success-toast').remove();
        const toast = $('<div id="chatbot-success-toast" class="chatbot-success-toast"><i class="fas fa-check-circle"></i> ' + msg + '</div>');
        $('body').append(toast);
        toast.fadeIn(150);
        setTimeout(() => toast.fadeOut(300, function(){ $(this).remove(); }), 1700);
    }

    $('#chatbot-clear-btn').off('click').on('click', function () {
        showConfirmDialog({
            title: 'Clear All Messages',
            message: 'Are you sure you want to delete all your chat messages? This cannot be undone.',
            confirmText: 'Clear All',
            onConfirm: function() {
                $.ajax({
                    url: 'ajax/clear-chat.php',
                    method: 'POST',
                    dataType: 'json',
                    success: function (res) {
                        if (res && res.success) {
                            $('#chatbot-messages').empty();
                            showSuccessToast('All messages cleared!');
                        }
                    }
                });
            }
        });
    });

    // --- Copy Button for AI responses ---
    function addCopyButton($msgDiv, plainText) {
        const $copyBtn = $('<button class="copy-btn" title="Copy"><i class="fas fa-copy"></i></button>');
        $msgDiv.append($copyBtn);
        $copyBtn.on('click', function (e) {
            e.stopPropagation();
            navigator.clipboard.writeText(plainText).then(function () {
                $copyBtn.html('<i class="fas fa-check"></i>');
                setTimeout(() => $copyBtn.html('<i class="fas fa-copy"></i>'), 1200);
            });
        });
    }

    // --- Character count for textarea ---
    const MAX_CHARS = 1000;
    function updateCharCount() {
        const len = $input.val().length;
        $('#chatbot-char-count').text(len + '/' + MAX_CHARS);
        if (len > MAX_CHARS) {
            $('#chatbot-char-count').css('color', '#e74c3c');
            $sendBtn.prop('disabled', true);
        } else {
            $('#chatbot-char-count').css('color', '');
            updateSendBtn();
        }
    }
    $input.on('input', updateCharCount);
    updateCharCount();

    // Prevent over-limit submit
    $('#chatbot-form').on('submit', function (e) {
        if ($input.val().length > MAX_CHARS) {
            e.preventDefault();
            return false;
        }
    });

    // --- ChatGPT-like textarea auto-expand and keyboard shortcuts ---
    function autoResizeTextarea() {
        this.style.height = 'auto';
        this.style.height = (this.scrollHeight) + 'px';
    }
    $input.on('input', autoResizeTextarea);
    $input.each(function() { autoResizeTextarea.call(this); });

    $input.on('keydown', function(e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            if (!$sendBtn.prop('disabled')) {
                $('#chatbot-form').submit();
            }
        }
        // Shift+Enter inserts newline (default behavior)
    });

    // --- Floating Scroll-to-Bottom Button ---
    const $scrollBtn = $('#chatbot-scroll-bottom');

    function isAtBottom() {
        const threshold = 40; // px
        return $messages[0].scrollHeight - $messages[0].scrollTop - $messages[0].clientHeight < threshold;
    }

    function showScrollBtnIfNeeded() {
        if (!isAtBottom()) {
            $scrollBtn.fadeIn(200);
        } else {
            $scrollBtn.fadeOut(150);
        }
    }
    $messages.on('scroll', showScrollBtnIfNeeded);
    // Also check on new message append
    const oldAppend = $messages.append;
    $messages.append = function () {
        oldAppend.apply(this, arguments);
        showScrollBtnIfNeeded();
    };
    $scrollBtn.on('click', function () {
        $messages.animate({ scrollTop: $messages[0].scrollHeight }, 350);
        $input.focus();
    });
    // On load, scroll to bottom
    $messages.scrollTop($messages[0].scrollHeight);

    function escapeHtml(text) {
        return text
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/\"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }
    function formatDateTime(dateObj) {
        if (!(dateObj instanceof Date)) return '';
        const options = { hour: '2-digit', minute: '2-digit', hour12: true };
        const time = dateObj.toLocaleTimeString([], options);
        const day = dateObj.getDate().toString().padStart(2, '0');
        const month = dateObj.toLocaleString('en', { month: 'short' });
        const year = dateObj.getFullYear();
        return `${time} · ${day} ${month} ${year}`;
    }
});
