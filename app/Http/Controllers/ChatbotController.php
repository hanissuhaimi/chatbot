<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\Projects;

class ChatbotController extends Controller
{
    private $faqData = [
        'Project Info' => [
            'What is this project about?' => 'The project management portal is developed by FGV Prodata to streamline project tracking and reporting.',
            'Who is managing the project?' => 'The project is managed by FGV Prodata\'s IT and project management office.'
        ],
        'Access' => [
            'How do I log in?' => 'Go to the login page at https://prompt.fgvprodata.com.my and enter your credentials.',
            'I forgot my password.' => 'Click on "Forgot Password" on the login page or contact IT support at support@fgvprodata.com.my.'
        ],
        'Submission' => [
            'How do I submit a project update?' => 'After logging in, go to the \'My Projects\' tab, select your project, and click \'Submit Update.\'',
            'What file types can I upload?' => 'You can upload PDF, DOCX, XLSX, and image formats like PNG or JPG.'
        ],
        'Status & Review' => [
            'What does "Pending Review" mean?' => 'It means your submission is awaiting approval from the project reviewer or admin.',
            'How long does review take?' => 'Reviews are typically completed within 3–5 business days.'
        ],
        'Troubleshooting' => [
            'My upload failed. What should I do?' => 'Check your file size (max 10MB) and internet connection. Try again or contact support.'
        ],
        'Support' => [
            'Who do I contact for technical help?' => 'Please contact IT support at support@fgvprodata.com.my or call 03-1234 5678.'
        ]
    ];

    public function index()
    {
        return view('chatbot.index');
    }

    public function getCategories(): JsonResponse
    {
        $categories = array_keys($this->faqData);
        return response()->json(['categories' => $categories]);
    }

    public function getQuestionsByCategory(Request $request): JsonResponse
    {
        $category = $request->input('category');
        
        if (!isset($this->faqData[$category])) {
            return response()->json(['error' => 'Category not found'], 404);
        }

        $questions = array_keys($this->faqData[$category]);
        return response()->json(['questions' => $questions]);
    }

    public function getAnswer(Request $request): JsonResponse
    {
        $category = $request->input('category');
        $question = $request->input('question');

        if (!isset($this->faqData[$category][$question])) {
            return response()->json(['error' => 'Question not found'], 404);
        }

        $answer = $this->faqData[$category][$question];
        return response()->json(['answer' => $answer]);
    }

    public function searchAnswer(Request $request): JsonResponse
    {
        $query = strtolower($request->input('query'));
        $results = [];

        foreach ($this->faqData as $category => $questions) {
            foreach ($questions as $question => $answer) {
                if (strpos(strtolower($question), $query) !== false || 
                    strpos(strtolower($answer), $query) !== false) {
                    $results[] = [
                        'category' => $category,
                        'question' => $question,
                        'answer' => $answer
                    ];
                }
            }
        }

        return response()->json(['results' => $results]);
    }

    public function getLiveAnswer(Request $request): JsonResponse
    {
        $query = strtolower($request->input('query'));

        // Pattern: What is the completion percentage of project [title]
        if (preg_match('/completion.*project (.+)/', $query, $matches)) {
            $projectName = trim($matches[1]);

            $project = Projects::where('title', 'like', "%{$projectName}%")->first();

            if ($project) {
                $value = $project->complete_percentage ?? 'N/A';
                return response()->json([
                    'answer' => "The completion percentage of project \"{$project->title}\" is {$value}%."
                ]);
            } else {
                return response()->json([
                    'answer' => "Sorry, I couldn't find any project named \"{$projectName}\"."
                ]);
            }
        }

        return response()->json([
            'answer' => "Sorry, I didn’t understand your request. Try asking: 'What is the completion percentage of project XYZ?'"
        ]);
    }

}