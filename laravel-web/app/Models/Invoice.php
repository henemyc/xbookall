<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    protected $fillable = [
        'invoice_id',
        'user_id',
        'invoice_date',
        'invoice_due_date',
        'status',
        'notes',
        'parent_id',
    ];

    protected $casts = [
        'invoice_date' => 'date',
        'invoice_due_date' => 'date',
    ];

    // ── Relationships ─────────────────────────────────────────────

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function items()
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function payments()
    {
        return $this->hasMany(InvoicePayment::class);
    }

    // ── Scopes ────────────────────────────────────────────────────

    public function scopeForGym($query, int $parentId)
    {
        return $query->where('parent_id', $parentId);
    }

    public function scopePaid($query)
    {
        return $query->where('status', 'paid');
    }

    public function scopePartial($query)
    {
        return $query->where('status', 'partial');
    }

    public function scopeUnpaid($query)
    {
        return $query->where('status', 'unpaid');
    }

    // ── Accessors ─────────────────────────────────────────────────

    public function getTotalAmountAttribute(): float
    {
        return $this->items()->sum('amount');
    }

    public function getPaidAmountAttribute(): float
    {
        return $this->payments()->sum('amount');
    }

    public function getDueAmountAttribute(): float
    {
        return $this->total_amount - $this->paid_amount;
    }

    public function getStatusColorsAttribute(): array
    {
        return match($this->status) {
            'paid' => ['bg' => 0xFFECFDF5, 'text' => 0xFF10B981, 'label' => 'PAID'],
            'partial' => ['bg' => 0xFFFEF3C7, 'text' => 0xFFD97706, 'label' => 'PARTIAL'],
            default => ['bg' => 0xFFFEF2F2, 'text' => 0xFFEF4444, 'label' => 'UNPAID'],
        };
    }
}
