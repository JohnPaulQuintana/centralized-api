<?php

namespace App\Http\Controllers\SmartHouse;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class SmartExpenseController extends Controller
{
    public function aiSuggestion(Request $request)
    {
        $overview = $request->overviewData ?? [];
        $weekly = $request->weeklySpending ?? [];
        $labels = $request->weeklyLabels ?? [];

        $prompt = "You are a friendly, casual finance assistant. Analyze the user's spending data only. " .
            "Do NOT invent reasons or give generic tips. Base your insights strictly on the numbers provided. " .
            "Return exactly 3–5 bullet points, each starting with '•'. " .
            "Keep it short, clear, and easy to follow, like talking to a friend.\n\n" .
            "Overview (Budget Left is for the month):\n" .
            "Total Spent: ₱" . ($overview['totalSpent'] ?? 0) . "\n" .
            "Today Spent: ₱" . ($overview['todaySpent'] ?? 0) . "\n" .
            "Top Category: " . ($overview['topCategory'] ?? '-') . "\n" .
            "Daily Avg: ₱" . ($overview['dailyAvg'] ?? 0) . "\n" .
            "Budget Left: ₱" . ($overview['budgetLeft'] ?? 0) . "\n\n" .
            "Weekly Spending:\n" .
            collect($labels)->map(fn($label, $i) => $label . ': ₱' . ($weekly[$i] ?? 0))->join("\n") . "\n\n" .
            "Compare today's spending with yesterday's and highlight any savings or overspending. " .
            "Also mention how today's spending affects the monthly budget left. " .
            "Provide 3–5 actionable bullet points the user can follow. " .
            "Always start each bullet with '•' and ensure each point directly relates to the data above. " .
            "End with a friendly closing like: 'This is all I found. Goodbye!'.";



        // \Log::info("Gemini AI Prompt: \n" . $prompt);

        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'x-goog-api-key' => env('GEMINI_API_KEY'),
            ])->post('https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent', [
                'contents' => [
                    [
                        'parts' => [
                            ['text' => $prompt],
                        ],
                    ],
                ],
            ]);

            if ($response->failed()) {
                // \Log::error('Gemini API request failed:', $response->json());
                return response()->json(['text' => 'Gemini API request failed.']);
            }

            $json = $response->json();
            $text = $json['candidates'][0]['content']['parts'][0]['text']
                ?? $json['candidates'][0]['output'][0]['text']
                ?? 'No insight could be generated.';

            return response()->json(['text' => $text]);
        } catch (\Exception $e) {
            // \Log::error('Gemini AI Exception: ' . $e->getMessage());
            return response()->json(['text' => 'Unable to generate AI suggestion at the moment.']);
        }
    }

    public function analyzeDamage(Request $request)
    {
        $base64 = $request->input('image_base64');
        $userDescription = $request->input('description', ''); // User input in any language

        if (!$base64) {
            return response()->json(['error' => 'Image required'], 422);
        }

        $apiKey = env('GEMINI_API_KEY');

        // Enhanced prompt
        $prompt = "
            You are a professional home damage inspection expert.

            The user provides:
            1. A photo of an item
            2. A description of the problem in any language: \"$userDescription\"

            IMPORTANT RULES:
            - Analyze both the image and the description.
            - If the image shows visible damage → analyze normally.
            - If the image shows NO visible damage BUT the description suggests a problem (internal issues, malfunctions, etc.), generate a diagnosis based on the description.
            - Always respond professionally.
            - **If the repair appears complicated, unsafe, or requires specialized skills, always include a recommendation for the user to consult a legitimate professional or licensed technician.**
            - Respect the language of the user's description. If the description is in English, respond in English. If it’s in another language, respond in the same language. If no description is provided, respond in English.
            - Output ONLY valid JSON. No markdown, no backticks.

            JSON format:
            {
                \"damage_type\": \"\",
                \"severity\": \"Low | Medium | High | Critical\",
                \"probable_causes\": [],
                \"repair_steps\": [],
                \"materials_needed\": [],
                \"estimated_cost\": \"\",
                \"urgent_level\": \"Low | Medium | High | Immediate\",
                \"message\": \"\" // Include guidance or notes in the same language as description, **and include professional advice if the repair seems complex or unsafe**
            }

            Use Philippine context and realistic repair/cost estimates.
            ";


        $payload = [
            "contents" => [
                [
                    "parts" => [
                        ["text" => $prompt],
                        [
                            "inline_data" => [
                                "mime_type" => "image/jpeg",
                                "data" => $base64
                            ]
                        ]
                    ]
                ]
            ]
        ];

        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'x-goog-api-key' => $apiKey,
        ])->post(
            'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent',
            $payload
        );

        return $response->json();
    }
}
