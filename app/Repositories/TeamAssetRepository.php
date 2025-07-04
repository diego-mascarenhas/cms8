<?php

namespace App\Repositories;

use Dotlogics\Grapesjs\App\Repositories\AssetRepository;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
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
        return substr(md5('team_salt_'.$teamId.'_'.config('app.key')), 0, 12);
    }

    /**
     * Normalize filename for URL safety
     *
     * @param  string  $filename
     * @return string
     */
    protected function normalizeFilename($filename)
    {
        // Get file extension
        $extension = pathinfo($filename, PATHINFO_EXTENSION);
        $name = pathinfo($filename, PATHINFO_FILENAME);

        // Transliterate accented characters to ASCII
        $name = Str::ascii($name);

        // Convert to lowercase and replace spaces with hyphens
        $name = Str::slug($name);

        // Remove any remaining non-alphanumeric characters except for hyphens
        $name = preg_replace('/[^a-z0-9\-]/', '', $name);

        // Ensure name is not empty
        if (empty($name)) {
            $name = 'file_'.substr(md5(time().rand()), 0, 8);
        }

        // Combine with extension
        return $name.'.'.strtolower($extension);
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
     * and filenames are normalized
     */
    public function uploadSinglgeFile(UploadedFile $file)
    {
        // Make sure we have the latest team ID in case it changed during the request
        $teamId = Auth::check() ? (Auth::user()->currentTeam->id ?? 'default') : 'default';
        $teamHash = $this->getTeamHash($teamId);
        $this->diskPath = "media/{$teamHash}";

        // Check if the file is from blob or has a real filename
        if ($file->getClientOriginalName() === 'blob') {
            // Use default method for blob files (these are typically from image editor)
            return parent::uploadSinglgeFile($file);
        } else {
            // Normalize the filename before storing
            $normalizedFilename = $this->normalizeFilename($file->getClientOriginalName());

            // Store file with normalized name
            $path = $this->storage->putFileAs($this->diskPath, $file, $normalizedFilename, 'public');

            return $this->storage->url($path);
        }
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
            'path' => $this->diskPath,
        ];
    }

    /**
     * Test filename normalization
     */
    public function testNormalizeFilename($filename)
    {
        return $this->normalizeFilename($filename);
    }
}
