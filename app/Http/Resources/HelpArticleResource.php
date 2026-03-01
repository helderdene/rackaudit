<?php

namespace App\Http\Resources;

use App\Models\HelpArticle;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

/**
 * API Resource for transforming HelpArticle model data.
 *
 * Provides consistent JSON representation of help articles including
 * content formatting and optional excerpt generation.
 *
 * @mixin HelpArticle
 */
class HelpArticleResource extends JsonResource
{
    /**
     * Maximum length for article excerpts.
     */
    private const EXCERPT_LENGTH = 150;

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'title' => $this->title,
            'content' => $this->content,
            'excerpt' => $this->generateExcerpt(),
            'context_key' => $this->context_key,
            'article_type' => $this->article_type?->value,
            'article_type_label' => $this->article_type?->label(),
            'category' => $this->category,
            'sort_order' => $this->sort_order,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    /**
     * Generate a plain text excerpt from markdown content.
     */
    protected function generateExcerpt(): string
    {
        if (empty($this->content)) {
            return '';
        }

        // Remove markdown formatting
        $plainText = $this->content;

        // Remove headers
        $plainText = preg_replace('/^#{1,6}\s+/m', '', $plainText);

        // Remove bold/italic markers
        $plainText = preg_replace('/[*_]{1,2}([^*_]+)[*_]{1,2}/', '$1', $plainText);

        // Remove links but keep text
        $plainText = preg_replace('/\[([^\]]+)\]\([^)]+\)/', '$1', $plainText);

        // Remove list markers
        $plainText = preg_replace('/^[-*+]\s+/m', '', $plainText);
        $plainText = preg_replace('/^\d+\.\s+/m', '', $plainText);

        // Remove code blocks
        $plainText = preg_replace('/```[\s\S]*?```/', '', $plainText);
        $plainText = preg_replace('/`[^`]+`/', '', $plainText);

        // Collapse whitespace
        $plainText = preg_replace('/\s+/', ' ', $plainText);

        // Trim and limit length
        return Str::limit(trim($plainText), self::EXCERPT_LENGTH);
    }
}
