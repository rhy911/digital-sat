<?php

namespace App\Services;

use App\Models\AnswerChoice;
use App\Models\Module;
use App\Models\Passage;
use App\Models\Question;
use App\Models\QuestionExplanation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use ZipArchive;

class BulkQuestionImportService
{
    public function __construct(
        private BulkQuestionCsvImportService $csvImportService
    ) {}

    /**
     * Import from a ZIP file containing a data file (json/csv) and images.
     */
    public function importFromZip(Request $request): array
    {
        @ini_set('memory_limit', '512M');
        $request->validate([
            'zip_file' => 'required|file|mimes:zip|max:20480',
            'module_id' => 'required|exists:modules,id',
            'start_position' => 'nullable|integer|min:1',
        ]);

        if (!class_exists('ZipArchive')) {
            throw new \Exception('PHP ZipArchive extension is not installed or enabled.');
        }

        $file = $request->file('zip_file');
        if (!$file) {
            throw new \Exception('No ZIP file uploaded.');
        }
        $zip = new ZipArchive();
        
        if ($zip->open($file->getRealPath()) !== true) {
            throw ValidationException::withMessages(['zip_file' => ['Could not open ZIP file.']]);
        }

        $tempDir = 'temp/import_' . Str::random(10);
        try {
            Storage::makeDirectory($tempDir);
            $tempPath = storage_path('app/' . $tempDir);
            
            if (!$zip->extractTo($tempPath)) {
                throw new \Exception("Failed to extract ZIP to $tempPath");
            }
            $zip->close();

            // 1. Find all data files (recursively)
            $allDataFiles = [];
            $allFiles = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($tempPath, \RecursiveDirectoryIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::LEAVES_ONLY
            );

            foreach ($allFiles as $file) {
                if (str_starts_with($file->getFilename(), '.') || str_contains($file->getPathname(), '__MACOSX')) {
                    continue;
                }

                if (preg_match('/\.(json|csv)$/i', $file->getFilename())) {
                    \Illuminate\Support\Facades\Log::info('Found data file in ZIP: ' . $file->getPathname());
                    $allDataFiles[] = [
                        'path' => $file->getPathname(),
                        'base' => $file->getPath(),
                        'ext' => strtolower($file->getExtension())
                    ];
                }
            }

            if (empty($allDataFiles)) {
                throw new \Exception('No JSON or CSV data files found in ZIP.');
            }

            // 2. Load and merge all items
            $allItems = [];
            foreach ($allDataFiles as $df) {
                $items = [];
                if ($df['ext'] === 'json') {
                    $raw = file_get_contents($df['path']);
                    $decoded = json_decode($raw, true);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        \Illuminate\Support\Facades\Log::error('JSON decode failed for file: ' . $df['path'] . ' Error: ' . json_last_error_msg());
                        continue;
                    }
                    
                    \Illuminate\Support\Facades\Log::info('Decoded JSON keys: ' . implode(', ', array_keys($decoded)));
                    
                    if (isset($decoded['items']) && is_array($decoded['items'])) {
                        $items = $decoded['items'];
                    } elseif (is_array($decoded) && !empty($decoded)) {
                        // If it's a list, check if items look like questions
                        if (array_is_list($decoded)) {
                            $items = $decoded;
                        } else {
                            // Single object with stem?
                            if (isset($decoded['stem'])) {
                                $items = [$decoded];
                            } else {
                                // Fallback: search for any key that contains a list
                                foreach ($decoded as $key => $val) {
                                    if (is_array($val) && array_is_list($val) && !empty($val) && (isset($val[0]['stem']) || isset($val[0]['question_number']))) {
                                        $items = $val;
                                        break;
                                    }
                                }
                                // If still no items, maybe the whole object IS a question (non-list array)
                                if (empty($items) && isset($decoded['stem'])) {
                                    $items = [$decoded];
                                }
                            }
                        }
                    }
                } else {
                    $raw = file_get_contents($df['path']);
                    $items = $this->csvImportService->parseCsvToItems($raw);
                }

                \Illuminate\Support\Facades\Log::info('Items extracted from ' . $df['path'] . ': ' . count($items));

                if (empty($items)) continue;

                // Process Media relative to THIS data file's folder
                $items = $this->processZipMedia($items, $df['base']);
                $allItems = array_merge($allItems, $items);
            }

            if (empty($allItems)) {
                throw new \Exception('No valid question items found in ZIP data files.');
            }

            // 3. Build payload
            $payload = [
                'module_id' => $request->input('module_id'),
                'start_position' => $request->input('start_position', 1),
                'items' => $allItems
            ];

            return $this->import($payload);

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('ZIP Import Error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        } finally {
            Storage::deleteDirectory($tempDir);
        }
    }

