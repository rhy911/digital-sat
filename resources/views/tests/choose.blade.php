<x-layouts.portal title="Choose a Full-Length Practice" nextUrl="#" backUrl="/home">
    @push('styles')
        <style>
            h3 {
                display: flex;
                font-size: 1.25rem;
                margin-bottom: 1rem;
            }

            sub {
                margin-left: auto;
                line-height: inherit;
                font-size: 16px;
                font-weight: 400;
            }

            .form-select,
            .form-select {
                padding: 12px 36px 12px 12px !important;
                border-radius: 12px !important;
                font-size: 20px !important;
                border: 2px solid #dee2e6;
                background-color: #ffffff;
                transition: all 0.3s ease;
            }

            .form-select:focus,
            .form-select:focus {
                border-color: #324dc7;
                box-shadow: 0 0 0 0.2rem rgba(50, 77, 199, 0.25);
                outline: none;
            }

            .form-select:hover,
            .form-select:hover {
                border-color: #324dc7;
            }

            .custom-select-container {
                position: relative;
                display: inline-block;
                width: 100%;
            }

            .custom-select {
                padding: 12px 36px 12px 16px;
                border-radius: 12px;
                font-size: 20px;
                border: 2px solid #dee2e6;
                background-color: #ffffff;
                cursor: pointer;
                width: 100%;
                display: flex;
                justify-content: space-between;
                align-items: center;
                transition: all 0.3s ease;
            }

            .custom-select:hover {
                border-color: #324dc7;
            }

            .custom-select.active {
                border-color: #324dc7;
                box-shadow: 0 0 0 0.2rem rgba(50, 77, 199, 0.25);
            }

            .custom-select::after {
                content: "▼";
                font-size: 12px;
                color: #6c757d;
                transition: transform 0.3s ease;
            }

            .custom-select.active::after {
                transform: rotate(180deg);
            }

            .custom-options {
                position: absolute;
                top: 100%;
                left: 0;
                right: 0;
                background: white;
                border: 2px solid #324dc7;
                border-top: none;
                border-radius: 0 0 12px 12px;
                max-height: 200px;
                z-index: 1000;
                display: none;
                text-align: start;
            }

            .custom-options.show {
                display: block;
            }

            .custom-option {
                padding: 10px 16px;
                cursor: pointer;
                font-size: 20px;
            }

            .custom-option:hover {
                background-color: #324dc7;
                color: white;
            }

            .custom-option.disabled {
                color: #6c757d;
                cursor: not-allowed;
            }

            .custom-option.disabled:hover {
                background-color: transparent;
                color: #6c757d;
            }

            /* Hide the original select */
            .form-select.hidden {
                display: none;
            }
        </style>
    @endpush
    <h3>Test Type *
        <sub>* = Required</sub>
    </h3>

    <!-- Custom Dropdown -->
    <div class="custom-select-container">
        <div class="custom-select" id="customSelect" onclick="toggleDropdown()">
            <span id="selectedText">Choose a test</span>
        </div>
        <div class="custom-options" id="customOptions">
            <div class="custom-option disabled">Choose a test</div>
            @foreach ($tests as $test)
                @php
                    $firstModule = $test->sections->first()?->modules->first();
                @endphp
                <div class="custom-option" data-value="{{ $firstModule?->id ?? '' }}">{{ $test->title }}</div>
            @endforeach
        </div>
    </div>

    <!-- Hidden select for form submission -->
    <select class="form-select hidden" id="testSelect" name="testSelect">
        <option value="" selected disabled>Choose a test</option>
        @foreach ($tests as $test)
            @php
                $firstModule = $test->sections->first()?->modules->first();
            @endphp
            <option value="{{ $firstModule?->id ?? '' }}">{{ $test->title }}</option>
        @endforeach
    </select>
    @push('scripts')
        <script>
            function toggleDropdown() {
                const customSelect = document.getElementById("customSelect");
                const customOptions = document.getElementById("customOptions");
                customSelect.classList.toggle("active");
                customOptions.classList.toggle("show");
            }

            function selectOption(value, text) {
                document.getElementById("selectedText").textContent = text;
                document.getElementById("testSelect").value = value;
                document.getElementById("customSelect").classList.remove("active");
                document.getElementById("customOptions").classList.remove("show");
                updateSelection();
            }

            function updateSelection() {
                const select = document.getElementById("testSelect");
                const nextLink = document.querySelector('footer a:first-child');

                if (select.value === 'preview') {
                    nextLink.href = '/take-test'; // Default or specific preview module
                } else if (select.value) {
                    nextLink.href = '/take-test/' + select.value;
                } else {
                    nextLink.href = '#';
                }
            }

            document.addEventListener("DOMContentLoaded", function() {
                const options = document.querySelectorAll(".custom-option:not(.disabled)");
                options.forEach(option => {
                    option.addEventListener("click", function() {
                        selectOption(this.getAttribute("data-value"), this.textContent);
                    });
                });

                document.addEventListener("click", function(event) {
                    const container = document.querySelector(".custom-select-container");
                    if (!container.contains(event.target)) {
                        const customSelect = document.getElementById("customSelect");
                        const customOptions = document.getElementById("customOptions");
                        if (customSelect) customSelect.classList.remove("active");
                        if (customOptions) customOptions.classList.remove("show");
                    }
                });
            });
        </script>
    @endpush
</x-layouts.portal>
