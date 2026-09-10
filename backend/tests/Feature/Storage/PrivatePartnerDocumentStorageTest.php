<?php

namespace Tests\Feature\Storage;

use App\Contracts\Storage\PartnerDocumentStorageInterface;
use App\Infrastructure\Storage\PrivatePartnerDocumentStorage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Tests\TestCase;

class PrivatePartnerDocumentStorageTest extends TestCase
{
    private PartnerDocumentStorageInterface $storage;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->storage = new PrivatePartnerDocumentStorage('local');
    }

    public function test_can_store_private_document_and_retrieve_metadata(): void
    {
        $file = UploadedFile::fake()->create('id_front.jpg', 500, 'image/jpeg');

        $result = $this->storage->storePrivate($file, 101, 'representative_id_front');

        $this->assertNotEmpty($result['file_path']);
        $this->assertEquals('id_front.jpg', $result['file_name']);
        $this->assertEquals('image/jpeg', $result['mime_type']);
        $this->assertGreaterThan(0, $result['file_size']);
        $this->assertNotEmpty($result['file_hash']);

        $this->assertTrue($this->storage->exists($result['file_path']));
    }

    public function test_rejects_unsupported_mime_types(): void
    {
        $file = UploadedFile::fake()->create('malicious.exe', 100, 'application/x-msdownload');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Định dạng tệp không được hỗ trợ');

        $this->storage->storePrivate($file, 101, 'license');
    }

    public function test_rejects_oversized_files(): void
    {
        // 11 MB file exceeds 10 MB limit
        $file = UploadedFile::fake()->create('huge.pdf', 11 * 1024, 'application/pdf');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('vượt quá giới hạn tối đa');

        $this->storage->storePrivate($file, 101, 'license');
    }

    public function test_generates_temporary_read_url(): void
    {
        $url = $this->storage->temporaryReadUrl('private/kyc/101/doc_123.pdf', 30);

        $this->assertStringContainsString('path=private%2Fkyc%2F101%2Fdoc_123.pdf', $url);
        $this->assertStringContainsString('signature=', $url);
    }

    public function test_can_delete_and_cleanup_failed_upload(): void
    {
        $file = UploadedFile::fake()->create('temp_doc.pdf', 200, 'application/pdf');
        $result = $this->storage->storePrivate($file, 102, 'power_of_attorney');

        $this->assertTrue($this->storage->exists($result['file_path']));

        // Cleanup
        $this->storage->cleanupFailedUpload($result['file_path']);
        $this->assertFalse($this->storage->exists($result['file_path']));

        // Cleanup non-existent file should be a silent no-op
        $this->storage->cleanupFailedUpload('non_existent.pdf');
        $this->assertTrue(true);
    }

    public function test_masks_sensitive_identity_and_bank_details(): void
    {
        $this->assertEquals('079*****6789', $this->storage->maskDocumentNumber('079123456789'));
        $this->assertEquals('001*****8901', $this->storage->maskDocumentNumber('001999998901'));
        $this->assertEquals('****', $this->storage->maskDocumentNumber('1234'));

        $this->assertEquals('******6789', $this->storage->maskBankAccount('1903456789'));
        $this->assertEquals('**********5678', $this->storage->maskBankAccount('00112233445678'));
        $this->assertEquals('****', $this->storage->maskBankAccount('1234'));
    }
}
