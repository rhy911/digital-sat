<x-ui.modal id="modal-ask-question" title="Ask a Question" maxWidth="sm">
    <div class="form-note">
        <form method="POST" action="{{ route('forum.store') }}">
            @csrf
            <div class="field">
                <label>Title</label>
                <input type="text" name="title" maxlength="150" required autocomplete="off" placeholder="What's your question about?" value="{{ old('title') }}">
            </div>
            <div class="field">
                <label>Category</label>
                <select name="category" required>
                    @foreach ($categories as $category)
                        <option value="{{ $category }}" @selected(old('category') === $category)>{{ $category }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label>Details</label>
                <textarea name="body" rows="5" maxlength="5000" required placeholder="Give a bit more detail so others can help.">{{ old('body') }}</textarea>
            </div>
            <button type="submit" class="btn-sm-primary btn-full">Post Question</button>
        </form>
    </div>
</x-ui.modal>
