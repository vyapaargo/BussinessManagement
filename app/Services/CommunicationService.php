<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Payroll;
use App\Models\RestaurantOrder;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Log;
use App\Notifications\InvoiceNotification;
use App\Notifications\PayslipNotification;
use App\Notifications\RestaurantOrderNotification;

class CommunicationService
{
    /**
     * Send Invoice Notification (Email, WhatsApp)
     */
    public static function sendInvoice(Invoice $invoice, array $channels = ['mail'])
    {
        // For walk-in customers with no client record, skip gracefully
        if (!$invoice->client) {
            Log::info("Invoice #{$invoice->invoice_number}: No registered customer record, skipping notification.");
            return false;
        }

        $email = trim($invoice->client->email ?? '');

        // Validate email format if email channel is requested
        if (in_array('mail', $channels)) {
            if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                Log::warning("Invoice #{$invoice->invoice_number}: Customer email '{$email}' is invalid or empty. Skipping email delivery.");
                $channels = array_values(array_diff($channels, ['mail']));
            }
        }

        if (empty($channels)) {
            return false;
        }

        try {
            $invoice->client->notify(new InvoiceNotification($invoice, $channels));
            
            // Mark email as sent to prevent duplicate sending on reloads
            if (in_array('mail', $channels)) {
                $invoice->updateQuietly(['email_sent_at' => now()]);
            }
            
            Log::info("Invoice #{$invoice->invoice_number}: Notification dispatched successfully to customer '{$invoice->client->name}'.");
            return true;
        } catch (\Throwable $e) {
            Log::error("Invoice #{$invoice->invoice_number}: Notification delivery failed to '{$email}'. Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Send Payslip Notification (Email)
     */
    public static function sendPayslip(Payroll $payroll, array $channels = ['mail'])
    {
        if (!$payroll->employee || !$payroll->employee->user) {
            return false;
        }

        $email = $payroll->employee->email ?? $payroll->employee->user->email;
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Log::warning("Payroll #{$payroll->id}: Employee email is invalid. Skipping payslip email.");
            return false;
        }

        try {
            Notification::route('mail', $email)
                ->notify(new PayslipNotification($payroll, $channels));
            return true;
        } catch (\Throwable $e) {
            Log::error("Payroll #{$payroll->id}: Payslip delivery failed to '{$email}'. Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Send Restaurant Order Notification
     */
    public static function sendRestaurantOrderNotification(RestaurantOrder $order, $type = 'status_update')
    {
        if (!$order->customer_phone && !$order->customer_email) {
            return false;
        }
        
        $route = Notification::route('WhatsApp', $order->customer_phone);
        
        if (!empty($order->customer_email) && filter_var($order->customer_email, FILTER_VALIDATE_EMAIL)) {
            $route->route('mail', $order->customer_email);
        }

        try {
            $route->notify(new RestaurantOrderNotification($order, $type));
            return true;
        } catch (\Throwable $e) {
            Log::error("Order #{$order->order_number}: Notification failed. Error: " . $e->getMessage());
            return false;
        }
    }
}
