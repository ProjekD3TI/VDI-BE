<?php

namespace App\Providers;

use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Kustomisasi URL Verifikasi Email untuk API/React
        VerifyEmail::createUrlUsing(function ($notifiable) {
            $frontendUrl = env('APP_FRONTEND_URL', 'http://localhost:3000');

            $temporarySignedUrl = URL::temporarySignedRoute(
                'verification.verify', // Nama route backend yang akan kita buat di langkah 4
                now()->addSeconds(10),  // Link kedaluwarsa dalam 60 menit
                [
                    'id' => $notifiable->getKey(),
                    'hash' => sha1($notifiable->getEmailForVerification()),
                ]
            );

            // Ekstrak query string (expires & signature) dari URL bawaan Laravel
            $queryString = parse_url($temporarySignedUrl, PHP_URL_QUERY);

            // Susun URL baru yang mengarah ke halaman verifikasi di React
            // Hasilnya akan seperti: http://localhost:3000/email-verification?expires=...&signature=...&id=...&hash=...
            return $frontendUrl . '/email-verification?' . $queryString . '&id=' . $notifiable->getKey() . '&hash=' . sha1($notifiable->getEmailForVerification());
        });
    }
}
