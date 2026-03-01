<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Calendar, Clock, Globe } from 'lucide-vue-next';
import { computed } from 'vue';

interface FrequencyOption {
    value: string;
    label: string;
    description: string;
}

interface TimezoneOption {
    value: string;
    label: string;
}

interface Props {
    frequency: string;
    dayOfWeek: number | null;
    dayOfMonth: string | null;
    timeOfDay: string;
    timezone: string;
    frequencies: FrequencyOption[];
    timezones: TimezoneOption[];
    errors?: Record<string, string>;
}

const props = withDefaults(defineProps<Props>(), {
    errors: () => ({}),
});

const emit = defineEmits<{
    (e: 'update:frequency', value: string): void;
    (e: 'update:dayOfWeek', value: number | null): void;
    (e: 'update:dayOfMonth', value: string | null): void;
    (e: 'update:timeOfDay', value: string): void;
    (e: 'update:timezone', value: string): void;
}>();

/**
 * Days of the week for weekly schedule
 */
const daysOfWeek = [
    { value: 0, label: 'Sunday' },
    { value: 1, label: 'Monday' },
    { value: 2, label: 'Tuesday' },
    { value: 3, label: 'Wednesday' },
    { value: 4, label: 'Thursday' },
    { value: 5, label: 'Friday' },
    { value: 6, label: 'Saturday' },
];

/**
 * Days of the month for monthly schedule (1-28 + "last")
 */
const daysOfMonth = [
    ...Array.from({ length: 28 }, (_, i) => ({
        value: String(i + 1),
        label: `${i + 1}${getOrdinalSuffix(i + 1)}`,
    })),
    { value: 'last', label: 'Last day of month' },
];

/**
 * Get ordinal suffix for a number
 */
function getOrdinalSuffix(n: number): string {
    if (n >= 11 && n <= 13) return 'th';
    switch (n % 10) {
        case 1:
            return 'st';
        case 2:
            return 'nd';
        case 3:
            return 'rd';
        default:
            return 'th';
    }
}

/**
 * Show day of week selector for weekly frequency
 */
const showDayOfWeek = computed(() => props.frequency === 'weekly');

/**
 * Show day of month selector for monthly frequency
 */
const showDayOfMonth = computed(() => props.frequency === 'monthly');

/**
 * Handle frequency change
 */
const handleFrequencyChange = (event: Event) => {
    const target = event.target as HTMLSelectElement;
    emit('update:frequency', target.value);
    // Reset day fields when frequency changes
    if (target.value !== 'weekly') {
        emit('update:dayOfWeek', null);
    }
    if (target.value !== 'monthly') {
        emit('update:dayOfMonth', null);
    }
};

/**
 * Handle day of week change
 */
const handleDayOfWeekChange = (event: Event) => {
    const target = event.target as HTMLSelectElement;
    emit('update:dayOfWeek', target.value ? parseInt(target.value, 10) : null);
};

/**
 * Handle day of month change
 */
const handleDayOfMonthChange = (event: Event) => {
    const target = event.target as HTMLSelectElement;
    emit('update:dayOfMonth', target.value || null);
};

/**
 * Handle time change
 */
const handleTimeChange = (event: Event) => {
    const target = event.target as HTMLInputElement;
    emit('update:timeOfDay', target.value);
};

/**
 * Handle timezone change
 */
const handleTimezoneChange = (event: Event) => {
    const target = event.target as HTMLSelectElement;
    emit('update:timezone', target.value);
};

/**
 * Select input class
 */
