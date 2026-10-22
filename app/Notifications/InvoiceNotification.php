<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;
use App\Models\Invoice;
use App\Services\DocumentService;

class InvoiceNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public $invoice;
    public $channels;

    public function __construct(Invoice $invoice, array $channels = ['mail'])
    {
        $this->invoice = $invoice;
        $this->channels = $channels;
    }

    public function via($notifiable)
    {
        $resolvedChannels = [];
        foreach ($this->channels as $channel) {
            if ($channel === 'whatsapp') {
                $resolvedChannels[] = \App\Notifications\Channels\WhatsAppChannel::class;
            } else {
                $resolvedChannels[] = $channel;
            }
        }
        return $resolvedChannels;
    }

    public function toMail($notifiable)
    {
        $this->invoice->loadMissing(['organization', 'client', 'items']);
        $pdf = DocumentService::generateInvoicePdf($this->invoice);
        
        // Cryptographically signed URL with expiration (30 days) to prevent tampering or predictable ID enumeration
        $complaintUrl = URL::temporarySignedRoute(
            'public.complaint.create',
            now()->addDays(30),
            [
                'org_id' => $this->invoice->organization_id,
                'invoice_id' => $this->invoice->id,
            ]
        );

        $orgName = $this->invoice->organization->name ?? 'Our Company';
        $customerName = $notifiable->name ?? 'Valued Customer';
        $invoiceDate = $this->invoice->invoice_date ? $this->invoice->invoice_date->format('d M, Y') : now()->format('d M, Y');

        $mail = (new MailMessage)
            ->subject("Invoice #{$this->invoice->invoice_number} from {$orgName}")
            ->greeting("Hello {$customerName},")
            ->line("Thank you for your business. Here is the summary of your finalized invoice from {$orgName}:")
            ->line("**Invoice Number:** #{$this->invoice->invoice_number}")
            ->line("**Invoice Date:** {$invoiceDate}")
            ->line("**Payment Status:** {$this->invoice->status}");

        if ($this->invoice->organization) {
            $orgDetails = "**Organization:** {$orgName}";
            if ($this->invoice->organization->phone) {
                $orgDetails .= " | Ph: {$this->invoice->organization->phone}";
            }
            if ($this->invoice->organization->gst_number) {
                $orgDetails .= " | GSTIN: {$this->invoice->organization->gst_number}";
            }
            $mail->line($orgDetails);
        }

        $mail->line("---");
        $mail->line("**Purchased Items:**");
        foreach ($this->invoice->items as $item) {
            $itemName = $item->product_name_snapshot ?: 'Item';
            $mail->line("• {$itemName} × {$item->quantity} @ ₹" . number_format($item->unit_price, 2) . " = ₹" . number_format($item->total, 2));
        }

        $mail->line("---");
        $mail->line("**Subtotal:** ₹" . number_format($this->invoice->subtotal, 2));

        if ((float)$this->invoice->effective_cgst > 0) {
            $mail->line("**CGST:** ₹" . number_format($this->invoice->effective_cgst, 2));
        }
        if ((float)$this->invoice->effective_sgst > 0) {
            $mail->line("**SGST:** ₹" . number_format($this->invoice->effective_sgst, 2));
        }
        if ((float)$this->invoice->discount > 0) {
            $mail->line("**Discount:** -₹" . number_format($this->invoice->discount, 2));
        }

        $mail->line("**Grand Total:** ₹" . number_format($this->invoice->grand_total, 2));
        $mail->line("**Amount Paid:** ₹" . number_format($this->invoice->amount_paid, 2));
        $mail->line("**Balance Due:** ₹" . number_format($this->invoice->amount_due, 2));

        $mail->action('Submit a Complaint / Support Request', $complaintUrl);
        $mail->line('If you have any feedback, service questions, or issues regarding this invoice, please click "Submit a Complaint" above.');

        $mail->attachData($pdf->output(), "invoice_{$this->invoice->invoice_number}.pdf", [
            'mime' => 'application/pdf',
        ]);

        return $mail;
    }

    public function toWhatsApp($notifiable)
    {
        $this->invoice->loadMissing(['organization']);
        $balance = number_format($this->invoice->amount_due, 2);
        
        $complaintUrl = URL::temporarySignedRoute(
            'public.complaint.create',
            now()->addDays(30),
            [
                'org_id' => $this->invoice->organization_id,
                'invoice_id' => $this->invoice->id,
            ]
        );
        
        return "Hello {$notifiable->name}, your invoice #{$this->invoice->invoice_number} for {$this->invoice->organization->name} has been finalized.\n" .
               "Total: ₹" . number_format($this->invoice->grand_total, 2) . "\n" .
               "Balance Due: ₹{$balance}\n" .
               "Status: {$this->invoice->status}\n\n" .
               "Submit a Complaint / Support Ticket:\n{$complaintUrl}\n\n" .
               "Thank you!";
    }
}
