<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Plan;
use Illuminate\Support\Facades\Log;

class PlanController extends Controller
{
    public function index()
    {
        $plans = Plan::with('features')->withCount('subscriptions')->latest()->paginate(15)->withQueryString();
        return view('super-admin.plans.index', compact('plans'));
    }

    public function create()
    {
        return view('super-admin.plans.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'duration_value' => 'required|integer|min:1',
            'duration_unit' => 'required|in:days,months,years',
            'price' => 'required|numeric|min:0',
            'price_monthly' => 'nullable|numeric|min:0',
            'price_yearly' => 'nullable|numeric|min:0',
            'type' => 'required|in:base,addon',
            'category' => 'required|in:business,restaurant,all',
            'description' => 'nullable|string',
            'features' => 'nullable|array',
            'features.*.code' => 'required_with:features|string',
            'features.*.value' => 'required_with:features|string',
        ], [
            'name.required' => 'Please provide a plan name.',
            'duration_value.required' => 'Please enter the plan duration value (e.g. 30, 90, 365).',
            'duration_value.min' => 'Plan duration must be at least 1.',
            'duration_unit.required' => 'Please select duration unit (Days, Months, or Years).',
            'price.required' => 'Please enter the plan price.',
            'price.min' => 'Plan price cannot be negative.',
        ]);

        $price = (float) $request->price;
        $priceMonthly = $request->filled('price_monthly') ? (float)$request->price_monthly : $price;
        $priceYearly = $request->filled('price_yearly') ? (float)$request->price_yearly : ($price * 12);

        $plan = Plan::create([
            'name' => $request->name,
            'duration_value' => (int) $request->duration_value,
            'duration_unit' => $request->duration_unit,
            'price' => $price,
            'price_monthly' => $priceMonthly,
            'price_yearly' => $priceYearly,
            'type' => $request->type,
            'category' => $request->category,
            'description' => $request->description,
            'is_active' => $request->boolean('is_active', true),
        ]);

        if ($request->has('features')) {
            foreach ($request->features as $feature) {
                if (!empty($feature['code']) && !empty($feature['value'])) {
                    $plan->features()->create([
                        'feature_code' => $feature['code'],
                        'feature_value' => $feature['value']
                    ]);
                }
            }
        }

        Log::info("Super Admin created plan: {$plan->name} ({$plan->formatted_duration} @ ₹{$plan->price})");

        return redirect()->route('super-admin.plans.index')->with('success', "Plan '{$plan->name}' created successfully.");
    }

    public function edit(Plan $plan)
    {
        $plan->load('features');
        return view('super-admin.plans.edit', compact('plan'));
    }

    public function update(Request $request, Plan $plan)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'duration_value' => 'required|integer|min:1',
            'duration_unit' => 'required|in:days,months,years',
            'price' => 'required|numeric|min:0',
            'price_monthly' => 'nullable|numeric|min:0',
            'price_yearly' => 'nullable|numeric|min:0',
            'type' => 'required|in:base,addon',
            'category' => 'required|in:business,restaurant,all',
            'description' => 'nullable|string',
            'features' => 'nullable|array',
            'features.*.code' => 'required_with:features|string',
            'features.*.value' => 'required_with:features|string',
        ], [
            'name.required' => 'Please provide a plan name.',
            'duration_value.required' => 'Please enter the plan duration value.',
            'duration_value.min' => 'Plan duration must be at least 1.',
            'duration_unit.required' => 'Please select duration unit.',
            'price.required' => 'Please enter the plan price.',
            'price.min' => 'Plan price cannot be negative.',
        ]);

        $price = (float) $request->price;
        $priceMonthly = $request->filled('price_monthly') ? (float)$request->price_monthly : $price;
        $priceYearly = $request->filled('price_yearly') ? (float)$request->price_yearly : ($price * 12);

        $plan->update([
            'name' => $request->name,
            'duration_value' => (int) $request->duration_value,
            'duration_unit' => $request->duration_unit,
            'price' => $price,
            'price_monthly' => $priceMonthly,
            'price_yearly' => $priceYearly,
            'type' => $request->type,
            'category' => $request->category,
            'description' => $request->description,
            'is_active' => $request->boolean('is_active', true),
        ]);

        $plan->features()->delete();
        if ($request->has('features')) {
            foreach ($request->features as $feature) {
                if (!empty($feature['code']) && !empty($feature['value'])) {
                    $plan->features()->create([
                        'feature_code' => $feature['code'],
                        'feature_value' => $feature['value']
                    ]);
                }
            }
        }

        Log::info("Super Admin updated plan: {$plan->name}");

        return redirect()->route('super-admin.plans.index')->with('success', "Plan '{$plan->name}' updated successfully.");
    }

    public function toggleStatus(Plan $plan)
    {
        $plan->update(['is_active' => !$plan->is_active]);
        $statusText = $plan->is_active ? 'Active' : 'Inactive';
        Log::info("Super Admin toggled plan '{$plan->name}' status to {$statusText}");
        return back()->with('success', "Plan '{$plan->name}' is now {$statusText}.");
    }

    public function show(Plan $plan)
    {
        $plan->load(['features', 'subscriptions.organization']);
        return view('super-admin.plans.show', compact('plan'));
    }

    public function destroy(Plan $plan)
    {
        $subscriptionsCount = $plan->subscriptions()->count();
        if ($subscriptionsCount > 0) {
            // Cannot hard delete due to foreign key constraints and financial history.
            // Safely deactivate the plan so it cannot be chosen for new subscriptions.
            $plan->update(['is_active' => false]);
            Log::warning("Super Admin attempted to delete plan '{$plan->name}' with {$subscriptionsCount} subscriptions. Deactivated plan instead.");
            return redirect()->route('super-admin.plans.index')->with('error', "Cannot delete plan '{$plan->name}' because {$subscriptionsCount} active/historical subscription record(s) depend on it. The plan has been safely deactivated instead so no new customers can select it.");
        }

        $plan->features()->delete();
        $plan->delete();
        Log::info("Super Admin permanently deleted plan '{$plan->name}'");

        return redirect()->route('super-admin.plans.index')->with('success', "Plan '{$plan->name}' deleted successfully.");
    }
}