const selectClass =
    'flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50';
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle class="flex items-center gap-2">
                <Calendar class="h-5 w-5" />
                Schedule Frequency
            </CardTitle>
            <CardDescription>
                Choose when and how often the report should be generated.
            </CardDescription>
        </CardHeader>
        <CardContent class="space-y-6">
            <!-- Frequency Selection -->
            <div class="space-y-3">
                <Label for="frequency"
                    >Frequency <span class="text-destructive">*</span></Label
                >
                <div class="grid gap-3 sm:grid-cols-3">
                    <div
                        v-for="option in frequencies"
                        :key="option.value"
                        class="flex cursor-pointer items-start space-x-3 rounded-lg border p-4 transition-colors"
                        :class="{
                            'border-primary bg-primary/5':
                                frequency === option.value,
                            'hover:border-muted-foreground/50':
                                frequency !== option.value,
                        }"
                        @click="
                            emit('update:frequency', option.value);
                            if (option.value !== 'weekly')
                                emit('update:dayOfWeek', null);
                            if (option.value !== 'monthly')
                                emit('update:dayOfMonth', null);
                        "
                    >
                        <input
                            type="radio"
                            :id="`freq-${option.value}`"
                            :value="option.value"
                            :checked="frequency === option.value"
                            name="frequency"
                            class="mt-1 h-4 w-4 text-primary focus:ring-primary"
                            @change="handleFrequencyChange"
                        />
                        <div class="space-y-1">
                            <label
                                :for="`freq-${option.value}`"
                                class="cursor-pointer text-sm font-medium"
                            >
                                {{ option.label }}
                            </label>
                            <p class="text-xs text-muted-foreground">
                                {{ option.description }}
                            </p>
                        </div>
                    </div>
                </div>
                <InputError :message="errors.frequency" />
            </div>

            <!-- Day of Week (for Weekly) -->
            <div v-if="showDayOfWeek" class="space-y-2">
                <Label for="day-of-week"
                    >Day of Week <span class="text-destructive">*</span></Label
                >
                <select
                    id="day-of-week"
                    :value="dayOfWeek ?? ''"
                    :class="selectClass"
                    @change="handleDayOfWeekChange"
                >
                    <option value="" disabled>Select a day</option>
                    <option
                        v-for="day in daysOfWeek"
                        :key="day.value"
                        :value="day.value"
                    >
                        {{ day.label }}
                    </option>
                </select>
                <InputError :message="errors.day_of_week" />
            </div>

            <!-- Day of Month (for Monthly) -->
            <div v-if="showDayOfMonth" class="space-y-2">
                <Label for="day-of-month"
                    >Day of Month <span class="text-destructive">*</span></Label
                >
                <select
                    id="day-of-month"
                    :value="dayOfMonth ?? ''"
                    :class="selectClass"
                    @change="handleDayOfMonthChange"
                >
                    <option value="" disabled>Select a day</option>
                    <option
                        v-for="day in daysOfMonth"
                        :key="day.value"
                        :value="day.value"
                    >
                        {{ day.label }}
                    </option>
                </select>
                <InputError :message="errors.day_of_month" />
            </div>

            <!-- Time and Timezone -->
            <div class="grid gap-4 sm:grid-cols-2">
                <!-- Time of Day -->
                <div class="space-y-2">
                    <Label for="time-of-day" class="flex items-center gap-2">
                        <Clock class="h-4 w-4" />
                        Time <span class="text-destructive">*</span>
                    </Label>
                    <Input
                        id="time-of-day"
                        type="time"
                        :value="timeOfDay"
                        @input="handleTimeChange"
                        :aria-invalid="!!errors.time_of_day"
                    />
                    <InputError :message="errors.time_of_day" />
                </div>

                <!-- Timezone -->
                <div class="space-y-2">
                    <Label for="timezone" class="flex items-center gap-2">
                        <Globe class="h-4 w-4" />
                        Timezone <span class="text-destructive">*</span>
                    </Label>
                    <select
                        id="timezone"
                        :value="timezone"
                        :class="selectClass"
                        @change="handleTimezoneChange"
                    >
                        <option
                            v-for="tz in timezones"
                            :key="tz.value"
                            :value="tz.value"
                        >
                            {{ tz.label }}
                        </option>
                    </select>
                    <InputError :message="errors.timezone" />
                </div>
            </div>
        </CardContent>
    </Card>
</template>
