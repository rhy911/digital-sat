<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>UI Kit — Dev Preview</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="bg-slate-50 p-8">
    <div class="mx-auto max-w-4xl space-y-12">
        <h1 class="text-2xl font-bold">UI Kit — Phase 2 component preview</h1>

        {{-- Button --}}
        <section class="space-y-3">
            <h2 class="text-lg font-bold">Button</h2>
            <div class="flex flex-wrap items-center gap-3">
                <x-ui.button variant="primary">Primary</x-ui.button>
                <x-ui.button variant="secondary">Secondary</x-ui.button>
                <x-ui.button variant="danger">Danger</x-ui.button>
                <x-ui.button variant="primary" size="sm">Small</x-ui.button>
                <x-ui.button variant="primary" loading>Loading</x-ui.button>
                <x-ui.button variant="primary" disabled>Disabled</x-ui.button>
                <x-ui.button variant="primary" href="#">As link</x-ui.button>
            </div>
            <div class="rounded-xl bg-slate-900 p-6">
                <x-ui.button variant="ghost-on-dark">Ghost on dark</x-ui.button>
            </div>
        </section>

        {{-- Status badge --}}
        <section class="space-y-3">
            <h2 class="text-lg font-bold">Status badge</h2>
            <div class="flex flex-wrap items-center gap-3">
                <x-ui.status-badge status="success">Completed</x-ui.status-badge>
                <x-ui.status-badge status="danger">Error</x-ui.status-badge>
                <x-ui.status-badge status="warning">In progress</x-ui.status-badge>
                <x-ui.status-badge status="brand">Shared</x-ui.status-badge>
                <x-ui.status-badge status="neutral">Archived</x-ui.status-badge>
            </div>
        </section>

        {{-- Card --}}
        <section class="space-y-3">
            <h2 class="text-lg font-bold">Card</h2>
            <x-ui.card>
                <x-slot:header>
                    <h3 class="font-bold">Card title</h3>
                    <x-ui.status-badge status="success">Active</x-ui.status-badge>
                </x-slot:header>
                <p class="text-slate-600">Card body content goes here.</p>
                <x-slot:footer>
                    <x-ui.button variant="secondary" size="sm">Cancel</x-ui.button>
                    <x-ui.button variant="primary" size="sm">Save</x-ui.button>
                </x-slot:footer>
            </x-ui.card>
        </section>

        {{-- Dropdown --}}
        <section class="space-y-3">
            <h2 class="text-lg font-bold">Dropdown</h2>
            <x-ui.dropdown align="left">
                <x-slot:trigger>
                    <x-ui.button variant="secondary">Open dropdown</x-ui.button>
                </x-slot:trigger>
                <x-slot:content>
                    <a href="#" class="block px-4 py-2 text-sm text-slate-700 hover:bg-slate-100">Item one</a>
                    <a href="#" class="block px-4 py-2 text-sm text-slate-700 hover:bg-slate-100">Item two</a>
                </x-slot:content>
            </x-ui.dropdown>
        </section>

        {{-- Alert --}}
        <section class="space-y-3">
            <h2 class="text-lg font-bold">Alert</h2>
            <x-ui.alert type="danger" :messages="['Email is required.', 'Password must be at least 8 characters.']" />
            <x-ui.alert type="success">Your changes have been saved.</x-ui.alert>
            <x-ui.alert type="warning">This action cannot be undone.</x-ui.alert>
        </section>

        {{-- Skeleton --}}
        <section class="space-y-3">
            <h2 class="text-lg font-bold">Skeleton</h2>
            <x-ui.skeleton class="h-4 w-1/3" />
            <x-ui.skeleton class="h-24 w-full" count="3" />
        </section>

        {{-- Icon --}}
        <section class="space-y-3">
            <h2 class="text-lg font-bold">Icon</h2>
            <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6">
                @foreach ([
                    'arrow-clockwise', 'arrow-left', 'arrow-repeat', 'arrow-right', 'box-arrow-right', 'box-seam',
                    'calculator', 'check-all', 'check-circle', 'check-circle-fill', 'check-lg', 'chevron-down',
                    'chevron-right', 'cloud-arrow-up', 'cloud-check', 'compass', 'copy', 'database',
                    'database-fill-x', 'diagram-3', 'download', 'eraser', 'exclamation-triangle-fill', 'eye',
                    'eye-fill', 'file-earmark-code', 'file-earmark-richtext', 'file-earmark-spreadsheet',
                    'file-earmark-spreadsheet-fill', 'file-earmark-zip', 'filetype-json', 'filter', 'folder-plus',
                    'folder2-open', 'grid-3x3', 'grid-3x3-gap-fill', 'house', 'image', 'inbox', 'info-circle',
                    'info-circle-fill', 'journal-text', 'layers', 'layout-sidebar-inset', 'layout-text-window',
                    'lightning-charge', 'list', 'list-ol', 'list-stars', 'pencil', 'pencil-square', 'people',
                    'person-plus', 'plus-circle', 'plus-circle-fill', 'plus-lg', 'puzzle-fill', 'question-circle',
                    'regex', 'robot', 'search', 'sliders', 'tag', 'trash', 'type-italic', 'unlock', 'x-circle',
                    'x-lg',
                ] as $iconName)
                    <div class="flex flex-col items-center gap-2 rounded-lg border border-slate-200 bg-white p-3 text-center">
                        <x-ui.icon :name="$iconName" class="h-6 w-6 text-slate-700" />
                        <span class="break-all text-xs text-slate-500">{{ $iconName }}</span>
                    </div>
                @endforeach
            </div>
        </section>
    </div>
</body>

</html>
