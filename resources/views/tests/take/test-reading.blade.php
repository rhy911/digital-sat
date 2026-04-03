@php
    // Sau này khi có DB, bạn chỉ cần xóa khối @php này.
    // Controller sẽ truyền biến $testData sang với cấu trúc tương tự.
    $testData = (object) [
        'page_title' => "Section 1: Reading and Writing",
        'section_title' => "Section 1: Reading and Writing",
        'section_directions' => "<p>Read the passage and answer the questions that follow.</p>",
        'username' => "Luu Hoang Minh"
    ];
@endphp

<x-layouts.test :pageTitle="$testData->page_title"
    :sectionTitle="$testData->section_title" 
    :sectionDirections="$testData->section_directions"
    :username="$testData->username">
    <div class="flex-grow-1 overflow-hidden">
        <div class="overlay" id="dropdownOverlay"></div>
        <div class="h-100">
            
        </div>
    </div>
</x-layouts.test>

