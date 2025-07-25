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
        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray();

        $headers = array_map('trim', $rows[0]);
        $data = array_slice($rows, 1);

        $structured = [];

        foreach ($data as $row) {
            $rowData = array_combine($headers, $row);

            $category = trim($rowData['Category'] ?? 'General');
            $subcategory = trim($rowData['subcategory'] ?? '');
            $questionEn = $rowData['Question (EN)'] ?? '';
            $questionBm = $rowData['Question (BM)'] ?? '';
            $answerEn = $rowData['Answer (EN)'] ?? '';
            $answerBm = $rowData['Answer (BM)'] ?? '';

            $entry = [
                'question_en' => $questionEn,
                'question_bm' => $questionBm,
                'answer_en' => $answerEn,
                'answer_bm' => $answerBm,
            ];

            if (!isset($structured[$category])) {
                $structured[$category] = [
                    'questions' => [],
                    'subcategories' => []
                ];
            }

            if (!empty($subcategory)) {
                $structured[$category]['subcategories'][$subcategory][] = $entry;
            } else {
                $structured[$category]['questions'][] = $entry;
            }
        }

        return $structured;
    }
}


