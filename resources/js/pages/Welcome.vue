<script setup lang="ts">
import ThemeToggle from '@/components/ThemeToggle.vue';
import { initializeTheme } from '@/composables/useAppearance';
import { dashboard, login } from '@/routes';
import { Head, Link } from '@inertiajs/vue3';
import { onMounted } from 'vue';

withDefaults(
    defineProps<{
        canRegister: boolean;
    }>(),
    {
        canRegister: false,
    },
);

onMounted(() => {
    initializeTheme();
});
</script>

<template>
    <Head title="RackAudit">
        <link rel="preconnect" href="https://fonts.googleapis.com" />
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin="anonymous" />
        <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@400;500;600&family=IBM+Plex+Mono:wght@400;500&display=swap" rel="stylesheet" />
    </Head>

    <div class="welcome-page min-h-screen flex flex-col relative bg-slate-50 dark:bg-slate-900 text-slate-700 dark:text-slate-200">
        <!-- Subtle grid background -->
        <div class="absolute inset-0 pointer-events-none bg-grid-light dark:bg-grid-dark"></div>

        <!-- Header -->
        <header class="relative z-10 border-b border-slate-200 dark:border-slate-700/50">
            <div class="max-w-[1200px] mx-auto px-8 py-5 flex justify-between items-center">
                <div class="flex items-center gap-2.5">
                    <svg class="w-7 h-7 text-sky-600 dark:text-sky-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                        <rect x="3" y="2" width="18" height="6" rx="1" />
                        <rect x="3" y="9" width="18" height="6" rx="1" />
                        <rect x="3" y="16" width="18" height="6" rx="1" />
                        <circle cx="6" cy="5" r="1" fill="currentColor" />
                        <circle cx="6" cy="12" r="1" fill="currentColor" />
                        <circle cx="6" cy="19" r="1" fill="currentColor" />
                    </svg>
                    <span class="font-mono font-semibold text-lg text-slate-900 dark:text-slate-50 tracking-tight">RackAudit</span>
                </div>

                <nav class="flex items-center gap-3">
                    <ThemeToggle />
                    <Link
                        v-if="$page.props.auth.user"
                        :href="dashboard()"
                        class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium rounded-md bg-sky-600 dark:bg-sky-500 text-white hover:bg-sky-700 dark:hover:bg-sky-400 transition-colors"
                    >
                        Go to Dashboard
                    </Link>
                    <Link v-else :href="login()" class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium rounded-md bg-sky-600 dark:bg-sky-500 text-white hover:bg-sky-700 dark:hover:bg-sky-400 transition-colors">
                        Sign In
                    </Link>
                </nav>
            </div>
        </header>

        <!-- Main content -->
        <main class="flex-1 relative z-10 max-w-[1200px] mx-auto px-8 py-16 w-full">
            <div class="text-center mb-16">
                <div class="inline-flex items-center gap-2 px-3.5 py-1.5 mb-6 rounded-full text-xs font-mono uppercase tracking-wider bg-sky-100 dark:bg-sky-500/10 text-sky-700 dark:text-sky-400 border border-sky-200 dark:border-sky-500/20">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                    Infrastructure Management
                </div>

                <h1 class="text-4xl sm:text-5xl font-semibold leading-tight text-slate-900 dark:text-slate-50 mb-5 tracking-tight">
                    Datacenter Audit<br />
                    <span class="text-sky-600 dark:text-sky-400">&amp; Asset Management</span>
                </h1>

                <p class="text-lg text-slate-500 dark:text-slate-400 max-w-md mx-auto mb-8 leading-relaxed">
                    Track racks, manage connections, and maintain compliance
                    across your datacenter infrastructure.
                </p>

                <div v-if="!$page.props.auth.user" class="flex justify-center">
                    <Link :href="login()" class="inline-flex items-center gap-2 px-6 py-3 text-base font-medium rounded-md bg-sky-600 dark:bg-sky-500 text-white hover:bg-sky-700 dark:hover:bg-sky-400 transition-colors">
                        Access System
                        <svg class="w-4.5 h-4.5" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M3 10a.75.75 0 01.75-.75h10.638L10.23 5.29a.75.75 0 111.04-1.08l5.5 5.25a.75.75 0 010 1.08l-5.5 5.25a.75.75 0 11-1.04-1.08l4.158-3.96H3.75A.75.75 0 013 10z" clip-rule="evenodd" />
                        </svg>
                    </Link>
                </div>
            </div>

            <!-- Features -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                <div class="p-6 rounded-lg bg-white dark:bg-slate-800/50 border border-slate-200 dark:border-slate-700/50 hover:border-sky-300 dark:hover:border-sky-500/30 transition-colors">
                    <div class="w-10 h-10 mb-4 text-sky-600 dark:text-sky-400">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                            <rect x="4" y="4" width="6" height="16" rx="1" />
                            <rect x="14" y="4" width="6" height="16" rx="1" />
                            <line x1="6" y1="8" x2="8" y2="8" />
                            <line x1="6" y1="11" x2="8" y2="11" />
                            <line x1="16" y1="8" x2="18" y2="8" />
                            <line x1="16" y1="11" x2="18" y2="11" />
                        </svg>
                    </div>
                    <h3 class="font-mono text-sm font-medium text-slate-900 dark:text-slate-50 mb-2">Rack Management</h3>
                    <p class="text-sm text-slate-500 dark:text-slate-400 leading-relaxed">Visual elevation diagrams with U-space tracking and power monitoring.</p>
                </div>

                <div class="p-6 rounded-lg bg-white dark:bg-slate-800/50 border border-slate-200 dark:border-slate-700/50 hover:border-sky-300 dark:hover:border-sky-500/30 transition-colors">
                    <div class="w-10 h-10 mb-4 text-sky-600 dark:text-sky-400">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                            <circle cx="5" cy="5" r="2" />
                            <circle cx="19" cy="5" r="2" />
                            <circle cx="5" cy="19" r="2" />
                            <circle cx="19" cy="19" r="2" />
                            <path d="M7 5h10M5 7v10M19 7v10M7 19h10" />
                        </svg>
                    </div>
                    <h3 class="font-mono text-sm font-medium text-slate-900 dark:text-slate-50 mb-2">Connection Tracking</h3>
                    <p class="text-sm text-slate-500 dark:text-slate-400 leading-relaxed">Port-to-port mapping with full cable documentation and history.</p>
                </div>

                <div class="p-6 rounded-lg bg-white dark:bg-slate-800/50 border border-slate-200 dark:border-slate-700/50 hover:border-sky-300 dark:hover:border-sky-500/30 transition-colors">
                    <div class="w-10 h-10 mb-4 text-sky-600 dark:text-sky-400">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                            <path d="M9 12l2 2 4-4" />
                            <circle cx="12" cy="12" r="9" />
                        </svg>
                    </div>
                    <h3 class="font-mono text-sm font-medium text-slate-900 dark:text-slate-50 mb-2">Compliance Auditing</h3>
                    <p class="text-sm text-slate-500 dark:text-slate-400 leading-relaxed">Automated discrepancy detection with detailed reporting.</p>
                </div>

                <div class="p-6 rounded-lg bg-white dark:bg-slate-800/50 border border-slate-200 dark:border-slate-700/50 hover:border-sky-300 dark:hover:border-sky-500/30 transition-colors">
                    <div class="w-10 h-10 mb-4 text-sky-600 dark:text-sky-400">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                            <path d="M9 17V7m0 10l-2-2m2 2l2-2M15 7v10m0-10l-2 2m2-2l2 2" />
                            <rect x="3" y="3" width="18" height="18" rx="2" />
                        </svg>
                    </div>
                    <h3 class="font-mono text-sm font-medium text-slate-900 dark:text-slate-50 mb-2">Change Management</h3>
                    <p class="text-sm text-slate-500 dark:text-slate-400 leading-relaxed">Track all infrastructure changes with approval workflows.</p>
                </div>
            </div>
        </main>

        <!-- Footer -->
        <footer class="relative z-10 border-t border-slate-200 dark:border-slate-700/50">
            <div class="max-w-[1200px] mx-auto px-8 py-4 flex justify-between items-center">
                <span class="font-mono text-xs text-slate-400 dark:text-slate-500">&copy; {{ new Date().getFullYear() }} RackAudit</span>
                <span class="flex items-center gap-2 font-mono text-xs text-slate-500 dark:text-slate-400">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                    System Operational
                </span>
            </div>
        </footer>
    </div>
</template>

<style scoped>
.welcome-page {
    font-family: 'IBM Plex Sans', system-ui, sans-serif;
}

.font-mono {
    font-family: 'IBM Plex Mono', monospace;
}

.bg-grid-light {
    background-image:
        linear-gradient(rgba(15, 23, 42, 0.03) 1px, transparent 1px),
        linear-gradient(90deg, rgba(15, 23, 42, 0.03) 1px, transparent 1px);
    background-size: 48px 48px;
}

.bg-grid-dark {
    background-image:
        linear-gradient(rgba(148, 163, 184, 0.03) 1px, transparent 1px),
        linear-gradient(90deg, rgba(148, 163, 184, 0.03) 1px, transparent 1px);
    background-size: 48px 48px;
}

@media (max-width: 768px) {
    .welcome-page header > div {
        padding-left: 1rem;
        padding-right: 1rem;
    }

    .welcome-page main {
        padding: 2rem 1rem;
    }

    .welcome-page footer > div {
        padding-left: 1rem;
        padding-right: 1rem;
        flex-direction: column;
        gap: 0.5rem;
    }
}
</style>
