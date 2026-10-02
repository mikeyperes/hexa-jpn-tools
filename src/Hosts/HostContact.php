<?php

declare(strict_types=1);

namespace Hexa\JpnTools\Hosts;

use Hexa\JpnTools\Rest\HostController;

/**
 * A host's preferred contact method (the `preferred_contact` field) as one
 * link visitors can use, built from the host's existing contact fields.
 *
 * The account (login) email is never shown; only the public `email` field is.
 */
final class HostContact
{
    /** Field choices; the keys are stored in `preferred_contact`. */
    public const CHOICES = [
        'website' => 'Website',
        'email' => 'Email',
        'phone' => 'Phone',
        'whatsapp' => 'WhatsApp',
        'instagram' => 'Instagram',
        'facebook' => 'Facebook',
    ];

    /**
     * The preferred method's link, or null when no method is chosen or its field is empty.
     *
     * @return array{method:string,label:string,url:string,value:string}|null
     */
    public static function preferred(int $hostId): ?array
    {
        $method = (string) get_user_meta($hostId, 'preferred_contact', true);

        return isset(self::CHOICES[$method]) ? self::link($hostId, $method) : null;
    }

    /** @return array{method:string,label:string,url:string,value:string}|null */
    public static function link(int $hostId, string $method): ?array
    {
        $meta = static fn (string $key): string => trim((string) get_user_meta($hostId, $key, true));
        [$url, $value, $label] = match ($method) {
            'website' => self::website($hostId),
            'email' => [is_email($meta('email')) ? 'mailto:' . $meta('email') : '', $meta('email'), __('Email the host', 'hexa-jpn-tools')],
            'phone' => self::phone($meta('phone')),
            'whatsapp' => self::whatsapp($meta('whatsapp_url')),
            'instagram' => self::instagram($hostId),
            'facebook' => [self::https($meta('facebook_url')), '', __('Message on Facebook', 'hexa-jpn-tools')],
            default => ['', '', ''],
        };

        return $url === '' ? null : ['method' => $method, 'label' => $label, 'url' => $url, 'value' => $value];
    }

    /** @return array{string,string,string} */
    private static function website(int $hostId): array
    {
        $user = get_userdata($hostId);
        $url = self::https((string) get_user_meta($hostId, 'website', true) ?: ($user ? (string) $user->user_url : ''));
        $host = (string) preg_replace('#^www\.#i', '', (string) wp_parse_url($url, PHP_URL_HOST));

        return [$url, $host, __('Visit website', 'hexa-jpn-tools')];
    }

    /** @return array{string,string,string} */
    private static function phone(string $phone): array
    {
        $digits = (string) preg_replace('/[^\d+]/', '', $phone);

        return [strlen(ltrim($digits, '+')) >= 7 ? 'tel:' . $digits : '', $phone, __('Call the host', 'hexa-jpn-tools')];
    }

    /** @return array{string,string,string} */
    private static function whatsapp(string $value): array
    {
        $digits = (string) preg_replace('/\D/', '', $value);
        $url = preg_match('#^https?://#i', $value) ? self::https($value) : (strlen($digits) >= 7 ? 'https://wa.me/' . $digits : '');

        return [$url, '', __('Message on WhatsApp', 'hexa-jpn-tools')];
    }

    /** @return array{string,string,string} */
    private static function instagram(int $hostId): array
    {
        $handle = (string) (HostController::summary($hostId)['instagram_handle'] ?? '');

        return [$handle !== '' ? 'https://www.instagram.com/' . rawurlencode($handle) . '/' : '', $handle !== '' ? '@' . $handle : '', __('Message on Instagram', 'hexa-jpn-tools')];
    }

    private static function https(string $url): string
    {
        $url = trim($url);
        if ($url !== '' && !preg_match('#^https?://#i', $url)) {
            $url = 'https://' . $url;
        }

        return (string) esc_url_raw($url, ['http', 'https']);
    }
}
