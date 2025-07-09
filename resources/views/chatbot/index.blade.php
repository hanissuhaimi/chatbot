@extends('layouts.app')

@section('title', 'Chatbot - FGV Prodata PROMPT System')

@section('content')
<div class="chatbot-container">
    <div class="chatbot-header">
        <h2>
            <span class="status-indicator"></span>
            <i class="fas fa-robot"></i> 
            PROMPT Assistant
        </h2>
        <p>I'm here to help you with your project management questions</p>
    </div>

    <div class="chat-messages" id="chatMessages">
        <!-- Messages will be populated here -->
    </div>

    <div class="typing-indicator" id="typingIndicator">
        <div class="typing-dots"></div>
    </div>

    <div class="chat-input">
        <div class="input-group">
            <input type="text" class="form-control" id="messageInput" placeholder="Type your message..." disabled>
            <button class="btn btn-primary" id="sendBtn" disabled>
                <i class="fas fa-paper-plane"></i>
            </button>
        </div>
        <div class="options-container" id="optionsContainer">
            <!-- Dynamic options will be populated here ..-->
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
class ChatBot {
    constructor() {
        this.chatMessages = document.getElementById('chatMessages');
        this.messageInput = document.getElementById('messageInput');
        this.sendBtn = document.getElementById('sendBtn');
        this.optionsContainer = document.getElementById('optionsContainer');
        this.typingIndicator = document.getElementById('typingIndicator');
        
        this.currentStep = 'greeting';
        this.selectedCategory = null;
        this.categories = [];
        
        this.init();
    }

    init() {
        this.setupEventListeners();
        this.startConversation();
        this.loadCategories();
    }

