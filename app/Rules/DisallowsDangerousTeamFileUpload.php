<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;

class DisallowsDangerousTeamFileUpload implements ValidationRule
{
    /**
     * @return list<string>
     */
    private static function blockedExtensions(): array
    {
        return [
            '386', 'acm', 'ade', 'adp', 'apk', 'app', 'application', 'appx', 'appxbundle',
            'asp', 'aspx', 'ashx', 'asmx', 'ax', 'bas', 'bat', 'bin', 'btm', 'cab', 'cda',
            'cer', 'chm', 'class', 'cmd', 'com', 'command', 'cpl', 'crt', 'csh', 'deb',
            'der', 'dll', 'dmg', 'drv', 'exe', 'fxp', 'gadget', 'grp', 'hlp', 'hpj', 'hta',
            'htaccess', 'htpasswd', 'inf',             'ins', 'inx', 'ipa', 'isp', 'isu', 'its', 'jar',
            'job', 'jse', 'jsp', 'jspx', 'ksh', 'library', 'lnk', 'mad', 'maf',
            'mag', 'mam', 'maq', 'mar', 'mas', 'mat', 'mau', 'mav', 'maw', 'mcf', 'mda',
            'mdb', 'mde', 'mdt', 'mdw', 'mdz', 'msc', 'msh', 'msh1', 'msh2', 'mshxml',
            'msh1xml', 'msh2xml', 'msi', 'msp', 'msix', 'msixbundle', 'mst', 'msu', 'ocx',
            'ops', 'osd', 'osx', 'osxapp', 'paf', 'pcd', 'php', 'php3', 'php4', 'php5',
            'php7', 'php8', 'phar', 'phtml', 'phps', 'pht', 'pkg', 'pl', 'pm', 'prf',
            'prg', 'ps1', 'ps1xml', 'ps2', 'ps2xml', 'psc1', 'psc2', 'pst', 'py', 'pyc',
            'pyd', 'pyo', 'pyw', 'pyz', 'rb', 'reg', 'rpm',             'scf', 'scr', 'sct', 'sh',
            'shb', 'shs', 'sys', 'vb', 'vbe', 'vbs', 'vbscript', 'vxd', 'war', 'website',
            'workflow', 'ws', 'wsc', 'wsf', 'wsh', 'xll', 'xnk',
        ];
    }

    /**
     * @return list<string>
     */
    private static function blockedMimePrefixes(): array
    {
        return [
            'application/x-dosexec',
            'application/x-php',
            'application/x-httpd-php',
            'text/x-php',
            'text/x-shellscript',
            'application/x-sh',
            'application/x-csh',
            'application/x-perl',
            'application/x-python',
            'application/x-ruby',
        ];
    }

    /**
     * @return list<string>
     */
    private static function blockedMimeExact(): array
    {
        return [
            'application/x-msdownload',
            'application/x-msdos-program',
            'application/vnd.microsoft.portable-executable',
            'application/x-executable',
            'application/x-sharedlib',
            'application/x-php',
            'text/x-php',
            'application/x-httpd-php',
            'application/x-httpd-php-source',
            'text/x-shellscript',
            'application/x-sh',
            'application/x-csh',
            'application/x-perl',
            'application/x-python-code',
            'application/x-ruby',
            'application/java-archive',
            'application/x-java-archive',
            'application/x-ms-installer',
            'application/x-msi',
            'application/x-ms-shortcut',
        ];
    }

    /**
     * @return list<string>
     */
    private static function blockedBasenames(): array
    {
        return [
            '.htaccess',
            '.htpasswd',
            '.user.ini',
            'web.config',
            'web.config.bak',
        ];
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value instanceof UploadedFile || ! $value->isValid())
        {
            return;
        }

        $originalName = $value->getClientOriginalName();
        if (str_contains($originalName, '..') || str_contains($originalName, "\0"))
        {
            $fail(__('validation.team_file_unsafe'));

            return;
        }

        $basename = strtolower(basename($originalName));
        foreach (self::blockedBasenames() as $blockedName)
        {
            if ($basename === $blockedName)
            {
                $fail(__('validation.team_file_unsafe'));

                return;
            }
        }

        $ext = strtolower($value->getClientOriginalExtension());
        if ($ext !== '' && in_array($ext, self::blockedExtensions(), true))
        {
            $fail(__('validation.team_file_unsafe'));

            return;
        }

        if ($ext === '' && str_starts_with($basename, '.ht'))
        {
            $fail(__('validation.team_file_unsafe'));

            return;
        }

        $mime = strtolower((string) $value->getMimeType());
        if ($mime === '')
        {
            return;
        }

        foreach (self::blockedMimeExact() as $blocked)
        {
            if ($mime === $blocked)
            {
                $fail(__('validation.team_file_unsafe'));

                return;
            }
        }

        foreach (self::blockedMimePrefixes() as $prefix)
        {
            if (str_starts_with($mime, $prefix))
            {
                $fail(__('validation.team_file_unsafe'));

                return;
            }
        }
    }
}
