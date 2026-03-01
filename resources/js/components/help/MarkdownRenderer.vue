<script setup lang="ts">
import { cn } from '@/lib/utils';
import { marked } from 'marked';
import { computed } from 'vue';

interface Props {
    content: string;
    class?: string;
}

const props = withDefaults(defineProps<Props>(), {
    class: '',
});

/**
 * Configure marked options for secure and consistent rendering
 */
marked.setOptions({
    gfm: true,
    breaks: true,
});

/**
 * Custom renderer for enhanced styling - compatible with marked v17+
 * Only override block-level elements to avoid recursion issues
 */
const renderer = new marked.Renderer();

// Enhanced heading rendering with anchor links
renderer.heading = function ({ tokens, depth, text }) {
    // Use this.parser to render child tokens
    const renderedText = this.parser.parseInline(tokens);
    const slug = text.toLowerCase().replace(/[^\w]+/g, '-');
    const sizes: Record<number, string> = {
        1: 'text-2xl font-bold tracking-tight text-foreground mt-0 mb-6 pb-3 border-b border-border/50',
        2: 'text-xl font-semibold tracking-tight text-foreground mt-10 mb-4 flex items-center gap-2',
        3: 'text-lg font-semibold text-foreground mt-8 mb-3',
        4: 'text-base font-semibold text-foreground mt-6 mb-2',
    };

    // Add decorative element to h2
    const h2Icon =
        depth === 2
            ? '<span class="inline-flex size-1.5 rounded-full bg-primary/70"></span>'
            : '';

    return `<h${depth} id="${slug}" class="${sizes[depth] || sizes[4]}">${h2Icon}${renderedText}</h${depth}>`;
};

// Enhanced paragraph rendering
renderer.paragraph = function ({ tokens }) {
    const text = this.parser.parseInline(tokens);
    return `<p class="text-[15px] leading-7 text-muted-foreground mb-4 [&:last-child]:mb-0">${text}</p>`;
};

// Enhanced code rendering
renderer.code = function ({ text, lang }) {
    const language = lang || '';
    return `
        <div class="relative group my-5">
            ${language ? `<span class="absolute top-2 right-2 text-[10px] uppercase tracking-wider text-muted-foreground/50 font-mono">${language}</span>` : ''}
            <pre class="bg-muted/50 border border-border/50 rounded-lg p-4 overflow-x-auto"><code class="text-[13px] font-mono text-foreground/90 leading-relaxed">${text}</code></pre>
        </div>
    `;
};

// Enhanced inline code
renderer.codespan = function ({ text }) {
    return `<code class="text-[13px] font-mono bg-muted/70 text-primary px-1.5 py-0.5 rounded-md border border-border/30">${text}</code>`;
};

// Enhanced blockquote
renderer.blockquote = function ({ tokens }) {
    const text = this.parser.parse(tokens);
    return `
        <blockquote class="my-6 relative pl-4 border-l-2 border-primary/50">
            <div class="text-[15px] text-muted-foreground italic leading-7">${text}</div>
        </blockquote>
    `;
};

// Enhanced table - handles array of rows in marked v17+
renderer.table = function ({ header, rows, align }) {
    // Render header row
    const headerCells = header
        .map((cell, i) => {
            const cellText = this.parser.parseInline(cell.tokens);
            const cellAlign = align[i];
            const alignClass =
                cellAlign === 'center'
                    ? 'text-center'
                    : cellAlign === 'right'
                      ? 'text-right'
                      : '';
            return `<th class="px-4 py-3 text-left font-semibold text-foreground text-[13px] uppercase tracking-wider ${alignClass}">${cellText}</th>`;
        })
        .join('');
    const headerRow = `<tr>${headerCells}</tr>`;

    // Render body rows
    const bodyRows = rows
        .map((row) => {
            const cells = row
                .map((cell, i) => {
                    const cellText = this.parser.parseInline(cell.tokens);
                    const cellAlign = align[i];
                    const alignClass =
                        cellAlign === 'center'
                            ? 'text-center'
                            : cellAlign === 'right'
                              ? 'text-right'
                              : '';
                    return `<td class="px-4 py-3 text-muted-foreground ${alignClass}">${cellText}</td>`;
                })
                .join('');
            return `<tr class="hover:bg-muted/30 transition-colors">${cells}</tr>`;
        })
        .join('');

    return `
        <div class="my-6 overflow-hidden rounded-lg border border-border/50">
            <table class="w-full text-[14px]">
                <thead class="bg-muted/50 border-b border-border/50">
                    ${headerRow}
                </thead>
                <tbody class="divide-y divide-border/30">
                    ${bodyRows}
                </tbody>
            </table>
        </div>
    `;
};

