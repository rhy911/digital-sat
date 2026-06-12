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
        ->map(fn($t) => ['value' => $t->id, 'label' => $t->title])
        ->toArray()" />

    <!-- Attempt Options Modal -->
    <div id="attemptModal" class="fixed inset-0 z-50 flex items-center justify-center hidden" aria-modal="true" role="dialog">
        <!-- Overlay -->
        <div class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm transition-opacity"></div>
        
        <!-- Modal content -->
        <div class="relative bg-white rounded-2xl shadow-xl max-w-md w-full mx-4 p-6 border border-slate-100 transform transition-all duration-300 scale-95 opacity-0 flex flex-col gap-4 text-center">
            <!-- Icon/Header -->
            <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-amber-100 text-amber-600">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" />
                </svg>
            </div>
            <div>
                <h3 class="text-xl font-semibold text-slate-900">Resume or Start Fresh?</h3>
                <p class="text-sm text-slate-500 mt-2">
                    You have an unfinished attempt for this practice test. Would you like to continue where you left off, or start a fresh attempt?
                </p>
            </div>
            
            <!-- Actions -->
            <div class="flex flex-col gap-2 mt-2">
                <button id="btnContinueAttempt" class="w-full py-2.5 px-4 rounded-xl text-white font-semibold bg-[#324dc7] hover:bg-[#253ca3] transition-colors focus:outline-none focus:ring-2 focus:ring-[#324dc7] focus:ring-offset-2">
                    Continue In-Progress
                </button>
                <button id="btnFreshAttempt" class="w-full py-2.5 px-4 rounded-xl text-slate-700 font-semibold bg-slate-100 hover:bg-slate-200 transition-colors focus:outline-none focus:ring-2 focus:ring-slate-300 focus:ring-offset-2">
                    Start Fresh
                </button>
                <button id="btnCancelAttempt" class="w-full py-2.5 px-4 rounded-xl text-slate-500 font-medium hover:text-slate-700 hover:bg-slate-50 transition-colors focus:outline-none">
                    Cancel
                </button>
            </div>
        </div>
    </div>

    @push('scripts')
        <script>
            function triggerLoadingScreen(message = 'Preparing your test...') {
                const loadingScreen = document.getElementById('loadingScreen');
                const loadingStatusText = document.getElementById('loadingStatusText');
                if (loadingStatusText) {
                    loadingStatusText.textContent = message;
                }
                if (loadingScreen) {
                    loadingScreen.classList.remove('hidden');
                    loadingScreen.setAttribute('aria-hidden', 'false');
                }
                document.body.style.cursor = 'wait';
            }

            function navigateAfterLoaderPaint(href) {
                triggerLoadingScreen();
                requestAnimationFrame(() => {
                    requestAnimationFrame(() => {
                        window.location.href = href;
                    });
                });
            }

            function showAttemptOptionsModal(testId, options) {
                const modal = document.getElementById('attemptModal');
                const content = modal.querySelector('.relative');
                
                const btnContinue = document.getElementById('btnContinueAttempt');
                const btnFresh = document.getElementById('btnFreshAttempt');
                const btnCancel = document.getElementById('btnCancelAttempt');
                
                btnContinue.onclick = function() {
                    hideAttemptModal();
                    const redirectUrl = `/take-test/${options.latest_in_progress_current_module_ulid}?attempt=${options.latest_in_progress_ulid}`;
                    navigateAfterLoaderPaint(redirectUrl);
                };
                
                btnFresh.onclick = function() {
                    hideAttemptModal();
                    startTestFresh(testId, options.first_module_ulid);
                };
                
                btnCancel.onclick = function() {
                    hideAttemptModal();
                };
                
                modal.classList.remove('hidden');
                setTimeout(() => {
                    content.classList.remove('scale-95', 'opacity-0');
                    content.classList.add('scale-100', 'opacity-100');
                }, 10);
            }

            function hideAttemptModal() {
                const modal = document.getElementById('attemptModal');
                const content = modal.querySelector('.relative');
                content.classList.remove('scale-100', 'opacity-100');
                content.classList.add('scale-95', 'opacity-0');
                setTimeout(() => {
                    modal.classList.add('hidden');
                }, 300);
            }

            async function startTestFresh(testId, firstModuleUlid) {
                triggerLoadingScreen('Creating fresh attempt...');
                try {
                    const response = await fetch(`/test/start/${testId}`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                        },
                        body: JSON.stringify({ mode: 'fresh' })
                    });
                    
                    if (!response.ok) {
                        throw new Error('Failed to start test');
                    }
                    
                    const data = await response.json();
                    if (data.redirect_url) {
                        window.location.href = data.redirect_url;
                    } else {
                        window.location.href = `/take-test/${data.first_module_ulid || firstModuleUlid}?attempt=${data.user_test_ulid}`;
                    }
                } catch (err) {
                    console.error(err);
                    const loadingScreen = document.getElementById('loadingScreen');
                    if (loadingScreen) loadingScreen.classList.add('hidden');
                    document.body.style.cursor = '';
                    alert('Could not start a new attempt. Please try again.');
                }
            }

            function updateSelection(value) {
                const nextLink = document.querySelector('footer a:first-child');
                if (value) {
                    nextLink.href = '#';
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

                const nextBtn = document.querySelector('footer .buttons a.btn[role="button"]');
                const testSelect = document.getElementById('testSelect');

                if (nextBtn) {
                    nextBtn.addEventListener('click', async function(e) {
                        const testId = testSelect.value;
                        if (!testId) {
                            return;
                        }
                        
                        e.preventDefault();
                        e.stopPropagation();
                        
                        try {
                            const response = await fetch(`/test/${testId}/attempt-options`);
                            if (!response.ok) {
                                throw new Error('Failed to load attempt options');
                            }
                            const optionsData = await response.json();
                            
                            if (optionsData.has_in_progress) {
                                showAttemptOptionsModal(testId, optionsData);
                            } else {
                                startTestFresh(testId, optionsData.first_module_ulid);
                            }
                        } catch (err) {
                            console.error(err);
                            alert('An error occurred. Please try again.');
                        }
                    });
                }
            });
        </script>
    @endpush
</x-layouts.portal>
