{{-- Shared file browser partial for entity files sub-pages.
     Expects: $grouped, $totalCount, $fileService, $entityLabel, $deleteAttachmentId --}}

@if($totalCount === 0)
    <x-signals.empty title="No Files" description="Upload files to keep documents, images, and other files organised for this {{ $entityLabel }}.">
        <x-slot:icon>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="size-10 opacity-30"><path d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z"/></svg>
        </x-slot:icon>
    </x-signals.empty>
@else
    {{-- Grouped file list --}}
    <div class="space-y-5">
        @foreach($grouped as $categoryName => $files)
            <div>
                <div class="mb-1.5 flex items-center gap-2 px-1">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="size-3.5 text-[var(--text-muted)]"><path d="M2.25 12.75V12A2.25 2.25 0 0 1 4.5 9.75h15A2.25 2.25 0 0 1 21.75 12v.75m-8.69-6.44-2.12-2.12a1.5 1.5 0 0 0-1.061-.44H4.5A2.25 2.25 0 0 0 2.25 6v12a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18V9a2.25 2.25 0 0 0-2.25-2.25h-5.379a1.5 1.5 0 0 1-1.06-.44Z"/></svg>
                    <span class="text-xs font-semibold uppercase tracking-wide text-[var(--text-muted)]" style="font-family: var(--font-display);">{{ $categoryName }}</span>
                    <span class="s-badge s-badge-muted">{{ $files->count() }}</span>
                </div>
                <div class="s-table-wrap">
                    <table class="s-table s-table-compact w-full">
                        <thead>
                            <tr>
                                <th class="text-left">Name</th>
                                <th class="text-left" style="width: 80px;">Type</th>
                                <th class="text-right" style="width: 80px;">Size</th>
                                <th class="text-left" style="width: 120px;">Uploaded</th>
                                <th style="width: 60px;"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($files as $attachment)
                                <tr wire:key="file-{{ $attachment->id }}">
                                    <td>
                                        @php
                                            try {
                                                $fileUrl = $fileService->signedUrl($attachment->file_path);
                                            } catch (\Throwable) {
                                                $fileUrl = '#';
                                            }
                                        @endphp
                                        <a href="{{ $fileUrl }}" target="_blank" class="s-cell-link">
                                            {{ $attachment->original_name }}
                                        </a>
                                    </td>
                                    <td class="s-cell-mono">{{ strtoupper(pathinfo($attachment->original_name, PATHINFO_EXTENSION)) }}</td>
                                    <td class="s-cell-amount">{{ number_format($attachment->file_size / 1024, 0) }} KB</td>
                                    <td class="text-[var(--text-muted)]">@localdate($attachment->created_at)</td>
                                    <td class="text-right">
                                        <button wire:click="confirmDelete({{ $attachment->id }})" class="s-btn s-btn-xs s-btn-ghost text-[var(--text-danger)]">
                                            Delete
                                        </button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endforeach
    </div>
@endif

{{-- Delete Confirmation Modal --}}
@if($deleteAttachmentId)
<div class="s-modal-backdrop" x-data x-transition.opacity>
    <div class="s-modal s-modal-sm">
        <div class="s-modal-header">
            <span class="s-modal-title">Delete File</span>
        </div>
        <div class="s-modal-body">
            <p>Are you sure you want to delete this file? This action cannot be undone.</p>
        </div>
        <div class="s-modal-footer">
            <button wire:click="cancelDelete" class="s-btn s-btn-sm">Cancel</button>
            <button wire:click="deleteAttachment" class="s-btn s-btn-sm s-btn-danger">Delete</button>
        </div>
    </div>
</div>
@endif