    private function processZipMedia(array $items, string $basePath): array
    {
        $processString = function ($str) use ($basePath) {
            if (!$str) return $str;
            return preg_replace_callback('/\[Media:([^\]]+)\]/i', function ($matches) use ($basePath) {
                $filename = trim($matches[1]);
                
                // Search strategy: 1. exact path, 2. recursive search in basePath
                $foundSrc = null;
                
                // Try direct relative paths first
                $searchPaths = [
                    $basePath . '/' . $filename,
                    $basePath . '/images/' . $filename,
                ];

                foreach ($searchPaths as $path) {
                    if (file_exists($path)) {
                        $foundSrc = $path;
                        break;
                    }
                }

                // If not found, do a recursive search for the filename
                if (!$foundSrc) {
                    $allFiles = new \RecursiveIteratorIterator(
                        new \RecursiveDirectoryIterator($basePath, \RecursiveDirectoryIterator::SKIP_DOTS),
                        \RecursiveIteratorIterator::LEAVES_ONLY
                    );
                    foreach ($allFiles as $file) {
                        if (strcasecmp($file->getFilename(), $filename) === 0) {
                            $foundSrc = $file->getPathname();
                            break;
                        }
                    }
                }

                if ($foundSrc) {
                    $ext = pathinfo($foundSrc, PATHINFO_EXTENSION);
                    $newName = Str::random(20) . '.' . $ext;
                    
                    // Ensure directory exists in storage/app/public/media
                    if (!Storage::disk('public')->exists('media')) {
                        Storage::disk('public')->makeDirectory('media');
                    }

                    $content = file_get_contents($foundSrc);
                    if ($content === false) {
                        \Illuminate\Support\Facades\Log::error("Failed to read media file: $foundSrc");
                        return $matches[0];
                    }

                    Storage::disk('public')->put('media/' . $newName, $content);
                    $url = Storage::disk('public')->url('media/' . $newName);
                    
                    \Illuminate\Support\Facades\Log::info("Media imported: $filename -> media/$newName");
                    
                    return "![]($url)";
                }
                
                \Illuminate\Support\Facades\Log::warning("Media file not found in ZIP: $filename (Base: $basePath)");
                return $matches[0];
            }, $str);
        };

        foreach ($items as &$item) {
            $item['stem'] = $processString($item['stem']);
            if (isset($item['passage'])) {
                if (is_string($item['passage'])) {
                    $item['passage'] = $processString($item['passage']);
                } elseif (isset($item['passage']['content'])) {
                    $item['passage']['content'] = $processString($item['passage']['content']);
                }
            }
            if (isset($item['explanation'])) {
                $item['explanation'] = $processString($item['explanation']);
            }
            // Process media in choices
            if (isset($item['choices']) && is_array($item['choices'])) {
                foreach ($item['choices'] as &$choice) {
                    if (is_array($choice) && isset($choice['content'])) {
                        $choice['content'] = $processString($choice['content']);
                    } elseif (is_string($choice)) {
                        // This handles cases where choices might be plain strings before normalization
                        // though normalization usually happens after.
                    }
                }
            }
            // Process media in rationales
            foreach (['rationale_a', 'rationale_b', 'rationale_c', 'rationale_d'] as $rat) {
                if (isset($item[$rat])) {
                    $item[$rat] = $processString($item[$rat]);
                }
            }
        }

        return $items;
    }

