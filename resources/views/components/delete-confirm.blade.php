@props(['action', 'label', 'confirm', 'question'])

{{-- A permanent deletion behind a disclosure: the first button only explains, the second one deletes --}}
<details data-menu {{ $attributes }}>
    <summary class="btn btn-danger-quiet"><x-icon name="trash" class="size-4" /> {{ $label }}</summary>
    <form method="POST" action="{{ $action }}" class="confirm-panel">
        @csrf
        @method('DELETE')
        <p class="text-sm">{{ $question }}</p>
        <div class="mt-3 flex flex-wrap gap-2">
            <button type="submit" class="btn btn-danger">{{ $confirm }}</button>
            <button type="button" class="btn btn-quiet" data-dismiss>Keep it</button>
        </div>
    </form>
</details>
