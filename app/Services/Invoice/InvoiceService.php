<?php

namespace App\Services\Invoice;

use App\Models\Booking;
use App\Models\Invoice;
use App\Exceptions\Invoice\PendingInvoiceAlreadyExistsException;
use App\Exceptions\Invoice\NotAllowedInvoiceStatusException;
use App\Exceptions\Invoice\InvoiceDiscountInvalidException;
use App\Exceptions\Booking\NotAllowedBookingStatusException;
use App\Enums\BookingStatus;
use App\Enums\InvoiceStatus;
use Carbon\Carbon;

class InvoiceService
{
    /**
     * Create an invoice for the given booking.
     *
     * TODO: implement real invoice creation (payment/billing integration).
     * In tests this service is mocked.
     */
    public function create(Booking $booking, string $amount, int $discount = 0): Invoice {
        if ($discount > 50 || $discount < 0) {
            throw new InvoiceDiscountInvalidException;
        }

        if (!in_array($booking->status->value, BookingStatus::blockingStatuses(), true)) {
            throw new NotAllowedBookingStatusException;
        }

        $pendingInvoice = Invoice::where([
            ['booking_id', '=', $booking->id],
            ['status', '=', InvoiceStatus::Pending->value],
        ])->exists();

        if ($pendingInvoice) {
            throw new PendingInvoiceAlreadyExistsException;
        }

        $discountFactor = bcdiv((string)$discount, '100', 4); // e.g., "15" -> "0.1500"
        $multiplier = bcsub('1', $discountFactor, 4); // e.g., "1 - 0.1500" -> "0.8500"
        $total = bcmul($amount, $multiplier, 2); // e.g., "100.50" * "0.8500" -> "85.42"
        $expiresAt = now()->addMinutes(15);
        $invoice = Invoice::create([
            'booking_id' => $booking->id,
            'discount' => $discount,
            'amount' => $amount,
            'status' => InvoiceStatus::Pending,
            'expires_at' => $expiresAt,
            'total' => $total,
        ]);

        return $invoice;
    }

    public function cancel(Invoice $invoice): Invoice {
        if ($invoice->status !== InvoiceStatus::Pending) {
            throw new NotAllowedInvoiceStatusException;
        }

        $invoice->status = InvoiceStatus::Cancelled;

        $invoice->save();

        return $invoice;
    }

    public function pay(Invoice $invoice): Invoice {
        if ($invoice->status !== InvoiceStatus::Pending) {
            throw new NotAllowedInvoiceStatusException;
        }

        $invoice->status = InvoiceStatus::Paid;
        $invoice->paid_at = Carbon::now();

        $invoice->save();

        return $invoice;
    }

    public function refund(Invoice $invoice): Invoice {
        if ($invoice->status !== InvoiceStatus::Paid) {
            throw new NotAllowedInvoiceStatusException;
        }

        $invoice->status = InvoiceStatus::Refunded;

        $invoice->save();

        return $invoice;
    }
}
