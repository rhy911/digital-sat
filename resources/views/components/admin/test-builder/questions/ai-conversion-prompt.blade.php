{{-- Raw text pasted by teachers into ChatGPT/Claude to convert a Word/Google Doc exam into import JSON.
     Kept out of import-guide-modal.blade.php so the prompt copy can be edited without touching UI markup.
     Written to be short on purpose: every line here is context the model pays for on each run, so it
     carries rules the model cannot infer and nothing else. The skill list is generated from
     config/sat_taxonomy.php so it cannot drift from what the importer accepts. --}}
Convert a Digital SAT exam (Word/Google Doc) into import JSON. You transcribe; you never author.

Overriding rules:
- Output only what the document contains. Never invent, rephrase, translate, reorder, or rebalance the answer key.
- Copy passages, stems and choices verbatim; fix only conversion typos.
- A field not in the document is omitted, never guessed.
- Output ONLY valid JSON: no code fences, no commentary.

Shape: {"items": [ ... ]}

multiple_choice:
{"question_number":1,"question_type":"multiple_choice","passage":"Reading and Writing only; omit for Math","stem":"...","difficulty":"easy|medium|hard","skill_domain":"...","skill_subdomain":"...","choices":{"A":"...","B":"...","C":"...","D":"..."},"correct_choice":"B"}

student_produced_response (Math grid-ins only):
{"question_number":1,"question_type":"student_produced_response","stem":"...","difficulty":"...","skill_domain":"...","skill_subdomain":"...","spr_correct_answers":["4/3","1.33","1.333"]}

Add ONLY when the document contains it: explanation, rationale_a/b/c/d, strategy_tip, common_mistakes, spr_hint (answer format only, never revealing the answer), is_pretest:true (unscored trial item). No other keys — the system fills the rest.

Field notes:
- question_number: as printed, restarts at 1 per module; emit items in that order.
- correct_choice: from the answer key, exactly one of A/B/C/D.
- spr_correct_answers: every accepted form of the answer.
- difficulty: the document's label if it has one, otherwise judge it.
- skill_domain / skill_subdomain: copy exactly from the list below and keep the subdomain inside its own domain. The importer rejects anything else.

Allowed skills:
@foreach (\App\Support\SatTaxonomy::all() as $sectionType => $domains)
{{ $sectionType === 'reading_writing' ? 'Reading and Writing' : 'Math' }}
@foreach ($domains as $domain => $subdomains)
  {{ $domain }}: {{ implode(', ', $subdomains) }}
@endforeach
@endforeach

LaTeX and formatting:
- Wrap every formula, variable, unit and symbol in $$...$$, even a lone variable: $$x^2$$, $$18^\circ$$.
- Inside $$...$$ use plain LaTeX with ONE backslash: $$50\%$$, $$\$45$$, $$\frac{a}{b}$$, $$\pi$$, $$\{1,2\}$$, $$5\,\text{cm}$$.
- Never write \displaystyle (added automatically). Never use a bare * to multiply — use \times or \cdot.
- Systems of equations: two separate $$...$$ blocks split by a blank line, not \begin{cases}.
- JSON escaping: double every backslash — "$$50\\%$$", "$$\\frac{a}{b}$$". A lone backslash corrupts silently instead of erroring, because \f \t \n \b are valid JSON escapes, so \frac \text \neq \beta turn into control characters.
- \n\n starts a new paragraph; a single \n is only a space.
- **bold**, *italic*, <u>underline</u>. Never start a line with "- " or "| " unless you mean a list or table.
- Readable table: HTML <table class="min-w-full divide-y divide-slate-200">.
- Any graph, figure, chart or picture-only table: put [Media:q##_description.png] at its exact spot (q05_graph.png, q12_triangle.png — never a generic name) and export that image under that filename.

Check before output: every field traceable to the document; stem, choices and explanation describe the same question; correct_choice matches the key; domain and subdomain are on the list and belong together; the count of $$ is even in every field; no repeated stem; question_number gapless; optional fields present only if the document had them; valid JSON with every backslash doubled.

Do not convert yet and do not ask for the document yet. First reply, under 100 words and in your own words: what you may take from the source versus never invent, which fields you omit, how you write and escape LaTeX, what you check before output. Then wait.
