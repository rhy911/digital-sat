<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AiClassificationService
{
    /**
     * Use LLM to classify question difficulty, domain, and passage genre.
     * 
     * @param array{section_type: string, stem: string, passage_content: ?string} $data
     * @return array{difficulty: string, skill_domain: string, genre: ?string}
     */
    public function classify(array $data): array
    {
        $prompt = $this->buildPrompt($data);
        
        // Strategy: Use an LLM API. 
        // For this demo, we use a structured prompt and expect JSON.
        // Replace with actual OpenAI/Gemini API call.
        
        try {
            // Mocking the AI call logic. 
            // In a real implementation, you'd use Http::post('...', [...])
            $result = $this->callLlm($prompt);
            \Illuminate\Support\Facades\Log::info('AI Classification Result:', $result);
            
            return [
                'difficulty' => $result['difficulty'] ?? 'medium',
                'skill_domain' => $result['skill_domain'] ?? ($data['section_type'] === 'math' ? 'algebra' : 'information_and_ideas'),
                'genre' => $result['genre'] ?? 'humanities',
            ];
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('AI Classification failed: ' . $e->getMessage());
            // Fallbacks
            return [
                'difficulty' => 'medium',
                'skill_domain' => $data['section_type'] === 'math' ? 'algebra' : 'information_and_ideas',
                'genre' => 'humanities',
            ];
        }
    }

    private function buildPrompt(array $data): string
    {
        $section = $data['section_type'];
        $stem = $data['stem'];
        $passage = $data['passage_content'] ?? 'No passage.';

        $domains = $section === 'math' 
            ? 'algebra, advanced_math, problem_solving_data_analysis, geometry_trigonometry'
            : 'craft_and_structure, information_and_ideas, standard_english_conventions, expression_of_ideas';
        
        $genres = 'literary_narrative, social_science, natural_science, humanities';

        return "Analyze this Digital SAT question and categorize it.
Section: $section
Passage: $passage
Stem: $stem

Rules:
1. Difficulty must be: easy, medium, or hard.
2. Skill Domain must be one of: [$domains].
3. Genre (only if Reading/Writing) must be one of: [$genres].

Return ONLY a JSON object with keys: difficulty, skill_domain, genre.";
    }

    private function callLlm(string $prompt): array
    {
        // Use Gemini API (or change to OpenAI) 
        $response = Http::withHeaders(['Content-Type' => 'application/json',])
        ->post('https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key='
        .env('GEMINI_API_KEY'), ['contents' => [['parts' => [['text' => $prompt]]]],
        // Force JSON output
        'generationConfig' => ['response_mime_type' => 'application/json',]]);
        if ($response->successful()) {
            $jsonString = $response->json('candidates.0.content.parts.0.text');
            return json_decode($jsonString, true) ?? [];
        }
        return [];
    }
}