    /**
     * Build the bulk-import payload from JSON body, multipart JSON file, or merged form fields.
     */
    public function buildPayloadFromRequest(Request $request): array
    {
        if ($request->hasFile('json_file')) {
            $request->validate(['json_file' => 'required|file|max:5120']);
            $file = $request->file('json_file');
            $raw = (string) file_get_contents($file->getRealPath());
            $decoded = json_decode($raw, true);
            if (json_last_error() !== JSON_ERROR_NONE || ! is_array($decoded)) {
                throw ValidationException::withMessages(['json_file' => ['Invalid JSON.']]);
            }
            $payload = $decoded;
        } else {
            $payload = $request->all();
        }

        // Ensure keys exist from request if not in payload
        foreach (['module_id', 'start_position'] as $key) {
            if ($request->filled($key)) $payload[$key] = $request->input($key);
        }

        // Auto-detect items wrapper if user sent raw list or single item
        if (isset($payload['stem']) && ! isset($payload['items'])) {
            $payload = ['items' => [$payload]];
        } elseif (array_is_list($payload) && isset($payload[0]['stem'])) {
            $payload = ['items' => $payload];
        }

        // Map "module" string to module_id if missing
        if (empty($payload['module_id']) && ! empty($payload['items'])) {
            $first = $payload['items'][0];
            $moduleName = $first['module'] ?? ($payload['module'] ?? null);
            
            if ($moduleName && is_string($moduleName)) {
                // Format: "Reading and Writing: Module 1"
                $parts = explode(':', $moduleName);
                $sectionNameRaw = strtolower(trim($parts[0]));
                
                // Manual mapping to match DB types
                $sectionType = match($sectionNameRaw) {
                    'reading and writing', 'reading & writing', 'r&w' => 'reading_writing',
                    'math' => 'math',
                    default => Str::snake($sectionNameRaw)
                };

                $moduleNum = preg_replace('/[^0-9]/', '', $parts[1] ?? '1');
                
                $module = Module::whereHas('section', function($q) use ($sectionType) {
                    $q->where('type', $sectionType);
                })->where('module_number', $moduleNum)->first();

                if ($module) {
                    $payload['module_id'] = $module->id;
                }
            }
        }

        return $payload;
    }

