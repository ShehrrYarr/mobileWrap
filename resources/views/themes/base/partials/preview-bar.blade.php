@if (theme()->isPreviewing())
    <div class="sf-preview-bar">
        <span><i class="ri-eye-line"></i> Previewing <strong>{{ theme()->option('name') }}</strong> — only you can see this.</span>
        <a href="{{ route('admin.storefront.themes') }}">Back to themes</a>
        <a href="{{ url()->current() }}?exit_preview=1">Exit preview</a>
    </div>
@endif
