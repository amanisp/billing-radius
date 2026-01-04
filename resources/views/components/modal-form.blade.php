<div class="modal fade" id="{{ $id }}" tabindex="-1" aria-labelledby="{{ $id }}Label"
    aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h1 class="modal-title fs-5" id="{{ $id }}Label">{{ $title }}</h1>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="{{ $action }}" method="POST">
                @csrf
                @if (isset($method) && $method !== 'POST')
                    @method($method)
                @endif
                <div class="modal-body">
                    {{ $slot }}
                </div>
                <div class="modal-footer">
                    <div class="btn-group gap-1">
                        <button type="button" class="btn btn-outline-danger " data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-outline-primary ">Save</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
