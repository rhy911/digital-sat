<div x-show="activeTab === 'recycle-bin'" id="recycle-bin" role="tabpanel" aria-labelledby="recycle-bin-tab"
    :aria-hidden="activeTab === 'recycle-bin' ? 'false' : 'true'" :class="{ 'active': activeTab === 'recycle-bin' }"
    class="tab-pane" style="display: none;">
    <div class="recycle-bin-page">
        <section class="recycle-bin-hero" aria-labelledby="recycleBinTitle">
            <div class="recycle-bin-hero-icon" aria-hidden="true">
                <x-ui.icon name="trash" class="h-6 w-6" />
            </div>
            <div class="min-w-0">
                <h2 id="recycleBinTitle">Recycle bin</h2>
                <p>Deleted content stays here for 7 days before the system permanently cleans it up.</p>
            </div>
            <button type="button" id="recycleBinRefresh" class="recycle-bin-refresh" aria-label="Refresh recycle bin" title="Refresh recycle bin">
                <x-ui.icon name="arrow-clockwise" class="h-4 w-4" />
                <span>Refresh</span>
            </button>
        </section>

        <div class="recycle-bin-note" role="note">
            <x-ui.icon name="info" class="h-4 w-4 shrink-0" />
            <span>Restoring a test also restores sections, modules, and questions that were deleted with it. Shared question-bank content is never removed with a clone.</span>
        </div>

        <div id="recycleBinBlockers" class="recycle-bin-blockers hidden" role="status" aria-live="polite">
            <x-ui.icon name="alert-triangle" class="h-5 w-5 shrink-0" />
            <div>
                <strong>Some items were kept</strong>
                <p id="recycleBinBlockerDetails"></p>
            </div>
        </div>

        <section class="recycle-bin-panel" aria-labelledby="recycleBinItemsTitle">
            <div class="recycle-bin-panel-header">
                <div>
                    <h3 id="recycleBinItemsTitle">Deleted content</h3>
                    <p id="recycleBinCount" class="recycle-bin-count" aria-live="polite">Loading…</p>
                </div>
                <div class="recycle-bin-panel-actions">
                    <span class="recycle-bin-retention"><x-ui.icon name="clock" class="h-3.5 w-3.5" /> 7-day retention</span>
                    <button type="button" id="recycleBinClearAll" class="recycle-bin-danger-action" disabled>
                        <x-ui.icon name="trash" class="h-3.5 w-3.5" />
                        <span>Empty bin</span>
                    </button>
                </div>
            </div>

            <div id="recycleBinError" class="recycle-bin-state recycle-bin-error hidden" role="alert">
                <x-ui.icon name="alert-triangle" class="h-5 w-5 shrink-0" />
                <div>
                    <strong>Could not load the recycle bin.</strong>
                    <p>Check your connection and try again.</p>
                </div>
            </div>

            <div id="recycleBinLoading" class="recycle-bin-list" aria-label="Loading deleted content">
                @for ($i = 0; $i < 3; $i++)
                    <div class="recycle-bin-skeleton" aria-hidden="true">
                        <span></span><span></span><span></span>
                    </div>
                @endfor
            </div>

            <div id="recycleBinEmpty" class="recycle-bin-state recycle-bin-empty hidden">
                <div class="recycle-bin-empty-icon" aria-hidden="true"><x-ui.icon name="inbox" class="h-7 w-7" /></div>
                <h4>Your recycle bin is empty</h4>
                <p>Deleted tests and content will appear here while they are still recoverable.</p>
            </div>

            <div id="recycleBinList" class="recycle-bin-list hidden" aria-live="polite"></div>
        </section>
    </div>
</div>