// Enhanced link
renderer.link = function ({ href, title, tokens }) {
    const text = this.parser.parseInline(tokens);
    const titleAttr = title ? ` title="${title}"` : '';
    return `<a href="${href}"${titleAttr} class="text-primary font-medium hover:underline underline-offset-4 transition-colors">${text}</a>`;
};

// Enhanced strong
renderer.strong = function ({ tokens }) {
    const text = this.parser.parseInline(tokens);
    return `<strong class="font-semibold text-foreground">${text}</strong>`;
};

// Enhanced emphasis
renderer.em = function ({ tokens }) {
    const text = this.parser.parseInline(tokens);
    return `<em class="italic">${text}</em>`;
};

// Enhanced horizontal rule
renderer.hr = function () {
    return `<hr class="my-8 border-none h-px bg-gradient-to-r from-transparent via-border to-transparent" />`;
};

// Enhanced list
renderer.list = function ({ ordered, start, items }) {
    const tag = ordered ? 'ol' : 'ul';
    const startAttr = ordered && start !== 1 ? ` start="${start}"` : '';
    const listClass = ordered
        ? 'list-decimal pl-6 my-4 space-y-2'
        : 'list-none pl-0 my-4 space-y-2';

    const itemsHtml = items
        .map((item) => {
            const itemText = this.parser.parse(item.tokens);
            return `<li class="text-[15px] leading-7 text-muted-foreground">${itemText}</li>`;
        })
        .join('');

    return `<${tag}${startAttr} class="${listClass}">${itemsHtml}</${tag}>`;
};

marked.use({ renderer });

/**
 * Parse markdown content to HTML
 */
const renderedContent = computed(() => {
    if (!props.content) {
        return '';
    }
    return marked.parse(props.content);
});
</script>

<template>
    <div
        :class="cn('markdown-content', props.class)"
        v-html="renderedContent"
    />
</template>

<style>
/* First heading should have no top margin */
.markdown-content > h1:first-child,
.markdown-content > h2:first-child,
.markdown-content > h3:first-child {
    margin-top: 0 !important;
}

/* List styling for unordered lists */
.markdown-content ul:not([class]) {
    list-style: none;
    padding-left: 0;
    margin: 1rem 0;
}

.markdown-content ul:not([class]) > li {
    position: relative;
    padding-left: 1.5rem;
    margin-bottom: 0.5rem;
    font-size: 15px;
    line-height: 1.75;
    color: hsl(var(--muted-foreground));
}

.markdown-content ul:not([class]) > li::before {
    content: '';
    position: absolute;
    left: 0;
    top: 0.65rem;
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background-color: hsl(var(--primary) / 0.6);
}

.markdown-content ol:not([class]) {
    list-style: decimal;
    padding-left: 1.5rem;
    margin: 1rem 0;
}

.markdown-content ol:not([class]) > li {
    margin-bottom: 0.5rem;
    font-size: 15px;
    line-height: 1.75;
    color: hsl(var(--muted-foreground));
}

.markdown-content ol:not([class]) > li::marker {
    color: hsl(var(--primary) / 0.7);
    font-weight: 600;
}

/* Nested list styling */
.markdown-content ul ul,
.markdown-content ol ul {
    margin-top: 0.5rem;
    margin-bottom: 0.5rem;
}

.markdown-content ul ul li::before {
    width: 5px;
    height: 5px;
    background-color: hsl(var(--muted-foreground) / 0.4);
}

/* Remove margin from last paragraph in list items */
.markdown-content li > p:last-child {
    margin-bottom: 0;
}

/* Image styling */
.markdown-content img {
    border-radius: 0.5rem;
    margin: 1.5rem 0;
    box-shadow:
        0 4px 6px -1px rgb(0 0 0 / 0.1),
        0 2px 4px -2px rgb(0 0 0 / 0.1);
}

/* Smooth scroll for anchor links */
.markdown-content {
    scroll-behavior: smooth;
}

/* Code block scrollbar */
.markdown-content pre::-webkit-scrollbar {
    height: 6px;
}

.markdown-content pre::-webkit-scrollbar-track {
    background: transparent;
}

.markdown-content pre::-webkit-scrollbar-thumb {
    background: hsl(var(--border));
    border-radius: 3px;
}

/* Task list checkboxes */
.markdown-content input[type='checkbox'] {
    appearance: none;
    width: 1rem;
    height: 1rem;
    border: 2px solid hsl(var(--border));
    border-radius: 0.25rem;
    margin-right: 0.5rem;
    vertical-align: middle;
    position: relative;
    cursor: pointer;
}

.markdown-content input[type='checkbox']:checked {
    background-color: hsl(var(--primary));
    border-color: hsl(var(--primary));
}

.markdown-content input[type='checkbox']:checked::after {
    content: '';
    position: absolute;
    left: 3px;
    top: 0px;
    width: 5px;
    height: 9px;
    border: solid white;
    border-width: 0 2px 2px 0;
    transform: rotate(45deg);
}
</style>
