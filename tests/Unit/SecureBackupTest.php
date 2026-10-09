<?php

namespace Tests\Unit;

use Nccia\Security\SecureBackup;
use PHPUnit\Framework\TestCase;

require_once __DIR__.'/../../scripts/security/lib/SecureBackup.php';

class SecureBackupTest extends TestCase
{
    private string $root;
    private string $key;

    protected function setUp(): void
    {
        if (!extension_loaded('sodium')) {
            $this->markTestSkipped('Enable sodium for encrypted backup verification.');
        }
        $this->root = sys_get_temp_dir().DIRECTORY_SEPARATOR.'nccia-backup-test-'.bin2hex(random_bytes(12));
        mkdir($this->root, 0700);
        mkdir($this->root.'/source', 0700);
        mkdir($this->root.'/source/nested', 0700);
        file_put_contents($this->root.'/source/nested/private.txt', 'synthetic-sensitive-record');
        file_put_contents($this->root.'/source/empty.txt', '');
        $this->key = random_bytes(32);
    }

    protected function tearDown(): void
    {
        if (!isset($this->root) || !is_dir($this->root)) {
            return;
        }
        $root = realpath($this->root);
        $this->assertStringStartsWith('nccia-backup-test-', basename($root));
        $items = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($items as $item) {
            $this->assertFalse($item->isLink());
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($root);
    }

    public function test_encrypted_snapshot_roundtrip_preserves_files_and_hides_metadata(): void
    {
        $archive = $this->root.'/backup.ncbk';
        $created = SecureBackup::create($this->root.'/source', $archive, $this->key);
        $this->assertSame(2, $created['files']);
        $this->assertSame($created, SecureBackup::verify($archive, $this->key));
        $encrypted = file_get_contents($archive);
        $this->assertStringNotContainsString('synthetic-sensitive-record', $encrypted);
        $this->assertStringNotContainsString('private.txt', $encrypted);
        $this->assertSame($created, SecureBackup::restore($archive, $this->root.'/restored', $this->key));
        $this->assertSame('synthetic-sensitive-record', file_get_contents($this->root.'/restored/nested/private.txt'));
        $this->assertSame('', file_get_contents($this->root.'/restored/empty.txt'));
    }

    public function test_wrong_key_tampering_truncation_and_trailing_data_are_rejected(): void
    {
        $archive = $this->root.'/backup.ncbk';
        SecureBackup::create($this->root.'/source', $archive, $this->key);
        $original = file_get_contents($archive);
        $corrupted = $original;
        $corrupted[60] = chr(ord($corrupted[60]) ^ 1);
        foreach ([$original, $corrupted, substr($original, 0, -1), $original.'extra'] as $index => $data) {
            $file = $this->root.'/invalid-'.$index.'.ncbk';
            file_put_contents($file, $data);
            try {
                SecureBackup::restore($file, $this->root.'/rejected-'.$index, $index === 0 ? random_bytes(32) : $this->key);
                $this->fail('Corrupted or unauthenticated archive was accepted.');
            } catch (\RuntimeException) {
                $this->assertDirectoryDoesNotExist($this->root.'/rejected-'.$index);
                $this->assertSame([], glob($this->root.'/.nccia-restore-*'));
            }
        }
    }

    public function test_authenticated_path_traversal_is_rejected_before_writing(): void
    {
        [$state, $header] = sodium_crypto_secretstream_xchacha20poly1305_init_push($this->key);
        $message = 'F'.json_encode(['path' => '../outside.txt', 'size' => 0]);
        $record = sodium_crypto_secretstream_xchacha20poly1305_push($state, $message);
        $archive = $this->root.'/traversal.ncbk';
        file_put_contents($archive, "NCBK1\n".$header.pack('N', strlen($record)).$record);
        try {
            SecureBackup::restore($archive, $this->root.'/rejected', $this->key);
            $this->fail('Traversal archive was accepted.');
        } catch (\RuntimeException) {
            $this->assertFileDoesNotExist($this->root.'/outside.txt');
            $this->assertDirectoryDoesNotExist($this->root.'/rejected');
        }
    }

    public function test_existing_target_and_excessive_data_limits_are_refused(): void
    {
        $archive = $this->root.'/backup.ncbk';
        SecureBackup::create($this->root.'/source', $archive, $this->key);
        try {
            SecureBackup::restore($archive, $this->root.'/source', $this->key);
            $this->fail('Existing directory must never be overwritten.');
        } catch (\RuntimeException) {
            $this->assertSame('synthetic-sensitive-record', file_get_contents($this->root.'/source/nested/private.txt'));
        }
        $this->expectException(\RuntimeException::class);
        SecureBackup::verify($archive, $this->key, 1);
    }
}
