<?php

namespace App\Console\Commands;

use App\Services\AuditCheckpointService;
use Illuminate\Console\Command;

class AuditCheckpoint extends Command
{
    protected $signature = 'security:audit-checkpoint {--output= : New protected checkpoint file for separate retention} {--verify= : Previously retained checkpoint file to verify}';
    protected $description = 'Export or verify a signed audit head checkpoint without disclosing record data';

    public function handle(AuditCheckpointService $service): int
    {
        try {
            $output = $this->option('output');
            $verify = $this->option('verify');
            if (($output && $verify) || (!$output && !$verify)) {
                $this->error('Choose exactly one of --output or --verify.');
                return self::INVALID;
            }
            $path = (string) ($output ?: $verify);
            $parent = realpath(dirname($path));
            $public = realpath(public_path());
            if (!$parent || is_link($path) || ($public && ($parent === $public
                || str_starts_with(strtolower($parent), strtolower($public).DIRECTORY_SEPARATOR)))) {
                throw new \RuntimeException('Checkpoint requires a protected non-public directory.');
            }
            if ($verify) {
                if (!is_file($path) || filesize($path) > 4096) {
                    throw new \RuntimeException('Invalid checkpoint file.');
                }
                $checkpoint = json_decode(file_get_contents($path), true, 16, JSON_THROW_ON_ERROR);
                if (!is_array($checkpoint) || !$service->verify($checkpoint)) {
                    throw new \RuntimeException('Checkpoint verification failed.');
                }
                $this->info('Retained checkpoint matches the verified audit chain.');
                return self::SUCCESS;
            }
            $checkpoint = $service->create();
            $oldMask = umask(0077);
            try {
                $file = @fopen($path, 'x');
            } finally {
                umask($oldMask);
            }
            if (!$file) {
                throw new \RuntimeException('Checkpoint destination must be a new writable file.');
            }
            try {
                $json = json_encode($checkpoint, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)."\n";
                if (fwrite($file, $json) !== strlen($json) || !fflush($file)) {
                    throw new \RuntimeException('Checkpoint write failed.');
                }
            } finally {
                fclose($file);
            }
            $this->info('Checkpoint exported. Retain it in access-separated, immutable storage; local export alone is not external protection.');
            return self::SUCCESS;
        } catch (\Throwable) {
            $this->error('Audit checkpoint operation failed. Check integrity, key custody and the protected file path.');
            return self::FAILURE;
        }
    }
}
