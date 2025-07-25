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
        <!--<p>I'm here to help you with your project management questions</p>-->
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
        this.optionsContainer = document.getElementById('optionsContainer');
        this.typingIndicator = document.getElementById('typingIndicator');
        
        this.currentStep = 'greeting';
        this.selectedCategory = null;
        this.categories = [];
        this.lang = 'en'; // default
        
        // Get translations from server
        this.translations = window.translations || {};

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

    // Helper method to get translation for current language
    t(key, replacements = {}) {
        console.log('Looking for translation key:', key, 'in language:', this.lang);
        
        let text = this.translations[this.lang]?.[key] || this.translations['en']?.[key] || key;
        
        console.log('Found translation:', text);
        
        // Handle replacements like :category
        Object.keys(replacements).forEach(placeholder => {
            text = text.replace(`:${placeholder}`, replacements[placeholder]);
        });
        
        return text;
    }

    async loadCategories() {
    try {
        console.log('🔍 Loading categories for language:', this.lang);
        console.log('🔍 Making GET request to: /chatbot/categories');
        console.log('🔍 Request params:', { lang: this.lang });
        
        // Since your route is GET, use query parameters instead of request body
        const response = await axios.get('/chatbot/categories', {
            params: { lang: this.lang }  // This sends as ?lang=en or ?lang=bm
        });
        
        console.log("✅ API Response received:", response);
        console.log("✅ Response status:", response.status);
        console.log("✅ Response data:", response.data);
        
        if (response.data && response.data.categories) {
            this.categories = response.data.categories;
            console.log("✅ Categories successfully loaded:", this.categories);
            console.log("✅ Number of categories:", this.categories.length);
        } else {
            console.warn('⚠️ No categories in response:', response.data);
            this.categories = [];
        }
        
    } catch (error) {
        console.error('❌ Error loading categories:', error);
        
        if (error.response) {
            console.error('❌ Response status:', error.response.status);
            console.error('❌ Response headers:', error.response.headers);
            console.error('❌ Response data:', error.response.data);
            
            if (error.response.status === 404) {
                console.error('❌ Route not found - check if /chatbot/categories route exists');
            } else if (error.response.status === 500) {
                console.error('❌ Server error - check Laravel logs');
            }
        } else if (error.request) {
            console.error('❌ Request was made but no response received:', error.request);
        } else {
            console.error('❌ Error setting up request:', error.message);
        }
        
        this.categories = [];
        throw error;
    }
}

    startConversation() {
        this.addMessage('bot', this.t('start_message'));

        setTimeout(() => {
            this.showOptions([
                { text: this.t('english'), action: 'setLanguage', data: 'en' },
                { text: this.t('malay'), action: 'setLanguage', data: 'bm' }
            ]);
        }, 1000);
    }

    async setLanguageAndContinue(lang) {
        console.log('🚀 Step 1: Setting language to:', lang);
        this.lang = lang;
        localStorage.setItem('chatbot_lang', lang);

        console.log('🚀 Step 2: Setting language on server...');
        try {
            const response = await axios.post('/chatbot/set-language', { lang: lang });
            console.log('✅ Step 2: Language set on server successfully:', response.data);
        } catch (error) {
            console.error('❌ Step 2: Error setting language on server:', error);
            console.log('🔄 Step 2: Continuing anyway...');
        }

        console.log('🚀 Step 3: Clearing existing categories');
        this.categories = [];
        
        console.log('🚀 Step 4: Checking translations');
        console.log('Available translations:', this.translations);
        console.log('Current language translations:', this.translations[this.lang]);

        console.log('🚀 Step 5: Showing welcome message');
        this.addMessage('bot', this.t('welcome_message'));

        console.log('🚀 Step 6: Pre-loading categories...');
        try {
            await this.loadCategories();
            console.log('✅ Step 6: Categories pre-loaded successfully');
        } catch (error) {
            console.error('❌ Step 6: Failed to pre-load categories:', error);
            console.log('🔄 Step 6: Will try loading categories when needed');
        }

        console.log('🚀 Step 7: Setting up help prompt...');
        setTimeout(() => {
            console.log('🚀 Step 7: Showing help prompt now');
            this.addMessage('bot', this.t('need_help_prompt'));
            this.showOptions([
                { text: this.t('yes_help'), action: 'needHelp' },
                { text: this.t('no_help'), action: 'noHelp' }
            ]);
            console.log('✅ Step 7: Help prompt setup complete');
        }, 1000);
        
        console.log('✅ setLanguageAndContinue completed');
    }

    addMessage(sender, message, isElement = false) {
        const messageDiv = document.createElement('div');
        messageDiv.className = `message ${sender}`;
        
        const messageContent = document.createElement('div');
        messageContent.className = 'message-content';

        if (isElement && message instanceof HTMLElement) {
            messageContent.appendChild(message);
        } else {
            messageContent.innerHTML = message;
        }

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
        }, 1000);
    }

    handleAction(action, data) {
        console.log('🎯 Handling action:', action, 'with data:', data);
        console.log('🎯 Current categories count:', this.categories.length);
        console.log('🎯 Categories:', this.categories);
        
        switch (action) {
            case 'setLanguage':
                console.log('🎯 Processing setLanguage action with:', data);
                this.setLanguageAndContinue(data);
                break;
                
            case 'needHelp':
                console.log('🎯 Processing needHelp action');
                if (this.categories.length === 0) {
                    console.log('🎯 No categories loaded, loading now...');
                    this.addMessage('bot', this.t('loading_categories') || 'Loading available topics...');
                    this.showTyping();
                    
                    this.loadCategories().then(() => {
                        console.log('✅ Categories loaded in needHelp, hiding typing');
                        this.hideTyping();
                        if (this.categories.length > 0) {
                            console.log('✅ Showing categories:', this.categories);
                            this.showCategories();
                        } else {
                            console.log('⚠️ Still no categories, enabling free text');
                            this.addMessage('bot', this.t('no_categories') || 'No help topics available. Please type your question directly.');
                            this.enableFreeText();
                        }
                    }).catch((error) => {
                        console.error('❌ Failed to load categories in needHelp:', error);
                        this.hideTyping();
                        this.addMessage('bot', this.t('error_loading_categories') || 'Sorry, I cannot load the help topics right now. Please type your question directly.');
                        this.enableFreeText();
                    });
                } else {
                    console.log('✅ Categories already loaded, showing them');
                    this.showCategories();
                }
                break;
                
            case 'noHelp':
                console.log('🎯 Processing noHelp action');
                this.addMessage('bot', this.t('thank_you'));
                break;
                
            case 'selectCategory':
                console.log('🎯 Processing selectCategory action with:', data);
                this.selectedCategory = data;
                this.showQuestions(data);
                break;
                
            case 'askQuestion':
                console.log('🎯 Processing askQuestion action with:', data);
                this.handleQuestion(data);
                break;
                
            case 'backToCategories':
                console.log('🎯 Processing backToCategories action');
                this.showCategories();
                break;

            case 'selectSubcategory':
                console.log('🎯 Processing selectSubcategory action with:', data);
                this.showSubcategoryQuestions(data.category, data.subcategory);
                break;

            case 'backToSubcategories':
                console.log('🎯 Processing backToSubcategories action');
                this.showQuestions(this.selectedCategory);
                break;
            
            case 'showDirectQuestions':
                console.log('🎯 Processing showDirectQuestions action with:', data);
                this.showDirectQuestions(data.category, data.questions);
                break;
                
            case 'newQuestion':
                console.log('🎯 Processing newQuestion action');
                this.enableFreeText();
                break;
                
            default:
                console.error('❌ Unknown action:', action);
                this.addMessage('bot', 'Something went wrong. Please try again.');
                this.showFollowUpOptions();
        }
    }

    showCategories() {
        this.currentStep = 'categories';
        this.addMessage('bot', this.t('select_category'));
        
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
        console.log('🔍 showQuestions called with category:', category);
        console.log('🔍 Current language:', this.lang);
        
        this.currentStep = 'questions';
        this.selectedCategory = category; // Store selected category
        this.addMessage('bot', this.t('selected_category', { category: category }));
        
        // Show typing indicator while loading
        this.showTyping();
        
        try {
            console.log('🔍 Making request to /chatbot/questions');
            console.log('🔍 Request payload:', { category: category, lang: this.lang });
            
            const response = await axios.post('/chatbot/questions', {
                category: category,
                lang: this.lang
            });
            
            console.log('✅ Questions API response:', response);
            console.log('✅ Response data:', response.data);
            
            // Hide typing indicator
            this.hideTyping();
            
            if (!response.data) {
                console.error('❌ No data in response');
                throw new Error('No data received from server');
            }
            
            const questions = response.data.questions || [];
            const subcategories = response.data.subcategories || [];
            
            console.log('✅ Questions received:', questions);
            console.log('✅ Subcategories received:', subcategories);
            
            // Check if we have subcategories
            if (subcategories.length > 0) {
                console.log('✅ Category has subcategories, showing subcategory selection');
                this.showSubcategorySelection(category, subcategories, questions);
            } else {
                console.log('✅ Category has no subcategories, showing direct questions');
                this.showDirectQuestions(category, questions);
            }
            
        } catch (error) {
            console.error('❌ Error in showQuestions:', error);
            this.hideTyping();
            this.addMessage('bot', this.t('error_loading_questions') || 'Sorry, I couldn\'t load the questions for this category.');
            this.showOptions([
                { text: this.t('back_to_categories'), action: 'backToCategories' },
            ]);
        }
    }

    showSubcategorySelection(category, subcategories, directQuestions) {
        const options = [];
        
        // Add subcategory options
        subcategories.forEach(subcategory => {
            options.push({
                text: subcategory,
                action: 'selectSubcategory',
                data: { category: category, subcategory: subcategory }
            });
        });
        
        // If there are direct questions (not in subcategories), add option to view them
        if (directQuestions.length > 0) {
            options.push({
                text: this.t('general_questions') || `General ${category} Questions`,
                action: 'showDirectQuestions',
                data: { category: category, questions: directQuestions }
            });
        }
        
        // Add navigation options
        options.push(
            { text: this.t('back_to_categories'), action: 'backToCategories' },
        );
        
        this.addMessage('bot', this.t('select_subcategory') || 'Please select a subcategory:');
        this.showOptions(options);
    }

    showDirectQuestions(category, questions) {
        if (questions.length === 0) {
            console.warn('⚠️ No questions found for category:', category);
            this.addMessage('bot', this.t('no_questions_found') || `No questions found for ${category}.`);
            this.showOptions([
                { text: this.t('back_to_categories'), action: 'backToCategories' },
            ]);
            return;
        }
        
        const questionOptions = questions.map(question => ({
            text: question,
            action: 'askQuestion',
            data: { category: category, question: question }
        }));
        
        questionOptions.push(
            { text: this.t('back_to_categories'), action: 'backToCategories' },
        );
        
        this.showOptions(questionOptions);
    }

    async showSubcategoryQuestions(category, subcategory) {
        console.log('🔍 showSubcategoryQuestions called:', { category, subcategory });
        
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
            console.log('✅ Subcategory questions:', questions);
            
            if (questions.length === 0) {
                this.addMessage('bot', this.t('no_questions_in_subcategory') || `No questions found in ${subcategory}.`);
            } else {
                const questionOptions = questions.map(question => ({
                    text: question,
                    action: 'askQuestion',
                    data: { category: category, subcategory: subcategory, question: question }
                }));
                
                questionOptions.push(
                    { text: this.t('back_to_subcategories') || 'Back to Subcategories', action: 'backToSubcategories' },
                    { text: this.t('back_to_categories'), action: 'backToCategories' },
                );
                
                this.showOptions(questionOptions);
            }
            
        } catch (error) {
            console.error('❌ Error loading subcategory questions:', error);
            this.hideTyping();
            this.addMessage('bot', this.t('error_loading_questions') || 'Sorry, I couldn\'t load the questions.');
            this.showOptions([
                { text: this.t('back_to_subcategories') || 'Back to Subcategories', action: 'backToSubcategories' },
                { text: this.t('back_to_categories'), action: 'backToCategories' }
            ]);
        }
    }

    async handleQuestion(data) {
        console.log('🎯 === DEBUG handleQuestion ===');
        console.log('🎯 Question data:', data);
        console.log('🎯 Current language:', this.lang);
        
        this.addMessage('bot', this.t('searching_answer'));
        
        try {
            // Prepare the request payload
            const requestPayload = {
                category: data.category,
                question: data.question,
                lang: this.lang
            };
            
            // Add subcategory if it exists
            if (data.subcategory) {
                requestPayload.subcategory = data.subcategory;
                console.log('🎯 Including subcategory:', data.subcategory);
            }
            
            console.log('🎯 Request payload:', requestPayload);
            
            const response = await axios.post('/chatbot/answer', requestPayload);
            
            console.log('✅ Answer response:', response);
            console.log('✅ Response data:', response.data);
            
            const answer = response.data.answer;
            
            if (!answer || answer.trim() === '') {
                console.warn('⚠️ Empty answer received');
                throw new Error('Empty answer received');
            }
            
            setTimeout(() => {
                this.addMessage('bot', answer);
                this.showFollowUpOptions();
            }, 1000);
            
        } catch (error) {
            console.error('❌ Error getting answer:', error);
            
            if (error.response) {
                console.error('❌ Response status:', error.response.status);
                console.error('❌ Response data:', error.response.data);
            }
            
            this.addMessage('bot', this.t('no_answer_found'));
            this.showFollowUpOptions();
        }
    }

    showFollowUpOptions() {
        setTimeout(() => {
            this.addMessage('bot', this.t('more_help'));
            this.showOptions([
                { text: this.t('back_to_categories'), action: 'backToCategories' },
                { text: this.t('another_question'), action: 'newQuestion' },
                { text: this.t('done'), action: 'noHelp' }
            ]);
        }, 1500);
    }

    enableFreeText() {
        this.currentStep = 'freeText';
        this.addMessage('bot', this.t('type_question'));
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
            const liveRes = await axios.post('/chatbot/live-answer', {
                query,
                lang: this.lang
            });
            const liveAnswer = liveRes.data.answer;

            if (liveRes.data.success) {
                this.hideTyping();
                this.addMessage('bot', liveRes.data.answer);
                this.showFollowUpOptions();
                return;
            }
            
            const response = await axios.post('/chatbot/search', {
                query,
                lang: this.lang
            });
            const results = response.data.results;
            
            this.hideTyping();
            
            if (results.length > 0) {
                const bestMatch = results[0];
                this.addMessage('bot', this.t('info_found', { question: bestMatch.question }));
                this.addMessage('bot', bestMatch.answer);
                
                if (results.length > 1) {
                    this.addMessage('bot', this.t('other_topics'));
                    const relatedOptions = results.slice(1, 4).map(result => ({
                        text: result.question,
                        action: 'askQuestion',
                        data: { category: result.category, question: result.question }
                    }));
                    this.showOptions(relatedOptions);
                }
            } else {
                this.addMessage('bot', this.t('no_results'));
            }
            
            this.showFollowUpOptions();
            
        } catch (error) {
            console.error('Error searching:', error);
            this.hideTyping();
            this.addMessage('bot', this.t('error_searching'));
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