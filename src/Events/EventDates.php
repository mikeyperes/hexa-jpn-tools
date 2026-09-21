<?php

declare(strict_types=1);

namespace Hexa\JpnTools\Events;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use RuntimeException;
use Throwable;

final class EventDates
{
    public const TIMEZONE = 'America/New_York';
    public const STORAGE_FORMAT = 'Y-m-d H:i:s';

    public function timezone(): DateTimeZone
    {
        return new DateTimeZone(self::TIMEZONE);
    }

    public function parse(string $value): DateTimeImmutable
    {
        $value = trim($value);
        if ($value === '') {
            throw new RuntimeException('Event date is empty.');
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/D', $value)) {
            $local = DateTimeImmutable::createFromFormat('!' . self::STORAGE_FORMAT, $value, $this->timezone());
            $errors = DateTimeImmutable::getLastErrors();
            if ($local instanceof DateTimeImmutable
                && ($errors === false || (($errors['warning_count'] ?? 0) === 0 && ($errors['error_count'] ?? 0) === 0))
                && $local->format(self::STORAGE_FORMAT) === $value) {
                return $local;
            }

            throw new RuntimeException('Invalid event date.');
        }

        try {
            $parsed = new DateTimeImmutable($value, $this->timezone());
        } catch (Throwable $exception) {
            throw new RuntimeException('Invalid event date.', 0, $exception);
        }

        return $parsed->setTimezone($this->timezone());
    }

    public function normalize(string $value): array
    {
        $date = $this->parse($value);

        return [
            'storage' => $date->format(self::STORAGE_FORMAT),
            'timestamp' => $date->getTimestamp(),
            'display' => $date->format('F j') . ' at ' . $date->format('g:iA'),
            'iso8601' => $date->format(DateTimeInterface::ATOM),
        ];
    }

    public function calendarWindow(string $period, ?int $now = null): array
    {
        $instant = (new DateTimeImmutable('@' . (string) ($now ?? time())))->setTimezone($this->timezone());
        $today = $instant->setTime(0, 0, 0);

        return match ($period) {
            'today' => [$today->getTimestamp(), $today->modify('+1 day')->getTimestamp()],
            'tomorrow' => [$today->modify('+1 day')->getTimestamp(), $today->modify('+2 days')->getTimestamp()],
            'week' => [$today->getTimestamp(), $today->modify('+7 days')->getTimestamp()],
            default => throw new RuntimeException('Unknown event period.'),
        };
    }

    public function formatTimestamp(int $timestamp, string $format): string
    {
        return (new DateTimeImmutable('@' . (string) $timestamp))
            ->setTimezone($this->timezone())
            ->format($format);
    }
}