    setupEventListeners() {
        this.sendBtn.addEventListener('click', () => this.sendMessage());
        this.messageInput.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') {
                this.sendMessage();
            }
        });
    }

    async loadCategories() {
        try {
            const response = await axios.get('/chatbot/categories');
            this.categories = response.data.categories;
        } catch (error) {
            console.error('Error loading categories:', error);
        }
    }

    startConversation() {
        this.addMessage('bot', 'Hello! Welcome to FGV Prodata PROMPT System. I\'m your virtual assistant, ready to help you with project management queries.');
        
        setTimeout(() => {
            this.addMessage('bot', 'Do you need help with something today?');
            this.showOptions([
                { text: 'Yes, I need help', action: 'needHelp' },
                { text: 'No, thank you', action: 'noHelp' }
            ]);
        }, 1000);
    }

    addMessage(sender, message) {
        const messageDiv = document.createElement('div');
        messageDiv.className = `message ${sender}`;
        
        const messageContent = document.createElement('div');
        messageContent.className = 'message-content';
        messageContent.innerHTML = message;
        
        messageDiv.appendChild(messageContent);
        this.chatMessages.appendChild(messageDiv);
        
        this.scrollToBottom();
    }

    showTyping() {
        this.typingIndicator.style.display = 'block';
        this.scrollToBottom();
    }

    hideTyping() {
        this.typingIndicator.style.display = 'none';
    }

    showOptions(options) {
        this.optionsContainer.innerHTML = '';
        
        options.forEach(option => {
            const btn = document.createElement('button');
            btn.className = 'option-btn';
            btn.textContent = option.text;
            btn.onclick = () => this.handleOptionClick(option);
            this.optionsContainer.appendChild(btn);
        });
    }

    clearOptions() {
        this.optionsContainer.innerHTML = '';
    }

    handleOptionClick(option) {
        this.addMessage('user', option.text);
        this.clearOptions();
        
        this.showTyping();
        
        setTimeout(() => {
            this.hideTyping();
            this.handleAction(option.action, option.data);
        }, 1000);
    }

    handleAction(action, data) {
        switch (action) {
            case 'needHelp':
                this.showCategories();
                break;
                
            case 'noHelp':
                this.addMessage('bot', 'Thank you for visiting! If you need help later, just refresh the page to start a new conversation.');
                break;
                
            case 'selectCategory':
                this.selectedCategory = data;
                this.showQuestions(data);
                break;
                
            case 'askQuestion':
                this.handleQuestion(data);
                break;
                
            case 'backToCategories':
                this.showCategories();
                break;
                
            case 'newQuestion':
                this.enableFreeText();
                break;
        }
    }

    showCategories() {
        this.currentStep = 'categories';
        this.addMessage('bot', 'Great! Please select a category that best matches your question:');
        
        const categoryOptions = this.categories.map(category => ({
            text: category,
            action: 'selectCategory',
            data: category
        }));
        
        this.showOptions(categoryOptions);
    }

    async showQuestions(category) {
        this.currentStep = 'questions';
        this.addMessage('bot', `You selected "${category}". Here are some common questions in this category:`);
        
        try {
            const response = await axios.post('/chatbot/questions', { category: category });
            const questions = response.data.questions;
            
            const questionOptions = questions.map(question => ({
                text: question,
                action: 'askQuestion',
                data: { category: category, question: question }
            }));
            
            questionOptions.push(
                { text: '← Back to Categories', action: 'backToCategories' },
                { text: 'Ask a different question', action: 'newQuestion' }
            );
            
            this.showOptions(questionOptions);
            
        } catch (error) {
            console.error('Error loading questions:', error);
            this.addMessage('bot', 'Sorry, I encountered an error loading the questions. Please try again.');
        }
    }

    async handleQuestion(data) {
        this.addMessage('bot', 'Let me find the answer for you...');
        
        try {
            const response = await axios.post('/chatbot/answer', {
                category: data.category,
                question: data.question
            });
            
            const answer = response.data.answer;
            
            setTimeout(() => {
                this.addMessage('bot', answer);
                this.showFollowUpOptions();
            }, 1000);
            
        } catch (error) {
            console.error('Error getting answer:', error);
            this.addMessage('bot', 'Sorry, I couldn\'t find an answer to that question. Please try contacting IT support at support@fgvprodata.com.my.');
            this.showFollowUpOptions();
        }
    }

    showFollowUpOptions() {
        setTimeout(() => {
            this.addMessage('bot', 'Is there anything else I can help you with?');
            this.showOptions([
                { text: 'Ask another question', action: 'backToCategories' },
                { text: 'Search for something specific', action: 'newQuestion' },
                { text: 'No, I\'m done', action: 'noHelp' }
            ]);
        }, 1500);
    }

    enableFreeText() {
        this.currentStep = 'freeText';
        this.addMessage('bot', 'Please type your question and I\'ll search for the best answer:');
        this.messageInput.disabled = false;
        this.sendBtn.disabled = false;
        this.messageInput.focus();
    }

    sendMessage() {
        const message = this.messageInput.value.trim();
        if (!message) return;
        
        this.addMessage('user', message);
        this.messageInput.value = '';
        this.messageInput.disabled = true;
        this.sendBtn.disabled = true;
        
        this.searchAnswer(message);
    }

    async searchAnswer(query) {
        this.showTyping();
        
        try {
            const liveRes = await axios.post('/chatbot/live-answer', { query });
            const liveAnswer = liveRes.data.answer;

            if (!liveAnswer.includes("didn’t understand") && !liveAnswer.includes("couldn't find")) {
                this.hideTyping();
                this.addMessage('bot', liveAnswer);
                this.showFollowUpOptions();
                return;
            }
            const response = await axios.post('/chatbot/search', { query: query });
            const results = response.data.results;
            
            this.hideTyping();
            
            if (results.length > 0) {
                const bestMatch = results[0];
                this.addMessage('bot', `I found this information about "${bestMatch.question}":`);
                this.addMessage('bot', bestMatch.answer);
                
                if (results.length > 1) {
                    this.addMessage('bot', 'Here are some other related topics that might help:');
                    const relatedOptions = results.slice(1, 4).map(result => ({
                        text: result.question,
                        action: 'askQuestion',
                        data: { category: result.category, question: result.question }
                    }));
                    this.showOptions(relatedOptions);
                }
            } else {
                this.addMessage('bot', 'I couldn\'t find a specific answer to your question. Please contact IT support at support@fgvprodata.com.my or call 03-1234 5678 for direct assistance.');
            }
            
            this.showFollowUpOptions();
            
        } catch (error) {
            console.error('Error searching:', error);
            this.hideTyping();
            this.addMessage('bot', 'Sorry, I encountered an error while searching. Please try again or contact IT support.');
            this.showFollowUpOptions();
        }
    }

    scrollToBottom() {
        this.chatMessages.scrollTop = this.chatMessages.scrollHeight;
    }
}

// Initialize the chatbot when the page loads
document.addEventListener('DOMContentLoaded', () => {
    new ChatBot();
});
</script>
@endsection