    /**
     * Validate and create questions.
     */
    public function import(array $payload): array
    {
        \Illuminate\Support\Facades\Log::info('Bulk Import payload items count: ' . count($payload['items'] ?? []));
        if (!empty($payload['items'])) {
             \Illuminate\Support\Facades\Log::info('First item keys:', array_keys($payload['items'][0]));
        }
        
        $validated = $this->validate($payload);
        $module = Module::with('section')->findOrFail($validated['module_id']);
        $sectionType = $module->section?->type;

        $createdIds = [];
        $passagesCreated = 0;

        DB::transaction(function () use ($validated, $module, $sectionType, &$createdIds, &$passagesCreated) {
            $startPos = (int) ($validated['start_position'] ?? 1);
            $itemCount = count($validated['items']);

            // Auto-shift: move everything after startPos forward by itemCount
            DB::table('module_questions')
                ->where('module_id', $module->id)
                ->where('position', '>=', $startPos)
                ->increment('position', $itemCount);

            $position = $startPos;
            foreach ($validated['items'] as $index => $item) {
                $passageId = $item['passage_id'] ?? null;
                $fromInline = false;
                $inline = $item['passage'] ?? null;
                if (is_array($inline) && trim((string) ($inline['content'] ?? '')) !== '') {
                    $passage = $this->createPassageFromBulkArray($inline);
                    $passageId = $passage->id;
                    $fromInline = true;
                    $passagesCreated++;
                }

                if ($sectionType === 'reading_writing' && empty($passageId)) {
                    throw ValidationException::withMessages(["items.$index.passage" => ['Reading & Writing requires a passage.']]);
                }

                $questionAttrs = [
                    'passage_id' => $passageId,
                    'paired_passage_id' => $item['paired_passage_id'] ?? null,
                    'stem' => $item['stem'],
                    'question_type' => $item['question_type'],
                    'difficulty' => $item['difficulty'],
                    'is_pretest' => (bool) ($item['is_pretest'] ?? false),
                    'is_complete' => (bool) ($item['is_complete'] ?? true),
                    'section_type' => $sectionType,
                    'skill_domain' => $item['skill_domain'],
                    'skill_subdomain' => $item['skill_subdomain'] ?? null,
                    'spr_hint' => $item['spr_hint'] ?? null,
                    'calculator_allowed' => (bool) ($item['calculator_allowed'] ?? true),
                    'external_id' => $item['external_id'] ?? null,
                ];

                $question = Question::create($questionAttrs);
                $module->questions()->attach($question->id, ['position' => $position]);

                if ($item['question_type'] === 'multiple_choice') {
                    foreach ($item['choices'] as $ord => $choiceData) {
                        AnswerChoice::create([
                            'question_id' => $question->id,
                            'label' => $choiceData['label'],
                            'content' => $choiceData['content'],
                            'is_correct' => (bool) ($choiceData['is_correct'] ?? false),
                            'order' => (int) ($choiceData['order'] ?? ($ord + 1)),
                        ]);
                    }
                } else {
                    foreach ($item['spr_correct_answers'] as $answerText) {
                        DB::table('spr_correct_answers')->insert([
                            'question_id' => $question->id,
                            'answer' => $answerText,
                            'answer_type' => 'exact',
                            'created_at' => now(),
                        ]);
                    }
                }

                if (! empty($item['explanation'])) {
                    QuestionExplanation::create([
                        'question_id' => $question->id,
                        'explanation' => $item['explanation'],
                        'rationale_a' => $item['rationale_a'] ?? null,
                        'rationale_b' => $item['rationale_b'] ?? null,
                        'rationale_c' => $item['rationale_c'] ?? null,
                        'rationale_d' => $item['rationale_d'] ?? null,
                    ]);
                }

                $createdIds[] = $question->id;
                $position++;
            }
        });

        return ['question_ids' => $createdIds, 'passages_created' => $passagesCreated];
    }

    public function validate(array $payload): array
    {
        $payload = $this->normalizePassageStringsInItems($payload);
        $validator = Validator::make($payload, $this->bulkItemValidationRules());
        $validated = $validator->validate();

        $module = Module::with('section')->findOrFail($validated['module_id']);
        $sectionType = $module->section?->type;

        $usedPassageIds = [];

        foreach ($validated['items'] as $index => &$item) {
            $item['section_type'] = $sectionType;
            $path = 'items.'.$index;

            if ($sectionType === 'reading_writing') {
                $pId = $item['passage_id'] ?? null;
                if ($pId) {
                    if (in_array($pId, $usedPassageIds)) {
                        throw ValidationException::withMessages([$path.'.passage_id' => ['This passage is already being assigned to another question in this import.']]);
                    }
                    $usedPassageIds[] = $pId;

                    if (Question::where('passage_id', $pId)->exists()) {
                        throw ValidationException::withMessages([$path.'.passage_id' => ['This passage is already linked to an existing question in the database. R&W requires 1:1 linkage.']]);
                    }
                }

                if ($item['question_type'] === 'student_produced_response') {
                    throw ValidationException::withMessages([$path.'.question_type' => ['Reading & Writing no SPR.']]);
                }
            }

            $item['is_complete'] = true;
            if (empty($item['difficulty'])) {
                $item['difficulty'] = 'medium';
                $item['is_complete'] = false;
            }
            if (empty($item['skill_domain'])) {
                $item['skill_domain'] = $sectionType === 'math' ? 'algebra' : 'information_and_ideas';
                $item['is_complete'] = false;
            }
            if ($sectionType === 'reading_writing' && isset($item['passage']) && empty($item['passage']['genre'])) {
                $item['passage']['genre'] = 'humanities';
                // Genre missing usually doesn't mean incomplete but can set if you want
            }

            // Ensure explanation fields are carried over
            $item['explanation'] = $item['explanation'] ?? null;
            $item['rationale_a'] = $item['rationale_a'] ?? null;
            $item['rationale_b'] = $item['rationale_b'] ?? null;
            $item['rationale_c'] = $item['rationale_c'] ?? null;
            $item['rationale_d'] = $item['rationale_d'] ?? null;
        }

        return $validated;
    }

