<?php

namespace App\Services\Calendar;

use App\Models\CalendarSource;
use App\Rules\PublicFeedUrl;
use Carbon\CarbonImmutable;
use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\UriResolver;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use RuntimeException;
use Sabre\VObject\Component\VEvent;
use Sabre\VObject\Reader;

class FeedImporter
{
    private const MAX_BYTES = 5_000_000;

    public function import(CalendarSource $source): int
    {
        $response = $this->fetch($source);

        if ($response->status() === 304) {
            $source->update(['sync_status' => 'synced', 'sync_error' => null, 'last_synced_at' => now()]);

            return $source->events()->count();
        }

        if (! $response->successful()) {
            throw new RuntimeException("Feed returned HTTP {$response->status()}.");
        }

        if (strlen($response->body()) > self::MAX_BYTES) {
            throw new RuntimeException('Feed is larger than the 5 MB import limit.');
        }

        $windowStart = CarbonImmutable::now('UTC')->subYear()->startOfDay();
        $windowEnd = CarbonImmutable::now('UTC')->addYears(2)->endOfDay();

        try {
            $calendar = Reader::read($response->body());
            $expanded = $calendar->expand($windowStart, $windowEnd);
        } catch (\Throwable $exception) {
            throw new RuntimeException('The feed is not valid iCalendar data.', previous: $exception);
        }

        $events = [];
        foreach ($expanded->VEVENT as $event) {
            if (! $event instanceof VEvent || strtoupper((string) ($event->STATUS ?? '')) === 'CANCELLED') {
                continue;
            }

            $normalized = $this->normalizeEvent($event);
            $normalized['calendar_source_id'] = $source->id;
            $events[$normalized['occurrence_key']] = $normalized;

            if (count($events) > 25_000) {
                throw new RuntimeException('Feed expands to more than 25,000 events in the import window.');
            }
        }

        DB::transaction(function () use ($source, $events, $response): void {
            $keys = array_keys($events);

            foreach (array_chunk(array_values($events), 500) as $chunk) {
                $source->events()->upsert(
                    $chunk,
                    ['calendar_source_id', 'occurrence_key'],
                    ['uid', 'title', 'description', 'location', 'starts_at', 'ends_at', 'all_day', 'status', 'updated_at'],
                );
            }

            $stale = $source->events();
            if ($keys !== []) {
                $stale->whereNotIn('occurrence_key', $keys);
            }
            $stale->delete();

            $source->update([
                'sync_status' => 'synced',
                'sync_error' => null,
                'etag' => $response->header('ETag'),
                'last_modified' => $response->header('Last-Modified'),
                'last_synced_at' => now(),
            ]);
        });

        return count($events);
    }

    private function request(CalendarSource $source): PendingRequest
    {
        $headers = ['Accept' => 'text/calendar, application/ics, text/plain;q=0.8'];

        if ($source->etag) {
            $headers['If-None-Match'] = $source->etag;
        }
        if ($source->last_modified) {
            $headers['If-Modified-Since'] = $source->last_modified;
        }

        return Http::withHeaders($headers)
            ->connectTimeout(5)
            ->timeout(15)
            ->withOptions(['allow_redirects' => false]);
    }

    private function fetch(CalendarSource $source): Response
    {
        $url = $source->normalized_url;

        for ($redirects = 0; $redirects <= 5; $redirects++) {
            $this->assertPublicUrl($url);
            $response = $this->request($source)->get($url);

            if (! in_array($response->status(), [301, 302, 303, 307, 308], true)) {
                return $response;
            }

            $location = $response->header('Location');
            if (! $location) {
                throw new RuntimeException('Feed redirect did not include a destination.');
            }

            $url = (string) UriResolver::resolve(new Uri($url), new Uri($location));
        }

        throw new RuntimeException('Feed exceeded the limit of 5 redirects.');
    }

    private function assertPublicUrl(string $url): void
    {
        $validation = Validator::make(
            ['url' => $url],
            ['url' => ['required', new PublicFeedUrl]],
        );

        if ($validation->fails()) {
            throw new RuntimeException('Feed URL no longer resolves to a public internet host.');
        }
    }

    /** @return array<string, mixed> */
    private function normalizeEvent(VEvent $event): array
    {
        $startsAt = CarbonImmutable::instance($event->DTSTART->getDateTime())->utc();
        $allDay = ! $event->DTSTART->hasTime();

        if (isset($event->DTEND)) {
            $endsAt = CarbonImmutable::instance($event->DTEND->getDateTime())->utc();
        } elseif (isset($event->DURATION)) {
            $endsAt = $startsAt->add($event->DURATION->getDateInterval());
        } else {
            $endsAt = $allDay ? $startsAt->addDay() : $startsAt->addHour();
        }

        $uid = trim((string) ($event->UID ?? ''));
        $identity = $uid.'|'.$startsAt->format('Y-m-d\TH:i:s\Z');

        return [
            'occurrence_key' => hash('sha256', $identity),
            'uid' => $uid !== '' ? $uid : hash('sha256', $identity.'|'.(string) ($event->SUMMARY ?? '')),
            'title' => trim((string) ($event->SUMMARY ?? 'Untitled event')) ?: 'Untitled event',
            'description' => $this->nullableText($event->DESCRIPTION ?? null),
            'location' => $this->nullableText($event->LOCATION ?? null),
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'all_day' => $allDay,
            'status' => $this->nullableText($event->STATUS ?? null),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    private function nullableText(mixed $value): ?string
    {
        $text = trim((string) ($value ?? ''));

        return $text === '' ? null : $text;
    }
}
