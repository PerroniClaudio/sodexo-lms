<x-layouts.admin>
    <div class="mx-auto flex w-full max-w-2xl flex-col gap-6 p-4 sm:p-6 lg:p-8">
        <x-page-header :title="__('Carica nuovo video')" />
        <form method="POST" action="{{ route('admin.videos.store') }}" enctype="multipart/form-data" class="space-y-6">
            @csrf
            <div class="form-control">
                <label class="label">
                    <span class="label-text">Titolo</span>
                </label>
                <input type="text" name="title" class="input input-bordered w-full" required>
            </div>
            <div class="form-control">
                <label class="label">
                    <span class="label-text">Descrizione</span>
                </label>
                <textarea name="description" class="textarea textarea-bordered w-full"></textarea>
            </div>
            <div class="form-control">
                <label class="label">
                    <span class="label-text">File video</span>
                </label>
                <input type="file" name="video_file" class="file-input file-input-bordered w-full" accept="video/*" required>
            </div>
            <button type="submit" class="btn btn-primary">Carica</button>
        </form>
    </div>
</x-layouts.admin>
