<?php

namespace App\Helpers;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;

class ActivityHelper
{
    /**
     * Log file upload activity
     */
    public static function logFileUpload(UploadedFile $file, $model = null, $description = null)
    {
        $user = Auth::user();

        if (! $user) {
            return;
        }

        $activity = activity()
            ->causedBy($user)
            ->withProperties([
                'file_name' => $file->getClientOriginalName(),
                'file_size' => $file->getSize(),
                'file_type' => $file->getMimeType(),
                'file_extension' => $file->getClientOriginalExtension(),
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);

        if ($model) {
            $activity->performedOn($model);
        }

        $logMessage = $description ?? 'File uploaded: '.$file->getClientOriginalName();

        $activity->log($logMessage);
    }

    /**
     * Log custom activity
     */
    public static function log($message, $model = null, array $properties = [])
    {
        $user = Auth::user();

        if (! $user) {
            return;
        }

        $defaultProperties = [
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'timestamp' => now()->toISOString(),
        ];

        $activity = activity()
            ->causedBy($user)
            ->withProperties(array_merge($defaultProperties, $properties));

        if ($model) {
            $activity->performedOn($model);
        }

        $activity->log($message);
    }

    /**
     * Log data export activity
     */
    public static function logDataExport($exportType, $recordCount = null, $model = null)
    {
        $properties = [
            'export_type' => $exportType,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ];

        if ($recordCount) {
            $properties['record_count'] = $recordCount;
        }

        self::log("Data exported: {$exportType}", $model, $properties);
    }

    /**
     * Log search activity
     */
    public static function logSearch($searchTerm, $module = null, $resultCount = null)
    {
        $properties = [
            'search_term' => $searchTerm,
            'module' => $module,
            'ip_address' => request()->ip(),
        ];

        if ($resultCount !== null) {
            $properties['result_count'] = $resultCount;
        }

        self::log("Search performed: {$searchTerm}", null, $properties);
    }

    /**
     * Log email sent activity
     */
    public static function logEmailSent($to, $subject, $model = null)
    {
        $properties = [
            'email_to' => $to,
            'email_subject' => $subject,
            'ip_address' => request()->ip(),
        ];

        self::log("Email sent to: {$to}", $model, $properties);
    }

    /**
     * Log API access
     */
    public static function logApiAccess($endpoint, $method = 'GET', $responseCode = null)
    {
        $properties = [
            'api_endpoint' => $endpoint,
            'http_method' => $method,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ];

        if ($responseCode) {
            $properties['response_code'] = $responseCode;
        }

        self::log("API accessed: {$method} {$endpoint}", null, $properties);
    }
}
