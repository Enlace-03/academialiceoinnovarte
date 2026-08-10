<div>
    <h1 class="text-xl font-semibold">Galería</h1>

    <div class="mt-6 space-y-6">
        @forelse ($posts as $post)
            <div class="bg-white rounded-lg shadow p-4">
                <div class="flex items-center justify-between">
                    <h2 class="font-medium">{{ $post->title }}</h2>
                    <span class="text-xs text-gray-400">{{ $post->published_at->format('d/m/Y') }}</span>
                </div>

                @if ($post->project)
                    <span class="text-xs text-emerald-700">{{ $post->project->title }}</span>
                @else
                    <span class="text-xs text-gray-400">General</span>
                @endif

                @if ($post->caption)
                    <p class="text-sm text-gray-700 mt-2">{{ $post->caption }}</p>
                @endif

                @if ($post->photos->isNotEmpty())
                    <div class="mt-3 grid grid-cols-2 sm:grid-cols-3 gap-2">
                        @foreach ($post->photos as $photo)
                            <a href="{{ route('gallery.photos.show', $photo->uuid) }}" target="_blank" class="block aspect-square overflow-hidden rounded bg-gray-100">
                                <img src="{{ route('gallery.photos.show', $photo->uuid) }}" alt="{{ $post->title }}" class="w-full h-full object-cover" loading="lazy">
                            </a>
                        @endforeach
                    </div>
                @endif
            </div>
        @empty
            <p class="text-sm text-gray-500">Todavía no hay publicaciones en la galería.</p>
        @endforelse
    </div>
</div>
