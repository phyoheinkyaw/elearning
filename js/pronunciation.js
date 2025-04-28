// Pronunciation Practice Logic
// Uses browser speech recognition and OpenRouter API for generation and feedback

// ========== CONFIG ==========
const PRACTICE_STORAGE_KEY = "pronunciation_practice_history";

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

// ========== API KEY HANDLING ==========
async function getApiKey() {
  const resp = await fetch("ajax/get-openrouter-key.php");
  if (!resp.ok) throw new Error("API key fetch failed");
  const data = await resp.json();
  return data.key;
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
        model: "google/learnlm-1.5-pro-experimental:free",
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
  // Use a Markdown parser if available, else fallback to simple formatting
  let html = "";
  if (window.marked) {
    html = marked.parse(feedback);
  } else {
    html = feedback.replace(/\n/g, "<br>");
  }
  feedbackBox.innerHTML = html;
}

// ========== AI FEEDBACK ==========
async function getFeedback(target, transcript) {
  const apiKey = await getApiKey();
  const prompt = `You are an English pronunciation coach. Compare the following target text and the user's spoken transcript. Give feedback on accuracy, missing or extra words, and suggestions for improvement. Do NOT comment on accent or voice quality.\n\nHighlight any words or phrases that require attention using **bold** for errors and *italic* for suggestions or improvements.\n\nReturn only the feedback, feedback text needs to be bold and use as title. Other feedback response start from new line.\n\nTarget: ${target}\nUser Transcript: ${transcript}`;
  const response = await fetch(
    "https://openrouter.ai/api/v1/chat/completions",
    {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        Authorization: `Bearer ${apiKey}`,
      },
      body: JSON.stringify({
        model: "google/learnlm-1.5-pro-experimental:free",
        messages: [{ role: "user", content: prompt }],
        max_tokens: 120,
        temperature: 0.5,
      }),
    }
  );
  const data = await response.json();
  const feedback =
    data.choices && data.choices[0]
      ? data.choices[0].message.content.trim()
      : "No feedback.";
  return feedback;
}

// ========== HISTORY STORAGE ==========
function saveToHistory(target, transcript, feedback) {
  let history = JSON.parse(localStorage.getItem(PRACTICE_STORAGE_KEY) || "[]");
  const type = practiceType.value;
  history.unshift({
    target,
    transcript,
    feedback,
    type,
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
    hideLoader(feedbackBox, "Failed to get feedback.");
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