    private function normalizePassageStringsInItems(array $payload): array
    {
        if (! isset($payload['items'])) return $payload;
        foreach ($payload['items'] as $i => $row) {
            // 1. Map "domain" to "skill_domain"
            if (!isset($row['skill_domain']) && isset($row['domain'])) {
                $payload['items'][$i]['skill_domain'] = $row['domain'];
            }

            // 2. Detect and normalize question_type
            if (!isset($row['question_type'])) {
                if (isset($row['type'])) {
                    $payload['items'][$i]['question_type'] = $row['type'];
                } elseif (!isset($row['choices']) || $row['choices'] === null || $row['choices'] === '') {
                    $payload['items'][$i]['question_type'] = 'student_produced_response';
                } else {
                    $payload['items'][$i]['question_type'] = 'multiple_choice';
                }
            }

            // 3. Normalize Passage (from string to array)
            if (isset($row['passage']) && is_string($row['passage']) && trim($row['passage']) !== '') {
                $payload['items'][$i]['passage'] = ['content' => $row['passage']];
            }

            // 4. Map correct_answer to choices or spr_correct_answers
            $correctAns = $row['correct_answer'] ?? ($row['correct_choice'] ?? null);

            if ($payload['items'][$i]['question_type'] === 'multiple_choice') {
                if (isset($row['choices']) && is_array($row['choices'])) {
                    if (!array_is_list($row['choices'])) {
                        // Handle Object structure: {"A": "...", "B": "..."}
                        $normalizedChoices = [];
                        foreach ($row['choices'] as $label => $content) {
                            $normalizedChoices[] = [
                                'label' => $label,
                                'content' => $content,
                                'is_correct' => (strtoupper(trim((string)$label)) === strtoupper(trim((string)$correctAns)))
                            ];
                        }
                        $payload['items'][$i]['choices'] = $normalizedChoices;
                    }
                }
            } else {
                // Handle student_produced_response
                if (!isset($row['spr_correct_answers']) && $correctAns !== null) {
                    $payload['items'][$i]['spr_correct_answers'] = is_array($correctAns) ? $correctAns : [$correctAns];
                }
            }
        }
        return $payload;
    }

    private function bulkItemValidationRules(): array
    {
        return [
            'module_id' => 'required|exists:modules,id',
            'start_position' => 'nullable|integer',
            'items' => 'required|array|min:1',
            'items.*.stem' => 'required|string',
            'items.*.question_type' => 'required|in:multiple_choice,student_produced_response',
            'items.*.difficulty' => 'nullable|in:easy,medium,hard',
            'items.*.skill_domain' => 'nullable|string',
            'items.*.passage_id' => 'nullable|exists:passages,id',
            'items.*.passage' => 'nullable|array',
            'items.*.choices' => 'nullable|array',
            'items.*.spr_correct_answers' => 'nullable|array',
            'items.*.explanation' => 'nullable|string',
            'items.*.rationale_a' => 'nullable|string',
            'items.*.rationale_b' => 'nullable|string',
            'items.*.rationale_c' => 'nullable|string',
            'items.*.rationale_d' => 'nullable|string',
        ];
    }

    private function createPassageFromBulkArray(array $passage): Passage
    {
        return Passage::create([
            'content' => $passage['content'],
            'passage_type' => $passage['passage_type'] ?? 'single',
            'genre' => $passage['genre'] ?? 'humanities',
        ]);
    }
}
