<?php 

use Illuminate\Support\Facades\Crypt;

if (!function_exists('encodeFilename')) {
    /**
     * Encode a filename using the ID and file extension.
     *
     * @param  int    $id
     * @param  string $extension
     * @return string
     */
    function encodeFilename($id, $extension)
    {
        $encryptedId = Crypt::encryptString($id);

        return $encryptedId . '.' . $extension;
    }
}

if (!function_exists('decodeFilename')) {
    /**
     * Decode a filename to get the original ID.
     *
     * @param  string $encodedFilename
     * @return string
     */
    function decodeFilename($encodedFilename)
    {
        $filenameParts = explode('.', $encodedFilename);
        if (count($filenameParts) < 2)
        {
            throw new \Exception('Invalid file name format.');
        }

        $encryptedId = $filenameParts[0];

        $decryptedId = Crypt::decryptString($encryptedId);

        return $decryptedId . '.' . end($filenameParts);
    }
}
