<?php

namespace App\Providers;

use App\Contracts\Services\LiveDetectionProviderInterface;
use App\Services\Detection\YouTubeLiveDetector;
use App\Services\Detection\YouTubeApiDetector;
use App\Services\Detection\HybridDetectionService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Map provider names to classes
        $providers = [
            'youtube_direct' => YouTubeLiveDetector::class,
            'youtube_api' => YouTubeApiDetector::class,
            'hybrid' => HybridDetectionService::class,
            YouTubeLiveDetector::class => YouTubeLiveDetector::class,
            YouTubeApiDetector::class => YouTubeApiDetector::class,
            HybridDetectionService::class => HybridDetectionService::class,
        ];

        // Get the configured provider or default to hybrid
        $configuredProvider = config('youtube.provider', 'hybrid');
        $providerClass = $providers[$configuredProvider] ?? HybridDetectionService::class;

        // Register providers as singletons
        $this->app->singleton(YouTubeLiveDetector::class, function ($app) {
            return new YouTubeLiveDetector(
                timeout: config('youtube.timeout', 15),
                maxRetries: config('youtube.max_retries', 2),
                retryDelayMs: 1000,
                concurrency: config('youtube.concurrency', 5),
                requestDelayMs: config('youtube.request_delay', 500),
            );
        });

        $this->app->singleton(YouTubeApiDetector::class, function ($app) {
            return new YouTubeApiDetector(
                apiKey: config('youtube.api.key')
            );
        });

        $this->app->singleton(HybridDetectionService::class, function ($app) {
            return new HybridDetectionService(
                apiKey: config('youtube.api.key')
            );
        });

        // Bind the interface to the configured provider
        $this->app->bind(
            LiveDetectionProviderInterface::class,
            $providerClass
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
