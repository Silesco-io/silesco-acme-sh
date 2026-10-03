<?php
declare(strict_types=1);
namespace Silesco\AcmeSh\Local;

/** Shell-free argv launch of a fixed reviewed shell script; own process group and bounded discarded output. */
final class NativeProcessRunner implements ProcessRunner
{
    /** No inherited environment, stdout buffer, logging or exception containing credentials. */
    public function run(array $arguments, #[\SensitiveParameter] array $environment, string $workingDirectory, int $timeoutSeconds): int
    {
        if ($arguments === [] || $arguments[0] !== '/bin/sh' || !is_executable('/usr/bin/setsid')
            || !is_executable('/usr/bin/timeout') || $timeoutSeconds < 1 || $timeoutSeconds > 3600) {
            throw new \RuntimeException('acme.executor_unavailable');
        }
        $previous = umask(0077);
        try {
            // The independent timeout survives loss of the PHP worker, bounding an orphaned issuance.
            $process = @proc_open(['/usr/bin/setsid', '--wait', '/usr/bin/timeout', '--signal=TERM', '--kill-after=1s',
                $timeoutSeconds . 's', ...$arguments],
                [0 => ['file', '/dev/null', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
                $pipes, $workingDirectory, $environment, ['bypass_shell' => true]);
        } catch (\Throwable) {
            throw new \RuntimeException('acme.executor_unavailable');
        } finally { umask($previous); }
        if (!is_resource($process)) throw new \RuntimeException('acme.executor_unavailable');
        stream_set_blocking($pipes[1], false); stream_set_blocking($pipes[2], false);
        $started = hrtime(true); $bytes = 0; $reason = null; $exit = -1;
        $status = proc_get_status($process); $pid = $status['pid'];
        try {
            while (true) {
                foreach ([1, 2] as $descriptor) {
                    $discard = fread($pipes[$descriptor], 8192);
                    if ($discard !== false) $bytes += strlen($discard);
                    unset($discard);
                }
                $status = proc_get_status($process);
                if (!$status['running']) { $exit = $status['exitcode']; break; }
                if ($bytes > 1048576 || (hrtime(true) - $started) / 1e9 >= $timeoutSeconds) {
                    $reason = $bytes > 1048576 ? 'acme.executor_unavailable' : 'acme.timeout';
                    break;
                }
                usleep(10000);
            }
        } finally {
            // Descendants retaining stdout are killed as well; normal exit may leave such descendants.
            if ($reason !== null || $status['running']) @posix_kill(-$pid, 15);
            for ($i = 0; $i < 25 && proc_get_status($process)['running']; $i++) usleep(10000);
            @posix_kill(-$pid, 9);
            if (proc_get_status($process)['running']) proc_terminate($process, 9);
            foreach ($pipes as $pipe) fclose($pipe);
            $closed = proc_close($process);
            if ($exit < 0 && $closed >= 0) $exit = $closed;
        }
        if ($reason !== null) throw new \RuntimeException($reason);
        if (in_array($exit, [124, 137], true)) throw new \RuntimeException('acme.timeout');
        return $exit;
    }
}
