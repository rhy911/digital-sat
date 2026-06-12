<x-layouts.landing>
    <!-- Hero Section -->
    <section class="relative px-6 pt-24 pb-32 overflow-hidden">
        <div class="max-w-7xl mx-auto grid lg:grid-cols-2 gap-16 items-center">
            <div class="max-w-xl">
                <h1 class="text-5xl md:text-7xl font-bold tracking-tight leading-[0.95] mb-8">
                    The most <span class="text-brand">Indistinguishable</span> Digital SAT Experience.
                </h1>
                <p class="text-lg text-slate-600 leading-relaxed mb-10 max-w-[45ch]">
                    Practice in an environment that looks and feels exactly like the real Digital SAT. From adaptive
                    M2 routing to advanced IRT scoring, we provide the definitive standard for prep.
                </p>
                <div class="flex flex-col sm:flex-row gap-4">
                    <a href="/register"
                        class="bg-brand text-white px-8 py-4 rounded-full font-bold text-center hover:shadow-xl hover:shadow-blue-500/20 transition-all">
                        Take a Practice Test
                    </a>
                    <a href="#features"
                        class="px-8 py-4 rounded-full border border-slate-200 font-bold text-center hover:bg-white transition-all">
                        Explore Features
                    </a>
                </div>
            </div>

            <div class="relative">
                <div
                    class="aspect-[4/3] rounded-3xl bg-slate-200 overflow-hidden shadow-2xl relative border border-slate-200">
                    <img src="https://picsum.photos/seed/institutional-sat/1200/900" alt="High-Fidelity Interface"
                        class="object-cover w-full h-full" width="1200" height="900" loading="lazy">
                    <!-- Floating Badge -->
                    <div
                        class="absolute top-8 right-8 bg-white/95 backdrop-blur p-5 rounded-2xl shadow-xl animate-float border border-white/50">
                        <div class="flex items-center gap-3">
                            <div
                                class="w-12 h-12 bg-brand rounded-full flex items-center justify-center text-white shadow-lg shadow-blue-200">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            </div>
                            <div>
                                <p class="text-[10px] text-slate-400 font-bold uppercase tracking-wider">Scoring
                                    Status</p>
                                <p class="text-sm font-bold">IRT Engine Calibrated</p>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- Background blobs -->
                <div class="absolute -z-10 -bottom-10 -left-10 w-72 h-72 bg-brand/10 blur-3xl rounded-full"></div>
            </div>
        </div>
    </section>

    <!-- Features Bento Grid -->
    <section id="features" class="py-32 px-6 bg-white">
        <div class="max-w-7xl mx-auto">
            <div class="mb-20">
                <h2 class="text-4xl md:text-5xl font-bold tracking-tight mb-6">Reality Meets Intelligence.</h2>
                <p class="text-slate-600 text-lg max-w-2xl">Built for student mastery and educator efficiency.</p>
            </div>

            <div class="grid md:grid-cols-12 gap-8">
                <!-- Feature 1: High-Fidelity Testing -->
                <div id="fidelity"
                    class="md:col-span-8 bg-brand rounded-[2.5rem] p-14 text-white overflow-hidden relative group bento-card">
                    <div class="max-w-md relative z-10">
                        <div
                            class="w-14 h-14 bg-white/10 backdrop-blur rounded-2xl flex items-center justify-center mb-10 border border-white/20">
                            <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z">
                                </path>
                            </svg>
                        </div>
                        <h3 class="text-4xl font-bold mb-6 leading-tight">Total Testing Immersion</h3>
                        <p class="text-blue-100 text-lg leading-relaxed mb-10">
                            Zero friction on test day. Our platform replicates every tool, transition, and technical
                            detail of the actual exam interface, ensuring students are perfectly calibrated for the
                            real experience.
                        </p>
                        <div class="flex flex-wrap gap-3">
                            <span
                                class="px-4 py-2 bg-white/10 rounded-full text-[10px] font-bold uppercase tracking-widest border border-white/20">Desmos
                                Integrated</span>
                            <span
                                class="px-4 py-2 bg-white/10 rounded-full text-[10px] font-bold uppercase tracking-widest border border-white/20">Annotation
                                Tools</span>
                            <span
                                class="px-4 py-2 bg-white/10 rounded-full text-[10px] font-bold uppercase tracking-widest border border-white/20">Review
                                Grid</span>
                        </div>
                    </div>
                    <div
                        class="absolute right-0 bottom-0 w-2/3 translate-y-20 translate-x-20 opacity-10 group-hover:opacity-20 group-hover:scale-105 transition-all">
                        <img src="https://picsum.photos/seed/ui-fidelity/1000/1000" alt="Interface Immersion"
                            class="rounded-3xl rotate-6 shadow-2xl" width="1000" height="1000" loading="lazy">
                    </div>
                </div>

                <!-- Feature 2: For Teachers -->
                <div id="teachers"
                    class="md:col-span-4 bg-slate-50 border border-slate-200 rounded-[2.5rem] p-12 flex flex-col justify-between bento-card shadow-sm hover:shadow-xl hover:border-brand/30 transition-all">
                    <div>
                        <div
                            class="w-14 h-14 bg-brand text-white rounded-2xl flex items-center justify-center mb-10 shadow-lg shadow-blue-200">
                            <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253">
                                </path>
                            </svg>
                        </div>
                        <h3 class="text-2xl font-bold mb-4">Command Center for Educators</h3>
                        <p class="text-slate-500 leading-relaxed mb-6">
                            Create and distribute custom SAT tests for your classroom. Set public challenges or
                            private mock exams with full control over student performance tracking.
                        </p>
                        <ul class="space-y-2 text-[11px] font-bold text-slate-400 uppercase tracking-widest">
                            <li class="flex items-center gap-2"><span class="w-1 h-1 bg-brand rounded-full"></span>
                                Bulk Question Import</li>
                            <li class="flex items-center gap-2"><span class="w-1 h-1 bg-brand rounded-full"></span>
                                Private Testing Links</li>
                            <li class="flex items-center gap-2"><span class="w-1 h-1 bg-brand rounded-full"></span>
                                Real-time Monitoring</li>
                        </ul>
                    </div>
                </div>

                <!-- Feature 3: True-to-Life UI -->
                <div class="md:col-span-4 bg-slate-900 rounded-[2.5rem] p-10 text-white bento-card">
                    <div class="mb-16">
                        <h3 class="text-2xl font-bold mb-4">True-to-Life UI</h3>
                        <p class="text-slate-400 text-sm leading-relaxed">
                            Zero friction on test day. Our interface is indistinguishable from the official
                            experience, ensuring students are comfortable with every tool and transition.
                        </p>
                    </div>
                    <div class="p-6 bg-slate-800 rounded-2xl border border-slate-700">
                        <div class="flex items-center gap-2 mb-4">
                            <div class="w-2 h-2 rounded-full bg-green-500"></div>
                            <span class="text-[10px] font-mono text-slate-500 tracking-tighter">UI FIDELITY:
                                100%</span>
                        </div>
                        <div class="space-y-2">
                            <div class="h-2 w-full bg-slate-700 rounded"></div>
                            <div class="h-2 w-3/4 bg-slate-700 rounded"></div>
                        </div>
                    </div>
                </div>

                <!-- Feature 4: Science-Backed IRT -->
                <div id="scoring"
                    class="md:col-span-8 bg-blue-50 border border-blue-100 rounded-[2.5rem] p-14 flex flex-col md:flex-row gap-14 items-center bento-card">
                    <div class="flex-1">
                        <div
                            class="inline-flex px-3 py-1 bg-brand/10 rounded-full text-[10px] font-bold text-brand uppercase tracking-widest mb-6">
                            Science of Scoring</div>
                        <h3 class="text-3xl font-bold mb-6">Advanced IRT Science.</h3>
                        <p class="text-slate-600 leading-relaxed mb-8">
                            We utilize 3PL Item Response Theory (IRT) and Theta-based MLE algorithms to ensure every
                            score estimate is as accurate as the official exam.
                        </p>
                        <div class="p-4 bg-white rounded-2xl border border-blue-100">
                            <div class="flex justify-between items-center text-[10px] font-mono mb-2">
                                <span class="text-slate-400">THETA ESTIMATION</span>
                                <span class="text-brand">MLE Newton-Raphson</span>
                            </div>
                            <div class="h-1 w-full bg-slate-100 rounded-full overflow-hidden">
                                <div class="h-full bg-brand w-2/3"></div>
                            </div>
                        </div>
                    </div>
                    <div
                        class="flex-1 w-full aspect-[4/3] bg-white rounded-3xl shadow-xl shadow-blue-900/5 border border-blue-200/50 overflow-hidden relative">
                        <img src="https://picsum.photos/seed/adaptive-flow/800/600" alt="Adaptive Science"
                            class="object-cover w-full h-full opacity-80" width="800" height="600" loading="lazy">
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Final CTA -->
    <section class="py-32 px-6">
        <div
            class="max-w-6xl mx-auto bg-slate-950 rounded-[3.5rem] p-12 md:p-28 text-center text-white relative overflow-hidden shadow-2xl">
            <div class="relative z-10">
                <h2 class="text-4xl md:text-7xl font-bold mb-8 tracking-tight">The definitive testing platform for
                    your <span class="text-brand italic">entire</span> classroom.</h2>
                <p class="text-xl text-slate-400 mb-14 max-w-2xl mx-auto leading-relaxed">
                    Accurate scoring, high-fidelity immersion, and powerful management tools. Ready for your first
                    session?
                </p>
                <div class="flex flex-col sm:flex-row justify-center gap-6">
                    <a href="/register"
                        class="bg-brand text-white px-12 py-6 rounded-full font-bold text-lg hover:scale-105 transition-all shadow-2xl shadow-blue-500/30 active:scale-95">
                        Launch Free Test
                    </a>
                    <a href="/signin"
                        class="px-12 py-6 rounded-full border border-slate-800 font-bold text-lg hover:bg-white/5 transition-all">
                        Institutional Login
                    </a>
                </div>
            </div>
            <!-- Background decoration -->
            <div class="absolute inset-0 bg-[radial-gradient(circle_at_bottom_left,#324dc720,transparent_50%)]">
            </div>
            <div class="absolute inset-0 bg-[radial-gradient(circle_at_top_right,#324dc710,transparent_50%)]"></div>
        </div>
    </section>

    @push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            if (typeof window.initLandingPage === 'function') {
                window.initLandingPage();
            }
        });
    </script>
    @endpush
</x-layouts.landing>