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
    </div>
</div>

<!-- Pass translations to JavaScript -->
<script>
    window.translations = @json($translations);
</script>
@endsection

@section('scripts')
<script>
class ChatBot {
    
    constructor() { 
        this.chatMessages = document.getElementById('chatMessages');
        this.messageInput = document.getElementById('messageInput');
        this.sendBtn = document.getElementById('sendBtn');
        this.typingIndicator = document.getElementById('typingIndicator');
        
        this.currentStep = 'greeting';
        this.selectedCategory = null;
        this.selectedSubcategory = null;
        this.categories = [];
        this.lang = this.getStoredLanguage() || 'en';
        
        // Get translations from server
        this.translations = window.translations || {};

        this.init();
    }

    getStoredLanguage() {
        return localStorage.getItem('chatbot_lang') || 'en';
    }

    init() {
        this.setupEventListeners();
        this.startConversation();
    }

    setupEventListeners() {
        this.sendBtn.addEventListener('click', () => this.sendMessage());
        this.messageInput.addEventListener('keypress', (e) => {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                this.sendMessage();
            }
        });
    }

    // Helper method to get translation for current language
    t(key, replacements = {}) {
        let text = this.translations[this.lang]?.[key] || this.translations['en']?.[key] || key;
        
        // Handle replacements like :category
        Object.keys(replacements).forEach(placeholder => {
            text = text.replace(`:${placeholder}`, replacements[placeholder]);
        });
        
        return text;
    }

    async loadCategories() {
        try {
            console.log('🔍 Loading categories for language:', this.lang);
            
            const response = await axios.get('/chatbot/categories', {
                params: { lang: this.lang }
            });
            
            console.log("✅ Categories API Response:", response.data);
            
            if (response.data && response.data.categories) {
                this.categories = response.data.categories;
                console.log("✅ Categories loaded:", this.categories.length);
                return true;
            } else {
                console.warn('⚠️ No categories in response');
                this.categories = [];
                return false;
            }
            
        } catch (error) {
            console.error('❌ Error loading categories:', error);
            this.categories = [];
            
            // Handle specific error cases
            if (error.response?.status === 500) {
                console.error('❌ Server error - check knowledge base file and Laravel logs');
            }
            
            return false;
        }
    }

    startConversation() {
        this.addMessage('bot', this.t('start_message') || 'Hello! Welcome to PROMPT Assistant.');

        setTimeout(() => {
            this.showOptions([
                { text: this.t('english') || 'English', action: 'setLanguage', data: 'en' },
                { text: this.t('malay') || 'Bahasa Malaysia', action: 'setLanguage', data: 'bm' }
            ]);
        }, 1000);
    }

    async setLanguageAndContinue(lang) {
        console.log('🚀 Setting language to:', lang);
        this.lang = lang;
        localStorage.setItem('chatbot_lang', lang);

        // Set language on server
        try {
            await axios.post('/chatbot/set-language', { lang: lang });
            console.log('✅ Language set on server');
        } catch (error) {
            console.error('❌ Error setting language on server:', error);
        }

        // Clear existing data
        this.categories = [];
        this.selectedCategory = null;
        this.selectedSubcategory = null;

        this.addMessage('bot', this.t('welcome_message') || 'Welcome! I\'m here to help you.');

        // Pre-load categories
        console.log('🚀 Pre-loading categories...');
        const categoriesLoaded = await this.loadCategories();
        
        if (!categoriesLoaded) {
            console.warn('⚠️ Failed to pre-load categories');
        }

        setTimeout(() => {
            this.addMessage('bot', this.t('need_help_prompt') || 'Do you need help with something?');
            this.showOptions([
                { text: this.t('yes_help') || 'Yes, I need help', action: 'needHelp' },
                { text: this.t('no_help') || 'No, thank you', action: 'noHelp' }
            ]);
        }, 1000);
    }

    addMessage(sender, message, isElement = false) {
        const messageDiv = document.createElement('div');
        messageDiv.className = `message ${sender}`;
        
        const messageContent = document.createElement('div');
        messageContent.className = 'message-content';

        if (isElement && message instanceof HTMLElement) {
            messageContent.appendChild(message);
        } else {
            messageContent.innerHTML = this.formatMessage(message);
        }

        messageDiv.appendChild(messageContent);
        this.chatMessages.appendChild(messageDiv);
        
        this.scrollToBottom();
    }

    formatMessage(message) {
        // Convert newlines to <br> tags and handle basic formatting
        return message.replace(/\n/g, '<br>');
    }

    showTyping() {
        this.typingIndicator.style.display = 'block';
        this.scrollToBottom();
    }

    hideTyping() {
        this.typingIndicator.style.display = 'none';
    }

    showOptions(options) {
        const messageDiv = document.createElement('div');
        messageDiv.className = 'message bot';

        const messageContent = document.createElement('div');
        messageContent.className = 'message-content';

        const buttonGroup = document.createElement('div');
        buttonGroup.className = 'button-group';

        options.forEach(option => {
            const btn = document.createElement('button');
            btn.className = 'option-btn';
            btn.textContent = option.text;
            btn.onclick = () => this.handleOptionClick(option);
            buttonGroup.appendChild(btn);
        });

        messageContent.appendChild(buttonGroup);
        messageDiv.appendChild(messageContent);
        this.chatMessages.appendChild(messageDiv);
        this.scrollToBottom();
    }

    handleOptionClick(option) {
        this.addMessage('user', option.text);
        
        this.showTyping();
        
        setTimeout(() => {
            this.hideTyping();
            this.handleAction(option.action, option.data);
        }, 800);
    }

    async handleAction(action, data) {
        console.log('🎯 Handling action:', action, 'with data:', data);
        
        try {
            switch (action) {
                case 'setLanguage':
                    await this.setLanguageAndContinue(data);
                    break;
                    
                case 'needHelp':
                    await this.handleNeedHelp();
                    break;
                    
                case 'noHelp':
                    this.addMessage('bot', this.t('thank_you') || 'Thank you! Feel free to ask if you need help later.');
                    break;
                    
                case 'selectCategory':
                    this.selectedCategory = data;
                    await this.showQuestions(data);
                    break;
                    
                case 'selectSubcategory':
                    this.selectedSubcategory = data.subcategory;
                    await this.showSubcategoryQuestions(data.category, data.subcategory);
                    break;
                    
                case 'askQuestion':
                    await this.handleQuestion(data);
                    break;
                    
                case 'showDirectQuestions':
                    this.showDirectQuestions(data.category, data.questions);
                    break;
                    
                case 'backToCategories':
                    this.selectedCategory = null;
                    this.selectedSubcategory = null;
                    this.showCategories();
                    break;

                case 'backToSubcategories':
                    this.selectedSubcategory = null;
                    await this.showQuestions(this.selectedCategory);
                    break;
                    
                case 'newQuestion':
                    this.enableFreeText();
                    break;
                    
                default:
                    console.error('❌ Unknown action:', action);
                    this.addMessage('bot', 'Something went wrong. Please try again.');
                    this.showFollowUpOptions();
            }
        } catch (error) {
            console.error('❌ Error handling action:', error);
            this.addMessage('bot', this.t('error_occurred') || 'An error occurred. Please try again.');
            this.showFollowUpOptions();
        }
    }

    async handleNeedHelp() {
        console.log('🎯 Processing needHelp - categories count:', this.categories.length);
        
        if (this.categories.length === 0) {
            this.addMessage('bot', this.t('loading_categories') || 'Loading available topics...');
            this.showTyping();
            
            const success = await this.loadCategories();
            this.hideTyping();
            
            if (success && this.categories.length > 0) {
                this.showCategories();
            } else {
                this.addMessage('bot', this.t('error_loading_categories') || 'Sorry, I cannot load the help topics right now. Please type your question directly.');
                this.enableFreeText();
            }
        } else {
            this.showCategories();
        }
    }

    showCategories() {
        this.currentStep = 'categories';
        this.addMessage('bot', this.t('select_category') || 'Please select a category:');
        
        const categoryOptions = this.categories.map(category => ({
            text: category,
            action: 'selectCategory',
            data: category
        }));
        
        categoryOptions.push({
            text: this.t('other_category') || 'Other / Ask Different Question',
            action: 'newQuestion'
        });
        
        this.showOptions(categoryOptions);
    }

    async showQuestions(category) {
        console.log('🔍 Loading questions for category:', category);
        
        this.currentStep = 'questions';
        this.selectedCategory = category;
        this.addMessage('bot', this.t('selected_category', { category: category }) || `You selected: ${category}`);
        
        this.showTyping();
        
        try {
            const response = await axios.post('/chatbot/questions', {
                category: category,
                lang: this.lang
            });
            
            this.hideTyping();
            
            console.log('✅ Questions response:', response.data);
            
            const questions = response.data.questions || [];
            const subcategories = response.data.subcategories || [];
            const hasSubcategories = response.data.has_subcategories || false;
            const hasDirectQuestions = response.data.has_direct_questions || false;
            
            console.log('Questions:', questions.length, 'Subcategories:', subcategories.length);
            
            if (hasSubcategories) {
                this.showSubcategorySelection(category, subcategories, questions, hasDirectQuestions);
            } else if (hasDirectQuestions || questions.length > 0) {
                this.showDirectQuestions(category, questions);
            } else {
                this.addMessage('bot', this.t('no_questions_found') || 'No questions found for this category.');
                this.showOptions([
                    { text: this.t('back_to_categories') || 'Back to Categories', action: 'backToCategories' }
                ]);
            }
            
        } catch (error) {
            console.error('❌ Error loading questions:', error);
            this.hideTyping();
            
            let errorMessage = this.t('error_loading_questions') || 'Sorry, I couldn\'t load the questions for this category.';
            
            if (error.response?.data?.error) {
                errorMessage += ' ' + error.response.data.error;
            }
            
            this.addMessage('bot', errorMessage);
            this.showOptions([
                { text: this.t('back_to_categories') || 'Back to Categories', action: 'backToCategories' }
            ]);
        }
    }

    showSubcategorySelection(category, subcategories, directQuestions, hasDirectQuestions) {
        const options = [];
        
        // Add subcategory options
        subcategories.forEach(subcategory => {
            options.push({
                text: subcategory,
                action: 'selectSubcategory',
                data: { category: category, subcategory: subcategory }
            });
        });
        
        // If there are direct questions, add option to view them
        if (hasDirectQuestions && directQuestions.length > 0) {
            options.push({
                text: this.t('general_questions') || `General ${category} Questions`,
                action: 'showDirectQuestions',
                data: { category: category, questions: directQuestions }
            });
        }
        
        // Add navigation options
        options.push({
            text: this.t('back_to_categories') || 'Back to Categories', 
            action: 'backToCategories'
        });
        
        this.addMessage('bot', this.t('select_subcategory') || 'Please select a subcategory:');
        this.showOptions(options);
    }

    showDirectQuestions(category, questions) {
        if (!questions || questions.length === 0) {
            this.addMessage('bot', this.t('no_questions_found') || `No questions found for ${category}.`);
            this.showOptions([
                { text: this.t('back_to_categories') || 'Back to Categories', action: 'backToCategories' }
            ]);
            return;
        }
        
        const questionOptions = questions.map(question => ({
            text: question,
            action: 'askQuestion',
            data: { 
                category: category, 
                question: question,
                subcategory: this.selectedSubcategory // Include current subcategory if any
            }
        }));
        
        // Add navigation options
        if (this.selectedSubcategory) {
            questionOptions.push({
                text: this.t('back_to_subcategories') || 'Back to Subcategories', 
                action: 'backToSubcategories'
            });
        }
        
        questionOptions.push({
            text: this.t('back_to_categories') || 'Back to Categories', 
            action: 'backToCategories'
        });
        
        this.showOptions(questionOptions);
    }

    async showSubcategoryQuestions(category, subcategory) {
        console.log('🔍 Loading subcategory questions:', { category, subcategory });
        
        this.addMessage('bot', this.t('selected_subcategory', { subcategory: subcategory }) || `You selected: ${subcategory}`);
        this.showTyping();
        
        try {
            const response = await axios.post('/chatbot/subcategory-questions', {
                category: category,
                subcategory: subcategory,
                lang: this.lang
            });
            
            this.hideTyping();
            
            const questions = response.data.questions || [];
            console.log('✅ Subcategory questions:', questions.length);
            
            if (questions.length === 0) {
                this.addMessage('bot', this.t('no_questions_in_subcategory') || `No questions found in ${subcategory}.`);
                this.showOptions([
                    { text: this.t('back_to_subcategories') || 'Back to Subcategories', action: 'backToSubcategories' },
                    { text: this.t('back_to_categories') || 'Back to Categories', action: 'backToCategories' }
                ]);
            } else {
                this.showDirectQuestions(category, questions);
            }
            
        } catch (error) {
            console.error('❌ Error loading subcategory questions:', error);
            this.hideTyping();
            
            this.addMessage('bot', this.t('error_loading_questions') || 'Sorry, I couldn\'t load the questions.');
            this.showOptions([
                { text: this.t('back_to_subcategories') || 'Back to Subcategories', action: 'backToSubcategories' },
                { text: this.t('back_to_categories') || 'Back to Categories', action: 'backToCategories' }
            ]);
        }
    }

    async handleQuestion(data) {
        console.log('🎯 Processing question:', data);
        
        this.addMessage('bot', this.t('searching_answer') || 'Let me find the answer for you...');
        this.showTyping();
        
        try {
            const requestPayload = {
                category: data.category,
                question: data.question,
                lang: this.lang
            };
            
            // Include subcategory if it exists
            if (data.subcategory) {
                requestPayload.subcategory = data.subcategory;
            }
            
            console.log('🎯 Request payload:', requestPayload);
            
            const response = await axios.post('/chatbot/answer', requestPayload);
            
            this.hideTyping();
            
            console.log('✅ Answer response:', response.data);
            
            if (response.data.success && response.data.answer && response.data.answer.trim()) {
                setTimeout(() => {
                    this.addMessage('bot', response.data.answer);
                    this.showFollowUpOptions();
                }, 500);
            } else {
                this.addMessage('bot', response.data.answer || this.t('no_answer_found') || 'Sorry, I couldn\'t find an answer to that question.');
                this.showFollowUpOptions();
            }
            
        } catch (error) {
            console.error('❌ Error getting answer:', error);
            this.hideTyping();
            
            let errorMessage = this.t('no_answer_found') || 'Sorry, I couldn\'t find an answer to that question.';
            
            if (error.response?.data?.answer) {
                errorMessage = error.response.data.answer;
            }
            
            this.addMessage('bot', errorMessage);
            this.showFollowUpOptions();
        }
    }

    showFollowUpOptions() {
        setTimeout(() => {
            this.addMessage('bot', this.t('more_help') || 'Can I help you with anything else?');
            this.showOptions([
                { text: this.t('back_to_categories') || 'Back to Categories', action: 'backToCategories' },
                { text: this.t('another_question') || 'Ask Another Question', action: 'newQuestion' },
                { text: this.t('done') || 'That\'s all, thanks!', action: 'noHelp' }
            ]);
        }, 1000);
    }

    enableFreeText() {
        this.currentStep = 'freeText';
        this.addMessage('bot', this.t('type_question') || 'Please type your question and I\'ll try to help you:');
        this.messageInput.disabled = false;
        this.sendBtn.disabled = false;
        this.messageInput.focus();
        this.messageInput.placeholder = this.t('type_here') || 'Type your question here...';
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
            // First try live answer (for project status queries)
            console.log('🔍 Trying live answer for:', query);
            const liveResponse = await axios.post('/chatbot/live-answer', {
                query: query,
                lang: this.lang
            });
            
            console.log('✅ Live answer response:', liveResponse.data);
            
            if (liveResponse.data.success) {
                this.hideTyping();
                this.addMessage('bot', liveResponse.data.answer);
                this.showFollowUpOptions();
                return;
            }
            
            // If no live answer, try regular search
            console.log('🔍 Trying regular search for:', query);
            const searchResponse = await axios.post('/chatbot/search', {
                query: query,
                lang: this.lang
            });
            
            const results = searchResponse.data.results || [];
            console.log('✅ Search results:', results.length);
            
            this.hideTyping();
            
            if (results.length > 0) {
                const bestMatch = results[0];
                this.addMessage('bot', this.t('info_found', { question: bestMatch.question }) || `I found this information:`);
                
                setTimeout(() => {
                    this.addMessage('bot', bestMatch.answer);
                    
                    // Show related questions if available
                    if (results.length > 1) {
                        setTimeout(() => {
                            this.addMessage('bot', this.t('other_topics') || 'Here are some related topics:');
                            const relatedOptions = results.slice(1, 4).map(result => ({
                                text: result.question,
                                action: 'askQuestion',
                                data: { 
                                    category: result.category, 
                                    question: result.question,
                                    subcategory: result.subcategory 
                                }
                            }));
                            this.showOptions(relatedOptions);
                            this.showFollowUpOptions();
                        }, 1000);
                    } else {
                        this.showFollowUpOptions();
                    }
                }, 500);
            } else {
                // No results found
                const noResultsMsg = liveResponse.data.answer || this.t('no_results') || 'Sorry, I couldn\'t find information about that. Please try rephrasing your question or select a category from the main menu.';
                this.addMessage('bot', noResultsMsg);
                this.showFollowUpOptions();
            }
            
        } catch (error) {
            console.error('❌ Error in search:', error);
            this.hideTyping();
            
            this.addMessage('bot', this.t('error_searching') || 'Sorry, there was an error processing your request. Please try again.');
            this.showFollowUpOptions();
        }
    }

    scrollToBottom() {
        this.chatMessages.scrollTop = this.chatMessages.scrollHeight;
    }
}

// Initialize the chatbot when the page loads
document.addEventListener('DOMContentLoaded', () => {
    try {
        new ChatBot();
    } catch (error) {
        console.error('❌ Error initializing chatbot:', error);
    }
});
</script>
@endsection