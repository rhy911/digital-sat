<?php

namespace App\Services;

use App\Models\AnswerChoice;
use App\Models\Module;
use App\Models\Passage;
use App\Models\Question;
use App\Models\QuestionExplanation;
use App\Support\SatTaxonomy;
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

        return $this->importZipFile(
            $request->file('zip_file'),
            (int) $request->input('module_id'),
            (int) $request->input('start_position', 1)
        );
    }

    /**
     * Decoupled ZIP import logic working directly on native UploadedFile and primitives.
     */
    public function importZipFile(\Illuminate\Http\UploadedFile $file, int $moduleId, int $startPosition = 1): array
    {
        @ini_set('memory_limit', '512M');
        if (!class_exists('ZipArchive')) {
            throw new \Exception('PHP ZipArchive extension is not installed or enabled.');
        }

        $zip = new ZipArchive();
        if ($zip->open($file->getRealPath()) !== true) {
            throw ValidationException::withMessages(['zip_file' => ['Could not open ZIP file.']]);
        }

        $this->assertZipIsSafeToExtract($zip);

        $tempDir = 'temp/import_' . Str::random(10);
        try {
            $tempPath = $this->extractZipToTempDir($zip, $tempDir);
            $dataFiles = $this->findZipDataFiles($tempPath);

            if (empty($dataFiles)) {
                throw ValidationException::withMessages([
                    'zip_file' => ['No .json or .csv data file found in the ZIP. Files found: '.$this->describeZipContents($tempPath).'.'],
                ]);
            }

            $allItems = [];
            $mediaProblems = [];
            foreach ($dataFiles as $dataFile) {
                $items = $this->parseZipDataFile($dataFile);

                // Media is resolved relative to THIS data file's folder. The
                // offset keeps error keys aligned with the merged item list.
                $items = $this->processZipMedia($items, $dataFile['base'], count($allItems), $mediaProblems);
                $allItems = array_merge($allItems, $items);
            }

            if ($mediaProblems !== []) {
                throw ValidationException::withMessages($mediaProblems);
            }

            return $this->import([
                'module_id' => $moduleId,
                'start_position' => $startPosition,
                'items' => $allItems,
            ]);
        } catch (ValidationException $e) {
            // Already a precise, teacher-facing message — not a server fault.
            throw $e;
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('ZIP Import Error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        } finally {
            Storage::deleteDirectory($tempDir);
        }
    }

    /**
     * Guard against ZIP bombs and path traversal before extracting to disk.
     */
    private function assertZipIsSafeToExtract(ZipArchive $zip): void
    {
        $maxFiles = 1000;
        $maxTotalSize = 1024 * 1024 * 200; // 200 MB
        $totalSize = 0;

        if ($zip->numFiles > $maxFiles) {
            $zip->close();
            throw ValidationException::withMessages(['zip_file' => ['ZIP file contains too many files.']]);
        }

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);
            if ($stat === false) continue;

            $filename = $stat['name'];
            if (str_contains($filename, '..') || str_starts_with($filename, '/') || str_starts_with($filename, '\\')) {
                $zip->close();
                throw ValidationException::withMessages(['zip_file' => ['ZIP file contains invalid paths (path traversal risk).']]);
            }

            $totalSize += $stat['size'];
        }

        if ($totalSize > $maxTotalSize) {
            $zip->close();
            throw ValidationException::withMessages(['zip_file' => ['ZIP file uncompressed size is too large.']]);
        }
    }

    private function extractZipToTempDir(ZipArchive $zip, string $tempDir): string
    {
        Storage::makeDirectory($tempDir);
        $tempPath = storage_path('app/' . $tempDir);

        if (!$zip->extractTo($tempPath)) {
            $zip->close();

            // A server path in the message would leak infrastructure detail and
            // tells the teacher nothing; a corrupt archive is the usual cause.
            throw ValidationException::withMessages([
                'zip_file' => ['The ZIP file could not be extracted. It may be corrupt — try re-creating the archive.'],
            ]);
        }
        $zip->close();

        return $tempPath;
    }

    /**
     * Recursively find every .json/.csv data file inside the extracted ZIP, skipping
     * hidden files and macOS resource-fork junk.
     */
    private function findZipDataFiles(string $tempPath): array
    {
        $dataFiles = [];
        $allFiles = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($tempPath, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($allFiles as $fileItem) {
            if (str_starts_with($fileItem->getFilename(), '.') || str_contains($fileItem->getPathname(), '__MACOSX')) {
                continue;
            }

            if (preg_match('/\.(json|csv)$/i', $fileItem->getFilename())) {
                $dataFiles[] = [
                    'path' => $fileItem->getPathname(),
                    'base' => $fileItem->getPath(),
                    'ext' => strtolower($fileItem->getExtension()),
                ];
            }
        }

        return $dataFiles;
    }

    /**
     * Parse one JSON or CSV data file (as found by findZipDataFiles) into a flat list of item arrays.
     */
    /**
     * A short, human-readable listing of what the ZIP actually contained, so
     * "no data file found" can point at the likely mistake (a nested folder, a
     * .txt instead of .json) instead of leaving the teacher guessing.
     */
    private function describeZipContents(string $tempPath): string
    {
        $names = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($tempPath, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (count($names) >= 12) {
                $names[] = '…';
                break;
            }
            $names[] = ltrim(str_replace([$tempPath, '\\'], ['', '/'], $file->getPathname()), '/');
        }

        return $names === [] ? '(the ZIP is empty)' : implode(', ', $names);
    }

    private function parseZipDataFile(array $dataFile): array
    {
        $name = basename($dataFile['path']);
        $raw = file_get_contents($dataFile['path']);

        if ($raw === false || trim($raw) === '') {
            throw ValidationException::withMessages([
                'zip_file' => ["{$name} is empty."],
            ]);
        }

        if ($dataFile['ext'] === 'json') {
            $decoded = json_decode($raw, true);

            // Used to be logged and swallowed, which turned a one-character typo
            // into "No valid question items found in ZIP data files."
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw ValidationException::withMessages([
                    'zip_file' => [sprintf(
                        '%s is not valid JSON (%s). A common cause is LaTeX written with a single backslash: write "\\\\frac", not "\\frac".',
                        $name,
                        json_last_error_msg()
                    )],
                ]);
            }

            $items = $this->extractItemsFromDecodedJson($decoded);

            if ($items === []) {
                throw ValidationException::withMessages([
                    'zip_file' => ["{$name} parsed as JSON but contains no questions. Expected {\"items\": [ ... ]}."],
                ]);
            }

            return $items;
        }

        try {
            $items = $this->csvImportService->parseCsvToItems($raw);
        } catch (ValidationException $e) {
            // The CSV service reports against a `csv_file` field that does not
            // exist on the ZIP form, so its messages never reached the teacher.
            throw ValidationException::withMessages([
                'zip_file' => array_map(
                    fn (string $message): string => "{$name}: {$message}",
                    collect($e->errors())->flatten()->all()
                ),
            ]);
        }

        if ($items === []) {
            throw ValidationException::withMessages([
                'zip_file' => ["{$name} contains no data rows below the header."],
            ]);
        }

        return $items;
    }

    /**
     * Locate the question-item list inside a decoded JSON payload, which may arrive as
     * {"items": [...]}, a bare list, a single question object, or a list nested under an
     * unknown key — teachers paste AI-generated JSON in whatever shape came out.
     */
    private function extractItemsFromDecodedJson($decoded): array
    {
        if (isset($decoded['items']) && is_array($decoded['items'])) {
            return $decoded['items'];
        }

        if (!is_array($decoded) || empty($decoded)) {
            return [];
        }

        if (array_is_list($decoded)) {
            return $decoded;
        }

        if (isset($decoded['stem'])) {
            return [$decoded];
        }

        // Fallback: search for any key that contains a list of question-shaped items
        foreach ($decoded as $val) {
            if (is_array($val) && array_is_list($val) && !empty($val) && (isset($val[0]['stem']) || isset($val[0]['question_number']))) {
                return $val;
            }
        }

        return [];
    }

    /**
     * Resolve every [Media:name.ext] placeholder against the files shipped in the
     * ZIP, copying each one into public storage.
     *
     * An unresolved placeholder used to be a Log::warning and nothing else, so a
     * package with a misspelled or missing image imported "successfully" and the
     * student was shown the literal text `[Media:q05_graph.png]`. Unresolved
     * references are now collected and reported per question.
     *
     * @param  list<array<string, mixed>>  $items
     * @param  int  $indexOffset  Position of this data file's first item in the merged list.
     * @param  array<string, list<string>>  $problems  Collected by reference, keyed for ValidationException.
     * @return list<array<string, mixed>>
     */
    private function processZipMedia(array $items, string $basePath, int $indexOffset, array &$problems): array
    {
        $validExtensions = ['png', 'jpg', 'jpeg', 'gif', 'svg', 'webp'];

        $processString = function ($str, string $itemKey, string $fieldLabel) use ($basePath, $validExtensions, &$problems) {
            if (! $str) {
                return $str;
            }

            return preg_replace_callback('/\[Media:([^\]]+)\]/i', function ($matches) use ($basePath, $validExtensions, $itemKey, $fieldLabel, &$problems) {
                // Prevent path traversal by extracting only the base name
                $filename = basename(trim($matches[1]));
                $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

                if (! in_array($ext, $validExtensions, true)) {
                    $problems[$itemKey][] = sprintf(
                        '%s: "%s" is not a supported image type. Allowed: %s.',
                        $fieldLabel,
                        $filename,
                        implode(', ', $validExtensions)
                    );

                    return $matches[0];
                }

                $foundSrc = null;
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

                if (! $foundSrc) {
                    $problems[$itemKey][] = sprintf(
                        '%s: image "%s" is referenced but not present in the ZIP. Put it next to the data file or in an images/ folder.',
                        $fieldLabel,
                        $filename
                    );

                    return $matches[0];
                }

                $content = file_get_contents($foundSrc);
                if ($content === false) {
                    $problems[$itemKey][] = sprintf('%s: image "%s" could not be read from the ZIP.', $fieldLabel, $filename);

                    return $matches[0];
                }

                $newName = Str::random(20) . '.' . pathinfo($foundSrc, PATHINFO_EXTENSION);

                // Ensure directory exists in storage/app/public/media
                if (! Storage::disk('public')->exists('media')) {
                    Storage::disk('public')->makeDirectory('media');
                }

                Storage::disk('public')->put('media/' . $newName, $content);

                return "![](/media/{$newName})";
            }, $str);
        };

        foreach ($items as $index => &$item) {
            $itemKey = 'items.' . ($indexOffset + $index) . '.media';
            $label = 'Question ' . ($indexOffset + $index + 1);

            $item['stem'] = $processString($item['stem'] ?? '', $itemKey, "{$label} stem");

            if (isset($item['passage'])) {
                if (is_string($item['passage'])) {
                    $item['passage'] = $processString($item['passage'], $itemKey, "{$label} passage");
                } elseif (isset($item['passage']['content'])) {
                    $item['passage']['content'] = $processString($item['passage']['content'], $itemKey, "{$label} passage");
                }
            }

            if (isset($item['explanation'])) {
                $item['explanation'] = $processString($item['explanation'], $itemKey, "{$label} explanation");
            }

            if (isset($item['choices']) && is_array($item['choices'])) {
                foreach ($item['choices'] as $choiceKey => &$choice) {
                    $choiceLabel = is_array($choice) ? ($choice['label'] ?? $choiceKey) : $choiceKey;

                    if (is_array($choice) && isset($choice['content'])) {
                        $choice['content'] = $processString($choice['content'], $itemKey, "{$label} choice {$choiceLabel}");
                    } elseif (is_string($choice)) {
                        $choice = $processString($choice, $itemKey, "{$label} choice {$choiceLabel}");
                    }
                }
                unset($choice);
            }

            foreach (['rationale_a', 'rationale_b', 'rationale_c', 'rationale_d'] as $rat) {
                if (isset($item[$rat])) {
                    $item[$rat] = $processString($item[$rat], $itemKey, "{$label} {$rat}");
                }
            }
        }
        unset($item);

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

        return $this->buildPayloadFromArray($payload);
    }

    /**
     * Standardize and build payload from native array, decoupled from HTTP Request.
     */
    public function buildPayloadFromArray(array $payload): array
    {
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
        $validated = $this->validate($payload);
        $module = Module::with('section')->findOrFail($validated['module_id']);
        app(TestContentLockService::class)->ensureModuleUnlocked($module);
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
                    throw ValidationException::withMessages([
                        "items.$index.passage" => ['Question '.($index + 1).' has no passage. Every Reading & Writing question needs passage text (or a passage_id).'],
                    ]);
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
                    'expected_time' => isset($item['expected_time']) && is_numeric($item['expected_time']) ? (int) $item['expected_time'] : null,
                    'spr_hint' => $item['spr_hint'] ?? null,
                    'calculator_allowed' => (bool) ($item['calculator_allowed'] ?? true),
                    'external_id' => $item['external_id'] ?? null,
                    'created_by' => auth()->id(),
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

                if (! empty($item['explanation']) || ! empty($item['strategy_tip']) || ! empty($item['common_mistakes'])) {
                    QuestionExplanation::create([
                        'question_id' => $question->id,
                        'explanation' => $item['explanation'] ?? '',
                        'rationale_a' => $item['rationale_a'] ?? null,
                        'rationale_b' => $item['rationale_b'] ?? null,
                        'rationale_c' => $item['rationale_c'] ?? null,
                        'rationale_d' => $item['rationale_d'] ?? null,
                        'strategy_tip' => $item['strategy_tip'] ?? null,
                        'common_mistakes' => $item['common_mistakes'] ?? null,
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
        $validator = Validator::make(
            $payload,
            $this->bulkItemValidationRules(),
            $this->bulkItemValidationMessages(),
            $this->bulkItemAttributeNames($payload)
        );
        $validated = $validator->validate();

        $module = Module::with('section')->findOrFail($validated['module_id']);
        $sectionType = $module->section?->type;

        $usedPassageIds = [];
        $seenStems = [];

        foreach ($validated['items'] as $index => &$item) {
            $item['section_type'] = $sectionType;
            $path = 'items.'.$index;

            if ($sectionType === 'reading_writing') {
                $pId = $item['passage_id'] ?? null;
                if ($pId) {
                    if (in_array($pId, $usedPassageIds)) {
                        throw ValidationException::withMessages([$path.'.passage_id' => ['Question '.($index + 1).' reuses passage #'.$pId.', which another question in this import already claims. Reading & Writing needs one passage per question.']]);
                    }
                    $usedPassageIds[] = $pId;

                    if (Question::where('passage_id', $pId)->exists()) {
                        throw ValidationException::withMessages([$path.'.passage_id' => ['Question '.($index + 1).' points at passage #'.$pId.', which is already linked to an existing question. Reading & Writing needs one passage per question.']]);
                    }
                }

                if ($item['question_type'] === 'student_produced_response') {
                    throw ValidationException::withMessages([$path.'.question_type' => ['Question '.($index + 1).' is a student-produced response, but Reading & Writing modules only allow multiple choice.']]);
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

            $label = 'Question '.($index + 1);

            $this->assertTaxonomyIsKnown($item, $path, $label, $sectionType);
            $this->assertAnswerKeyIsUsable($item, $path, $label);
            $this->assertContentIsImportable($item, $path, $label);

            $stemKey = preg_replace('/\s+/', ' ', trim((string) $item['stem']));
            if ($stemKey !== '' && isset($seenStems[$stemKey])) {
                throw ValidationException::withMessages([
                    $path.'.stem' => [$label.' has the same stem as question '.($seenStems[$stemKey] + 1).' in this import.'],
                ]);
            }
            $seenStems[$stemKey] = $index;

            // Ensure explanation fields are carried over
            $item['explanation'] = $item['explanation'] ?? null;
            $item['rationale_a'] = $item['rationale_a'] ?? null;
            $item['rationale_b'] = $item['rationale_b'] ?? null;
            $item['rationale_c'] = $item['rationale_c'] ?? null;
            $item['rationale_d'] = $item['rationale_d'] ?? null;
            $item['strategy_tip'] = $item['strategy_tip'] ?? null;
            $item['common_mistakes'] = $item['common_mistakes'] ?? null;
        }

        return $validated;
    }

    /**
     * skill_domain and skill_subdomain are plain strings in the database and
     * are used directly as grouping keys by the score report and the analytics
     * summaries. An invented value never fails at import time — it just becomes
     * a one-question "skill" and quietly ruins weak-area reporting, so reject
     * it here where a human is still looking at the preview.
     *
     * @param  array<string, mixed>  $item
     */
    private function assertTaxonomyIsKnown(array $item, string $path, string $label, ?string $sectionType): void
    {
        $domain = (string) ($item['skill_domain'] ?? '');
        if ($domain === '') {
            return;
        }

        if (! SatTaxonomy::isValidDomain($domain, $sectionType)) {
            throw ValidationException::withMessages([
                $path.'.skill_domain' => [
                    "{$label}: unknown skill_domain \"{$domain}\". Allowed: ".implode(', ', SatTaxonomy::domains($sectionType)).'.',
                ],
            ]);
        }

        $subdomain = trim((string) ($item['skill_subdomain'] ?? ''));
        if ($subdomain === '' || SatTaxonomy::isValidSubdomain($domain, $subdomain)) {
            return;
        }

        $belongsTo = SatTaxonomy::domainForSubdomain($subdomain);
        $message = $belongsTo !== null
            ? "{$label}: skill_subdomain \"{$subdomain}\" belongs to domain \"{$belongsTo}\", not \"{$domain}\"."
            : "{$label}: unknown skill_subdomain \"{$subdomain}\".";

        throw ValidationException::withMessages([
            $path.'.skill_subdomain' => [
                $message.' Allowed for '.$domain.': '.implode(', ', SatTaxonomy::subdomains($domain)).'.',
            ],
        ]);
    }

    /**
     * A question with no correct answer imports cleanly today and marks every
     * student wrong forever. Catch it before it reaches the database.
     *
     * @param  array<string, mixed>  $item
     */
    private function assertAnswerKeyIsUsable(array $item, string $path, string $label): void
    {
        if (($item['question_type'] ?? null) === 'multiple_choice') {
            $choices = $item['choices'] ?? null;

            if (! is_array($choices) || count($choices) < 2) {
                throw ValidationException::withMessages([
                    $path.'.choices' => ["{$label} is multiple choice but has fewer than two answer choices."],
                ]);
            }

            $correct = array_filter($choices, fn ($choice) => is_array($choice) && ! empty($choice['is_correct']));
            if (count($correct) !== 1) {
                $labels = implode(', ', array_map(
                    fn ($choice) => (string) ($choice['label'] ?? '?'),
                    is_array($choices) ? $choices : []
                ));

                throw ValidationException::withMessages([
                    $path.'.correct_choice' => [
                        count($correct) === 0
                            ? "{$label}: no answer choice is marked correct — check that correct_choice matches one of: {$labels}."
                            : "{$label}: exactly one answer choice may be marked correct, found ".count($correct).'.',
                    ],
                ]);
            }

            return;
        }

        $answers = array_filter(
            (array) ($item['spr_correct_answers'] ?? []),
            fn ($answer) => trim((string) $answer) !== ''
        );

        if ($answers === []) {
            throw ValidationException::withMessages([
                $path.'.spr_correct_answers' => ["{$label} is a student-produced response but has no accepted answer. Add spr_correct_answers, for example [\"3\", \"3.0\"]."],
            ]);
        }
    }

    /**
     * Two failure modes that otherwise reach students as garbled formulas.
     *
     * Control characters mean the source JSON spelled LaTeX with a single
     * backslash: `\frac` is a valid JSON escape for form feed, `\text` for tab,
     * `\neq` for newline. Those parse without error and silently corrupt the
     * formula, unlike `\pi` or `\%` which fail loudly at decode time.
     *
     * An odd number of `$$` means a delimiter was dropped, so the rest of the
     * field renders as raw LaTeX source.
     *
     * @param  array<string, mixed>  $item
     */
    private function assertContentIsImportable(array $item, string $path, string $label): void
    {
        foreach ($this->contentFields($item) as $field => $value) {
            $where = "{$label} {$this->fieldLabel($field)}";

            if (preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', $value)) {
                throw ValidationException::withMessages([
                    $path.'.'.$field => [
                        "{$where} contains control characters, which means LaTeX backslashes were not escaped for JSON. Write \"\\\\frac\" and \"\\\\text\" in the JSON file, not \"\\frac\" and \"\\text\".",
                    ],
                ]);
            }

            if (preg_match('/\$\$[^$]*\t/', $value)) {
                throw ValidationException::withMessages([
                    $path.'.'.$field => [
                        "{$where} contains a tab inside a \$\$...\$\$ formula, which usually means \"\\text\" was written with a single backslash in the JSON file.",
                    ],
                ]);
            }

            if (substr_count($value, '$$') % 2 !== 0) {
                throw ValidationException::withMessages([
                    $path.'.'.$field => [
                        "{$where} has unbalanced \$\$ delimiters — ".substr_count($value, '$$').' found, and every formula must open and close with $$.',
                    ],
                ]);
            }
        }
    }

    /**
     * Human label for an item field, including `choices.A` style keys.
     */
    private function fieldLabel(string $field): string
    {
        if (str_starts_with($field, 'choices.')) {
            return 'choice '.substr($field, strlen('choices.'));
        }

        return self::ITEM_FIELD_LABELS[$field] ?? $field;
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, string>
     */
    private function contentFields(array $item): array
    {
        $fields = [];

        foreach (['stem', 'explanation', 'rationale_a', 'rationale_b', 'rationale_c', 'rationale_d', 'strategy_tip', 'common_mistakes', 'spr_hint'] as $key) {
            if (is_string($item[$key] ?? null) && $item[$key] !== '') {
                $fields[$key] = $item[$key];
            }
        }

        $passage = $item['passage'] ?? null;
        if (is_array($passage) && is_string($passage['content'] ?? null)) {
            $fields['passage'] = $passage['content'];
        } elseif (is_string($passage) && $passage !== '') {
            $fields['passage'] = $passage;
        }

        foreach ((array) ($item['choices'] ?? []) as $ord => $choice) {
            $content = is_array($choice) ? ($choice['content'] ?? null) : $choice;
            if (is_string($content) && $content !== '') {
                $label = is_array($choice) ? ($choice['label'] ?? $ord) : $ord;
                $fields['choices.'.$label] = $content;
            }
        }

        return $fields;
    }

    private function normalizePassageStringsInItems(array $payload): array
    {
        if (! isset($payload['items'])) return $payload;
        foreach ($payload['items'] as $i => $row) {
            // 1. Map "domain" to "skill_domain" and "time_expected" to "expected_time"
            if (!isset($row['skill_domain']) && isset($row['domain'])) {
                $payload['items'][$i]['skill_domain'] = $row['domain'];
            }
            if (!isset($row['expected_time']) && isset($row['time_expected'])) {
                $payload['items'][$i]['expected_time'] = $row['time_expected'];
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
                    } elseif (isset($row['choices'][0]) && is_string($row['choices'][0])) {
                        // Handle List of Strings structure: ["...", "..."]
                        $normalizedChoices = [];
                        $labels = ['A', 'B', 'C', 'D'];
                        foreach ($row['choices'] as $idx => $content) {
                            $label = $labels[$idx] ?? chr(65 + $idx);
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

    /**
     * Human labels for each item field, used to build attribute names.
     *
     * @var array<string, string>
     */
    private const ITEM_FIELD_LABELS = [
        'stem' => 'stem',
        'question_type' => 'question type',
        'difficulty' => 'difficulty',
        'skill_domain' => 'skill domain',
        'skill_subdomain' => 'skill subdomain',
        'passage' => 'passage',
        'passage_id' => 'passage id',
        'paired_passage_id' => 'paired passage id',
        'choices' => 'answer choices',
        'correct_choice' => 'correct answer',
        'spr_correct_answers' => 'accepted answers',
        'spr_hint' => 'answer hint',
        'explanation' => 'explanation',
        'rationale_a' => 'rationale A',
        'rationale_b' => 'rationale B',
        'rationale_c' => 'rationale C',
        'rationale_d' => 'rationale D',
        'strategy_tip' => 'strategy tip',
        'common_mistakes' => 'common mistakes',
        'spr_hint_text' => 'answer hint',
        'is_pretest' => 'pretest flag',
        'calculator_allowed' => 'calculator flag',
        'expected_time' => 'expected time',
        'external_id' => 'external id',
        'media' => 'media',
    ];

    /**
     * Turn `items.1.stem` into `Question 2 stem`.
     *
     * Validation keys are zero-indexed and mean nothing to whoever wrote the
     * file, so "The items.1.stem field is required." sent teachers looking at
     * the wrong question.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, string>
     */
    private function bulkItemAttributeNames(array $payload): array
    {
        $attributes = ['items' => 'questions', 'module_id' => 'destination module'];

        foreach (array_keys((array) ($payload['items'] ?? [])) as $index) {
            if (! is_int($index)) {
                continue;
            }

            foreach (self::ITEM_FIELD_LABELS as $field => $label) {
                $attributes["items.{$index}.{$field}"] = 'Question '.($index + 1).' '.$label;
            }

            foreach (['A', 'B', 'C', 'D'] as $ord => $choiceLabel) {
                $attributes["items.{$index}.choices.{$ord}.content"] = 'Question '.($index + 1).' choice '.$choiceLabel;
            }
        }

        return $attributes;
    }

    /**
     * Rule-specific messages. The defaults name the rule but never the accepted
     * values, so "The selected items.3.question_type is invalid." left the
     * author with no way to work out what to write instead.
     *
     * @return array<string, string>
     */
    private function bulkItemValidationMessages(): array
    {
        return [
            'module_id.required' => 'Choose the destination module before importing.',
            'module_id.exists' => 'The destination module no longer exists.',

            'items.required' => 'No questions found to import.',
            'items.array' => 'No questions found to import — expected a list under "items".',
            'items.min' => 'No questions found to import.',

            'items.*.stem.required' => ':attribute is missing. Every question needs stem text.',
            'items.*.question_type.required' => ':attribute is missing — use "multiple_choice" or "student_produced_response".',
            'items.*.question_type.in' => ':attribute must be "multiple_choice" or "student_produced_response".',
            'items.*.difficulty.in' => ':attribute must be "easy", "medium" or "hard".',
            'items.*.choices.array' => ':attribute must be a list, or an object like {"A": "...", "B": "...", "C": "...", "D": "..."}.',
            'items.*.spr_correct_answers.array' => ':attribute must be a list of strings, for example ["3", "3.0"].',
            'items.*.passage.array' => ':attribute must be passage text, or an object with a "content" key.',
            'items.*.passage_id.exists' => ':attribute points at a passage that does not exist.',
            'items.*.paired_passage_id.exists' => ':attribute points at a passage that does not exist.',
            'items.*.is_pretest.boolean' => ':attribute must be true or false (or 1 / 0).',
            'items.*.calculator_allowed.boolean' => ':attribute must be true or false (or 1 / 0).',
            'items.*.expected_time.integer' => ':attribute must be an integer (seconds).',
            'items.*.expected_time.min' => ':attribute must be greater than or equal to 0.',
        ];
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
            'items.*.skill_subdomain' => 'nullable|string',
            'items.*.passage_id' => 'nullable|exists:passages,id',
            'items.*.paired_passage_id' => 'nullable|exists:passages,id',
            'items.*.passage' => 'nullable|array',
            'items.*.choices' => 'nullable|array',
            'items.*.spr_correct_answers' => 'nullable|array',
            'items.*.explanation' => 'nullable|string',
            'items.*.rationale_a' => 'nullable|string',
            'items.*.rationale_b' => 'nullable|string',
            'items.*.rationale_c' => 'nullable|string',
            'items.*.rationale_d' => 'nullable|string',
            'items.*.strategy_tip' => 'nullable|string',
            'items.*.common_mistakes' => 'nullable|string',
            'items.*.spr_hint' => 'nullable|string',
            'items.*.calculator_allowed' => 'nullable|boolean',
            'items.*.is_pretest' => 'nullable|boolean',
            'items.*.expected_time' => 'nullable|integer|min:0',
            'items.*.external_id' => 'nullable|string',
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
