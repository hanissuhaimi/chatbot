<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use App\Services\KnowledgeBaseService;
use App\Models\Projects;

class ChatbotController extends Controller
{
    protected $knowledgeBaseService;
    
    public function __construct(KnowledgeBaseService $knowledgeBaseService) 
    {
        $this->knowledgeBaseService = $knowledgeBaseService;
    }

    private function loadKnowledgeBase(): Collection
    {
        try {
            $path = storage_path('app/knowledge/KnowledgeBase.xlsx');
            
            // Check if file exists
            if (!file_exists($path)) {
                \Log::error('Knowledge base file not found: ' . $path);
                throw new \Exception('Knowledge base file not found');
            }
            
            \Log::info('Loading knowledge base from: ' . $path);
            \Log::info('File size: ' . filesize($path) . ' bytes');
            
            // Load the Excel file
            $collection = Excel::toCollection(null, $path);
            
            if ($collection->isEmpty()) {
                \Log::error('Excel file is empty or has no sheets');
                throw new \Exception('Excel file is empty or has no sheets');
            }
            
            $sheet = $collection[0]; // Sheet 1
            
            \Log::info('Sheet loaded successfully');
            \Log::info('Total rows in sheet: ' . $sheet->count());
            
            // Log first few rows for debugging (be careful with sensitive data)
            if ($sheet->count() > 0) {
                \Log::info('First row (headers): ' . json_encode($sheet->first()->toArray()));
                
                if ($sheet->count() > 1) {
                    \Log::info('Second row (sample data): ' . json_encode($sheet->skip(1)->first()->toArray()));
                }
            }
            
            return $sheet;
            
        } catch (\Exception $e) {
            \Log::error('Error loading knowledge base: ' . $e->getMessage());
            \Log::error('Stack trace: ' . $e->getTraceAsString());
            
            // Return empty collection instead of throwing to prevent complete failure
            return collect();
        }
    }

    public function index()
    {
        // Get all translations for both languages to pass to frontend
        $translations = [
            'en' => __('messages', [], 'en'),
            'bm' => __('messages', [], 'bm')
        ];
        
        return view('chatbot.index', compact('translations'));
    }

    public function setLanguage(Request $request)
    {
        $lang = $request->input('lang', 'en');
        
        if (in_array($lang, ['en', 'bm'])) {
            session(['chatbot_lang' => $lang]); 
            app()->setLocale($lang);
        }
        
        return response()->json(['success' => true]);
    }

