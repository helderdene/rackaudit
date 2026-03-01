<?php

namespace App\Http\Resources;

use App\Models\HelpArticle;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

/**
 * API Resource for transforming search results with highlighted snippets.
 *
 * Extends HelpArticle data with search-specific fields like
 * highlighted excerpts showing matching text in context.
 *
 * @mixin HelpArticle
 */
class HelpSearchResultResource extends JsonResource
{
    /**
     * Length of context around matches in snippets.
     */
    private const SNIPPET_CONTEXT_LENGTH = 50;

    /**
     * Maximum length for generated snippets.
     */
    private const MAX_SNIPPET_LENGTH = 200;

    /**
     * The search query for highlighting.
     */
    protected ?string $searchQuery = null;

    /**
     * Set the search query for highlighting.
     */
    public function setSearchQuery(string $query): self
    {
        $this->searchQuery = $query;

        return $this;
    }

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $query = $this->searchQuery ?? $request->input('query', '');

        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'title' => $this->title,
            'highlighted_title' => $this->highlightText($this->title, $query),
            'excerpt' => $this->generateSearchExcerpt($query),
            'context_key' => $this->context_key,
            'article_type' => $this->article_type?->value,
            'category' => $this->category,
            'match_type' => $this->determineMatchType($query),
        ];
    }

    /**
     * Generate a search excerpt with highlighted matches.
     */
    protected function generateSearchExcerpt(string $query): string
    {
        if (empty($this->content) || empty($query)) {
            return Str::limit($this->stripMarkdown($this->content ?? ''), self::MAX_SNIPPET_LENGTH);
        }

        $plainContent = $this->stripMarkdown($this->content);
        $position = stripos($plainContent, $query);

        if ($position === false) {
            return Str::limit($plainContent, self::MAX_SNIPPET_LENGTH);
        }

        // Calculate start position for snippet
        $start = max(0, $position - self::SNIPPET_CONTEXT_LENGTH);

        // Extract snippet
        $snippet = Str::substr($plainContent, $start, self::MAX_SNIPPET_LENGTH);

        // Add ellipsis if needed
        if ($start > 0) {
            $snippet = '...'.ltrim($snippet);
        }
        if (strlen($plainContent) > $start + self::MAX_SNIPPET_LENGTH) {
            $snippet = rtrim($snippet).'...';
        }

        return $this->highlightText($snippet, $query);
    }

    /**
     * Highlight search term in text.
     */
    protected function highlightText(string $text, string $query): string
    {
        if (empty($query)) {
            return $text;
        }

        // Escape special regex characters and make case-insensitive
        $escapedQuery = preg_quote($query, '/');

        return preg_replace(
            '/('.$escapedQuery.')/i',
            '<mark>$1</mark>',
            $text
        );
    }

    /**
     * Determine if match is in title or content.
     */
    protected function determineMatchType(string $query): string
    {
        if (empty($query)) {
            return 'none';
        }

        $titleMatch = stripos($this->title, $query) !== false;
        $contentMatch = stripos($this->content ?? '', $query) !== false;

        if ($titleMatch && $contentMatch) {
            return 'both';
        } elseif ($titleMatch) {
            return 'title';
        } elseif ($contentMatch) {
            return 'content';
        }

        return 'none';
    }

    /**
     * Strip markdown formatting from text.
     */
    protected function stripMarkdown(string $text): string
    {
        // Remove headers
        $text = preg_replace('/^#{1,6}\s+/m', '', $text);

        // Remove bold/italic markers
        $text = preg_replace('/[*_]{1,2}([^*_]+)[*_]{1,2}/', '$1', $text);

        // Remove links but keep text
        $text = preg_replace('/\[([^\]]+)\]\([^)]+\)/', '$1', $text);

        // Remove list markers
        $text = preg_replace('/^[-*+]\s+/m', '', $text);
        $text = preg_replace('/^\d+\.\s+/m', '', $text);

        // Remove code blocks
        $text = preg_replace('/```[\s\S]*?```/', '', $text);
        $text = preg_replace('/`[^`]+`/', '', $text);

        // Collapse whitespace
        $text = preg_replace('/\s+/', ' ', $text);

        return trim($text);
    }
}
