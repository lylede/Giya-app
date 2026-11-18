{{-- Under an admin table: "Showing 1 to 10 of 171", the pages, and Rows per
     page. The rows-per-page form keeps the current search and filters and
     goes back to page 1, so a shorter page never lands past the last one.
     Expects: $paginator, $perPage, $noun (singular), $options, $id. --}}
<div class="sm-foot">
    <span>
        Showing {{ $paginator->firstItem() ?? 0 }} to {{ $paginator->lastItem() ?? 0 }} of {{ $paginator->total() }} {{ \Illuminate\Support\Str::plural($noun, $paginator->total()) }}
    </span>

    <div class="d-flex align-items-center gap-3 flex-wrap">
        <x-pagination :paginator="$paginator" />

        <form method="GET" class="d-flex align-items-center gap-2">
            @foreach (request()->except(['per_page', 'page']) as $k => $v)
                @if (is_scalar($v))
                    <input type="hidden" name="{{ $k }}" value="{{ $v }}">
                @endif
            @endforeach
            <label for="{{ $id }}" class="per-page-label">Rows per page</label>
            <select id="{{ $id }}" name="per_page" class="giya-input per-page-select" onchange="this.form.requestSubmit ? this.form.requestSubmit() : this.form.submit()">
                @foreach ($options as $n)
                    <option value="{{ $n }}" @selected($perPage === $n)>{{ $n }}</option>
                @endforeach
            </select>
            <noscript><button type="submit" class="btn btn-outline btn-sm">Apply</button></noscript>
        </form>
    </div>
</div>
