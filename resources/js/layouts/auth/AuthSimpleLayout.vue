<script setup lang="ts">
import ThemeToggle from '@/components/ThemeToggle.vue';
import { initializeTheme } from '@/composables/useAppearance';
import { home } from '@/routes';
import { Link } from '@inertiajs/vue3';
import { onMounted } from 'vue';

defineProps<{
    title?: string;
    description?: string;
}>();

onMounted(() => {
    initializeTheme();
});
</script>

<template>
    <div class="auth-container min-h-screen flex items-center justify-center relative bg-slate-50 dark:bg-slate-900 text-slate-700 dark:text-slate-200">
        <!-- Subtle grid background -->
        <div class="absolute inset-0 pointer-events-none bg-grid-light dark:bg-grid-dark"></div>

        <!-- Theme toggle -->
        <div class="absolute top-6 right-6 z-20">
            <ThemeToggle />
        </div>

        <!-- Main content -->
        <div class="relative z-10 flex flex-col items-center p-8 w-full max-w-[440px]">
            <div class="w-full bg-white dark:bg-slate-800/50 border border-slate-200 dark:border-slate-700/50 rounded-lg p-8">
                <!-- Logo -->
                <Link :href="home()" class="flex items-center justify-center gap-2.5 mb-8 no-underline">
                    <svg class="w-7 h-7 text-sky-600 dark:text-sky-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                        <rect x="3" y="2" width="18" height="6" rx="1" />
                        <rect x="3" y="9" width="18" height="6" rx="1" />
                        <rect x="3" y="16" width="18" height="6" rx="1" />
                        <circle cx="6" cy="5" r="1" fill="currentColor" />
                        <circle cx="6" cy="12" r="1" fill="currentColor" />
                        <circle cx="6" cy="19" r="1" fill="currentColor" />
                    </svg>
                    <span class="font-mono font-semibold text-lg text-slate-900 dark:text-slate-50 tracking-tight">RackAudit</span>
                </Link>

                <!-- Header -->
                <div class="text-center mb-7">
                    <h1 class="text-xl font-semibold text-slate-900 dark:text-slate-50 mb-2">{{ title }}</h1>
                    <p class="text-sm text-slate-500 dark:text-slate-400 leading-relaxed">{{ description }}</p>
                </div>

                <!-- Form slot -->
                <div class="auth-form">
                    <slot />
                </div>
            </div>

            <!-- Footer -->
            <footer class="mt-6 flex justify-between items-center w-full px-2">
                <span class="font-mono text-xs text-slate-400 dark:text-slate-500">&copy; {{ new Date().getFullYear() }} RackAudit</span>
                <span class="flex items-center gap-2 font-mono text-xs text-slate-500 dark:text-slate-400">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                    System Operational
                </span>
            </footer>
        </div>
    </div>
</template>

<style scoped>
.auth-container {
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

/* Form styles */
.auth-form :deep(label) {
    font-family: 'IBM Plex Mono', monospace;
    font-size: 0.75rem;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.auth-form :deep(input) {
    transition: all 0.15s ease;
}

.auth-form :deep(button[type="submit"]) {
    font-family: 'IBM Plex Sans', system-ui, sans-serif !important;
    font-weight: 500 !important;
    transition: all 0.15s ease !important;
}

.auth-form :deep(a) {
    transition: color 0.15s ease;
}

/* Responsive */
@media (max-width: 640px) {
    .auth-container > div:last-child {
        padding: 1rem;
    }

    .auth-container > div:last-child > div:first-child {
        padding: 1.5rem;
    }

    .auth-container > div:last-child > footer {
        flex-direction: column;
        gap: 0.5rem;
    }
}
</style>
