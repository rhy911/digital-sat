@if (session('success'))
    <div class="toast toast-success" 
         x-data="{ show: true }" 
         x-show="show" 
         x-init="setTimeout(() => show = false, 5000)"
         x-transition:enter="transition-all ease-out duration-500"
         x-transition:enter-start="opacity-0 transform -translate-y-2 scale-95"
         x-transition:enter-end="opacity-100 transform translate-y-0 scale-100"
         x-transition:leave="transition-all ease-in duration-300"
         x-transition:leave-start="opacity-100 transform translate-y-0 scale-100"
         x-transition:leave-end="opacity-0 transform -translate-y-2 scale-95">
        {{ session('success') }}
    </div>
@endif

@if ($errors->any())
    <div class="toast toast-error" 
         x-data="{ show: true }" 
         x-show="show" 
         x-init="setTimeout(() => show = false, 7000)"
         x-transition:enter="transition-all ease-out duration-500"
         x-transition:enter-start="opacity-0 transform -translate-y-2 scale-95"
         x-transition:enter-end="opacity-100 transform translate-y-0 scale-100"
         x-transition:leave="transition-all ease-in duration-300"
         x-transition:leave-start="opacity-100 transform translate-y-0 scale-100"
         x-transition:leave-end="opacity-0 transform -translate-y-2 scale-95">
        <ul class="toast-list">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif
