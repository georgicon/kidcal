<?php

namespace App\Jobs;

use App\Models\CalendarSource;
use App\Services\Calendar\FeedImporter;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SyncCalendarSource implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public function __construct(public int $sourceId) {}

    public function handle(FeedImporter $importer): void
    {
        $source = CalendarSource::find($this->sourceId);

        if (! $source || ! $source->enabled) {
            return;
        }

        $source->update(['sync_status' => 'syncing', 'sync_error' => null]);

        try {
            $importer->import($source);
        } catch (\Throwable $exception) {
            report($exception);
            $source->update([
                'sync_status' => 'failed',
                'sync_error' => str($exception->getMessage())->limit(1000),
            ]);
        }
    }
}
