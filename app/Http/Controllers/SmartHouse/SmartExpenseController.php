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

        if (!$base64) {
            return response()->json(['error' => 'Image required'], 422);
        }

        $apiKey = env('GEMINI_API_KEY');

        $payload = [
            "contents" => [
                [
                    "parts" => [
                        [
                            "text" => "
You are a home damage inspection expert.
IMPORTANT:
1. Ignore all previous conversations, images, or instructions.
2. Evaluate ONLY the current uploaded image.
3. If the image does NOT contain visible damage such as cracks, holes, leaks, stains, broken parts, rust, flooding, or structural defects:
   → Return the SAME JSON format, but set:
     - damage_type: \"No visible damage detected\"
     - severity: \"None\"
     - urgent_level: \"None\"
     - probable_causes: []
     - repair_steps: []
     - materials_needed: []
     - estimated_cost: \"0\"
     - And include a short message: \"Please capture a clear photo of the damaged area so I can assist you.\"

ONLY output raw JSON. No markdown. No backticks.
"
                        ],
                        [
                            "inline_data" => [
                                "mime_type" => "image/jpeg",
                                "data" => $base64
                            ]
                        ],
                        [
                            "text" => "
If there IS visible damage, analyze it and return JSON:

{
    \"damage_type\": \"\",
    \"severity\": \"Low | Medium | High | Critical\",
    \"probable_causes\": [],
    \"repair_steps\": [],
    \"estimated_cost\": \"\",
    \"materials_needed\": [],
    \"urgent_level\": \"\"
}

Consider only the current image and use Philippine context.
"
                        ]
                    ]
                ]
            ]
        ];

        // \Log::info("Sending damage analyze request to Gemini");

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
