<?php

/**
 * AI Safety Guard
 * This script validates commands before execution to prevent unauthorized actions
 */

class AISafetyGuard
{
    private static $restrictionsFile = '.ai-command-restrictions';
    private static $blockedCommands = [];
    private static $initialized = false;

    /**
     * Initialize the safety guard by loading restricted commands
     */
    public static function init()
    {
        if (self::$initialized) {
            return;
        }

        $restrictionsPath = __DIR__ . '/' . self::$restrictionsFile;

        if (file_exists($restrictionsPath)) {
            $content = file_get_contents($restrictionsPath);
            $lines = explode("\n", $content);

            foreach ($lines as $line) {
                $line = trim($line);
                // Skip comments and empty lines
                if (empty($line) || strpos($line, '#') === 0) {
                    continue;
                }

                self::$blockedCommands[] = strtolower($line);
            }
        }

        self::$initialized = true;
    }

    /**
     * Check if a command is allowed to be executed
     */
    public static function isCommandAllowed($command)
    {
        self::init();

        $normalizedCommand = strtolower(trim($command));

        foreach (self::$blockedCommands as $blockedCommand) {
            // Check if the command starts with a blocked command
            if (strpos($normalizedCommand, $blockedCommand) === 0) {
                return false;
            }

            // Check if the command contains a blocked pattern
            if (strpos($normalizedCommand, $blockedCommand) !== false) {
                // Additional check for git commands
                if (strpos($blockedCommand, 'git') === 0 && strpos($normalizedCommand, 'git') !== false) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Get the reason why a command was blocked
     */
    public static function getBlockReason($command)
    {
        self::init();

        $normalizedCommand = strtolower(trim($command));

        foreach (self::$blockedCommands as $blockedCommand) {
            if (strpos($normalizedCommand, $blockedCommand) !== false) {
                return "Command blocked: '{$blockedCommand}' is in the AI restrictions list";
            }
        }

        return "Command blocked for security reasons";
    }

    /**
     * Validate and approve a command for execution
     */
    public static function validateCommand($command)
    {
        if (!self::isCommandAllowed($command)) {
            throw new Exception(self::getBlockReason($command));
        }

        return true;
    }

    /**
     * Log blocked command attempts
     */
    public static function logBlockedAttempt($command, $context = [])
    {
        $logEntry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'command' => $command,
            'reason' => self::getBlockReason($command),
            'context' => $context
        ];

        $logFile = __DIR__ . '/ai-blocked-commands.log';
        file_put_contents($logFile, json_encode($logEntry) . "\n", FILE_APPEND | LOCK_EX);
    }
}

// Example usage:
/*
try {
    AISafetyGuard::validateCommand($userCommand);
    // Execute command if validation passes
    exec($userCommand);
} catch (Exception $e) {
    AISafetyGuard::logBlockedAttempt($userCommand, ['user' => 'AI Assistant']);
    echo "❌ " . $e->getMessage();
}
*/
