<?php

namespace App\Models;

use Database\Factories\TicketFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $order_id
 * @property int $ticket_type_id
 * @property string $holder_name
 * @property string|null $holder_email
 * @property string $qr_payload
 * @property string $signature
 * @property string|null $qr_image_path
 * @property Carbon|null $scanned_at
 * @property int|null $scanned_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['order_id', 'ticket_type_id', 'holder_name', 'holder_email', 'qr_payload', 'signature', 'qr_image_path', 'scanned_at', 'scanned_by'])]
class Ticket extends Model
{
    /** @use HasFactory<TicketFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'scanned_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * @return BelongsTo<TicketType, $this>
     */
    public function ticketType(): BelongsTo
    {
        return $this->belongsTo(TicketType::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function scannedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'scanned_by');
    }

    /**
     * The full signed QR string ("payload.signature") — the single source
     * of truth for how these two columns combine. Every place that needs
     * to encode, re-render, or hand out this ticket's QR content (the
     * downloadable PDF, the scanner API's offline-download endpoint, …)
     * must go through this method rather than concatenating the columns
     * itself: qr_payload alone has already shipped as a bug twice.
     */
    public function fullToken(): string
    {
        return "{$this->qr_payload}.{$this->signature}";
    }
}
