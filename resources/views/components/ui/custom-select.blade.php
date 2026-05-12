@props([
    'id',
    'name',
    'options',
    'placeholder' => 'Choose an option',
])

<div class="custom-select-container">
    <div class="custom-select" id="{{ $id }}Display" onclick="toggleDropdown('{{ $id }}')">
        <span id="{{ $id }}SelectedText">{{ $placeholder }}</span>
    </div>
    <div class="custom-options" id="{{ $id }}Options">
        <div class="custom-option disabled">{{ $placeholder }}</div>
        @foreach ($options as $option)
            <div class="custom-option" data-value="{{ $option['value'] }}">{{ $option['label'] }}</div>
        @endforeach
    </div>
</div>

<select class="form-select hidden" id="{{ $id }}" name="{{ $name }}">
    <option value="" selected disabled>{{ $placeholder }}</option>
    @foreach ($options as $option)
        <option value="{{ $option['value'] }}">{{ $option['label'] }}</option>
    @endforeach
</select>

@once
    @push('scripts')
        <script>
            function toggleDropdown(id) {
                const display = document.getElementById(id + "Display");
                const options = document.getElementById(id + "Options");
                display.classList.toggle("active");
                options.classList.toggle("show");
            }

            function selectOption(id, value, text, onSelect) {
                document.getElementById(id + "SelectedText").textContent = text;
                document.getElementById(id).value = value;
                document.getElementById(id + "Display").classList.remove("active");
                document.getElementById(id + "Options").classList.remove("show");
                if (typeof onSelect === 'function') {
                    onSelect(value, text);
                }
            }

            document.addEventListener("click", function(event) {
                document.querySelectorAll(".custom-select-container").forEach(container => {
                    if (!container.contains(event.target)) {
                        const display = container.querySelector(".custom-select");
                        const options = container.querySelector(".custom-options");
                        if (display) display.classList.remove("active");
                        if (options) options.classList.remove("show");
                    }
                });
            });
        </script>
    @endpush
@endonce
