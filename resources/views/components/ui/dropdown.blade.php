@props(['align' => 'right', 'width' => '48', 'contentClasses' => 'bg-white'])

@php
    switch ($width) {
        case '48':
            $widthClasses = 'w-48';
            break;
        case '56':
            $widthClasses = 'w-56';
            break;
        case '64':
            $widthClasses = 'w-64';
            break;
        case 'auto':
        default:
            $widthClasses = 'w-auto min-w-[12rem]';
            break;
    }
@endphp

{{-- Attributes are merged onto the root so a caller can observe the menu's own
     state (e.g. x-bind:data-menu-open="open"); they were silently dropped before. --}}
<div {{ $attributes->merge(['class' => 'relative inline-block text-left']) }} x-data="{
    open: false,
    align: '{{ $align }}',
    isDropup: false,
    coords: { top: '0px', left: '0px' },
    calculatePosition(triggerRect) {
        const panel = this.$refs.panel;
        const panelWidth = (panel && panel.offsetWidth > 0) ? panel.offsetWidth : 192;
        const panelHeight = (panel && panel.offsetHeight > 0) ? panel.offsetHeight : 90;
        const margin = 6;
        const vh = window.innerHeight;
        const vw = window.innerWidth;

        const spaceBelow = vh - triggerRect.bottom;
        const spaceAbove = triggerRect.top;

        this.isDropup = spaceBelow < (panelHeight + margin) && spaceAbove > spaceBelow;

        let top;
        if (this.isDropup) {
            top = Math.max(margin, triggerRect.top - panelHeight - margin);
        } else {
            top = Math.min(vh - panelHeight - margin, triggerRect.bottom + margin);
        }

        let left;
        if (this.align === 'left') {
            left = triggerRect.left;
            if (left + panelWidth > vw - margin) {
                left = vw - panelWidth - margin;
            }
        } else {
            left = triggerRect.right - panelWidth;
            if (left < margin) {
                left = margin;
            }
        }

        this.coords = {
            top: Math.round(top) + 'px',
            left: Math.round(left) + 'px',
        };
    },
    toggle() {
        if (!this.open) {
            if (this.$refs.trigger) {
                this.calculatePosition(this.$refs.trigger.getBoundingClientRect());
            }
            this.open = true;
            this.$nextTick(() => {
                if (this.$refs.trigger) {
                    this.calculatePosition(this.$refs.trigger.getBoundingClientRect());
                }
            });
        } else {
            this.open = false;
        }
    },
    close() {
        this.open = false;
    }
}"
    @close.stop="close()"
    @click.window="if (open && !$refs.trigger?.contains($event.target) && !$refs.panel?.contains($event.target)) close()"
    @scroll.window.capture="if (open) close()" @resize.window="if (open) close()"
    @keydown.escape.window="if (open) close()">

    <div x-ref="trigger" @click="toggle()" aria-haspopup="true" :aria-expanded="open" class="inline-flex">
        {{ $trigger }}
    </div>

    <template x-teleport="body">
        <div x-ref="panel" x-show="open" x-transition:enter="transition ease-out duration-150"
            x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
            x-transition:leave="transition-opacity ease-in duration-100" x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0" :style="{ top: coords.top, left: coords.left }"
            :class="isDropup ? (align === 'left' ? 'origin-bottom-left' : 'origin-bottom-right') : (align === 'left' ?
                'origin-top-left' : 'origin-top-right')"
            class="fixed z-[100] {{ $widthClasses }} rounded-xl shadow-xl border border-slate-200/90 bg-white overflow-hidden"
            style="display: none;" @click="close()">
            <div class="rounded-xl divide-y divide-slate-100 {{ $contentClasses }}">
                {{ $content }}
            </div>
        </div>
    </template>
</div>
