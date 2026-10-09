<?php

namespace App\Http\Controllers;

use App\Models\Attribute;
use App\Rules\UniqueInBusiness;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AttributeController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', Attribute::class);

        $query = Attribute::query();

        // Search
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Filter by type
        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        // Filter by status
        if ($request->filled('status')) {
            $query->where('is_active', $request->status === 'active');
        }

        $attributes = $query->orderBy('display_order')->orderBy('name')->paginate(15);

        $stats = Attribute::query()->selectRaw('
            COUNT(*) as total,
            SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active,
            SUM(CASE WHEN is_active = 0 THEN 1 ELSE 0 END) as inactive,
            SUM(CASE WHEN is_required = 1 THEN 1 ELSE 0 END) as required_count
        ')->first();

        $totalAttributes = (int) $stats->total;
        $activeAttributes = (int) $stats->active;
        $inactiveAttributes = (int) $stats->inactive;
        $requiredAttributes = (int) $stats->required_count;

        return view('attributes.index', compact(
            'attributes',
            'totalAttributes',
            'activeAttributes',
            'inactiveAttributes',
            'requiredAttributes'
        ));
    }

    public function create()
    {
        $this->authorize('create', Attribute::class);

        return view('attributes.create');
    }

    public function store(Request $request)
    {
        $this->authorize('create', Attribute::class);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', UniqueInBusiness::for('attributes', 'name')],
            'slug' => ['nullable', 'string', 'max:255', UniqueInBusiness::for('attributes', 'slug')],
            'description' => 'nullable|string',
            'values' => 'nullable|string',
            'type' => 'required|in:dropdown,radio,checkbox,color,button',
            'display_order' => 'nullable|integer|min:0',
            'is_required' => 'nullable|boolean',
            'is_visible' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
        ]);

        // Generate slug if not provided
        if (empty($validated['slug'])) {
            $validated['slug'] = Str::slug($validated['name']);
        }

        // Convert comma-separated values to array
        if (! empty($validated['values'])) {
            $validated['values'] = array_map('trim', explode(',', $validated['values']));
        }

        $validated['is_required'] = $request->boolean('is_required');
        $validated['is_visible'] = $request->boolean('is_visible');
        $validated['is_active'] = $request->boolean('is_active', true);

        Attribute::create($validated);

        return redirect()->route('attributes.index')
            ->with('success', 'Attribute created successfully.');
    }

    public function show(Attribute $attribute)
    {
        $this->authorize('view', $attribute);

        $attribute->loadCount('products');

        return view('attributes.show', compact('attribute'));
    }

    public function edit(Attribute $attribute)
    {
        $this->authorize('update', $attribute);

        return view('attributes.edit', compact('attribute'));
    }

    public function update(Request $request, Attribute $attribute)
    {
        $this->authorize('update', $attribute);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', UniqueInBusiness::for('attributes', 'name')->ignore($attribute->id)],
            'slug' => ['nullable', 'string', 'max:255', UniqueInBusiness::for('attributes', 'slug')->ignore($attribute->id)],
            'description' => 'nullable|string',
            'values' => 'nullable|string',
            'type' => 'required|in:dropdown,radio,checkbox,color,button',
            'display_order' => 'nullable|integer|min:0',
            'is_required' => 'nullable|boolean',
            'is_visible' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
        ]);

        // Generate slug if not provided
        if (empty($validated['slug'])) {
            $validated['slug'] = Str::slug($validated['name']);
        }

        // Convert comma-separated values to array
        if (! empty($validated['values'])) {
            $validated['values'] = array_map('trim', explode(',', $validated['values']));
        }

        $validated['is_required'] = $request->boolean('is_required');
        $validated['is_visible'] = $request->boolean('is_visible');
        $validated['is_active'] = $request->boolean('is_active');

        $attribute->update($validated);

        return redirect()->route('attributes.index')
            ->with('success', 'Attribute updated successfully.');
    }

    public function destroy(Attribute $attribute)
    {
        $this->authorize('delete', $attribute);

        $attribute->delete();

        return redirect()->route('attributes.index')
            ->with('success', 'Attribute deleted successfully.');
    }

    public function activate(Attribute $attribute)
    {
        $this->authorize('update', $attribute);

        $attribute->update(['is_active' => true]);

        return back()->with('success', 'Attribute activated successfully.');
    }

    public function deactivate(Attribute $attribute)
    {
        $this->authorize('update', $attribute);

        $attribute->update(['is_active' => false]);

        return back()->with('success', 'Attribute deactivated successfully.');
    }
}
