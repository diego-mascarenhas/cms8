<?php

namespace App\Repositories;

use Dotlogics\Grapesjs\App\Repositories\AssetRepository;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

class TeamAssetRepository extends AssetRepository
{
    /**
     * Generate a secure hash for the team ID
     */
    protected function getTeamHash($teamId)
    {
        // Using md5 truncated to 12 characters for a good balance
        // between brevity and security
        return substr(md5('team_salt_' . $teamId . '_' . config('app.key')), 0, 12);
    }

    public function __construct()
    {
        parent::__construct();
        
        // Set team-specific upload path with simplified structure
        $teamId = Auth::check() ? (Auth::user()->currentTeam->id ?? 'default') : 'default';
        $teamHash = $this->getTeamHash($teamId);
        $this->diskPath = "media/{$teamHash}";
    }
    
    /**
     * Override the single file upload method to ensure team ID is included
     */
    public function uploadSinglgeFile(UploadedFile $file)
    {
        // Make sure we have the latest team ID in case it changed during the request
        $teamId = Auth::check() ? (Auth::user()->currentTeam->id ?? 'default') : 'default';
        $teamHash = $this->getTeamHash($teamId);
        $this->diskPath = "media/{$teamHash}";
        
        return parent::uploadSinglgeFile($file);
    }
    
    /**
     * Get the current disk path for testing
     */
    public function getDiskPath()
    {
        return $this->diskPath;
    }
    
    /**
     * For debugging - get the current team ID and hash
     */
    public function getTeamInfo()
    {
        $teamId = Auth::check() ? (Auth::user()->currentTeam->id ?? 'default') : 'default';
        $teamHash = $this->getTeamHash($teamId);
        
        return [
            'team_id' => $teamId,
            'team_hash' => $teamHash,
            'path' => $this->diskPath
        ];
    }
} 