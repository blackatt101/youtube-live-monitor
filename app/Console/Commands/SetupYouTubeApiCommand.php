<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class SetupYouTubeApiCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'youtube:setup-api
                            {api_key : YouTube Data API v3 key}
                            {--test : Test the API key after setting}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Set up YouTube Data API v3 key for accurate stream detection';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $apiKey = $this->argument('api_key');
        $testKey = $this->option('test');

        $this->info('Setting up YouTube Data API v3...');

        // Validate API key format
        if (!preg_match('/^[a-zA-Z0-9_-]{30,}$/', $apiKey)) {
            $this->error('Invalid API key format. YouTube API keys are typically 39 characters.');
            return self::FAILURE;
        }

        // Test the API key if requested
        if ($testKey || $this->confirm('Would you like to test the API key?', true)) {
            $this->info('Testing API key...');

            try {
                $response = Http::timeout(10)
                    ->get('https://www.googleapis.com/youtube/v3/channels', [
                        'key' => $apiKey,
                        'id' => 'UC_x5XG1OV2P6uZZ5FSM9Ttw', // Google's official channel
                        'part' => 'snippet',
                    ]);

                if ($response->successful()) {
                    $data = $response->json();
                    if (!empty($data['items'])) {
                        $channelName = $data['items'][0]['snippet']['title'] ?? 'Unknown';
                        $this->info("✓ API key is valid! Connected to: {$channelName}");
                    } else {
                        $this->warn('API key returned no data - quota may be exhausted or key may be restricted.');
                    }
                } else {
                    $error = $response->json()['error']['message'] ?? 'Unknown error';
                    $this->error("API key test failed: {$error}");
                    $this->newLine();
                    $this->line('Common issues:');
                    $this->line('  - API key not enabled for YouTube Data API v3');
                    $this->line('  - API key is restricted to specific IPs or referrers');
                    $this->line('  - API quota has been exceeded');
                    return self::FAILURE;
                }
            } catch (\Exception $e) {
                $this->error("Connection test failed: {$e->getMessage()}");
                return self::FAILURE;
            }
        }

        // Save to .env
        $this->saveToEnv($apiKey);

        $this->newLine();
        $this->info('✓ YouTube API setup complete!');
        $this->newLine();
        $this->line('Next steps:');
        $this->line('  1. Update config/youtube.php or set YOUTUBE_DETECTION_PROVIDER=youtube_api');
        $this->line('  2. Run: php artisan config:clear');
        $this->line('  3. Test: php artisan monitor:check --sync');

        return self::SUCCESS;
    }

    /**
     * Save API key to .env file
     */
    private function saveToEnv(string $apiKey): void
    {
        $envFile = base_path('.env');
        $envContent = file_get_contents($envFile);

        // Check if YOUTUBE_API_KEY already exists
        if (preg_match('/^YOUTUBE_API_KEY=/m', $envContent)) {
            $envContent = preg_replace(
                '/^YOUTUBE_API_KEY=.*$/m',
                "YOUTUBE_API_KEY={$apiKey}",
                $envContent
            );
            $this->info('Updated YOUTUBE_API_KEY in .env');
        } else {
            $envContent .= "\nYOUTUBE_API_KEY={$apiKey}\n";
            $this->info('Added YOUTUBE_API_KEY to .env');
        }

        // Also update detection provider to use API
        if (preg_match('/^YOUTUBE_DETECTION_PROVIDER=/m', $envContent)) {
            $envContent = preg_replace(
                '/^YOUTUBE_DETECTION_PROVIDER=.*$/m',
                "YOUTUBE_DETECTION_PROVIDER=youtube_api",
                $envContent
            );
        } else {
            $envContent .= "YOUTUBE_DETECTION_PROVIDER=youtube_api\n";
        }
        $this->info('Updated YOUTUBE_DETECTION_PROVIDER to youtube_api');

        file_put_contents($envFile, $envContent);

        // Also update runtime config
        config(['youtube.api.key' => $apiKey]);
        config(['youtube.provider' => 'youtube_api']);
    }
}
