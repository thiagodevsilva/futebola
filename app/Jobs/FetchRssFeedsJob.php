<?php

namespace App\Jobs;

use App\Models\Feed;
use App\Services\Rss\RssAggregatorService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class FetchRssFeedsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 120;

    public function __construct(
        public ?int $feedId = null
    ) {
        $this->onQueue('rss');
    }

    public function handle(RssAggregatorService $aggregator): void
    {
        $feeds = $this->feedId
            ? Feed::active()->where('id', $this->feedId)->get()
            : Feed::active()->ordered()->get();

        foreach ($feeds as $feed) {
            try {
                $newCount = $aggregator->processFeed($feed);
                Log::info('FetchRssFeedsJob processed feed', ['feed' => $feed->name, 'new_items' => $newCount]);
            } catch (\Throwable $e) {
                Log::error('FetchRssFeedsJob failed for feed', ['feed_id' => $feed->id, 'message' => $e->getMessage()]);
                if ($this->feedId) {
                    throw $e;
                }
            }
        }
    }
}
