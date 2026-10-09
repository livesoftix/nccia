<?php

namespace Nccia\Security;

/** File snapshots encrypted by libsodium secretstream; never connects to a database. */
final class SecureBackup
{
    private const MAGIC = "NCBK1\n";
    private const CHUNK = 1024 * 1024;
    private const MAX_FILES = 100000;

    public static function keyFromEnvironment(): string
    {
        $key = base64_decode((string) getenv('NCCIA_BACKUP_KEY'), true);
        if (!is_string($key) || strlen($key) !== 32) {
            throw new \RuntimeException('A separate base64-encoded 32-byte NCCIA_BACKUP_KEY is required.');
        }
        return $key;
    }

    public static function create(string $source, string $archive, string $key): array
    {
        self::requireSodium($key);
        $root = realpath($source);
        if ($root === false || !is_dir($root) || is_link($source) || dirname($root) === $root) {
            throw new \RuntimeException('Source must be an explicit non-root directory without a symlink.');
        }
        $parent = realpath(dirname($archive));
        if ($parent === false || self::within($parent, $root)) {
            throw new \RuntimeException('Archive parent must exist outside the source directory.');
        }
        $archive = $parent.DIRECTORY_SEPARATOR.basename($archive);
        $output = fopen($archive, 'x+b');
        if ($output === false) {
            throw new \RuntimeException('Archive must be a new file.');
        }
        chmod($archive, 0600);
        try {
            [$state, $header] = sodium_crypto_secretstream_xchacha20poly1305_init_push($key);
            self::writeAll($output, self::MAGIC.$header);
            $files = 0;
            $bytes = 0;
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));
            foreach ($iterator as $entry) {
                $resolved = $entry->getRealPath();
                if ($entry->isLink() || $resolved === false || !self::within($resolved, $root)) {
                    throw new \RuntimeException('Source links or paths outside the source directory are refused.');
                }
                if (!$entry->isFile()) {
                    if ($entry->isDir()) {
                        continue; // Empty directories have no data to snapshot.
                    }
                    throw new \RuntimeException('Only regular source files are supported.');
                }
                $relative = str_replace(DIRECTORY_SEPARATOR, '/', substr($entry->getPathname(), strlen($root) + 1));
                self::safePath($relative);
                if (++$files > self::MAX_FILES) {
                    throw new \RuntimeException('Snapshot file count limit exceeded.');
                }
                $input = fopen($resolved, 'rb');
                if ($input === false || !flock($input, LOCK_SH)) {
                    throw new \RuntimeException('Source file is unavailable.');
                }
                try {
                    $before = fstat($input);
                    self::push($output, $state, 'F'.self::json(['path' => $relative, 'size' => $before['size']]));
                    $hash = hash_init('sha256');
                    $size = 0;
                    while (!feof($input)) {
                        $chunk = fread($input, self::CHUNK);
                        if ($chunk === false) {
                            throw new \RuntimeException('Source file read failed.');
                        }
                        if ($chunk === '') {
                            continue;
                        }
                        hash_update($hash, $chunk);
                        $size += strlen($chunk);
                        self::push($output, $state, 'D'.$chunk);
                    }
                    $after = fstat($input);
                    if ($size !== $before['size'] || $before['size'] !== $after['size'] || $before['mtime'] !== $after['mtime']) {
                        throw new \RuntimeException('Source changed during backup; use an offline consistent snapshot.');
                    }
                    self::push($output, $state, 'E'.self::json(['size' => $size, 'sha256' => hash_final($hash)]));
                    $bytes += $size;
                } finally {
                    fclose($input);
                }
            }
            self::push($output, $state, 'Z'.self::json(['files' => $files, 'bytes' => $bytes]), SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL);
            fflush($output);
            return ['files' => $files, 'bytes' => $bytes, 'authenticated' => true];
        } catch (\Throwable $error) {
            fclose($output);
            unlink($archive); // Exactly the exclusively created output, never a supplied existing file.
            throw $error;
        } finally {
            if (is_resource($output)) {
                fclose($output);
            }
        }
    }

    public static function verify(string $archive, string $key, int $maxBytes = 10737418240): array
    {
        return self::read($archive, $key, null, $maxBytes);
    }

    public static function restore(string $archive, string $target, string $key, int $maxBytes = 10737418240): array
    {
        $parent = realpath(dirname($target));
        $name = basename($target);
        self::safePath($name);
        if ($parent === false || !is_dir($parent) || file_exists($target) || is_link($target)) {
            throw new \RuntimeException('Restore requires an existing private parent and a new target directory.');
        }
        $target = $parent.DIRECTORY_SEPARATOR.$name;
        $stage = $parent.DIRECTORY_SEPARATOR.'.nccia-restore-'.bin2hex(random_bytes(16));
        if (!mkdir($stage, 0700)) {
            throw new \RuntimeException('Cannot create isolated restore staging directory.');
        }
        try {
            $result = self::read($archive, $key, $stage, $maxBytes);
            if (file_exists($target) || is_link($target) || !rename($stage, $target)) {
                throw new \RuntimeException('Restore target appeared or could not be published.');
            }
            return $result;
        } finally {
            if (is_dir($stage)) {
                self::removeOwnedStage($stage);
            }
        }
    }

    private static function read(string $archive, string $key, ?string $stage, int $maxBytes): array
    {
        self::requireSodium($key);
        if ($maxBytes < 1 || is_link($archive) || !is_file($archive)) {
            throw new \RuntimeException('Invalid archive or restore limit.');
        }
        $input = fopen($archive, 'rb');
        if ($input === false) {
            throw new \RuntimeException('Archive is unavailable.');
        }
        $output = null;
        try {
            if (self::readExact($input, strlen(self::MAGIC)) !== self::MAGIC) {
                throw new \RuntimeException('Unsupported backup format.');
            }
            $state = sodium_crypto_secretstream_xchacha20poly1305_init_pull(self::readExact($input, SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_HEADERBYTES), $key);
            $paths = [];
            $files = 0;
            $bytes = 0;
            $active = null;
            while (true) {
                $length = unpack('Nlength', self::readExact($input, 4))['length'];
                if ($length < SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_ABYTES + 1 || $length > self::CHUNK + 8192) {
                    throw new \RuntimeException('Invalid encrypted record length.');
                }
                $record = sodium_crypto_secretstream_xchacha20poly1305_pull($state, self::readExact($input, $length));
                if ($record === false) {
                    throw new \RuntimeException('Archive authentication failed.');
                }
                [$message, $tag] = $record;
                $type = $message[0];
                $data = substr($message, 1);
                if ($type !== 'Z' && $tag !== SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_MESSAGE) {
                    throw new \RuntimeException('Unexpected stream tag.');
                }
                if ($type === 'F' && $active === null) {
                    $meta = json_decode($data, true, 16, JSON_THROW_ON_ERROR);
                    $path = $meta['path'] ?? null;
                    if (!is_string($path) || !is_int($meta['size'] ?? null) || $meta['size'] < 0) {
                        throw new \RuntimeException('Invalid file metadata.');
                    }
                    self::safePath($path);
                    $identity = strtolower($path); // Prevent Windows/macOS case collisions everywhere.
                    if (isset($paths[$identity]) || ++$files > self::MAX_FILES || $bytes + $meta['size'] > $maxBytes) {
                        throw new \RuntimeException('Duplicate file or restore limits exceeded.');
                    }
                    $paths[$identity] = true;
                    $active = ['expected' => $meta['size'], 'size' => 0, 'hash' => hash_init('sha256')];
                    if ($stage !== null) {
                        $destination = $stage.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $path);
                        $directory = dirname($destination);
                        if (!is_dir($directory) && !mkdir($directory, 0700, true)) {
                            throw new \RuntimeException('Cannot create restore directory.');
                        }
                        $output = fopen($destination, 'x+b');
                        if ($output === false) {
                            throw new \RuntimeException('Cannot create restore file.');
                        }
                        chmod($destination, 0600);
                    }
                } elseif ($type === 'D' && $active !== null) {
                    $active['size'] += strlen($data);
                    if ($active['size'] > $active['expected']) {
                        throw new \RuntimeException('File data exceeds authenticated metadata.');
                    }
                    hash_update($active['hash'], $data);
                    if ($output !== null) {
                        self::writeAll($output, $data);
                    }
                } elseif ($type === 'E' && $active !== null) {
                    $meta = json_decode($data, true, 16, JSON_THROW_ON_ERROR);
                    if (($meta['size'] ?? null) !== $active['size'] || $active['expected'] !== $active['size']
                        || !is_string($meta['sha256'] ?? null) || !hash_equals(hash_final($active['hash']), $meta['sha256'])) {
                        throw new \RuntimeException('File integrity or size verification failed.');
                    }
                    $bytes += $active['size'];
                    $active = null;
                    if ($output !== null) {
                        fclose($output);
                        $output = null;
                    }
                } elseif ($type === 'Z' && $active === null && $tag === SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL) {
                    $meta = json_decode($data, true, 16, JSON_THROW_ON_ERROR);
                    if (($meta['files'] ?? null) !== $files || ($meta['bytes'] ?? null) !== $bytes || fread($input, 1) !== '') {
                        throw new \RuntimeException('Final manifest mismatch or trailing archive data.');
                    }
                    return ['files' => $files, 'bytes' => $bytes, 'authenticated' => true];
                } else {
                    throw new \RuntimeException('Invalid record order.');
                }
            }
        } finally {
            fclose($input);
            if (is_resource($output)) {
                fclose($output);
            }
        }
    }

    private static function requireSodium(string $key): void
    {
        if (!extension_loaded('sodium') || strlen($key) !== 32) {
            throw new \RuntimeException('The sodium extension and a separate 32-byte key are required.');
        }
    }

    private static function safePath(string $path): void
    {
        if ($path === '' || strlen($path) > 4096 || str_starts_with($path, '/') || preg_match('/[\\\\\x00-\x1f\x7f:<">|?*]/', $path)) {
            throw new \RuntimeException('Unsafe backup path.');
        }
        foreach (explode('/', $path) as $component) {
            if ($component === '' || $component === '.' || $component === '..' || preg_match('/[. ]$/', $component)
                || preg_match('/^(CON|PRN|AUX|NUL|COM[1-9]|LPT[1-9])(?:\.|$)/i', $component)) {
                throw new \RuntimeException('Unsafe backup path component.');
            }
        }
    }

    private static function within(string $path, string $root): bool
    {
        $path = str_replace('\\', '/', $path);
        $root = rtrim(str_replace('\\', '/', $root), '/');
        if (PHP_OS_FAMILY === 'Windows') {
            $path = strtolower($path);
            $root = strtolower($root);
        }
        return $path === $root || str_starts_with($path, $root.'/');
    }

    private static function push($output, string &$state, string $data, int $tag = 0): void
    {
        $encrypted = sodium_crypto_secretstream_xchacha20poly1305_push($state, $data, '', $tag);
        self::writeAll($output, pack('N', strlen($encrypted)).$encrypted);
    }

    private static function writeAll($output, string $data): void
    {
        $offset = 0;
        while ($offset < strlen($data)) {
            $written = fwrite($output, substr($data, $offset));
            if ($written === false || $written === 0) {
                throw new \RuntimeException('File write failed.');
            }
            $offset += $written;
        }
    }

    private static function readExact($input, int $bytes): string
    {
        $value = '';
        while (strlen($value) < $bytes) {
            $part = fread($input, $bytes - strlen($value));
            if ($part === false || $part === '') {
                throw new \RuntimeException('Archive truncated.');
            }
            $value .= $part;
        }
        return $value;
    }

    private static function json(array $data): string
    {
        return json_encode($data, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    private static function removeOwnedStage(string $stage): void
    {
        $root = realpath($stage);
        if ($root === false || !str_starts_with(basename($root), '.nccia-restore-')) {
            throw new \RuntimeException('Unexpected restore staging directory.');
        }
        $items = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($items as $item) {
            if ($item->isLink() || !self::within((string) $item->getRealPath(), $root)) {
                throw new \RuntimeException('Refusing cleanup outside the owned staging directory.');
            }
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($root);
    }
}
