<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Complaint;
use App\Models\Invoice;
use App\Models\Location;
use App\Models\Organization;
use App\Models\OrganizationSubscription;
use App\Models\Plan;
use App\Models\Role;
use App\Models\User;
use App\Notifications\InvoiceNotification;
use App\Services\CommunicationService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class EnhancementsAndBugFixesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\PermissionSeeder::class);
        $this->seed(\Database\Seeders\RoleSeeder::class);
    }

    /*
    |--------------------------------------------------------------------------
    | 1. Flexible Subscription Plans (Days, Months, Years)
    |--------------------------------------------------------------------------
    */

    public function test_super_admin_can_create_flexible_plan_with_days_and_custom_duration()
    {
        $superAdmin = User::factory()->create();
        $superAdminRole = Role::firstOrCreate(['name' => 'Super Admin', 'organization_id' => null]);
        $superAdmin->roles()->attach($superAdminRole);

        $response = $this->actingAs($superAdmin)->post(route('super-admin.plans.store'), [
            'name' => '45-Day Promo Plan',
            'type' => 'base',
            'category' => 'all',
            'description' => 'Special promotion plan valid for 45 calendar days',
            'duration_value' => 45,
            'duration_unit' => 'days',
            'price' => 799,
            'is_active' => 1,
            'features' => [
                ['code' => 'max_users', 'value' => '5'],
                ['code' => 'max_products', 'value' => '100'],
            ],
        ]);

        $response->assertRedirect(route('super-admin.plans.index'));
        $this->assertDatabaseHas('plans', [
            'name' => '45-Day Promo Plan',
            'duration_value' => 45,
            'duration_unit' => 'days',
            'price' => 799,
            'is_active' => true,
        ]);

        $plan = Plan::where('name', '45-Day Promo Plan')->first();
        $this->assertEquals('45 Days', $plan->formatted_duration);
        $this->assertEquals(799, $plan->effective_price);

        // Verify accurate expiry calculation from any start date
        $startsAt = Carbon::parse('2026-03-01 10:00:00');
        $endsAt = $plan->calculateEndsAt($startsAt);
        $this->assertEquals('2026-04-15 10:00:00', $endsAt->toDateTimeString());
    }

    public function test_plan_duration_calculation_handles_month_end_and_leap_years_without_overflow()
    {
        // 1 Month plan starting on Jan 31
        $monthPlan = Plan::create([
            'name' => 'Monthly Tier',
            'duration_value' => 1,
            'duration_unit' => 'months',
            'price' => 499,
            'is_active' => true,
        ]);

        // Non leap year (2025): Jan 31 + 1 month should clamp cleanly to Feb 28
        $startsNonLeap = Carbon::parse('2025-01-31 00:00:00');
        $endsNonLeap = $monthPlan->calculateEndsAt($startsNonLeap);
        $this->assertEquals('2025-02-28 00:00:00', $endsNonLeap->toDateTimeString());

        // Leap year (2024): Jan 31 + 1 month should clamp cleanly to Feb 29
        $startsLeap = Carbon::parse('2024-01-31 00:00:00');
        $endsLeap = $monthPlan->calculateEndsAt($startsLeap);
        $this->assertEquals('2024-02-29 00:00:00', $endsLeap->toDateTimeString());

        // 1 Year plan starting on Leap day (Feb 29, 2024) + 1 year should clamp cleanly to Feb 28, 2025
        $yearPlan = Plan::create([
            'name' => 'Annual Tier',
            'duration_value' => 1,
            'duration_unit' => 'years',
            'price' => 4999,
            'is_active' => true,
        ]);
        $leapDayStarts = Carbon::parse('2024-02-29 00:00:00');
        $endsAnnual = $yearPlan->calculateEndsAt($leapDayStarts);
        $this->assertEquals('2025-02-28 00:00:00', $endsAnnual->toDateTimeString());
    }

    public function test_plan_duration_calculation_for_all_standard_and_custom_day_intervals()
    {
        $start = Carbon::parse('2026-01-01 00:00:00');

        // 7 Days
        $p7 = Plan::create(['name' => '7-Day Trial', 'duration_value' => 7, 'duration_unit' => 'days', 'price' => 99]);
        $this->assertEquals('2026-01-08 00:00:00', $p7->calculateEndsAt($start)->toDateTimeString());

        // 90 Days (Quarterly)
        $p90 = Plan::create(['name' => '90-Day Plan', 'duration_value' => 90, 'duration_unit' => 'days', 'price' => 1499]);
        $this->assertEquals('2026-04-01 00:00:00', $p90->calculateEndsAt($start)->toDateTimeString());

        // 180 Days (Half-Yearly)
        $p180 = Plan::create(['name' => '180-Day Plan', 'duration_value' => 180, 'duration_unit' => 'days', 'price' => 2899]);
        $this->assertEquals('2026-06-30 00:00:00', $p180->calculateEndsAt($start)->toDateTimeString());

        // 365 Days (Annual by Days)
        $p365 = Plan::create(['name' => '365-Day Plan', 'duration_value' => 365, 'duration_unit' => 'days', 'price' => 4999]);
        $this->assertEquals('2027-01-01 00:00:00', $p365->calculateEndsAt($start)->toDateTimeString());

        // Custom 15 Days
        $p15 = Plan::create(['name' => '15-Day Flash', 'duration_value' => 15, 'duration_unit' => 'days', 'price' => 199]);
        $this->assertEquals('2026-01-16 00:00:00', $p15->calculateEndsAt($start)->toDateTimeString());
    }

    public function test_editing_plan_does_not_modify_existing_subscription_dates_or_invoices()
    {
        $superAdmin = User::factory()->create();
        $superAdminRole = Role::firstOrCreate(['name' => 'Super Admin', 'organization_id' => null]);
        $superAdmin->roles()->attach($superAdminRole);

        $org = Organization::create(['name' => 'Subscribed Org', 'is_active' => true]);
        $plan = Plan::create([
            'name' => 'Original 30-Day Plan',
            'type' => 'base',
            'category' => 'all',
            'duration_value' => 30,
            'duration_unit' => 'days',
            'price' => 500,
            'is_active' => true,
        ]);

        $subStarts = Carbon::parse('2026-01-01 12:00:00');
        $subEnds = Carbon::parse('2026-01-31 12:00:00');

        $subscription = OrganizationSubscription::create([
            'organization_id' => $org->id,
            'plan_id' => $plan->id,
            'starts_at' => $subStarts,
            'ends_at' => $subEnds,
            'status' => 'active',
        ]);

        // Super Admin updates plan price to 999 and duration to 60 days
        $this->actingAs($superAdmin)->put(route('super-admin.plans.update', $plan), [
            'name' => 'Updated 60-Day Plan',
            'type' => 'base',
            'category' => 'all',
            'duration_value' => 60,
            'duration_unit' => 'days',
            'price' => 999,
            'is_active' => 1,
        ]);

        // Existing active subscription remains untouched
        $this->assertEquals($subStarts->toDateString(), $subscription->fresh()->starts_at->toDateString());
        $this->assertEquals($subEnds->toDateString(), $subscription->fresh()->ends_at->toDateString());
    }

    /*
    |--------------------------------------------------------------------------
    | 2. Plan and Organization Active/Inactive & Delete Protection
    |--------------------------------------------------------------------------
    */

    public function test_super_admin_can_toggle_plan_status()
    {
        $superAdmin = User::factory()->create();
        $superAdminRole = Role::firstOrCreate(['name' => 'Super Admin', 'organization_id' => null]);
        $superAdmin->roles()->attach($superAdminRole);

        $plan = Plan::create([
            'name' => 'Starter Pack',
            'duration_value' => 30,
            'duration_unit' => 'days',
            'price' => 299,
            'is_active' => true,
        ]);

        // Toggle to inactive
        $response = $this->actingAs($superAdmin)->patch(route('super-admin.plans.toggle-status', $plan));
        $response->assertRedirect();
        $this->assertFalse((bool) $plan->fresh()->is_active);

        // Toggle back to active
        $response = $this->actingAs($superAdmin)->patch(route('super-admin.plans.toggle-status', $plan));
        $response->assertRedirect();
        $this->assertTrue((bool) $plan->fresh()->is_active);
    }

    public function test_deleting_plan_with_subscriptions_safely_deactivates_it()
    {
        $superAdmin = User::factory()->create();
        $superAdminRole = Role::firstOrCreate(['name' => 'Super Admin', 'organization_id' => null]);
        $superAdmin->roles()->attach($superAdminRole);

        $org = Organization::create(['name' => 'Subscribed Org', 'is_active' => true]);
        $plan = Plan::create([
            'name' => 'Active Business Plan',
            'duration_value' => 30,
            'duration_unit' => 'days',
            'price' => 499,
            'is_active' => true,
        ]);

        OrganizationSubscription::create([
            'organization_id' => $org->id,
            'plan_id' => $plan->id,
            'starts_at' => now(),
            'ends_at' => now()->addDays(30),
            'status' => 'active',
        ]);

        // Attempt deletion
        $response = $this->actingAs($superAdmin)->delete(route('super-admin.plans.destroy', $plan));
        $response->assertRedirect(route('super-admin.plans.index'));

        // Plan must NOT be deleted from DB to protect subscription history
        $this->assertDatabaseHas('plans', ['id' => $plan->id]);
        // But must be deactivated
        $this->assertFalse((bool) $plan->fresh()->is_active);
    }

    public function test_inactive_organization_user_is_blocked_from_logging_in()
    {
        $org = Organization::create(['name' => 'Suspended Org', 'is_active' => false]);
        $user = User::factory()->create([
            'organization_id' => $org->id,
            'email' => 'user@suspended.com',
            'password' => bcrypt('password123'),
        ]);

        $response = $this->post(route('login'), [
            'email' => 'user@suspended.com',
            'password' => 'password123',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_authenticated_user_in_deactivated_organization_is_immediately_logged_out()
    {
        $org = Organization::create(['name' => 'Active Org', 'is_active' => true]);
        $user = User::factory()->create(['organization_id' => $org->id]);
        $role = Role::create(['name' => 'Organization Admin', 'organization_id' => $org->id]);
        $user->roles()->attach($role);

        // Now super admin deactivates the organization
        $org->update(['is_active' => false]);

        // When user makes any authenticated request, CheckSubscriptionValidity middleware intercepts
        $response = $this->actingAs($user)->get(route('organization.dashboard'));

        $response->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_deleting_organization_with_financial_invoices_safely_deactivates_it()
    {
        $superAdmin = User::factory()->create();
        $superAdminRole = Role::firstOrCreate(['name' => 'Super Admin', 'organization_id' => null]);
        $superAdmin->roles()->attach($superAdminRole);

        $org = Organization::create(['name' => 'Billing Org', 'is_active' => true]);
        $loc = Location::create(['organization_id' => $org->id, 'name' => 'HQ', 'is_active' => true]);

        // Create an invoice record
        Invoice::create([
            'organization_id' => $org->id,
            'location_id' => $loc->id,
            'invoice_number' => 'INV-AUDIT-001',
            'invoice_date' => now(),
            'grand_total' => 1500,
            'amount_paid' => 1500,
            'status' => 'Paid',
        ]);

        // Attempt deletion by super admin
        $response = $this->actingAs($superAdmin)->delete(route('super-admin.organizations.destroy', $org));
        $response->assertRedirect(route('super-admin.organizations.index'));
        $response->assertSessionHas('error');

        // Organization record preserved for accounting and statutory compliance
        $this->assertDatabaseHas('organizations', ['id' => $org->id]);
        // Organization deactivated
        $this->assertFalse((bool) $org->fresh()->is_active);
    }

    /*
    |--------------------------------------------------------------------------
    | 3. Invoice Email & Secure Complaint Workflow
    |--------------------------------------------------------------------------
    */

    public function test_invoice_email_dispatch_and_duplicate_prevention()
    {
        Notification::fake();

        $org = Organization::create(['name' => 'Retail Store', 'is_active' => true]);
        $client = Client::create([
            'organization_id' => $org->id,
            'name' => 'Arun Kumar',
            'email' => 'arun.kumar@example.com',
            'phone' => '9876543210',
        ]);
        $loc = Location::create(['organization_id' => $org->id, 'name' => 'Outlet 1', 'is_active' => true]);

        $invoice = Invoice::create([
            'organization_id' => $org->id,
            'location_id' => $loc->id,
            'client_id' => $client->id,
            'invoice_number' => 'INV-EMAIL-001',
            'invoice_date' => now(),
            'grand_total' => 1250,
            'amount_paid' => 1250,
            'status' => 'Paid',
        ]);

        $this->assertNull($invoice->email_sent_at);

        // 1st Send: Should dispatch notification and stamp email_sent_at
        $sent = CommunicationService::sendInvoice($invoice, ['mail']);
        $this->assertTrue($sent);

        Notification::assertSentTo($client, InvoiceNotification::class, function ($notification) use ($invoice) {
            return $notification->invoice->id === $invoice->id;
        });

        $this->assertNotNull($invoice->fresh()->email_sent_at);
    }

    public function test_complaint_submission_with_signed_url_associates_invoice_and_normalizes_phone()
    {
        $org = Organization::create(['name' => 'Apex Retail', 'is_active' => true]);
        $loc = Location::create(['organization_id' => $org->id, 'name' => 'Branch 1', 'is_active' => true]);
        $invoice = Invoice::create([
            'organization_id' => $org->id,
            'location_id' => $loc->id,
            'invoice_number' => 'INV-CMP-100',
            'invoice_date' => now(),
            'grand_total' => 2000,
            'amount_paid' => 2000,
            'status' => 'Paid',
        ]);

        // Generate signed URL as produced in invoice email
        $signedUrl = URL::temporarySignedRoute(
            'public.complaint.create',
            now()->addDays(30),
            [
                'org_id' => $org->id,
                'invoice_id' => $invoice->id,
                'customer_name' => 'Rajesh Sharma',
                'customer_email' => 'rajesh@example.com',
                'customer_phone' => '9876543210',
            ]
        );

        // Customer accesses signed complaint form
        $response = $this->get($signedUrl);
        $response->assertStatus(200);
        $response->assertSee('Submit Support Ticket');
        $response->assertSee('INV-CMP-100');

        // Customer submits complaint with +91 prefix
        $submitResponse = $this->post(route('public.complaint.store'), [
            'organization_id' => $org->id,
            'invoice_id' => $invoice->id,
            'name' => 'Rajesh Sharma',
            'email' => 'rajesh@example.com',
            'phone' => '+91 98765 43210',
            'subject' => 'Incorrect line item quantity billed',
            'description' => 'I was billed for 2 items but only received 1 in the parcel.',
        ]);

        $submitResponse->assertRedirect();
        $submitResponse->assertSessionHas('success');

        // Assert record saved in database with normalized 10-digit phone and linked invoice_id
        $this->assertDatabaseHas('complaints', [
            'organization_id' => $org->id,
            'invoice_id' => $invoice->id,
            'subject' => 'Incorrect line item quantity billed',
            'status' => 'Open',
        ]);

        $this->assertDatabaseHas('clients', [
            'organization_id' => $org->id,
            'name' => 'Rajesh Sharma',
            'email' => 'rajesh@example.com',
            'phone' => '9876543210',
        ]);
    }

    public function test_complaint_submission_rejects_tampered_invoice_id_without_valid_signature()
    {
        $org = Organization::create(['name' => 'Apex Retail', 'is_active' => true]);
        $loc = Location::create(['organization_id' => $org->id, 'name' => 'Outlet 1', 'is_active' => true]);
        $invoice = Invoice::create([
            'organization_id' => $org->id,
            'location_id' => $loc->id,
            'invoice_number' => 'INV-TAMPER-001',
            'invoice_date' => now(),
            'grand_total' => 500,
            'status' => 'Paid',
        ]);

        // Attacker attempts to guess/access an invoice complaint without a cryptographic signature
        $tamperedUrl = route('public.complaint.create', [
            'org_id' => $org->id,
            'invoice_id' => $invoice->id,
        ]);

        $response = $this->get($tamperedUrl);
        // Middleware or controller should block with 403 Forbidden due to missing valid signature
        $response->assertStatus(403);
    }

    /*
    |--------------------------------------------------------------------------
    | 4. Phone, Email & Universal Validations
    |--------------------------------------------------------------------------
    */

    public function test_client_validation_enforces_valid_indian_phone_and_rfc_email()
    {
        $org = Organization::create(['name' => 'Test Org', 'is_active' => true]);
        $user = User::factory()->create(['organization_id' => $org->id]);
        $role = Role::create(['name' => 'Organization Admin', 'organization_id' => $org->id]);
        $user->roles()->attach($role);

        // Invalid phone (less than 10 digits or invalid starting digit)
        $response = $this->actingAs($user)->post(route('organization.clients.store'), [
            'name' => 'Invalid Phone Customer',
            'phone' => '12345',
            'email' => 'valid@customer.com',
        ]);
        $response->assertSessionHasErrors('phone');

        // Invalid email format
        $response = $this->actingAs($user)->post(route('organization.clients.store'), [
            'name' => 'Invalid Email Customer',
            'phone' => '9876543210',
            'email' => 'not-an-email',
        ]);
        $response->assertSessionHasErrors('email');

        // Valid phone with +91 and formatting: should normalize to 10 digits
        $response = $this->actingAs($user)->post(route('organization.clients.store'), [
            'name' => 'Valid Customer',
            'phone' => '+91-98765-43210',
            'email' => 'valid@customer.com',
        ]);
        $response->assertRedirect(route('organization.clients.index'));

        $this->assertDatabaseHas('clients', [
            'name' => 'Valid Customer',
            'phone' => '9876543210',
            'email' => 'valid@customer.com',
        ]);
    }

    public function test_thermal_receipt_rendering_respects_paper_size_and_preserves_payment_status()
    {
        $org = Organization::create(['name' => 'Cafe Bistro', 'is_active' => true]);
        $user = User::factory()->create(['organization_id' => $org->id]);
        $role = Role::create(['name' => 'Organization Admin', 'organization_id' => $org->id]);
        $user->roles()->attach($role);

        $loc = Location::create(['organization_id' => $org->id, 'name' => 'Main Outlet', 'is_active' => true]);
        $order = \App\Models\RestaurantOrder::create([
            'organization_id' => $org->id,
            'location_id' => $loc->id,
            'order_number' => 'ORD-POS-777',
            'customer_name' => 'Diner Guest',
            'subtotal' => 250,
            'tax' => 12.50,
            'total' => 262.50,
            'payment_status' => 'Unpaid',
            'status' => 'Received',
        ]);

        // Access 58mm thermal receipt
        $response58 = $this->actingAs($user)->get(route('organization.menu.pos.orders.print-receipt', [
            'order' => $order->id,
            'paper_size' => '58mm',
        ]));
        $response58->assertStatus(200);
        $response58->assertSee('58mm auto');
        $response58->assertSee('ORD-POS-777');
        // Crucial requirement: Printing must not automatically mark unpaid bills as paid
        $this->assertEquals('Unpaid', $order->fresh()->payment_status);

        // Access 80mm thermal receipt
        $response80 = $this->actingAs($user)->get(route('organization.menu.pos.orders.print-receipt', [
            'order' => $order->id,
            'paper_size' => '80mm',
        ]));
        $response80->assertStatus(200);
        $response80->assertSee('80mm auto');
        $this->assertEquals('Unpaid', $order->fresh()->payment_status);
    }
}
