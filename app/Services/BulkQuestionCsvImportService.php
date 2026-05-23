<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class BulkQuestionCsvImportService
{
    /**
     * Parse uploaded CSV and return payload for the import pipeline.
     *
     * @return array
     */
    public function getPayloadFromRequest(Request $request): array
    {
        $validated = $request->validate([
            'csv_file' => ['required', 'file', 'max:5120', function (string $attribute, mixed $value, \Closure $fail): void {
                if (! $value instanceof \Illuminate\Http\UploadedFile) {
                    return;
                }
                $ext = strtolower($value->getClientOriginalExtension());
                if (! in_array($ext, ['csv', 'txt'], true)) {
                    $fail('The csv file must be a .csv or .txt file.');
                }
            }],
            'module_id' => 'required|exists:modules,id',
            'start_position' => 'required|integer|min:1',
        ]);

        return $this->parseCsvFileToPayload(
            $request->file('csv_file'),
            (int) $validated['module_id'],
            (int) $validated['start_position']
        );
    }

    /**
     * Parse an UploadedFile CSV to pipeline payload directly (Request decoupled).
     */
    public function parseCsvFileToPayload(\Illuminate\Http\UploadedFile $file, int $moduleId, int $startPosition): array
    {
        if (! $file->isValid()) {
            throw ValidationException::withMessages([
                'csv_file' => ['The uploaded file is invalid.'],
            ]);
        }

        $raw = (string) file_get_contents($file->getRealPath());
        $items = $this->parseCsvToItems($raw);

        return [
            'module_id' => $moduleId,
            'start_position' => $startPosition,
            'items' => $items,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function parseCsvToItems(string $raw, bool $tolerant = false): array
    {
        if (str_starts_with($raw, "\xEF\xBB\xBF")) {
            $raw = substr($raw, 3);
        }

        $handle = fopen('php://memory', 'r+b');
        if ($handle === false) {
            throw ValidationException::withMessages([
                'csv_file' => ['Could not read CSV content.'],
            ]);
        }
        fwrite($handle, $raw);
        rewind($handle);

        $header = fgetcsv($handle);
        if ($header === false || $header === [null] || $header === ['']) {
            fclose($handle);
            throw ValidationException::withMessages([
                'csv_file' => ['CSV must include a header row.'],
            ]);
        }

        $headerMap = [];
        foreach ($header as $i => $col) {
            $key = strtolower(trim((string) $col));
            if ($key !== '') {
                $headerMap[$i] = $key;
            }
        }

        $items = [];
        $rowNum = 1;
        while (($row = fgetcsv($handle)) !== false) {
            $rowNum++;
            if ($this->csvRowIsEmpty($row)) {
                continue;
            }
            $assoc = [];
            foreach ($row as $i => $cell) {
                if (! isset($headerMap[$i])) {
                    continue;
                }
                $assoc[$headerMap[$i]] = $cell;
            }
            $items[] = $this->mapCsvRowToItem($assoc, $rowNum, $tolerant);
        }
        fclose($handle);

        if ($items === [] && !$tolerant) {
            throw ValidationException::withMessages([
                'csv_file' => ['No data rows found after the header.'],
            ]);
        }

        return $items;
    }

    /**
     * @param  array<int, string|null>  $row
     */
    private function csvRowIsEmpty(array $row): bool
    {
        foreach ($row as $cell) {
            if (trim((string) $cell) !== '') {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<string, string|null>  $row
     * @return array<string, mixed>
     */
    private function mapCsvRowToItem(array $row, int $rowNum, bool $tolerant = false): array
    {
        $errors = [];
        $g = fn (string $k) => trim((string) ($row[$k] ?? ''));

        $questionType = $g('question_type');
        $difficulty = $g('difficulty');
        $skillDomain = $g('skill_domain');
        $stem = $row['stem'] ?? '';
        if (is_string($stem)) {
            $stem = trim($stem);
        } else {
            $stem = '';
        }

        if ($questionType === '' || $difficulty === '' || $skillDomain === '' || $stem === '') {
            $missing = [];
            if ($questionType === '') $missing[] = 'question_type';
            if ($difficulty === '') $missing[] = 'difficulty';
            if ($skillDomain === '') $missing[] = 'skill_domain';
            if ($stem === '') $missing[] = 'stem';
            
            $errMsg = "Row {$rowNum}: " . implode(', ', $missing) . " are required.";
            if (!$tolerant) {
                throw ValidationException::withMessages([
                    'csv_file' => [$errMsg],
                ]);
            }
            $errors[] = $errMsg;
        }

        $item = [
            'question_type' => $questionType,
            'difficulty' => $difficulty,
            'skill_domain' => $skillDomain,
            'stem' => $stem,
        ];

        $opt = function (string $csvKey, string $itemKey) use (&$item, $g, $row): void {
            $v = $g($csvKey);
            if ($v !== '') {
                $item[$itemKey] = $v;
            }
        };

        $opt('skill_subdomain', 'skill_subdomain');
        $opt('external_id', 'external_id');
        $opt('spr_hint', 'spr_hint');
        $opt('explanation', 'explanation');
        $opt('rationale_a', 'rationale_a');
        $opt('rationale_b', 'rationale_b');
        $opt('rationale_c', 'rationale_c');
        $opt('rationale_d', 'rationale_d');
        $opt('strategy_tip', 'strategy_tip');
        $opt('common_mistakes', 'common_mistakes');

        if ($g('is_pretest') !== '') {
            $item['is_pretest'] = $this->parseBool($g('is_pretest'));
        }
        if ($g('calculator_allowed') !== '') {
            $item['calculator_allowed'] = $this->parseBool($g('calculator_allowed'));
        }
        if ($g('question_number') !== '' && is_numeric($g('question_number'))) {
            $item['question_number'] = (int) $g('question_number');
        }
        if ($g('passage_id') !== '' && is_numeric($g('passage_id'))) {
            $item['passage_id'] = (int) $g('passage_id');
        }
        if ($g('paired_passage_id') !== '' && is_numeric($g('paired_passage_id'))) {
            $item['paired_passage_id'] = (int) $g('paired_passage_id');
        }

        $passageContent = isset($row['passage_content']) ? trim((string) $row['passage_content']) : '';
        if ($passageContent !== '') {
            $passage = ['content' => $passageContent];
            foreach (['passage_type', 'genre', 'word_count', 'source_title', 'source_author', 'source_year'] as $suffix) {
                $ck = 'passage_'.$suffix;
                $val = $g($ck);
                if ($val === '') {
                    continue;
                }
                if ($suffix === 'word_count' || $suffix === 'source_year') {
                    if (is_numeric($val)) {
                        $passage[$suffix] = (int) $val;
                    }
                } else {
                    $passage[$suffix] = $val;
                }
            }
            $item['passage'] = $passage;
        }

        if ($questionType === 'multiple_choice') {
            $labels = ['A', 'B', 'C', 'D'];
            $choices = [];
            foreach ($labels as $idx => $label) {
                $lk = 'choice_'.strtolower($label).'_label';
                $ck = 'choice_'.strtolower($label).'_content';
                $content = isset($row[$ck]) ? trim((string) $row[$ck]) : '';
                if ($content === '') {
                    continue;
                }
                $lbl = $g($lk);
                if ($lbl === '') {
                    $lbl = $label;
                }
                $choices[] = [
                    'label' => $lbl,
                    'content' => $content,
                    'is_correct' => false,
                    'order' => $idx + 1,
                ];
            }
            if ($choices === []) {
                $errMsg = "Row {$rowNum}: multiple_choice requires at least one choice_*_content column with text.";
                if (!$tolerant) {
                    throw ValidationException::withMessages([
                        'csv_file' => [$errMsg],
                    ]);
                }
                $errors[] = $errMsg;
            }
            $correct = strtoupper($g('correct_choice'));
            if ($correct === '') {
                $errMsg = "Row {$rowNum}: correct_choice is required for multiple_choice (A, B, C, or D).";
                if (!$tolerant) {
                    throw ValidationException::withMessages([
                        'csv_file' => [$errMsg],
                    ]);
                }
                $errors[] = $errMsg;
            }
            $marked = false;
            foreach ($choices as $idx => $c) {
                $lab = strtoupper((string) $c['label']);
                if ($lab === $correct || strtoupper(substr($lab, 0, 1)) === $correct) {
                    $choices[$idx]['is_correct'] = true;
                    $marked = true;
                    break;
                }
            }
            if ($correct !== '' && !$marked) {
                $errMsg = "Row {$rowNum}: correct_choice must match a choice label (e.g. A).";
                if (!$tolerant) {
                    throw ValidationException::withMessages([
                        'csv_file' => [$errMsg],
                    ]);
                }
                $errors[] = $errMsg;
            }
            $item['choices'] = $choices;
        } else {
            $sprRaw = $g('spr_correct_answers');
            if ($sprRaw === '') {
                $errMsg = "Row {$rowNum}: spr_correct_answers is required for student_produced_response (use | to separate multiple accepted answers).";
                if (!$tolerant) {
                    throw ValidationException::withMessages([
                        'csv_file' => [$errMsg],
                    ]);
                }
                $errors[] = $errMsg;
            }
            $answers = array_values(array_filter(array_map('trim', preg_split('/[|;]/', $sprRaw) ?: [])));
            if ($sprRaw !== '' && $answers === []) {
                $errMsg = "Row {$rowNum}: spr_correct_answers could not be parsed.";
                if (!$tolerant) {
                    throw ValidationException::withMessages([
                        'csv_file' => [$errMsg],
                    ]);
                }
                $errors[] = $errMsg;
            }
            $item['spr_correct_answers'] = $answers;
        }

        if ($tolerant) {
            $item['errors'] = $errors;
        }

        return $item;
    }

    private function parseBool(string $v): bool
    {
        $n = strtolower(trim($v));

        return in_array($n, ['1', 'true', 'yes', 'y'], true);
    }
}
