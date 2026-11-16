<?php

declare(strict_types=1);

/** PAP over RADIUS. Secrets and passwords never appear in command arguments. */
function radius_authenticate(array $config, string $username, string $password): bool
{
    if ($username === '' || strlen($username) > 192 || $password === '' || strlen($password) > 72 || str_contains($password, "\0")) {
        return false;
    }
    $process = proc_open([
        '/usr/bin/radclient', '-q', '-s', '-r', '1', '-t', '3',
        '-S', $config['secret_file'], $config['server'] . ':1812', 'auth',
    ], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    if (!is_resource($process)) {
        throw new RuntimeException('RADIUS client unavailable.');
    }
    // radclient string attributes use quoted octal escapes, not bare hex.
    $quote = static function (string $value): string {
        $encoded = '"';
        foreach (str_split($value) as $byte) {
            $encoded .= sprintf('\\%03o', ord($byte));
        }
        return $encoded . '"';
    };
    fwrite($pipes[0], 'User-Name = ' . $quote($username) . "\nUser-Password = " . $quote($password) . "\nMessage-Authenticator = 0x00\nService-Type = Login-User\n");
    fclose($pipes[0]);
    $result = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    stream_get_contents($pipes[2]);
    fclose($pipes[2]);
    $status = proc_close($process);
    if ($status === 0 && preg_match('/Accepted\s*:\s*1\b/', $result)) {
        return true;
    }
    if (preg_match('/Rejected\s*:\s*1\b/', $result)) {
        return false;
    }
    throw new FormError(t('The login service is unavailable. Please try again shortly.'));
}
