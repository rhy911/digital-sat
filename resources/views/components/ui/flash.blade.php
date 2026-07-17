@if (session('success'))
    <div class="toast toast-success">
        {{ session('success') }}
    </div>
@endif

@if ($errors->any())
    <div class="toast toast-error">
        <ul class="toast-list">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif
