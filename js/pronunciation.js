/**
 * Pronunciation Practice Module
 * File: js/pronunciation.js
 * 
 * This module handles the pronunciation practice feature using browser speech recognition
 * and OpenRouter AI API for generating practice content and providing feedback.
 * 
 * Features:
 * - Speech recognition for capturing user's pronunciation
 * - AI-generated practice content (words, sentences, paragraphs)
 * - AI feedback on pronunciation accuracy
 * - History storage for tracking progress
 */

// Pronunciation Practice Logic
// Uses browser speech recognition and OpenRouter API for generation and feedback

// ========== CONFIG ==========
const BASE_STORAGE_KEY = "pronunciation_practice_history";
let PRACTICE_STORAGE_KEY = BASE_STORAGE_KEY;

// Get user ID from the page if available
const getUserId = () => {
  // Try to get user_id from a data attribute on body
  const userId = document.body.getAttribute('data-user-id') || 
                document.querySelector('meta[name="user-id"]')?.getAttribute('content') || 
                'guest';
  return userId;
};

// Update storage key with user ID
document.addEventListener('DOMContentLoaded', () => {
  const userId = getUserId();
  PRACTICE_STORAGE_KEY = `${BASE_STORAGE_KEY}_${userId}`;
  // Load history after setting the correct key
  renderHistory();
});

// ========== UI ELEMENTS ==========
const practiceType = document.getElementById("practiceType");
const userLevel = document.getElementById("userLevel");
const generateBtn = document.getElementById("generateBtn");
const regenerateBtn = document.getElementById("regenerateBtn");
const practiceSection = document.getElementById("practice-section");
const practiceTarget = document.getElementById("practiceTarget");
const startSpeechBtn = document.getElementById("startSpeechBtn");
const retryBtn = document.getElementById("retryBtn");
const transcriptBox = document.getElementById("transcriptBox");
const feedbackBtn = document.getElementById("feedbackBtn");
const feedbackBox = document.getElementById("feedbackBox");
const historyList = document.getElementById("historyList");

let currentTarget = "";
let currentTranscript = "";

// ========== UX ELEMENTS ==========
// Create loader spinner
function showLoader(target, message = "Loading...") {
  target.innerHTML = `<div class="d-flex align-items-center justify-content-center py-2">
        <span class="spinner-border spinner-border-sm text-primary me-2" role="status"></span>
        <span>${message}</span>
    </div>`;
}
function hideLoader(target, content = "") {
  target.innerHTML = content;
}
function showToast(msg, type = "info") {
  let toast = document.createElement("div");
  toast.className = `toast align-items-center text-bg-${type} border-0 show position-fixed bottom-0 end-0 m-3`;
  toast.style.zIndex = 9999;
  toast.innerHTML = `<div class="d-flex"><div class="toast-body">${msg}</div><button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button></div>`;
  document.body.appendChild(toast);
  setTimeout(() => {
    toast.remove();
  }, 3500);
}

// Show API key error modal for user to fix
function showApiKeyErrorModal() {
  // Only create modal if it doesn't exist yet
  if (!document.getElementById('apiKeyErrorModal')) {
    const modal = document.createElement('div');
    modal.className = 'modal fade';
    modal.id = 'apiKeyErrorModal';
    modal.tabIndex = '-1';
    modal.setAttribute('aria-labelledby', 'apiKeyErrorModalLabel');
    modal.setAttribute('aria-hidden', 'true');
    
    modal.innerHTML = `
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header bg-warning">
            <h5 class="modal-title" id="apiKeyErrorModalLabel">API Key Missing</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <p>The OpenRouter API key is missing or invalid. To fix this:</p>
            <ol>
              <li>Create a file named <code>.env</code> in the root directory of your website</li>
              <li>Add the following line to the file:<br>
              <code>OPENROUTER_API_KEY=your_api_key_here</code></li>
              <li>Replace <code>your_api_key_here</code> with your actual OpenRouter API key</li>
              <li>Save the file and refresh this page</li>
            </ol>
            <p>You can get an API key from <a href="https://openrouter.ai/keys" target="_blank">OpenRouter.ai</a></p>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
          </div>
        </div>
      </div>
    `;
    
    document.body.appendChild(modal);
  }
  
  // Show the modal
  const bsModal = new bootstrap.Modal(document.getElementById('apiKeyErrorModal'));
  bsModal.show();
}

// ========== API KEY HANDLING ==========
async function getApiKey() {
  try {
    console.log("Fetching API key");
    const resp = await fetch("ajax/get-openrouter-key.php");
    
    if (!resp.ok) {
      console.error("API key fetch failed with status:", resp.status);
      if (resp.status === 404 || resp.status === 500) {
        // Show modal to help user create the .env file
        showApiKeyErrorModal();
      }
      throw new Error("API key fetch failed: " + resp.status);
    }
    
    const data = await resp.json();
    
    if (!data || !data.key) {
      console.error("Invalid API key response format:", data);
      showApiKeyErrorModal();
      throw new Error("Invalid API key format");
    }
    
    console.log("API key retrieved successfully");
    return data.key;
  } catch (error) {
    console.error("Error fetching API key:", error);
    throw error;
  }
}

// ========== GENERATE PRACTICE CONTENT ==========
async function generatePracticeContent(type, level) {
  const apiKey = await getApiKey();
  let prompt = `Generate a single English ${type} suitable for a learner at CEFR level ${level}. The content must be related to English language learning. Only return the ${type}, no explanation. Must be unique when generate, no repeated.`;
  const response = await fetch(
    "https://openrouter.ai/api/v1/chat/completions",
    {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        Authorization: `Bearer ${apiKey}`,
      },
      body: JSON.stringify({
        model: "meta-llama/llama-4-scout:free",
        messages: [{ role: "user", content: prompt }],
        max_tokens: type === "word" ? 5 : type === "sentence" ? 20 : 60,
        temperature: 0.7,
      }),
    }
  );
  const data = await response.json();
  const content =
    data.choices && data.choices[0]
      ? data.choices[0].message.content.trim()
      : "";
  return content;
}

// ========== SPEECH RECOGNITION ==========
let recognition = null;
if ("webkitSpeechRecognition" in window) {
  recognition = new webkitSpeechRecognition();
} else if ("SpeechRecognition" in window) {
  recognition = new SpeechRecognition();
}
if (recognition) {
  recognition.lang = "en-US";
  recognition.interimResults = false;
}

function startSpeechRecognition() {
  if (!recognition) {
    showToast("Speech recognition is not supported in this browser.", "danger");
    return;
  }
  transcriptBox.textContent = "Listening...";
  transcriptBox.classList.add("border", "border-warning");
  recognition.start();
}
if (recognition) {
  recognition.onresult = function (event) {
    currentTranscript = event.results[0][0].transcript;
    transcriptBox.textContent = currentTranscript;
    transcriptBox.classList.remove("border-warning");
    transcriptBox.classList.add("border", "border-success");
    feedbackBtn.disabled = false;
    retryBtn.style.display = "inline-block";
    showToast("Speech captured!", "success");
  };
  recognition.onerror = function (event) {
    transcriptBox.textContent = "Error: " + event.error;
    transcriptBox.classList.remove("border-warning", "border-success");
    transcriptBox.classList.add("border", "border-danger");
    showToast("Speech recognition error.", "danger");
  };
  recognition.onend = function () {
    if (!currentTranscript) {
      transcriptBox.textContent = "No speech detected.";
      transcriptBox.classList.remove("border-warning", "border-success");
      transcriptBox.classList.add("border", "border-danger");
      showToast("No speech detected.", "warning");
    }
  };
}

// ========== FEEDBACK RENDERING ==========
function renderFeedback(feedback) {
  if (!feedback) {
    feedbackBox.innerHTML = '<div class="alert alert-warning">No feedback available.</div>';
    return;
  }
  
  try {
    // Use a Markdown parser if available, else fallback to simple formatting
    let html = "";
    if (window.marked && typeof window.marked.parse === "function") {
      html = marked.parse(feedback);
    } else {
      // Basic formatting for markdown-like syntax
      html = feedback
        .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>') // Bold
        .replace(/\*(.*?)\*/g, '<em>$1</em>')           // Italic
        .replace(/\n/g, '<br>');                        // Line breaks
    }
    
    feedbackBox.innerHTML = `<div class="p-3">${html}</div>`;
  } catch (error) {
    console.error("Error rendering feedback:", error);
    feedbackBox.innerHTML = `<div class="alert alert-warning">Error rendering feedback: ${error.message}</div>
                           <div class="p-3">${feedback}</div>`;
  }
}

// ========== AI FEEDBACK ==========
async function getFeedback(target, transcript) {
  try {
    const apiKey = await getApiKey();
    if (!apiKey) {
      console.error("Failed to get API key");
      return "No feedback available. API key not found.";
    }
    
    // Track current location for accurate referer header
    const currentLocation = window.location.origin;
    const currentPage = window.location.pathname;
    
    // Improved prompt with clearer formatting instructions
    const prompt = `You are an English pronunciation coach helping a language learner. 
    
Task: Compare the target text with the user's transcript and provide helpful feedback. use "you pronounced" instead of "your transcript".

Target text: "${target}"
User's transcript: "${transcript}"

Instructions:
1. Highlight any mispronounced, missing, or extra words
2. Use **bold** for errors and *italic* for suggestions
3. Be encouraging and constructive
4. Format your response with a clear "Feedback:" heading
5. Keep feedback concise and actionable
6. Focus only on pronunciation accuracy, not on accent or voice quality
7. Add relavant emojis to make the feedback more engaging

Example format:
**Feedback:**
[Your specific feedback about accuracy]
[Point out specific words with issues]
[End with an encouraging note]`;
    
    console.log("Sending feedback request to OpenRouter API");
    const response = await fetch(
      "https://openrouter.ai/api/v1/chat/completions",
      {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          "Authorization": `Bearer ${apiKey}`,
          "HTTP-Referer": currentLocation+currentPage,
          "X-Title": "ELearning Pronunciation Practice"
        },
        body: JSON.stringify({
          model: "meta-llama/llama-4-scout:free",
          messages: [{ role: "user", content: prompt }],
          max_tokens: 300,
          temperature: 0.5,
        }),
      }
    );
    
    if (!response.ok) {
      const errorData = await response.text();
      console.error("API response not OK:", response.status, errorData);
      return `API Error: ${response.status}. Please try again later.`;
    }
    
    const data = await response.json();
    console.log("API response received:", data);
    
    if (!data || !data.choices || !data.choices[0]) {
      console.error("Invalid API response format:", data);
      return "No feedback available. Invalid response from API.";
    }
    
    const feedback = data.choices[0].message.content.trim();
    return feedback || "No specific feedback provided. Your pronunciation was likely good!";
  } catch (error) {
    console.error("Error getting feedback:", error);
    return `Error: ${error.message}. Please try again.`;
  }
}

