<?php

declare(strict_types=1);

namespace Hexa\JpnTools\Events;

use Hexa\JpnTools\Plugin;

/**
 * How one event reads in Hexa WP Core's item lightbox, shared by the event
 * calendar and the host map: the flyer beside the same badges, facts, and
 * RSVP / Details buttons as the event cards.
 *
 * Core owns the dialog, the endpoint, eligibility, and the interaction; it
 * makes the event the global post while this renders, so the card
 * shortcodes read it.
 */
final class EventLightbox
{
    /** Profile settings for a Core calendar or map: `link_behavior`, `lightbox`, and the dialog labels. */
    public function profile(): array
    {
        return [
            'link_behavior' => 'lightbox',
            'lightbox' => [
                'post_types' => ['event'],
                'render' => [$this, 'render'],
                'assets' => [Plugin::class, 'enqueueFrontend'],
                // The card's own Details button opens the full page.
                'page_link' => false,
            ],
        ];
    }

    /** @return array<string,string> */
    public function labels(): array
    {
        return [
            'lightbox_close' => __('Close', 'hexa-jpn-tools'),
            'lightbox_loading' => __('Loading event…', 'hexa-jpn-tools'),
            'lightbox_error' => __('This event could not load.', 'hexa-jpn-tools'),
        ];
    }

    public function render(int $eventId): string
    {
        $image = has_post_thumbnail($eventId)
            ? '<div class="jpn-lightbox__media">' . get_the_post_thumbnail($eventId, 'large', ['class' => 'jpn-lightbox__image', 'loading' => 'eager']) . '</div>'
            : '';
        $excerpt = wp_trim_words(wp_strip_all_tags((string) get_the_excerpt($eventId)), 45);

        return '<article class="jpn-lightbox' . ($image === '' ? ' jpn-lightbox--no-image' : '') . '">' . $image
            . '<div class="jpn-lightbox__body">'
            . do_shortcode('[jpn_event_badges]')
            . '<h2 class="jpn-lightbox__title">' . esc_html(html_entity_decode(get_the_title($eventId), ENT_QUOTES, 'UTF-8')) . '</h2>'
            . ($excerpt !== '' ? '<p class="jpn-lightbox__excerpt">' . esc_html($excerpt) . '</p>' : '')
            . do_shortcode('[jpn_event_facts]')
            . do_shortcode('[jpn_event_actions]')
            . '</div></article>';
    }
}
