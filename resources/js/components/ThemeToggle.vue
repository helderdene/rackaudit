<script setup lang="ts">
import { useAppearance } from '@/composables/useAppearance';
import { onMounted, ref } from 'vue';

const { updateAppearance } = useAppearance();

const isDark = ref(true);

function checkDarkMode() {
    isDark.value = document.documentElement.classList.contains('dark');
}

onMounted(() => {
    checkDarkMode();
    const observer = new MutationObserver(checkDarkMode);
    observer.observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });
});

function toggle() {
    const newTheme = isDark.value ? 'light' : 'dark';
    updateAppearance(newTheme);
    isDark.value = !isDark.value;
}
</script>

<template>
    <button
        type="button"
        class="flex items-center justify-center w-9 h-9 rounded-md border cursor-pointer transition-colors
               border-slate-200 dark:border-slate-700
               text-slate-500 dark:text-slate-400
               hover:border-sky-300 dark:hover:border-sky-500/50
               hover:text-sky-600 dark:hover:text-sky-400
               hover:bg-sky-50 dark:hover:bg-sky-500/10"
        :title="isDark ? 'Switch to light mode' : 'Switch to dark mode'"
        @click="toggle"
    >
        <!-- Sun icon (shown in dark mode) -->
        <svg
            v-if="isDark"
            class="w-[18px] h-[18px]"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="1.5"
        >
            <circle cx="12" cy="12" r="4" />
            <path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M6.34 17.66l-1.41 1.41M19.07 4.93l-1.41 1.41" />
        </svg>
        <!-- Moon icon (shown in light mode) -->
        <svg
            v-else
            class="w-[18px] h-[18px]"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="1.5"
        >
            <path d="M21 12.79A9 9 0 1111.21 3 7 7 0 0021 12.79z" />
        </svg>
    </button>
</template>
