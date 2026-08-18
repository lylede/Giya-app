<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Church;
use App\Models\ChurchCategory;
use App\Models\ChurchImage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ChurchController extends Controller
{
    public function index(Request $request): View
    {
        $churches = Church::with('churchCategory', 'primaryImage')
            ->search($request->search)
            ->when($request->category && $request->category !== 'All',
                fn ($q, $c) => $q->ofCategory($c))
            ->when($request->status === 'active', fn ($q) => $q->where('is_active', true))
            ->when($request->status === 'hidden', fn ($q) => $q->where('is_active', false))
            ->orderBy('name')
            ->paginate(10);

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

    /**
     * Swap the primary image.
     *
     * Files land in public/images/churches so they sit beside the seeded
     * artwork, need no storage:link, and can be committed to the repository —
     * which means photos travel with a clone to another machine.
     */
    protected function replacePhoto(Church $church, $file, ?string $caption): void
    {
        foreach ($church->images()->where('is_primary', true)->get() as $old) {
            $this->deleteImageFile($old->image_url);
            $old->delete();
        }

        $dir = public_path('images/churches');
        File::ensureDirectoryExists($dir);

        // church-name-1712345678.jpg — readable, and unique per upload.
        $name = Str::slug($church->name).'-'.now()->timestamp.'.'.$file->getClientOriginalExtension();
        $file->move($dir, $name);

        ChurchImage::create([
            'church_id'   => $church->id,
            'image_url'   => 'images/churches/'.$name,
            'caption'     => $caption ?: $church->name,
            'is_primary'  => true,
            'uploaded_at' => now(),
            'created_at'  => now(),
        ]);
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
            Storage::disk('public')->delete(str_replace('storage/', '', $url));
            return;
        }

        $path = public_path($url);

        if (File::exists($path)) {
            File::delete($path);
        }
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
}
