<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ChatbotController;

Route::get('/', [ChatbotController::class, 'index'])->name('home');


Route::get('/chatbot', [ChatbotController::class, 'index'])->name('chatbot.index');
Route::get('/chatbot/categories', [ChatbotController::class, 'getCategories'])->name('chatbot.categories');
Route::get('/debug-excel', [ChatbotController::class, 'debugExcel']);
Route::post('/chatbot/questions', [ChatbotController::class, 'getQuestionsByCategory'])->name('chatbot.questions');
Route::post('/chatbot/answer', [ChatbotController::class, 'getAnswer'])->name('chatbot.answer');
Route::post('/chatbot/search', [ChatbotController::class, 'searchAnswer'])->name('chatbot.search');
Route::post('/chatbot/live-answer', [ChatbotController::class, 'getLiveAnswer']);
Route::post('/chatbot/set-language', [ChatbotController::class, 'setLanguage']);
Route::post('/chatbot/subcategory-questions', [ChatbotController::class, 'getSubcategoryQuestions'])->name('chatbot.subcategory-questions');