@props([
    'title' => 'Domain Accuracy',
    'subtitle' => 'Accuracy across evaluated questions, lowest first.',
    'rows' => [],
    'emptyTitle' => 'No domain data yet',
    'emptyBody' => 'Complete a practice test to see your domain performance.',
    'ariaLabelledby' => 'accuracy-card-title',
    'rowLabelKey' => 'domain',
])

<article class="p-card" aria-labelledby="{{ $ariaLabelledby }}">
    <div class="p-card-header">
        <div>
            <h3 id="{{ $ariaLabelledby }}" class="p-card-title">{{ $title }}</h3>
            <p class="p-card-subtitle">{{ $subtitle }}</p>
        </div>
    </div>

    @if(!empty($rows) && count($rows))
        <div class="space-y-3">
            @foreach($rows as $row)
                @php
                    $label = $row[$rowLabelKey] ?? 'Unknown';
                    $pct = (int) ($row['percentCorrect'] ?? 0);
                    $hasSkills = !empty($row['skills']);
                    $section = $row['section'] ?? null;
                    $isRw = ($section === 'Reading and Writing' || $section === 'reading_and_writing');
                    $sectionBadge = $isRw ? 'RW' : ($section ? 'Math' : null);
                    $sectionBadgeClass = $isRw ? 'bg-teal-50 text-teal-700 border border-teal-200' : 'bg-orange-50 text-orange-700 border border-orange-200';
                    $status = $pct >= 80 ? 'success' : ($pct >= 60 ? 'warning' : 'danger');
                    $barColor = $pct >= 80 ? 'bg-emerald-500' : ($pct >= 60 ? 'bg-amber-500' : 'bg-rose-500');
                @endphp

                <div class="p-domain-item rounded-xl border border-slate-200/80 bg-white hover:border-slate-300 transition-all p-3.5"
                     @if($hasSkills) x-data="{ open: false }" @endif>

                    <div class="flex items-center justify-between gap-4 {{ $hasSkills ? 'cursor-pointer select-none' : '' }}"
                         @if($hasSkills) @click="open = !open" @endif>

                        <div class="flex-1 min-w-0">
                            <div class="flex items-center justify-between gap-2 mb-1.5">
                                <span class="font-bold text-slate-800 text-sm truncate" title="{{ $label }}">{{ $label }}</span>
                                @if($sectionBadge)
                                    <span class="shrink-0 text-[10px] font-bold uppercase px-1.5 py-0.5 rounded {{ $sectionBadgeClass }}">
                                        {{ $sectionBadge }}
                                    </span>
                                @endif
                            </div>

                            <!-- Progress Track -->
                            <div class="flex items-center gap-3">
                                <div class="flex-1 h-2 rounded-full bg-slate-100 overflow-hidden relative" role="progressbar" aria-valuenow="{{ $pct }}" aria-valuemin="0" aria-valuemax="100">
                                    <div class="absolute inset-0 h-full rounded-full {{ $barColor }}" style="transform: scaleX({{ $pct / 100 }}); transform-origin: left;"></div>
                                </div>
                                <span class="text-xs font-semibold text-slate-500 w-16 text-right tabular-nums">
                                    {{ $row['correct'] }}/{{ $row['total'] }}
                                </span>
                            </div>
                        </div>

                        <div class="flex items-center gap-2 shrink-0">
                            <x-ui.status-badge :status="$status">
                                {{ $pct }}%
                            </x-ui.status-badge>

                            @if($hasSkills)
                                <button type="button" class="p-1 text-slate-400 hover:text-slate-600 transition-transform" :class="{ 'rotate-180': open }" aria-label="Toggle sub-skills">
                                    <x-ui.icon name="chevron-down" class="w-4 h-4" aria-hidden="true" />
                                </button>
                            @endif
                        </div>
                    </div>

                    @if($hasSkills)
                        <!-- Collapsible sub-skills list -->
                        <div class="mt-3 pt-3 border-t border-slate-100 space-y-2.5" x-show="open" x-cloak x-transition>
                            @foreach($row['skills'] as $skill)
                                @php
                                    $sPct = (int) ($skill['percentCorrect'] ?? 0);
                                    $sBarColor = $sPct >= 80 ? 'bg-emerald-400' : ($sPct >= 60 ? 'bg-amber-400' : 'bg-rose-400');
                                @endphp
                                <div class="pl-3 border-l-2 border-slate-200">
                                    <div class="flex items-center justify-between text-xs mb-1">
                                        <span class="text-slate-600 font-medium">{{ $skill['name'] }}</span>
                                        <span class="text-slate-500 font-semibold tabular-nums">{{ $sPct }}% ({{ $skill['correct'] }}/{{ $skill['total'] }})</span>
                                    </div>
                                    <div class="h-1.5 rounded-full bg-slate-100 overflow-hidden relative">
                                        <div class="absolute inset-0 h-full rounded-full {{ $sBarColor }}" style="transform: scaleX({{ $sPct / 100 }}); transform-origin: left;"></div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    @else
        <div class="p-empty-state">
            <x-ui.icon name="bar-chart-2" class="w-10 h-10 text-slate-300 mb-2" aria-hidden="true" />
            <p class="font-semibold text-slate-700">{{ $emptyTitle }}</p>
            <p class="text-xs text-slate-500 max-w-sm mt-1">{{ $emptyBody }}</p>
        </div>
    @endif
</article>
