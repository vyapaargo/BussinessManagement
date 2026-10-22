<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\User;
use App\Models\Invoice;
use App\Models\Transaction;
use App\Models\RestaurantOrder;
use App\Models\OrganizationSubscription;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OrganizationController extends Controller
{
    public function index(Request $request)
    {
        $query = Organization::query();

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
        }

        if ($request->filled('status')) {
            $status = $request->status === 'active' ? 1 : 0;
            $query->where('is_active', $status);
        }

        $organizations = $query->latest()->paginate(10)->withQueryString();
        return view('super-admin.organizations.index', compact('organizations'));
    }

    public function create()
    {
        return view('super-admin.organizations.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'nullable|email:rfc,filter|max:255',
            'phone' => ['nullable', 'string', 'regex:/^(?:\+91[\-\s]?|0)?[6-9](?:[\-\s]?[0-9]){9}$/'],
            'gst_number' => 'nullable|string|max:50',
            'admin_name' => 'required|string|max:255',
            'admin_email' => 'required|email:rfc,filter|unique:users,email',
            'admin_password' => 'required|string|min:8',
        ], [
            'name.required' => 'Organization name is required.',
            'phone.regex' => 'Please enter a valid 10-digit mobile number starting with 6, 7, 8, or 9.',
            'admin_name.required' => 'Primary administrator name is required.',
            'admin_email.required' => 'Administrator email address is required.',
            'admin_email.email' => 'Please enter a valid email address.',
            'admin_email.unique' => 'This administrator email is already registered.',
            'admin_password.required' => 'Administrator password is required.',
            'admin_password.min' => 'Password must be at least 8 characters long.',
        ]);

        $normalizedPhone = $request->phone ? substr(preg_replace('/[^0-9]/', '', $request->phone), -10) : null;

        DB::transaction(function () use ($request, $normalizedPhone) {
            $org = Organization::create([
                'name' => $request->name,
                'email' => $request->email,
                'phone' => $normalizedPhone,
                'gst_number' => $request->gst_number,
                'is_active' => true,
            ]);

            $user = User::create([
                'name' => $request->admin_name,
                'email' => $request->admin_email,
                'password' => Hash::make($request->admin_password),
                'organization_id' => $org->id,
            ]);

            \App\Models\Role::firstOrCreate(['name' => 'Organization Admin', 'organization_id' => $org->id]);
            $user->assignRole('Organization Admin');

            Log::info("Super Admin created organization '{$org->name}' (ID: {$org->id}) with admin {$user->email}");
        });

        return redirect()->route('super-admin.organizations.index')->with('success', 'Organization created successfully.');
    }

    public function show(Organization $organization)
    {
        $users = $organization->users()->paginate(10);
        return view('super-admin.organizations.show', compact('organization', 'users'));
    }

    public function edit(Organization $organization)
    {
        return view('super-admin.organizations.edit', compact('organization'));
    }

    public function update(Request $request, Organization $organization)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'nullable|email:rfc,filter|max:255',
            'phone' => ['nullable', 'string', 'regex:/^(?:\+91[\-\s]?|0)?[6-9](?:[\-\s]?[0-9]){9}$/'],
            'gst_number' => 'nullable|string|max:50',
        ], [
            'name.required' => 'Organization name is required.',
            'phone.regex' => 'Please enter a valid 10-digit mobile number starting with 6, 7, 8, or 9.',
            'email.email' => 'Please enter a valid email address.',
        ]);

        $normalizedPhone = $request->phone ? substr(preg_replace('/[^0-9]/', '', $request->phone), -10) : null;

        $organization->update([
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $normalizedPhone,
            'gst_number' => $request->gst_number,
        ]);

        Log::info("Super Admin updated organization #{$organization->id} ({$organization->name})");

        return redirect()->route('super-admin.organizations.index')->with('success', 'Organization updated successfully.');
    }

    public function toggleStatus(Organization $organization)
    {
        $organization->update(['is_active' => !$organization->is_active]);
        $statusText = $organization->is_active ? 'Active' : 'Inactive';
        Log::info("Super Admin toggled organization #{$organization->id} status to {$statusText}");
        return back()->with('success', "Organization '{$organization->name}' is now {$statusText}.");
    }

    public function destroy(Organization $organization)
    {
        // Check financial and operational records to protect data integrity
        $invoicesCount = Invoice::withoutGlobalScopes()->where('organization_id', $organization->id)->count();
        $transactionsCount = Transaction::withoutGlobalScopes()->where('organization_id', $organization->id)->count();
        $ordersCount = RestaurantOrder::withoutGlobalScopes()->where('organization_id', $organization->id)->count();
        $subsCount = OrganizationSubscription::where('organization_id', $organization->id)->count();

        if ($invoicesCount > 0 || $transactionsCount > 0 || $ordersCount > 0 || $subsCount > 0) {
            // Protect financial history! Deactivate instead of hard deleting.
            $organization->update(['is_active' => false]);
            Log::warning("Super Admin attempted deletion of organization #{$organization->id} ('{$organization->name}'). Prevented due to existing records ({$invoicesCount} invoices, {$transactionsCount} transactions, {$ordersCount} orders, {$subsCount} subscriptions). Deactivated instead.");

            return redirect()->route('super-admin.organizations.index')->with('error', 
                "Cannot permanently delete organization '{$organization->name}' because it contains financial records ({$invoicesCount} invoices, {$transactionsCount} transactions, {$ordersCount} orders). The organization has been safely deactivated instead to protect financial audit history."
            );
        }

        // Organization has 0 financial records - safe to delete setup records completely
        DB::transaction(function () use ($organization) {
            $orgId = $organization->id;
            
            // Delete users and employee profiles
            $userIds = User::where('organization_id', $orgId)->pluck('id');
            \App\Models\Employee::where('organization_id', $orgId)->delete();
            \App\Models\Location::where('organization_id', $orgId)->delete();
            \App\Models\Role::where('organization_id', $orgId)->delete();
            \App\Models\Product::where('organization_id', $orgId)->delete();
            \App\Models\Category::where('organization_id', $orgId)->delete();
            \App\Models\Client::where('organization_id', $orgId)->delete();
            
            DB::table('user_locations')->whereIn('user_id', $userIds)->delete();
            DB::table('user_roles')->whereIn('user_id', $userIds)->delete();
            User::where('organization_id', $orgId)->delete();

            $organization->delete();
            Log::info("Super Admin permanently deleted clean organization #{$orgId} ('{$organization->name}')");
        });

        return redirect()->route('super-admin.organizations.index')->with('success', "Organization '{$organization->name}' deleted successfully.");
    }
}
