import { 
    BULK_PREVIEW_URL, CSV_BULK_PREVIEW_URL, BULK_STORE_URL, CSV_BULK_URL 
} from '../core/config.js';
import { 
    showAlert, showCustomConfirm, getTomSelectValue, humanizeUnderscores, 
    processMedia, captureTomSelectPreservation 
} from '../utils/helpers.js';

export function setBulkQuestionsJson(obj) {
    const ta = document.getElementById('bulkQuestionsJson');
    if (ta) {
        ta.value = JSON.stringify(obj, null, 2);
    }
}

export function downloadJsonFile(filename, obj) {
    const blob = new Blob([JSON.stringify(obj, null, 2)], { type: 'application/json;charset=utf-8' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = filename;
    a.rel = 'noopener';
    document.body.appendChild(a);
    a.click();
    a.remove();
    URL.revokeObjectURL(url);
}

export function csvEscapeCell(val) {
    const s = String(val);
    if (/[",\n\r]/.test(s)) {
        return '"' + s.replace(/"/g, '""') + '"';
    }
    return s;
}

export function downloadCsvFile(filename, rows) {
    const lines = rows.map(function (row) {
        return row.map(csvEscapeCell).join(',');
    });
    const blob = new Blob([lines.join('\r\n')], { type: 'text/csv;charset=utf-8' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = filename;
    a.rel = 'noopener';
    document.body.appendChild(a);
    a.click();
    a.remove();
    URL.revokeObjectURL(url);
}

// ... include all the validation grid logic here too
export function validateItemLocal(item, sectionType) {
    const blockers = {};
    const warnings = {};

    if (!item.stem || item.stem.trim() === '') {
        blockers.stem = "Question stem is required.";
    }

    const qType = item.question_type || '';
    if (qType !== 'multiple_choice' && qType !== 'student_produced_response') {
        blockers.question_type = "Question type must be multiple_choice or student_produced_response.";
    }

    if (sectionType === 'reading_writing') {
        if (qType === 'student_produced_response') {
            blockers.question_type = "Reading & Writing section only allows multiple choice questions.";
        }
        const passageContent = item.passage_content || (item.passage ? (typeof item.passage === 'string' ? item.passage : item.passage.content) : '') || '';
        if (passageContent.trim() === '') {
            blockers.passage_content = "Passage content is required for Reading & Writing questions.";
        }
    }

    if (qType === 'multiple_choice') {
        const correctChoice = String(item.correct_choice || '').trim().toUpperCase();
        if (!['A', 'B', 'C', 'D'].includes(correctChoice)) {
            blockers.correct_choice = "Correct choice is required and must be A, B, C, or D.";
        }
        if (!item.choices || item.choices.length < 4 || item.choices.some(c => !c.content || c.content.trim() === '')) {
            blockers.choices = "Multiple choice questions require choices A, B, C, and D with text.";
        }
    } else if (qType === 'student_produced_response') {
        const sprAnswers = item.spr_correct_answers;
        if (!sprAnswers || sprAnswers.length === 0 || sprAnswers.every(ans => !ans || ans.trim() === '')) {
            blockers.spr_correct_answers = "At least one accepted correct answer is required for SPR.";
        }
    }

    const difficulty = String(item.difficulty || '').trim().toLowerCase();
    if (!['easy', 'medium', 'hard'].includes(difficulty)) {
        warnings.difficulty = "Difficulty is missing or invalid. Will default to 'medium'.";
    }

    const domain = String(item.skill_domain || '').trim();
    if (!domain) {
        warnings.skill_domain = "Domain is missing. Will default based on section type.";
    }

    return { blockers, warnings };
}

export function flatToStructured(flat) {
    const item = {
        question_type: flat.question_type || 'multiple_choice',
        difficulty: flat.difficulty || 'medium',
        skill_domain: flat.skill_domain || '',
        stem: flat.stem || '',
        explanation: flat.explanation || '',
    };

    if (flat.passage_content && flat.passage_content.trim() !== '') {
        item.passage = {
            content: flat.passage_content,
            source_title: flat.passage_source_title || ''
        };
        item.passage_content = flat.passage_content;
    }

    if (item.question_type === 'multiple_choice') {
        item.choices = [
            { label: 'A', content: flat.choice_a || '', is_correct: flat.correct_choice === 'A', order: 1 },
            { label: 'B', content: flat.choice_b || '', is_correct: flat.correct_choice === 'B', order: 2 },
            { label: 'C', content: flat.choice_c || '', is_correct: flat.correct_choice === 'C', order: 3 },
            { label: 'D', content: flat.choice_d || '', is_correct: flat.correct_choice === 'D', order: 4 },
        ];
        item.correct_choice = flat.correct_choice;
    } else {
        let spr = flat.spr_correct_answers || '';
        if (Array.isArray(spr)) {
            item.spr_correct_answers = spr;
        } else {
            item.spr_correct_answers = spr.split(/[|;]/).map(a => a.trim()).filter(Boolean);
        }
    }

    return item;
}

export function structuredToFlat(item, index) {
    const flat = {
        row_index: index + 1,
        question_type: item.question_type || 'multiple_choice',
        difficulty: item.difficulty || 'medium',
        skill_domain: item.skill_domain || '',
        stem: item.stem || '',
        passage_content: item.passage_content || (item.passage ? (typeof item.passage === 'string' ? item.passage : item.passage.content) : '') || '',
        explanation: item.explanation || '',
        choice_a: '',
        choice_b: '',
        choice_c: '',
        choice_d: '',
        correct_choice: item.correct_choice || '',
        spr_correct_answers: '',
        original_errors: item.errors || []
    };

    if (item.choices && Array.isArray(item.choices)) {
        item.choices.forEach(c => {
            const label = c.label.toUpperCase();
            if (label === 'A') flat.choice_a = c.content || '';
            if (label === 'B') flat.choice_b = c.content || '';
            if (label === 'C') flat.choice_c = c.content || '';
            if (label === 'D') flat.choice_d = c.content || '';
            if (c.is_correct) flat.correct_choice = label;
        });
    }

    if (item.spr_correct_answers) {
        if (Array.isArray(item.spr_correct_answers)) {
            flat.spr_correct_answers = item.spr_correct_answers.join('|');
        } else {
            flat.spr_correct_answers = item.spr_correct_answers;
        }
    }

    return flat;
}
