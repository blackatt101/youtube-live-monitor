<?php

namespace App\Console\Commands;

use App\Jobs\DetectChannelJob;
use App\Models\MonitoredChannel;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class MonitorChannelsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'monitor:check
                            {--sync : Run synchronously instead of queuing}
                            {--channel=* : Specific channel IDs to check}
                            {--batch-id= : Optional batch ID for tracking}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check monitored YouTube channels for live streams';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $sync = $this->option('sync');
        $channelIds = $this->option('channel');
        $batchId = $this->option('batch-id') ?? Str::uuid()->toString();

        // Get active monitored channels
        $query = MonitoredChannel::where('is_active', true);

        if (!empty($channelIds)) {
            $query->whereIn('id', $channelIds);
        }

        $channels = $query->get();

        if ($channels->isEmpty()) {
            $this->info('No active channels to monitor.');
            return self::SUCCESS;
        }

        $this->info("Checking {$channels->count()} channel(s) [Batch: {$batchId}]");

        if ($sync) {
            $this->runSynchronously($channels, $batchId);
        } else {
            $this->dispatchJobs($channels, $batchId);
        }

        $this->info('Monitoring jobs dispatched.');

        return self::SUCCESS;
    }

    /**
     * Run detection synchronously
     */
    private function runSynchronously($channels, string $batchId): void
    {
        $detector = app(\App\Contracts\Services\LiveDetectionProviderInterface::class);

        $bar = $this->output->createProgressBar($channels->count());
        $bar->start();

        foreach ($channels as $channel) {
            $job = new DetectChannelJob($channel, $batchId);
            $job->handle($detector);
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
    }

    /**
     * Dispatch jobs to the queue
     */
    private function dispatchJobs($channels, string $batchId): void
    {
        $dispatched = 0;

        foreach ($channels as $channel) {
            DetectChannelJob::dispatch($channel, $batchId)
                ->onQueue(config('youtube.monitoring_queue', 'youtube'));

            $dispatched++;
        }

        $this->info("Dispatched {$dispatched} detection job(s).");
        $this->info('Run queue worker: php artisan queue:work --queue=youtube');
    }
}
