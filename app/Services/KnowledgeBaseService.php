<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\IOFactory;

class KnowledgeBaseService
{
    public function getStructuredData(): array
    {
        $path = storage_path('app/knowledge/KnowledgeBase.xlsx'); 

        if (!file_exists($path)) {
            throw new \Exception("Knowledge base file not found at: $path");
        }

        $spreadsheet = IOFactory::load($path);
        
        // Get English sheet data
        $englishData = $this->readSheetData($spreadsheet, 'English');
        
        // Get Malay sheet data
        $malayData = $this->readSheetData($spreadsheet, 'Malay');
        
        // Merge the data from both sheets
        $structured = $this->mergeLanguageData($englishData, $malayData);

        return $structured;
    }

    private function readSheetData($spreadsheet, $sheetName): array
    {
        try {
            $sheet = $spreadsheet->getSheetByName($sheetName);
            
            if (!$sheet) {
                throw new \Exception("Sheet '$sheetName' not found in the Excel file");
            }
            
            $rows = $sheet->toArray();
            
            if (empty($rows)) {
                return [];
            }

            $headers = array_map('trim', $rows[0]);
            $data = array_slice($rows, 1);

            $sheetData = [];

            foreach ($data as $row) {
                // Skip empty rows
                if (empty(array_filter($row))) {
                    continue;
                }
                
                $rowData = array_combine($headers, $row);

                $category = trim($rowData['Category'] ?? 'General');
                $subcategory = trim($rowData['subcategory'] ?? '');
                $question = trim($rowData['Question'] ?? '');
                $answer = trim($rowData['Answer'] ?? '');

                // Skip rows with empty questions
                if (empty($question)) {
                    continue;
                }

                $entry = [
                    'question' => $question,
                    'answer' => $answer,
                ];

                if (!isset($sheetData[$category])) {
                    $sheetData[$category] = [
                        'questions' => [],
                        'subcategories' => []
                    ];
                }

                if (!empty($subcategory)) {
                    if (!isset($sheetData[$category]['subcategories'][$subcategory])) {
                        $sheetData[$category]['subcategories'][$subcategory] = [];
                    }
                    $sheetData[$category]['subcategories'][$subcategory][] = $entry;
                } else {
                    $sheetData[$category]['questions'][] = $entry;
                }
            }

            return $sheetData;
            
        } catch (\Exception $e) {
            \Log::error("Error reading sheet '$sheetName': " . $e->getMessage());
            throw new \Exception("Failed to read '$sheetName' sheet: " . $e->getMessage());
        }
    }

    private function mergeLanguageData(array $englishData, array $malayData): array
    {
        $merged = [];

        // Get all categories from both languages
        $allCategories = array_unique(array_merge(array_keys($englishData), array_keys($malayData)));

        foreach ($allCategories as $category) {
            $merged[$category] = [
                'questions' => [],
                'subcategories' => []
            ];

            // Merge direct questions
            $englishQuestions = $englishData[$category]['questions'] ?? [];
            $malayQuestions = $malayData[$category]['questions'] ?? [];

            $maxQuestions = max(count($englishQuestions), count($malayQuestions));
            
            for ($i = 0; $i < $maxQuestions; $i++) {
                $englishQ = $englishQuestions[$i] ?? ['question' => '', 'answer' => ''];
                $malayQ = $malayQuestions[$i] ?? ['question' => '', 'answer' => ''];

                $merged[$category]['questions'][] = [
                    'question_en' => $englishQ['question'],
                    'question_bm' => $malayQ['question'],
                    'answer_en' => $englishQ['answer'],
                    'answer_bm' => $malayQ['answer'],
                ];
            }

            // Merge subcategories
            $englishSubcats = $englishData[$category]['subcategories'] ?? [];
            $malaySubcats = $malayData[$category]['subcategories'] ?? [];

            $allSubcategories = array_unique(array_merge(array_keys($englishSubcats), array_keys($malaySubcats)));

            foreach ($allSubcategories as $subcategory) {
                $englishSubQuestions = $englishSubcats[$subcategory] ?? [];
                $malaySubQuestions = $malaySubcats[$subcategory] ?? [];

                $maxSubQuestions = max(count($englishSubQuestions), count($malaySubQuestions));

                $merged[$category]['subcategories'][$subcategory] = [];

                for ($i = 0; $i < $maxSubQuestions; $i++) {
                    $englishSQ = $englishSubQuestions[$i] ?? ['question' => '', 'answer' => ''];
                    $malaySQ = $malaySubQuestions[$i] ?? ['question' => '', 'answer' => ''];

                    $merged[$category]['subcategories'][$subcategory][] = [
                        'question_en' => $englishSQ['question'],
                        'question_bm' => $malaySQ['question'],
                        'answer_en' => $englishSQ['answer'],
                        'answer_bm' => $malaySQ['answer'],
                    ];
                }
            }
        }

        return $merged;
    }