    public function debugExcel()
    {
        try {
            $path = storage_path('app/knowledge/KnowledgeBase.xlsx');
            
            if (!file_exists($path)) {
                return response()->json(['error' => 'File not found: ' . $path]);
            }
            
            $collection = Excel::toCollection(null, $path);
            $sheet = $collection[0];
            
            // Get first 5 rows to understand structure
            $sampleData = $sheet->take(5)->toArray();
            
            return response()->json([
                'file_path' => $path,
                'file_exists' => file_exists($path),
                'file_size' => filesize($path),
                'total_rows' => $sheet->count(),
                'sample_data' => $sampleData,
                'first_row_keys' => array_keys($sheet->first()->toArray()),
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    public function getCategories(Request $request): JsonResponse
    {
        try {
            // For GET requests, use query parameters
            $lang = $request->query('lang', session('chatbot_lang', 'en'));
            
            \Log::info('Loading categories for language: ' . $lang);
            \Log::info('Request method: ' . $request->method());
            \Log::info('All query params: ', $request->query());
            
            // Use your KnowledgeBaseService
            $knowledgeBaseService = new KnowledgeBaseService(); // or inject it
            $structuredData = $knowledgeBaseService->getStructuredData();
            
            \Log::info('Structured data loaded, categories: ' . count($structuredData));
            
            $categories = array_keys($structuredData);
            
            \Log::info('Found categories: ', $categories);
            
            return response()->json([
                'categories' => $categories,
                'language' => $lang,
                'total_categories' => count($categories)
            ]);
            
        } catch (\Exception $e) {
            \Log::error('Error in getCategories: ' . $e->getMessage());
            \Log::error('Stack trace: ' . $e->getTraceAsString());
            
            return response()->json([
                'error' => 'Failed to load categories',
                'message' => $e->getMessage(),
                'categories' => []
            ], 500);
        }
    }

    public function getQuestionsByCategory(Request $request): JsonResponse
    {
        try {
            $category = $request->input('category');
            $lang = $request->input('lang', 'en');
            
            \Log::info('=== DEBUG getQuestionsByCategory ===');
            \Log::info('Received category: "' . $category . '"');
            \Log::info('Received language: "' . $lang . '"');
            
            $knowledgeBaseService = new KnowledgeBaseService();
            $structuredData = $knowledgeBaseService->getStructuredData();
            
            \Log::info('Available categories: ', array_keys($structuredData));
            
            if (!isset($structuredData[$category])) {
                \Log::warning('Category not found: ' . $category);
                return response()->json([
                    'questions' => [],
                    'subcategories' => [],
                    'error' => 'Category not found'
                ]);
            }
            
            $categoryData = $structuredData[$category];
            
            // Extract direct questions for this category
            $directQuestions = [];
            foreach ($categoryData['questions'] as $questionData) {
                $question = $lang === 'bm' ? $questionData['question_bm'] : $questionData['question_en'];
                if (!empty(trim($question))) {
                    $directQuestions[] = trim($question);
                }
            }
            
            // Get subcategory names
            $subcategories = array_keys($categoryData['subcategories']);
            
            \Log::info('Category analysis:', [
                'category' => $category,
                'direct_questions_count' => count($directQuestions),
                'subcategories_count' => count($subcategories),
                'subcategories' => $subcategories
            ]);
            
            return response()->json([
                'questions' => $directQuestions,
                'subcategories' => $subcategories,
                'has_subcategories' => count($subcategories) > 0,
                'has_direct_questions' => count($directQuestions) > 0,
                'debug' => [
                    'category' => $category,
                    'language' => $lang
                ]
            ]);
            
        } catch (\Exception $e) {
            \Log::error('Error loading questions: ' . $e->getMessage());
            return response()->json([
                'questions' => [],
                'subcategories' => [],
                'error' => 'Failed to load questions: ' . $e->getMessage()
            ], 500);
        }
    }
    
    public function getSubcategoryQuestions(Request $request): JsonResponse
    {
        try {
            $category = $request->input('category');
            $subcategory = $request->input('subcategory');
            $lang = $request->input('lang', 'en');
            
            \Log::info('=== DEBUG getSubcategoryQuestions ===');
            \Log::info('Category: ' . $category);
            \Log::info('Subcategory: ' . $subcategory);
            \Log::info('Language: ' . $lang);
            
            $knowledgeBaseService = new KnowledgeBaseService();
            $structuredData = $knowledgeBaseService->getStructuredData();
            
            if (!isset($structuredData[$category])) {
                return response()->json([
                    'questions' => [],
                    'error' => 'Category not found'
                ]);
            }
            
            if (!isset($structuredData[$category]['subcategories'][$subcategory])) {
                return response()->json([
                    'questions' => [],
                    'error' => 'Subcategory not found'
                ]);
            }
            
            $subcategoryData = $structuredData[$category]['subcategories'][$subcategory];
            
            $questions = [];
            foreach ($subcategoryData as $questionData) {
                $question = $lang === 'bm' ? $questionData['question_bm'] : $questionData['question_en'];
                if (!empty(trim($question))) {
                    $questions[] = trim($question);
                }
            }
            
            \Log::info('Subcategory questions found: ' . count($questions));
            
            return response()->json([
                'questions' => $questions,
                'category' => $category,
                'subcategory' => $subcategory,
                'total_found' => count($questions)
            ]);
            
        } catch (\Exception $e) {
            \Log::error('Error loading subcategory questions: ' . $e->getMessage());
            return response()->json([
                'questions' => [],
                'error' => 'Failed to load subcategory questions: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getAnswer(Request $request): JsonResponse
    {
        try {
            $category = $request->input('category');
            $subcategory = $request->input('subcategory'); // This might be null
            $question = $request->input('question');
            $lang = $request->input('lang', 'en');
            
            \Log::info('=== DEBUG getAnswer ===');
            \Log::info('Category: ' . $category);
            \Log::info('Subcategory: ' . ($subcategory ?? 'none'));
            \Log::info('Question: ' . $question);
            \Log::info('Language: ' . $lang);
            
            $knowledgeBaseService = new KnowledgeBaseService();
            $structuredData = $knowledgeBaseService->getStructuredData();
            
            if (!isset($structuredData[$category])) {
                return response()->json([
                    'answer' => __('messages.no_answer_found', [], $lang), // ✅ Fixed: Pass language
                    'error' => 'Category not found'
                ]);
            }
            
            $categoryData = $structuredData[$category];
            $foundAnswer = null;
            
            // If subcategory is specified, search only in that subcategory
            if ($subcategory && isset($categoryData['subcategories'][$subcategory])) {
                \Log::info('Searching in subcategory: ' . $subcategory);
                
                foreach ($categoryData['subcategories'][$subcategory] as $questionData) {
                    $storedQuestion = $lang === 'bm' ? $questionData['question_bm'] : $questionData['question_en'];
                    
                    if (Str::lower(trim($storedQuestion)) === Str::lower(trim($question))) {
                        $foundAnswer = $lang === 'bm' ? $questionData['answer_bm'] : $questionData['answer_en'];
                        \Log::info('Found answer in subcategory');
                        break;
                    }
                }
            } else {
                // Search in direct questions first
                \Log::info('Searching in direct questions');
                
                foreach ($categoryData['questions'] as $questionData) {
                    $storedQuestion = $lang === 'bm' ? $questionData['question_bm'] : $questionData['question_en'];
                    
                    if (Str::lower(trim($storedQuestion)) === Str::lower(trim($question))) {
                        $foundAnswer = $lang === 'bm' ? $questionData['answer_bm'] : $questionData['answer_en'];
                        \Log::info('Found answer in direct questions');
                        break;
                    }
                }
                
                // If not found in direct questions, search all subcategories
                if (!$foundAnswer) {
                    \Log::info('Searching in all subcategories');
                    
                    foreach ($categoryData['subcategories'] as $subName => $subQuestions) {
                        foreach ($subQuestions as $questionData) {
                            $storedQuestion = $lang === 'bm' ? $questionData['question_bm'] : $questionData['question_en'];
                            
                            if (Str::lower(trim($storedQuestion)) === Str::lower(trim($question))) {
                                $foundAnswer = $lang === 'bm' ? $questionData['answer_bm'] : $questionData['answer_en'];
                                \Log::info('Found answer in subcategory: ' . $subName);
                                break 2;
                            }
                        }
                    }
                }
            }
            
            // Add additional check for empty answers
            if ($foundAnswer && trim($foundAnswer) !== '') {
                \Log::info('Returning found answer: ' . substr($foundAnswer, 0, 100) . '...');
                return response()->json([
                    'answer' => $foundAnswer,
                    'success' => true
                ]);
            }
            
            // Log when no answer is found
            \Log::info('No answer found, returning translated error message');
            \Log::info('Using language: ' . $lang);
            
            // Test the translation
            $translatedMessage = __('messages.no_answer_found', [], $lang);
            \Log::info('Translated message: ' . $translatedMessage);
            
            return response()->json([
                'answer' => $translatedMessage, 
                'success' => false
            ]);
            
        } catch (\Exception $e) {
            \Log::error('Error getting answer: ' . $e->getMessage());
            return response()->json([
                'answer' => __('messages.error_searching', [], $lang), 
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function searchAnswer(Request $request): JsonResponse
    {
        try {
            $query = strtolower(trim($request->input('query')));
            $lang = $request->input('lang', 'en');
            
            \Log::info('=== DEBUG searchAnswer ===');
            \Log::info('Query: ' . $query);
            \Log::info('Language: ' . $lang);
            
            // Use KnowledgeBaseService
            $knowledgeBaseService = new KnowledgeBaseService(); 
            $structuredData = $knowledgeBaseService->getStructuredData();
            
            $results = [];
            
            foreach ($structuredData as $categoryName => $categoryData) {
                
                // Search direct questions
                foreach ($categoryData['questions'] as $questionData) {
                    $question = $lang === 'bm' ? $questionData['question_bm'] : $questionData['question_en'];
                    $answer = $lang === 'bm' ? $questionData['answer_bm'] : $questionData['answer_en'];
                    
                    $questionLower = strtolower($question ?? '');
                    $answerLower = strtolower($answer ?? '');
                    
                    if (Str::contains($questionLower, $query) || Str::contains($answerLower, $query)) {
                        $results[] = [
                            'question' => $question,
                            'answer' => $answer,
                            'category' => $categoryName,
                            'subcategory' => null
                        ];
                    }
                }
                
                // Search subcategory questions
                foreach ($categoryData['subcategories'] as $subcategoryName => $subcategoryQuestions) {
                    foreach ($subcategoryQuestions as $questionData) {
                        $question = $lang === 'bm' ? $questionData['question_bm'] : $questionData['question_en'];
                        $answer = $lang === 'bm' ? $questionData['answer_bm'] : $questionData['answer_en'];
                        
                        $questionLower = strtolower($question ?? '');
                        $answerLower = strtolower($answer ?? '');
                        
                        if (Str::contains($questionLower, $query) || Str::contains($answerLower, $query)) {
                            $results[] = [
                                'question' => $question,
                                'answer' => $answer,
                                'category' => $categoryName,
                                'subcategory' => $subcategoryName
                            ];
                        }
                    }
                }
            }
            
            // Remove duplicates and sort by relevance (exact question matches first)
            $results = collect($results)->unique(function ($item) {
                return $item['question'] . '|' . $item['answer'];
            })->sortBy(function ($item) use ($query) {
                $questionLower = strtolower($item['question']);
                // Exact matches first, then partial matches
                if ($questionLower === $query) return 0;
                if (Str::startsWith($questionLower, $query)) return 1;
                return 2;
            })->values()->toArray();
            
            \Log::info('Search results count: ' . count($results));
            
            return response()->json([
                'results' => $results,
                'total' => count($results)
            ]);
            
        } catch (\Exception $e) {
            \Log::error('Error searching: ' . $e->getMessage());
            \Log::error('Stack trace: ' . $e->getTraceAsString());
            
            return response()->json([
                'results' => [],
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getLiveAnswer(Request $request): JsonResponse
    {
        try {
            $query = strtolower($request->input('query'));
            $lang = $request->input('lang', 'en');

            if (preg_match('/(?:completion|progress|kemajuan|perkembangan|siap|selesai|status).*(?:project|projek)\s+(.+)/i', $query, $matches)) {
                $projectName = trim($matches[1], " \t\n\r\0\x0B?.,'\"");

                \Log::info("🔎 Searching project title like: %{$projectName}%");

                $project = Projects::where('title', 'like', "%{$projectName}%")->first();

                if ($project) {
                    $value = $project->complete_percentage ?? $project->progress;

                    if ($value !== null) {
                        $message = $lang === 'bm'
                            ? "Peratusan kemajuan projek \"{$project->title}\" ialah {$value}%."
                            : "The completion percentage of project \"{$project->title}\" is {$value}%.";

                        return response()->json([
                            'answer' => $message,
                            'success' => true
                        ]);
                    } else {
                        $message = $lang === 'bm'
                            ? "Projek \"{$project->title}\" dijumpai, tetapi tiada data kemajuan tersedia."
                            : "Project \"{$project->title}\" found, but no progress data is available.";

                        return response()->json([
                            'answer' => $message,
                            'success' => false
                        ]);
                    }
                } else {
                    $message = $lang === 'bm'
                        ? "Maaf, tiada projek bernama \"{$projectName}\" dijumpai."
                        : "Sorry, I couldn't find any project named \"{$projectName}\".";

                    return response()->json([
                        'answer' => $message,
                        'success' => false
                    ]);
                }
            }

            $fallback = $lang === 'bm'
                ? "Maaf, saya tidak faham permintaan anda. Cuba tanya: 'Apakah peratusan kemajuan projek XYZ?'"
                : "Sorry, I didn't understand your request. Try asking: 'What is the completion percentage of project XYZ?'";

            return response()->json([
                'answer' => $fallback,
                'success' => false
            ]);

        } catch (\Throwable $e) {
            \Log::error('🔥 Live answer error: ' . $e->getMessage());

            return response()->json([
                'answer' => __('messages.error_searching'),
                'success' => false
            ], 500);
        }
    }
}