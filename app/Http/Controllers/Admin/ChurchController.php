<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Church;
use App\Models\ChurchCategory;
use App\Models\ChurchImage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class ChurchController extends Controller
{
    public function index(Request $request): View
    {
        /*
           when() hands the CONDITION to the callback, not the value. The old
           line tested `$request->category !== 'All'`, so the callback received
           `true` and searched for a category literally named "1" - which is why
           filtering by category returned an empty table.

           Passing the value itself means the callback receives the value.
        */
        $category = $request->category === 'All' ? null : $request->category;

        $churches = Church::with('churchCategory', 'primaryImage')
            ->search($request->search)
            ->when($category, fn ($q, $name) => $q->ofCategory($name))
            ->when($request->status === 'active', fn ($q) => $q->where('is_active', true))
            ->when($request->status === 'hidden', fn ($q) => $q->where('is_active', false))
            ->orderBy('name')
            ->paginate(10)
            ->withQueryString();

        $all = Church::with('churchCategory', 'primaryImage')->get();

        return view('admin.destinations', [
            'churches'   => $churches,
            'search'     => $request->search,
            'category'   => $request->category ?? 'All',
            'categories' => ChurchCategory::orderBy('name')->pluck('name')->prepend('All')->all(),

            // Pins on the picker map.
            'markers' => $all
                ->filter(fn (Church $c) => $c->latitude && $c->longitude)
                ->map(fn (Church $c) => [
                    'id'     => $c->id,
                    'name'   => $c->name,
                    'lat'    => (float) $c->latitude,
                    'lng'    => (float) $c->longitude,
                    'image'  => $c->imagePath(),
                    'color'  => $c->color(),
                    'active' => (bool) $c->is_active,
                ])->values()->all(),

            // Full records so clicking edit fills the form without another request.
            'rows' => $all->map(fn (Church $c) => [
                'id'          => $c->id,
                'name'        => $c->name,
                'category'    => $c->category,
                'location'    => $c->location,
                'address'     => $c->address,
                'lat'         => $c->latitude ? (float) $c->latitude : null,
                'lng'         => $c->longitude ? (float) $c->longitude : null,
                'open'        => $c->opening_time ? substr($c->opening_time, 0, 5) : '',
                'close'       => $c->closing_time ? substr($c->closing_time, 0, 5) : '',
                'description' => $c->description,
                'image'       => $c->imagePath(),
                'active'      => (bool) $c->is_active,
            ])->values()->all(),
        ]);
    }

    /** Handles both create and update — the form posts church_id when editing. */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'church_id'    => ['nullable', 'integer', 'exists:churches,id'],
            'name'         => ['required', 'string', 'max:200'],
            'location'     => ['required', 'string', 'max:200'],
            'address'      => ['nullable', 'string', 'max:255'],
            'category'     => ['required', 'string', 'exists:church_categories,name'],
            'status'       => ['nullable', 'in:Published,Draft'],
            'description'  => ['nullable', 'string'],
            'latitude'     => ['nullable', 'numeric', 'between:-90,90'],
            'longitude'    => ['nullable', 'numeric', 'between:-180,180'],
            'opening_time' => ['nullable', 'date_format:H:i'],
            'closing_time' => ['nullable', 'date_format:H:i'],
            'photo'        => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:4096'],
            'caption'      => ['nullable', 'string', 'max:255'],
        ], [
            'photo.image' => 'Choose an image file (JPG, PNG, or WEBP).',
            'photo.max'   => 'Keep the photo under 4 MB.',
        ]);

        $category = ChurchCategory::firstOrCreate(
            ['name' => $data['category']],
            ['created_at' => now(), 'updated_at' => now()]
        );

        $fields = [
            'category_id'  => $category->id,
            'name'         => $data['name'],
            'location'     => $data['location'],
            'address'      => $data['address'] ?? null,
            'description'  => $data['description'] ?? null,
            'latitude'     => $data['latitude'] ?? null,
            'longitude'    => $data['longitude'] ?? null,
            'opening_time' => $data['opening_time'] ?? null,
            'closing_time' => $data['closing_time'] ?? null,
            'is_active'    => ($data['status'] ?? 'Published') === 'Published',
            'updated_at'   => now(),
        ];

        if (! empty($data['church_id'])) {
            $church = Church::findOrFail($data['church_id']);
            $church->update($fields);
            $message = $church->name.' updated.';
        } else {
            $church = Church::create($fields + ['created_at' => now()]);
            $message = $church->name.' added.';
        }

        if ($request->hasFile('photo')) {
            $this->replacePhoto($church, $request->file('photo'), $data['caption'] ?? null);
        }

        return back()->with('success', $message);
    }

    public function import(Request $request): RedirectResponse
    {
        $request->validate([
            'import_file' => ['required', 'file', 'max:10240'],
        ], [
            'import_file.required' => 'Choose a CSV or JSON file to import.',
        ]);

        $file = $request->file('import_file');
        $extension = strtolower($file->getClientOriginalExtension() ?: pathinfo($file->getClientOriginalName(), PATHINFO_EXTENSION));

        if (! in_array($extension, ['csv', 'json'], true)) {
            return back()->with('error', 'Use a CSV or JSON file for destination import.');
        }

        try {
            $rows = $this->parseImportRows($file->getRealPath(), $extension);
        } catch (\Throwable $e) {
            return back()->with('error', 'The file could not be read. Please check that it is valid CSV or JSON.');
        }

        $created = 0;
        $skipped = 0;
        $errors = [];

        foreach ($rows as $index => $row) {
            $data = $this->normalizeImportRow($row);

            if ($data['name'] === '' || $data['location'] === '' || $data['category'] === '') {
                $errors[] = 'Row '.($index + 2).': missing name, location or category.';
                $skipped++;
                continue;
            }

            if ($this->destinationExists($data['name'], $data['location'])) {
                $errors[] = 'Row '.($index + 2).': '.$data['name'].' in '.$data['location'].' already exists.';
                $skipped++;
                continue;
            }

            $category = ChurchCategory::firstOrCreate(
                ['name' => $data['category']],
                ['created_at' => now(), 'updated_at' => now()]
            );

            $church = Church::create([
                'category_id' => $category->id,
                'name' => $data['name'],
                'location' => $data['location'],
                'address' => $data['address'] ?? null,
                'description' => $data['description'] ?? null,
                'latitude' => $data['latitude'] ?? null,
                'longitude' => $data['longitude'] ?? null,
                'opening_time' => $data['opening_time'] ?? null,
                'closing_time' => $data['closing_time'] ?? null,
                'is_active' => ($data['status'] ?? 'Published') === 'Published',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            if (! empty($data['image_url']) && filter_var($data['image_url'], FILTER_VALIDATE_URL)) {
                ChurchImage::create([
                    'church_id' => $church->id,
                    'image_url' => $data['image_url'],
                    'caption' => $data['caption'] ?? $church->name,
                    'is_primary' => true,
                    'uploaded_at' => now(),
                    'created_at' => now(),
                ]);
            }

            $created++;
        }

        $message = 'Imported '.$created.' destination'.($created === 1 ? '' : 's').'.';

        if ($skipped > 0) {
            $message .= ' '.$skipped.' row'.($skipped === 1 ? '' : 's').' skipped.';
        }

        if (! empty($errors)) {
            $response = redirect()->route('admin.destinations')->with('success', $message)->with('import_errors', $errors);

            if ($created === 0) {
                $response->with('error', $message);
            }

            return $response;
        }

        return redirect()->route('admin.destinations')->with('success', $message);
    }

    public function updatePhoto(Request $request, Church $church): RedirectResponse
    {
        $request->validate([
            'photo'   => ['required', 'image', 'mimes:jpeg,jpg,png,webp', 'max:4096'],
            'caption' => ['nullable', 'string', 'max:255'],
        ]);

        $this->replacePhoto($church, $request->file('photo'), $request->caption);

        return back()->with('success', 'Photo updated for '.$church->name.'.');
    }

    public function toggle(Church $church): RedirectResponse
    {
        $church->update(['is_active' => ! $church->is_active, 'updated_at' => now()]);

        return back()->with('success', $church->name.($church->is_active ? ' published.' : ' moved to draft.'));
    }

    protected function parseImportRows(string $path, string $extension): array
    {
        if ($extension === 'json') {
            $decoded = json_decode(file_get_contents($path), true);

            if (is_array($decoded) && isset($decoded['destinations']) && is_array($decoded['destinations'])) {
                return $decoded['destinations'];
            }

            if (is_array($decoded)) {
                return $decoded;
            }

            throw new \RuntimeException('Invalid JSON import file.');
        }

        $handle = fopen($path, 'r');
        if ($handle === false) {
            throw new \RuntimeException('Could not open CSV import file.');
        }

        $header = null;
        $rows = [];

        while (($row = fgetcsv($handle)) !== false) {
            if ($row === [null] || count(array_filter($row, fn ($cell) => trim((string) $cell) !== '')) === 0) {
                continue;
            }

            if ($header === null) {
                $header = array_map(fn ($cell) => trim((string) $cell), $row);
                continue;
            }

            $data = [];
            foreach ($header as $index => $key) {
                $data[trim((string) $key)] = $row[$index] ?? '';
            }

            $rows[] = $data;
        }

        fclose($handle);

        return $rows;
    }

    protected function normalizeImportRow(array $row): array
    {
        $normalized = [];

        foreach ($row as $key => $value) {
            $lookup = strtolower(str_replace([' ', '-', '/'], '_', trim((string) $key)));
            $normalized[$lookup] = is_scalar($value) ? trim((string) $value) : (string) $value;
        }

        $aliases = [
            'church_name' => 'name',
            'destination_name' => 'name',
            'title' => 'name',
            'city' => 'location',
            'municipality' => 'location',
            'new_category' => 'category',
            'status_name' => 'status',
            'photo_url' => 'image_url',
            'image' => 'image_url',
        ];

        foreach ($aliases as $from => $to) {
            if (isset($normalized[$from])) {
                $normalized[$to] = $normalized[$from];
            }
        }

        $status = $normalized['status'] ?? 'Published';
        if ($status !== 'Published' && $status !== 'Draft') {
            $status = 'Published';
        }

        $latitude = $this->coerceImportNumber($normalized['latitude'] ?? null, -90, 90);
        $longitude = $this->coerceImportNumber($normalized['longitude'] ?? null, -180, 180);

        return [
            'name' => $normalized['name'] ?? '',
            'category' => $normalized['category'] ?? '',
            'location' => $normalized['location'] ?? '',
            'address' => $normalized['address'] ?? null,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'opening_time' => $this->normalizeImportTime($normalized['opening_time'] ?? null),
            'closing_time' => $this->normalizeImportTime($normalized['closing_time'] ?? null),
            'description' => $normalized['description'] ?? null,
            'status' => $status,
            'image_url' => $normalized['image_url'] ?? null,
            'caption' => $normalized['caption'] ?? null,
        ];
    }

    protected function destinationExists(string $name, string $location): bool
    {
        return Church::whereRaw('LOWER(name) = ?', [mb_strtolower(trim($name))])
            ->whereRaw('LOWER(location) = ?', [mb_strtolower(trim($location))])
            ->exists();
    }

    protected function coerceImportNumber(?string $value, float $min, float $max): ?float
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        $value = trim((string) $value);
        $numeric = is_numeric($value) ? (float) $value : null;

        if ($numeric === null || $numeric < $min || $numeric > $max) {
            return null;
        }

        return $numeric;
    }

    protected function normalizeImportTime(?string $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        $time = trim((string) $value);
        if (preg_match('/^\d{1,2}:\d{2}$/', $time)) {
            return $time;
        }

        $parsed = strtotime($time);

        return $parsed === false ? null : date('H:i', $parsed);
    }

    /** Swap the primary image, deleting the file the old row pointed at. */
    /**
     * Store the primary photograph for a destination.
     *
     * Files go to public/images/churches, named after the church:
     *
     *     basilica-del-santo-nino.jpg
     *
     * public/ is committed to the repository while storage/app/public is
     * gitignored, so a teammate who clones or pulls gets the photographs rather
     * than a wall of placeholders. Naming the file after the church also means
     * Church::imagePath() can find it even if the ChurchImage row is lost.
     */
    protected function replacePhoto(Church $church, $file, ?string $caption): void
    {
        $dir = public_path('images/churches');

        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        // Remove whatever this church had before, in either location.
        foreach ($church->images()->where('is_primary', true)->get() as $old) {
            $this->deleteImageFile($old->image_url);
            $old->delete();
        }

        $slug = \Illuminate\Support\Str::slug($church->name);
        $ext  = strtolower($file->getClientOriginalExtension() ?: 'jpg');

        // One church, one photo file: a repeatedly edited destination should not
        // leave a trail of orphans behind it.
        foreach (['jpg', 'jpeg', 'png', 'webp'] as $stale) {
            $path = "{$dir}/{$slug}.{$stale}";

            if (! file_exists($path) || $path === "{$dir}/{$slug}.{$ext}") {
                continue;
            }

            if (! @unlink($path)) {
                \Illuminate\Support\Facades\Log::warning("Could not remove old photo: {$path}");
            }
        }

        $file->move($dir, "{$slug}.{$ext}");

        ChurchImage::create([
            'church_id'   => $church->id,
            'image_url'   => "images/churches/{$slug}.{$ext}",
            'caption'     => $caption ?: $church->name,
            'is_primary'  => true,
            'uploaded_at' => now(),
            'created_at'  => now(),
        ]);
    }
        /** Remove a destination and everything attached to it. */
    public function destroy(Church $church): RedirectResponse
    {
        $name = $church->name;

        foreach ($church->images as $image) {
            $this->deleteImageFile($image->image_url);
        }

        // church_images, itinerary_stops, visit_history, feedback and
        // favorites all cascade in the schema.
        $church->delete();

        return redirect()
            ->route('admin.destinations')
            ->with('success', $name.' deleted.');
    }

    /**
     * Remove an uploaded file from disk.
     *
     * Seeded .svg artwork ships with the repository and is shared, so it is
     * never deleted — only files this admin panel wrote.
     */
    protected function deleteImageFile(?string $url): void
    {
        if (! $url || str_starts_with($url, 'http') || str_ends_with($url, '.svg')) {
            return;
        }

        if (str_starts_with($url, 'storage/')) {
            \Illuminate\Support\Facades\Storage::disk('public')->delete(str_replace('storage/', '', $url));
            return;
        }

        $path = public_path($url);

        if (file_exists($path)) {
            unlink($path);
        }
    }
}
