<?php

use Idoneo\HumanoCore\Http\Controllers\TeamSettingController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Team Settings Routes - Humano Core Package
|--------------------------------------------------------------------------
|
| These routes handle all team settings functionality including
| configurations, valorations, API tokens, and custom translations.
|
*/

Route::middleware(['web', 'auth'])->group(function ()
{
    // Team Settings
    Route::get('/team/{team}/settings', [TeamSettingController::class, 'index'])
        ->name('team-settings.index');

    Route::get('/team/{team}/settings/{group?}', [TeamSettingController::class, 'edit'])
        ->name('team-settings.edit');

    Route::put('/team/{team}/settings', [TeamSettingController::class, 'update'])
        ->name('team-settings.update');

    Route::post('/team/{team}/test-smtp', [TeamSettingController::class, 'testSmtpConnection'])
        ->name('team-settings.test-smtp');

    Route::post('/team/{team}/test-imap', [TeamSettingController::class, 'testImapConnection'])
        ->name('team-settings.test-imap');

    Route::post('/team/{team}/test-stripe', [TeamSettingController::class, 'testStripeConnection'])
        ->name('team-settings.test-stripe');

    Route::post('/team/{team}/test-twilio', [TeamSettingController::class, 'testTwilioConnection'])
        ->name('team-settings.test-twilio');

    // Team Valorations
    Route::get('/team/{team}/valorations', [TeamSettingController::class, 'valorations'])
        ->name('team-settings.valorations');

    Route::post('/team/{team}/valorations', [TeamSettingController::class, 'storeValoration'])
        ->name('team-settings.valorations.store');

    Route::put('/team/{team}/valorations/{valoration}', [TeamSettingController::class, 'updateValoration'])
        ->name('team-settings.valorations.update');

    Route::delete('/team/{team}/valorations/{valoration}', [TeamSettingController::class, 'destroyValoration'])
        ->name('team-settings.valorations.destroy');

    // Team API Tokens
    Route::get('/team/{team}/api-tokens', [TeamSettingController::class, 'apiTokens'])
        ->name('team-settings.api-tokens');

    Route::post('/team/{team}/api-tokens/generate', [TeamSettingController::class, 'generateApiToken'])
        ->name('team-settings.generate-api-token');

    Route::delete('/team/{team}/api-tokens/revoke', [TeamSettingController::class, 'revokeApiToken'])
        ->name('team-settings.revoke-api-token');

    // Custom Translations
    Route::get('/team/{team}/custom-translations', [TeamSettingController::class, 'customTranslations'])
        ->name('team-settings.custom-translations');

    Route::post('/team/{team}/custom-translations', [TeamSettingController::class, 'storeCustomTranslation'])
        ->name('team-settings.custom-translations.store');

    Route::put('/team/{team}/custom-translations/{translation}', [TeamSettingController::class, 'updateCustomTranslation'])
        ->name('team-settings.custom-translations.update');

    Route::delete('/team/{team}/custom-translations/{translation}', [TeamSettingController::class, 'destroyCustomTranslation'])
        ->name('team-settings.custom-translations.destroy');

    Route::post('/team/{team}/custom-translations/import', [TeamSettingController::class, 'importCustomTranslations'])
        ->name('team-settings.custom-translations.import');
});
