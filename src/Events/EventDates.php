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

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/D', $value)) {
            $local = DateTimeImmutable::createFromFormat('!Y-m-d', $value, $this->timezone());
            $errors = DateTimeImmutable::getLastErrors();
            if ($local instanceof DateTimeImmutable
                && ($errors === false || (($errors['warning_count'] ?? 0) === 0 && ($errors['error_count'] ?? 0) === 0))
                && $local->format('Y-m-d') === $value) {
                return $local;
            }

            throw new RuntimeException('Invalid event date.');
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
        $dateOnly = preg_match('/^\d{4}-\d{2}-\d{2}$/D', trim($value)) === 1;
        $date = $this->parse($value);

        return [
            'storage' => $date->format(self::STORAGE_FORMAT),
            'timestamp' => $date->getTimestamp(),
            'display' => $dateOnly ? $date->format('F j') : $date->format('F j') . ' at ' . $date->format('g:iA'),
            'iso8601' => $date->format(DateTimeInterface::ATOM),
            'precision' => $dateOnly ? 'date' : 'date_time',
        ];
    }

    /** Midnight today in the event timezone: the single cutoff between upcoming and past events. */
    public static function startOfToday(?int $now = null): int
    {
        return (new DateTimeImmutable('@' . (string) ($now ?? time())))
            ->setTimezone(new DateTimeZone(self::TIMEZONE))
            ->setTime(0, 0, 0)
            ->getTimestamp();
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

    /**
     * Card "When" value: the day (or first – last day for multi-day events) and
     * the start time, which is empty for date-only events.
     *
     * @return array{date:string,time:string}
     */
    public function when(int $start, int $end = 0, bool $dateOnly = false): array
    {
        $date = $this->formatTimestamp($start, 'D, M j');
        if ($end > $start) {
            // A date-only end stored at local midnight names its own day; a timed
            // event ending before 6 AM belongs to the night it started.
            $last = $this->formatTimestamp($dateOnly ? $end : max($start, $end - 6 * 3600), 'D, M j');
            if ($last !== $date) {
                $date .= ' – ' . $last;
            }
        }

        return ['date' => $date, 'time' => $dateOnly ? '' : $this->formatTimestamp($start, 'g:i A')];
    }

    public function formatTimestamp(int $timestamp, string $format): string
    {
        return (new DateTimeImmutable('@' . (string) $timestamp))
            ->setTimezone($this->timezone())
            ->format($format);
    }
}