// ========== HISTORY STORAGE ==========
function saveToHistory(target, transcript, feedback) {
  const userId = getUserId();
  let history = JSON.parse(localStorage.getItem(PRACTICE_STORAGE_KEY) || "[]");
  const type = practiceType.value;
  history.unshift({
    target,
    transcript,
    feedback,
    type,
    userId,
    time: new Date().toISOString(),
  });
  if (history.length > 30) history = history.slice(0, 30);
  localStorage.setItem(PRACTICE_STORAGE_KEY, JSON.stringify(history));
  renderHistory();
}
function renderHistory() {
  let history = JSON.parse(localStorage.getItem(PRACTICE_STORAGE_KEY) || "[]");
  historyList.innerHTML = "";
  const categories = { word: [], sentence: [], paragraph: [] };
  history.forEach((item) => {
    if (categories[item.type]) categories[item.type].push(item);
  });
  Object.keys(categories).forEach((type) => {
    if (categories[type].length) {
      const header = document.createElement("div");
      header.className = "fw-bold text-primary mt-3 mb-2";
      header.textContent =
        type.charAt(0).toUpperCase() + type.slice(1) + " Practice";
      historyList.appendChild(header);

      // Create a row for cards
      const row = document.createElement("div");
      row.className = "row g-3";
      categories[type].forEach((item) => {
        const col = document.createElement("div");
        col.className = "col-12 col-md-6";
        const card = document.createElement("div");
        card.className = "card h-100 shadow-sm";
        const cardBody = document.createElement("div");
        cardBody.className = "card-body";
        cardBody.innerHTML = `
                    <div class="mb-2"><span class="fw-semibold text-secondary">Target:</span> ${
                      item.target
                    }</div>
                    <div class="mb-2"><span class="fw-semibold text-secondary">Your Speech:</span> ${
                      item.transcript
                    }</div>
                    <div class="mb-2">${
                      window.marked && typeof window.marked.parse === "function"
                        ? window.marked.parse(item.feedback)
                        : item.feedback.replace(/\n/g, "<br>")
                    }</div>
                    <div class="text-end text-muted small">${new Date(
                      item.time
                    ).toLocaleString()}</div>
                `;
        card.appendChild(cardBody);
        col.appendChild(card);
        row.appendChild(col);
      });
      historyList.appendChild(row);
    }
  });
}

// ========== UI EVENTS ==========
generateBtn.addEventListener("click", async function () {
  generateBtn.disabled = true;
  showLoader(practiceTarget, "Generating...");
  practiceSection.style.display = "block";
  try {
    const content = await generatePracticeContent(
      practiceType.value,
      userLevel.value
    );
    currentTarget = content;
    hideLoader(practiceTarget, content);
    transcriptBox.textContent = "Transcript will appear here...";
    transcriptBox.classList.remove(
      "border-warning",
      "border-success",
      "border-danger",
      "border"
    );
    currentTranscript = "";
    feedbackBox.style.display = "none";
    feedbackBtn.disabled = true;
    retryBtn.style.display = "none";
    showToast("Practice generated!", "success");
  } catch (e) {
    hideLoader(practiceTarget, "Failed to generate. Try again.");
    showToast("Failed to generate practice.", "danger");
  }
  generateBtn.disabled = false;
});

regenerateBtn.addEventListener("click", function () {
  generateBtn.click();
});

startSpeechBtn.addEventListener("click", function () {
  startSpeechRecognition();
  feedbackBox.style.display = "none";
  feedbackBtn.disabled = true;
  currentTranscript = "";
});

retryBtn.addEventListener("click", function () {
  startSpeechBtn.click();
});

feedbackBtn.addEventListener("click", async function () {
  feedbackBtn.disabled = true;
  showLoader(feedbackBox, "Getting feedback...");
  feedbackBox.style.display = "block";
  try {
    const feedback = await getFeedback(currentTarget, currentTranscript);
    renderFeedback(feedback);
    saveToHistory(currentTarget, currentTranscript, feedback);
    showToast("Feedback received!", "success");
  } catch (e) {
    console.error("Feedback error:", e);
    hideLoader(feedbackBox, `Failed to get feedback: ${e.message}`);
    showToast("Failed to get feedback.", "danger");
  }
  feedbackBtn.disabled = false;
});

renderHistory();

// ========== MARKDOWN SUPPORT ==========
// Load marked.js dynamically if not present
if (typeof window.marked === "undefined") {
  const script = document.createElement("script");
  script.src = "https://cdn.jsdelivr.net/npm/marked/marked.min.js";
  document.head.appendChild(script);
}
