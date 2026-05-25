<x-layouts.portal title="Choose a Full-Length Practice" nextUrl="#" backUrl="/home">
    @push('styles')
        <style>
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
                overflow-y: auto;
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
    <h3 class="text-xl md:text-2xl flex mb-3 font-bold">Test Type <span class="text-red-500 ml-2">*</span>
        <sub class="font-light text-base ml-auto">* = Required</sub>
    </h3>

    <x-ui.custom-select id="testSelect" name="testSelect" placeholder="Choose a test" :options="$tests
        ->map(fn($t) => ['value' => $t->sections->first()?->modules->first()?->id ?? '', 'label' => $t->title])
        ->toArray()" />

    @push('scripts')
        <script>
            function updateSelection(value) {
                const nextLink = document.querySelector('footer a:first-child');

                if (value === 'preview') {
                    nextLink.href = '/take-test';
                } else if (value) {
                    nextLink.href = '/take-test/' + value;
                } else {
                    nextLink.href = '#';
                }
            }

            document.addEventListener("DOMContentLoaded", function() {
                const options = document.querySelectorAll("#testSelectOptions .custom-option:not(.disabled)");
                options.forEach(option => {
                    option.addEventListener("click", function() {
                        selectOption('testSelect', this.getAttribute("data-value"), this.textContent,
                            updateSelection);
                    });
                });
            });
        </script>
    @endpush
</x-layouts.portal>