    public function getCategories(string $lang = 'en'): array
    {
        $structuredData = $this->getStructuredData();
        $filteredCategories = [];
        
        \Log::info('=== DEBUG getCategories ===');
        \Log::info('Requested language: ' . $lang);
        \Log::info('Total merged categories: ' . count($structuredData));
        
        foreach ($structuredData as $categoryName => $categoryData) {
            $hasContent = false;
            
            // Check if category has content in the requested language
            
            // Check direct questions
            foreach ($categoryData['questions'] as $questionData) {
                $question = $lang === 'bm' ? $questionData['question_bm'] : $questionData['question_en'];
                $answer = $lang === 'bm' ? $questionData['answer_bm'] : $questionData['answer_en'];
                
                if (!empty(trim($question)) && !empty(trim($answer))) {
                    $hasContent = true;
                    break;
                }
            }
            
            // If no direct questions, check subcategories
            if (!$hasContent) {
                foreach ($categoryData['subcategories'] as $subcategoryName => $subcategoryQuestions) {
                    foreach ($subcategoryQuestions as $questionData) {
                        $question = $lang === 'bm' ? $questionData['question_bm'] : $questionData['question_en'];
                        $answer = $lang === 'bm' ? $questionData['answer_bm'] : $questionData['answer_en'];
                        
                        if (!empty(trim($question)) && !empty(trim($answer))) {
                            $hasContent = true;
                            break 2;
                        }
                    }
                }
            }
            
            // Only include category if it has content in the requested language
            if ($hasContent) {
                $filteredCategories[] = $categoryName;
                \Log::info('Category "' . $categoryName . '" has content in ' . $lang);
            } else {
                \Log::info('Category "' . $categoryName . '" has NO content in ' . $lang);
            }
        }
        
        \Log::info('Filtered categories for ' . $lang . ': ' . json_encode($filteredCategories));
        \Log::info('Filtered categories count: ' . count($filteredCategories));
        
        return $filteredCategories;
    }

    public function getQuestions(string $category, string $lang = 'en'): array
    {
        $structuredData = $this->getStructuredData();
        
        if (!isset($structuredData[$category])) {
            return [];
        }

        $questions = [];
        $questionField = $lang === 'bm' ? 'question_bm' : 'question_en';

        foreach ($structuredData[$category]['questions'] as $questionData) {
            if (!empty(trim($questionData[$questionField]))) {
                $questions[] = trim($questionData[$questionField]);
            }
        }

        return $questions;
    }

    public function getSubcategories(string $category, string $lang = 'en'): array
    {
        $structuredData = $this->getStructuredData();
        
        if (!isset($structuredData[$category]['subcategories'])) {
            return [];
        }

        $filteredSubcategories = [];
        
        foreach ($structuredData[$category]['subcategories'] as $subcategoryName => $subcategoryQuestions) {
            $hasContent = false;
            
            // Check if this subcategory has content in the requested language
            foreach ($subcategoryQuestions as $questionData) {
                $question = $lang === 'bm' ? $questionData['question_bm'] : $questionData['question_en'];
                $answer = $lang === 'bm' ? $questionData['answer_bm'] : $questionData['answer_en'];
                
                if (!empty(trim($question)) && !empty(trim($answer))) {
                    $hasContent = true;
                    break;
                }
            }
            
            if ($hasContent) {
                $filteredSubcategories[] = $subcategoryName;
            }
        }

        return $filteredSubcategories;
    }

    public function debugStructure(): array
    {
        try {
            $path = storage_path('app/knowledge/KnowledgeBase.xlsx');
            $spreadsheet = IOFactory::load($path);
            
            $sheets = [];
            foreach ($spreadsheet->getSheetNames() as $sheetName) {
                $sheet = $spreadsheet->getSheetByName($sheetName);
                $rows = $sheet->toArray();
                
                $sheets[$sheetName] = [
                    'headers' => $rows[0] ?? [],
                    'row_count' => count($rows) - 1,
                    'sample_data' => array_slice($rows, 1, 3) // First 3 data rows
                ];
            }
            
            return $sheets;
            
        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }
}