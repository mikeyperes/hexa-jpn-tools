<?php

declare(strict_types=1);

namespace Hexa\JpnTools\Frontend;

final class Privacy
{
    public function register(): void
    {
        if (function_exists('jpn_code_reference_private_strip_blocks')) {
            return;
        }

        add_filter('the_content', [$this, 'filterContent'], 1);
        add_filter('the_excerpt', [$this, 'filterText'], 1);
        add_filter('get_the_excerpt', [$this, 'filterText'], 1);
        add_filter('wp_trim_excerpt', [$this, 'filterText'], 1);
        add_filter('rank_math/frontend/description', [$this, 'filterText'], 20);
        add_filter('rank_math/opengraph/facebook/description', [$this, 'filterText'], 20);
        add_filter('rank_math/opengraph/twitter/description', [$this, 'filterText'], 20);
        add_filter('rank_math/json_ld', [$this, 'filterStructuredData'], 20);
    }

    public function filterContent(mixed $content): string
    {
        $content = (string) $content;
        return $this->canViewInternalReference() ? $content : self::stripBlocks($content);
    }

    public function filterText(mixed $content): string
    {
        $content = (string) $content;
        return $this->canViewInternalReference() ? $content : self::stripText($content);
    }

    public function filterStructuredData(mixed $data): mixed
    {
        if ($this->canViewInternalReference() || !is_array($data)) {
            return $data;
        }
        array_walk_recursive($data, static function (&$value): void {
            if (is_string($value)) {
                $value = self::stripText($value);
            }
        });
        return $data;
    }

    public static function stripBlocks(string $content): string
    {
        $patterns = [
            '~<p>\s*<!--\s*jpn-code-reference:start\s*-->\s*</p>.*?<p>\s*<!--\s*jpn-code-reference:end\s*-->\s*</p>~is',
            '~<!--\s*jpn-code-reference:start\s*-->.*?<!--\s*jpn-code-reference:end\s*-->~is',
            '~<p[^>]*class=["\'][^"\']*jpn-code-reference[^"\']*["\'][^>]*>.*?</p>~is',
        ];
        return trim((string) preg_replace($patterns, '', $content));
    }

    public static function stripText(string $content): string
    {
        $content = self::stripBlocks($content);
        $content = (string) preg_replace('~\s*Code\s+ID\s+#?\d+\s*(?:&#8599;|↗|↗️)?~iu', '', $content);
        return trim((string) preg_replace('~\s{2,}~', ' ', $content));
    }

    private function canViewInternalReference(): bool
    {
        $postId = (int) get_the_ID();
        return $postId > 0 ? current_user_can('edit_post', $postId) : current_user_can('edit_posts');
    }
}
