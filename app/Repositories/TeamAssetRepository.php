<?php

namespace App\Repositories;

use Dotlogics\Grapesjs\App\Repositories\AssetRepository;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\UploadedFile;

class TeamAssetRepository extends AssetRepository
{
    public function __construct()
    {
        parent::__construct();
        
        // Set team-specific upload path
        $teamId = Auth::check() ? (Auth::user()->currentTeam->id ?? 'default') : 'default';
        $this->diskPath = "laravel-grapesjs/media/team_{$teamId}";
    }
    
    /**
     * Override the single file upload method to ensure team ID is included
     */
    public function uploadSinglgeFile(UploadedFile $file)
    {
        // Make sure we have the latest team ID in case it changed during the request
        $teamId = Auth::check() ? (Auth::user()->currentTeam->id ?? 'default') : 'default';
        $this->diskPath = "laravel-grapesjs/media/team_{$teamId}";
        
        return parent::uploadSinglgeFile($file);
    }
    
    /**
     * Get the current disk path for testing
     */
    public function getDiskPath()
    {
        return $this->diskPath;
    }
} 