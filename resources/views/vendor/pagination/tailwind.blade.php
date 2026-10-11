@if ($paginator->hasPages())
    <nav role="navigation" aria-label="{{ __('Pagination Navigation') }}" class="flex items-center justify-between w-full py-2">
        {{-- Mobile Compact View (< 640px) --}}
        <div class="flex items-center justify-between w-full gap-2 sm:hidden">
            @if ($paginator->onFirstPage())
                <span aria-disabled="true" aria-label="{{ __('pagination.previous') }}" class="inline-flex items-center justify-center gap-1.5 h-9 px-3 text-xs font-medium text-slate-300 bg-slate-50/50 border border-slate-200/60 rounded-lg cursor-not-allowed select-none">
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg>
                    <span>{!! __('pagination.previous') !!}</span>
                </span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="{{ __('pagination.previous') }}" class="inline-flex items-center justify-center gap-1.5 h-9 px-3 text-xs font-medium text-slate-700 bg-white border border-slate-200/80 rounded-lg shadow-xs hover:bg-slate-50 hover:text-slate-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand/30 transition-colors">
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg>
                    <span>{!! __('pagination.previous') !!}</span>
                </a>
            @endif

            <span class="text-xs font-medium text-slate-500 tabular-nums">
                {{ __('Page') }} <span class="font-semibold text-slate-800">{{ $paginator->currentPage() }}</span> {{ __('of') }} <span class="font-semibold text-slate-800">{{ $paginator->lastPage() }}</span>
            </span>

            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="{{ __('pagination.next') }}" class="inline-flex items-center justify-center gap-1.5 h-9 px-3 text-xs font-medium text-slate-700 bg-white border border-slate-200/80 rounded-lg shadow-xs hover:bg-slate-50 hover:text-slate-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand/30 transition-colors">
                    <span>{!! __('pagination.next') !!}</span>
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m9 18 6-6-6-6"/></svg>
                </a>
            @else
                <span aria-disabled="true" aria-label="{{ __('pagination.next') }}" class="inline-flex items-center justify-center gap-1.5 h-9 px-3 text-xs font-medium text-slate-300 bg-slate-50/50 border border-slate-200/60 rounded-lg cursor-not-allowed select-none">
                    <span>{!! __('pagination.next') !!}</span>
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m9 18 6-6-6-6"/></svg>
                </span>
            @endif
        </div>

        {{-- Desktop Extended View (>= 640px) --}}
        <div class="hidden sm:flex sm:items-center sm:justify-between sm:gap-4 w-full">
            <div>
                <p class="text-xs sm:text-sm text-slate-500 leading-normal">
                    {!! __('Showing') !!}
                    @if ($paginator->firstItem())
                        <span class="font-semibold text-slate-800 tabular-nums">{{ $paginator->firstItem() }}</span>
                        {!! __('to') !!}
                        <span class="font-semibold text-slate-800 tabular-nums">{{ $paginator->lastItem() }}</span>
                    @else
                        <span class="font-semibold text-slate-800 tabular-nums">{{ $paginator->count() }}</span>
                    @endif
                    {!! __('of') !!}
                    <span class="font-semibold text-slate-800 tabular-nums">{{ $paginator->total() }}</span>
                    {!! __('results') !!}
                </p>
            </div>

            <div class="inline-flex items-center gap-1.5" role="list">
                {{-- Previous Page Button --}}
                @if ($paginator->onFirstPage())
                    <span aria-disabled="true" aria-label="{{ __('pagination.previous') }}" class="inline-flex items-center justify-center h-9 w-9 text-slate-300 bg-slate-50/50 border border-slate-200/60 rounded-lg cursor-not-allowed select-none">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg>
                    </span>
                @else
                    <a href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="{{ __('pagination.previous') }}" class="inline-flex items-center justify-center h-9 w-9 text-slate-600 bg-white border border-slate-200/80 rounded-lg shadow-xs hover:bg-slate-50 hover:text-slate-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand/30 transition-colors">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg>
                    </a>
                @endif

                {{-- Page Number Elements --}}
                @foreach ($elements as $element)
                    {{-- "Three Dots" Separator --}}
                    @if (is_string($element))
                        <span aria-hidden="true" class="inline-flex items-center justify-center h-9 min-w-9 px-1 text-sm font-medium text-slate-400 select-none">
                            {{ $element }}
                        </span>
                    @endif

                    {{-- Array Of Links --}}
                    @if (is_array($element))
                        @foreach ($element as $page => $url)
                            @if ($page == $paginator->currentPage())
                                <span aria-current="page">
                                    <span class="inline-flex items-center justify-center h-9 min-w-9 px-3 text-sm font-semibold text-white bg-brand border border-brand rounded-lg shadow-xs tabular-nums select-none">
                                        {{ $page }}
                                    </span>
                                </span>
                            @else
                                <a href="{{ $url }}" aria-label="{{ __('Go to page :page', ['page' => $page]) }}" class="inline-flex items-center justify-center h-9 min-w-9 px-3 text-sm font-medium text-slate-700 bg-white border border-slate-200/80 rounded-lg shadow-xs hover:bg-slate-50 hover:text-slate-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand/30 transition-colors tabular-nums">
                                    {{ $page }}
                                </a>
                            @endif
                        @endforeach
                    @endif
                @endforeach

                {{-- Next Page Button --}}
                @if ($paginator->hasMorePages())
                    <a href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="{{ __('pagination.next') }}" class="inline-flex items-center justify-center h-9 w-9 text-slate-600 bg-white border border-slate-200/80 rounded-lg shadow-xs hover:bg-slate-50 hover:text-slate-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand/30 transition-colors">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m9 18 6-6-6-6"/></svg>
                    </a>
                @else
                    <span aria-disabled="true" aria-label="{{ __('pagination.next') }}" class="inline-flex items-center justify-center h-9 w-9 text-slate-300 bg-slate-50/50 border border-slate-200/60 rounded-lg cursor-not-allowed select-none">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m9 18 6-6-6-6"/></svg>
                    </span>
                @endif
            </div>
        </div>
    </nav>
@endif
