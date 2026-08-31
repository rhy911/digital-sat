@php
    $matrix = $chartHeatmap ?? [];
@endphp

<article class="p-card" aria-labelledby="heatmap-title">
    <div class="p-card-header">
        <div>
            <h3 id="heatmap-title" class="p-card-title">Difficulty & Domain Cross-Matrix</h3>
            <p class="p-card-subtitle">Accuracy matrix broken down by content domain across official difficulty tiers.</p>
        </div>
        <div class="flex flex-wrap items-center gap-3 text-xs text-slate-600">
            <span class="inline-flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-emerald-500"></span> &ge; 75% High</span>
            <span class="inline-flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-amber-500"></span> 50–74% Med</span>
            <span class="inline-flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-rose-500"></span> &lt; 50% Low</span>
        </div>
    </div>

    @if(!empty($matrix))
        <div class="overflow-x-auto rounded-lg border border-slate-200">
            <table class="w-full text-left text-xs border-collapse">
                <thead>
                    <tr class="bg-slate-50/90 border-b border-slate-200 text-slate-700 uppercase tracking-wider font-bold">
                        <th class="py-3 px-4">Content Domain</th>
                        <th class="py-3 px-3 text-center w-28">Easy Tier</th>
                        <th class="py-3 px-3 text-center w-28">Medium Tier</th>
                        <th class="py-3 px-3 text-center w-28">Hard Tier</th>
                        <th class="py-3 px-3 text-center w-32">Overall</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach($matrix as $sectionName => $domains)
                        <tr class="bg-slate-100/75">
                            <td colspan="5" class="py-2 px-4 font-bold text-[11px] uppercase tracking-wider text-slate-600">
                                {{ $sectionName }}
                            </td>
                        </tr>
                        @foreach($domains as $key => $row)
                            <tr class="hover:bg-slate-50/80 transition-colors">
                                <td class="py-2.5 px-4 font-semibold text-slate-800">
                                    {{ $row['label'] }}
                                </td>

                                @foreach(['easy', 'medium', 'hard'] as $tier)
                                    @php
                                        $tData = $row[$tier];
                                        $pct = $tData['pct'];
                                        $total = $tData['total'];
                                        $correct = $tData['correct'];
                                        
                                        $bgClass = 'bg-slate-50 text-slate-400';
                                        if ($total > 0) {
                                            if ($pct >= 75) $bgClass = 'bg-emerald-50 text-emerald-900 border border-emerald-300';
                                            elseif ($pct >= 50) $bgClass = 'bg-amber-50 text-amber-900 border border-amber-300';
                                            else $bgClass = 'bg-rose-50 text-rose-900 border border-rose-300';
                                        }
                                    @endphp
                                    <td class="py-2.5 px-3 text-center">
                                        @if($total > 0)
                                            <div class="inline-flex flex-col items-center justify-center min-w-[4.5rem] px-2 py-1 rounded-md {{ $bgClass }} font-bold text-xs">
                                                <span>{{ $pct }}%</span>
                                                <span class="text-[10px] font-medium opacity-80">({{ $correct }}/{{ $total }})</span>
                                            </div>
                                        @else
                                            <span class="text-slate-300 font-mono">—</span>
                                        @endif
                                    </td>
                                @endforeach

                                @php
                                    $totData = $row['total'];
                                    $totPct = $totData['pct'];
                                    $totCount = $totData['total'];
                                @endphp
                                <td class="py-2.5 px-3 text-center font-bold text-slate-700">
                                    @if($totCount > 0)
                                        <div class="inline-flex flex-col items-center justify-center min-w-[5rem] px-2 py-1 rounded-md bg-slate-100 text-slate-900 border border-slate-200 font-bold">
                                            <span>{{ $totPct }}%</span>
                                            <span class="text-[10px] font-normal text-slate-600">({{ $totData['correct'] }}/{{ $totCount }})</span>
                                        </div>
                                    @else
                                        <span class="text-slate-300 font-mono">—</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    @endforeach
                </tbody>
            </table>
        </div>
    @else
        <div class="p-empty-state">
            <x-ui.icon name="layers" class="w-10 h-10 text-slate-300 mb-2" aria-hidden="true" />
            <p class="font-semibold text-slate-700">No cross-matrix data available</p>
            <p class="text-xs text-slate-500 max-w-sm mt-1">Complete practice tests with varied difficulties to generate this heatmap.</p>
        </div>
    @endif
</article>
