<?php

namespace App\Providers;

use App\Contracts\Services\LiveDetectionProviderInterface;
use App\Services\Detection\YouTubeLiveDetector;
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
            YouTubeLiveDetector::class => YouTubeLiveDetector::class,
        ];

        // Get the configured provider or default to YouTubeLiveDetector
        $configuredProvider = config('youtube.provider', 'youtube_direct');
        $providerClass = $providers[$configuredProvider] ?? YouTubeLiveDetector::class;

        // Register the provider as singleton
        $this->app->singleton($providerClass, function ($app) {
            return new YouTubeLiveDetector(
                timeout: config('youtube.timeout', 15),
                maxRetries: config('youtube.max_retries', 2),
                retryDelayMs: 1000,
                concurrency: config('youtube.concurrency', 5),
                requestDelayMs: config('youtube.request_delay', 500),
            );
        });

        // Bind the interface to the concrete implementation
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
