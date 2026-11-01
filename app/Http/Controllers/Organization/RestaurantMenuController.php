<?php

namespace App\Http\Controllers\Organization;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\MenuItemVariant;

class RestaurantMenuController extends Controller
{
    public function index()
    {
        $orgId = auth()->user()->organization_id;
        $locationId = session('active_location_id') ?? \App\Services\LocationManager::getActiveLocationId();

        if (!$locationId) {
            return redirect()->route('dashboard')->with('error', 'Please select a location to manage the menu.');
        }

        $categories = MenuCategory::with(['items' => function($q) {
            $q->with('variants')->orderBy('sort_order');
        }])->where('organization_id', $orgId)
          ->where('location_id', $locationId)
          ->orderBy('sort_order')
          ->get();

        return view('organization.menu.index', compact('categories'));
    }

    public function storeCategory(Request $request)
    {
        $request->validate(['name' => 'required|string|max:255']);
        
        MenuCategory::create([
            'organization_id' => auth()->user()->organization_id,
            'location_id' => session('active_location_id') ?? \App\Services\LocationManager::getActiveLocationId(),
            'name' => $request->name,
            'sort_order' => MenuCategory::where('location_id', session('active_location_id') ?? \App\Services\LocationManager::getActiveLocationId())->count()
        ]);

        return back()->with('success', 'Category created successfully.');
    }

    public function updateCategory(Request $request, MenuCategory $category)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'is_active' => 'boolean'
        ]);

        $category->update([
            'name' => $request->name,
            'is_active' => $request->has('is_active')
        ]);

        return back()->with('success', 'Category updated successfully.');
    }

    public function storeItem(Request $request)
    {
        $hasVariants = $request->boolean('has_variants', false);

        $rules = [
            'menu_category_id' => 'required|exists:menu_categories,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'photo' => 'nullable|image|max:2048',
            'is_veg' => 'nullable|boolean',
            'has_variants' => 'nullable|boolean',
        ];

        if ($hasVariants) {
            $rules['variants'] = 'required|array|min:1';
            $rules['variants.*.name'] = 'required|string|max:100';
            $rules['variants.*.price'] = 'required|numeric|min:0';
        } else {
            $rules['price'] = 'required|numeric|min:0';
        }

        $request->validate($rules);

        $category = MenuCategory::find($request->menu_category_id);
        if (!$category || $category->organization_id !== auth()->user()->organization_id) {
            abort(403);
        }

        $path = $request->hasFile('photo') ? \App\Services\ImageOptimizer::compressAndStore($request->file('photo'), 'menu_photos', 'public', 1000, 75) : null;

        $basePrice = $hasVariants 
            ? (collect($request->variants)->min(fn($v) => (float)($v['price'] ?? 0)) ?? 0)
            : (float)$request->price;

        $item = MenuItem::create([
            'menu_category_id' => $request->menu_category_id,
            'name' => $request->name,
            'price' => $basePrice,
            'has_variants' => $hasVariants,
            'description' => $request->description,
            'photo' => $path,
            'is_veg' => $request->boolean('is_veg', true),
            'sort_order' => MenuItem::where('menu_category_id', $request->menu_category_id)->count()
        ]);

        if ($hasVariants && !empty($request->variants)) {
            $sortOrder = 0;
            foreach ($request->variants as $vData) {
                if (!empty($vData['name'])) {
                    $item->variants()->create([
                        'name' => trim($vData['name']),
                        'price' => (float)$vData['price'],
                        'sort_order' => $sortOrder++,
                        'is_available' => true,
                    ]);
                }
            }
        }

        return back()->with('success', 'Item added successfully.');
    }

    public function updateItem(Request $request, MenuItem $item)
    {
        if ($item->category->organization_id !== auth()->user()->organization_id) {
            abort(403, 'Unauthorized action.');
        }

        $hasVariants = $request->boolean('has_variants', false);

        $rules = [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'photo' => 'nullable|image|max:2048',
            'is_veg' => 'nullable|boolean',
            'has_variants' => 'nullable|boolean',
        ];

        if ($hasVariants) {
            $rules['variants'] = 'required|array|min:1';
            $rules['variants.*.name'] = 'required|string|max:100';
            $rules['variants.*.price'] = 'required|numeric|min:0';
        } else {
            $rules['price'] = 'required|numeric|min:0';
        }

        $request->validate($rules);

        $basePrice = $hasVariants 
            ? (collect($request->variants)->min(fn($v) => (float)($v['price'] ?? 0)) ?? 0)
            : (float)$request->price;

        $data = [
            'name' => $request->name,
            'price' => $basePrice,
            'has_variants' => $hasVariants,
            'description' => $request->description,
            'is_veg' => $request->boolean('is_veg', true),
            'is_available' => $request->has('is_available'),
            'is_active' => $request->has('is_active'),
        ];

        if ($request->hasFile('photo')) {
            $data['photo'] = \App\Services\ImageOptimizer::compressAndStore($request->file('photo'), 'menu_photos', 'public', 1000, 75);
        }

        $item->update($data);

        if ($hasVariants && !empty($request->variants)) {
            $existingVariantIds = [];
            $sortOrder = 0;
            foreach ($request->variants as $vData) {
                if (empty($vData['name'])) continue;

                if (!empty($vData['id'])) {
                    $variant = $item->variants()->find($vData['id']);
                    if ($variant) {
                        $variant->update([
                            'name' => trim($vData['name']),
                            'price' => (float)$vData['price'],
                            'sort_order' => $sortOrder++,
                        ]);
                        $existingVariantIds[] = $variant->id;
                    }
                } else {
                    $newVariant = $item->variants()->create([
                        'name' => trim($vData['name']),
                        'price' => (float)$vData['price'],
                        'sort_order' => $sortOrder++,
                        'is_available' => true,
                    ]);
                    $existingVariantIds[] = $newVariant->id;
                }
            }
            $item->variants()->whereNotIn('id', $existingVariantIds)->delete();
        } elseif (!$hasVariants) {
            $item->variants()->delete();
        }

        return back()->with('success', 'Item updated successfully.');
    }

    public function destroyCategory(MenuCategory $category)
    {
        $category->delete();
        return back()->with('success', 'Category deleted.');
    }

    public function destroyItem(MenuItem $item)
    {
        if ($item->category->organization_id !== auth()->user()->organization_id) {
            abort(403, 'Unauthorized action.');
        }

        $item->delete();
        return back()->with('success', 'Item deleted.');
    }
}
