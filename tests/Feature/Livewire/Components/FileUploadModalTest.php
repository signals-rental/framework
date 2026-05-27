<?php

use App\Livewire\Components\FileUploadModal;
use App\Models\Attachment;
use App\Models\ListName;
use App\Models\ListValue;
use App\Models\Member;
use App\Models\User;
use App\Services\FileService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

beforeEach(function () {
    $default = config('filesystems.default', 'local');
    Storage::fake($default === 'local' ? 'public' : $default);
    $this->actingAs(User::factory()->owner()->create());
    $this->member = Member::factory()->create();
});

/**
 * @return Testable<FileUploadModal>
 */
function fileUploadModal(): Testable
{
    return Livewire::test(FileUploadModal::class, [
        'modelType' => Member::class,
        'modelId' => test()->member->id,
    ]);
}

it('opens the modal on the open-file-upload event', function () {
    fileUploadModal()
        ->assertSet('show', false)
        ->dispatch('open-file-upload')
        ->assertSet('show', true);
});

it('closes and resets the modal state', function () {
    fileUploadModal()
        ->set('show', true)
        ->set('category', 'contracts')
        ->set('description', 'something')
        ->call('close')
        ->assertSet('show', false)
        ->assertSet('category', null)
        ->assertSet('description', '');
});

it('uploads a file and dispatches file-uploaded', function () {
    $file = UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf');

    fileUploadModal()
        ->set('attachment', $file)
        ->set('category', 'contracts')
        ->set('description', 'Main contract')
        ->set('show', true)
        ->call('save')
        ->assertHasNoErrors()
        ->assertDispatched('file-uploaded')
        ->assertSet('show', false);

    expect(Attachment::query()
        ->where('attachable_type', $this->member->getMorphClass())
        ->where('attachable_id', $this->member->id)
        ->where('category', 'contracts')
        ->exists()
    )->toBeTrue();
});

it('requires an attachment', function () {
    fileUploadModal()
        ->call('save')
        ->assertHasErrors(['attachment'])
        ->assertNotDispatched('file-uploaded');
});

it('shows an error and keeps the modal open when the model no longer exists', function () {
    $file = UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf');

    Livewire::test(FileUploadModal::class, ['modelType' => Member::class, 'modelId' => 999999])
        ->set('attachment', $file)
        ->call('save')
        ->assertHasErrors(['attachment'])
        ->assertNotDispatched('file-uploaded');
});

it('shows an error when the upload service fails', function () {
    $mock = Mockery::mock(FileService::class);
    $mock->shouldReceive('upload')->andThrow(new RuntimeException('Storage unavailable'));
    app()->instance(FileService::class, $mock);

    $file = UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf');

    fileUploadModal()
        ->set('attachment', $file)
        ->call('save')
        ->assertHasErrors(['attachment'])
        ->assertNotDispatched('file-uploaded');
});

it('renders file categories from the File Category list', function () {
    $list = ListName::factory()->create(['name' => 'File Category']);
    ListValue::factory()->forList($list)->create([
        'name' => 'Contracts',
        'is_active' => true,
        'sort_order' => 1,
    ]);

    fileUploadModal()
        ->assertOk()
        ->assertViewHas('categories', ['Contracts']);
});
