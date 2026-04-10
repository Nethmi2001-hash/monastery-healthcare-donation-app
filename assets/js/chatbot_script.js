/**
 * AI Chatbot JavaScript
 * Handles chat interface and communication with OpenAI API
 */

function sendMessage() {
    const input = document.getElementById('userInput');
    const message = input.value.trim();
    
    if (message === '') return;
    
    // Add user message to chat
    addMessage(message, 'user');
    input.value = '';
    
    // Show typing indicator
    showTyping();
    
    // Send to API
    fetch('chatbot_api.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            message: message,
            language: document.getElementById('languageSelect').value,
            context: systemContext
        })
    })
    .then(response => response.json())
    .then(data => {
        hideTyping();
        if (data.success) {
            addMessage(data.response, 'bot');
        } else {
            addMessage('Sorry, I encountered an error. Please try again. / සමාවන්න, දෝෂයක් සිදු වී ඇත.', 'bot');
        }
    })
    .catch(error => {
        hideTyping();
        console.error('Error:', error);
        addMessage('Connection error. Please check your internet connection. / සම්බන්ධතා දෝෂයක්.', 'bot');
    });
}

function sendQuickQuestion(question) {
    document.getElementById('userInput').value = question;
    sendMessage();
}

function addMessage(content, type) {
    const chatBox = document.getElementById('chatBox');
    const messageDiv = document.createElement('div');
    messageDiv.className = `message ${type}`;
    
    const avatar = document.createElement('div');
    avatar.className = 'message-avatar';
    avatar.innerHTML = type === 'bot' ? '🪷' : '👤';
    
    const messageContent = document.createElement('div');
    messageContent.className = 'message-content';
    
    // Format message (convert markdown-style formatting)
    let formattedContent = content
        .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
        .replace(/\n/g, '<br>');
    
    messageContent.innerHTML = formattedContent;
    
    messageDiv.appendChild(avatar);
    messageDiv.appendChild(messageContent);
    chatBox.appendChild(messageDiv);
    
    // Scroll to bottom
    chatBox.scrollTop = chatBox.scrollHeight;
}

function showTyping() {
    document.getElementById('typingIndicator').classList.add('active');
}

function hideTyping() {
    document.getElementById('typingIndicator').classList.remove('active');
}

function handleKeyPress(event) {
    if (event.key === 'Enter') {
        sendMessage();
    }
}

// Auto-focus on input
document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('userInput').focus();
});
