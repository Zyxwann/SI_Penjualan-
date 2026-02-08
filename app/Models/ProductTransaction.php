<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class ProductTransaction extends Model
{
    // Trait untuk factory & soft delete
    use HasFactory, SoftDeletes;

    /**
     * Field yang boleh diisi secara mass assignment
     */
    protected $fillable = [
        'name',
        'phone',
        'email',
        'booking_trx_id',
        'city',
        'post_code',
        'address',
        'quantity',
        'sub_total_amount',
        'grand_total_amount',
        'discount_amount',
        'is_paid',
        'produk_id',
        'shoe_size',
        'promo_code_id',
        'proof',
    ];

    /**
     * Generate booking transaction ID yang unik
     */
    public static function generateUniqueTrxId(): string
    {
        $prefix = 'TJH';

        do {
            $randomString = $prefix . mt_rand(10001, 99999);
        } while (
            self::where('booking_trx_id', $randomString)->exists()
        );

        return $randomString;
    }

    public function getPromoDiscountAmountAttribute(): int
    {
        if (! $this->promoCode) {
            return 0;
        }

        $subTotal = $this->sub_total_amount * $this->quantity;
        $discount = 0;

        if ($this->promoCode->discount_percent) {
            $discount += ($subTotal * $this->promoCode->discount_percent / 100);
        }

        if ($this->promoCode->discount_amount) {
            $discount += $this->promoCode->discount_amount;
        }

        return (int) $discount;
    }

    protected static function booted()
    {
        // =====================
        // CREATE TRANSACTION
        // =====================
        static::created(function (ProductTransaction $transaction) {
            DB::transaction(function () use ($transaction) {

                $produk = Produk::lockForUpdate()->find($transaction->produk_id);

                if (! $produk) {
                    throw new \Exception('Produk tidak ditemukan');
                }

                if ($produk->stock < $transaction->quantity) {
                    throw new \Exception('Stok produk tidak mencukupi');
                }

                // Kurangi stok saat create
                $produk->decrement('stock', $transaction->quantity);
            });
        });

        // =====================
        // UPDATE TRANSACTION
        // =====================
        static::updating(function (ProductTransaction $transaction) {
            DB::transaction(function () use ($transaction) {

                // Data lama
                $oldQty = $transaction->getOriginal('quantity');
                $oldProductId = $transaction->getOriginal('produk_id');

                // Data baru
                $newQty = $transaction->quantity;
                $newProductId = $transaction->produk_id;

                // Jika tidak ada perubahan qty & produk → stop
                if ($oldQty == $newQty && $oldProductId == $newProductId) {
                    return;
                }

                // 1️⃣ Kembalikan stok produk lama
                Produk::lockForUpdate()
                    ->where('id', $oldProductId)
                    ->increment('stock', $oldQty);

                // 2️⃣ Ambil produk baru
                $produkBaru = Produk::lockForUpdate()->find($newProductId);

                if (! $produkBaru) {
                    throw new \Exception('Produk tidak ditemukan');
                }

                // 3️⃣ Validasi stok produk baru
                if ($produkBaru->stock < $newQty) {
                    throw new \Exception('Stok produk tidak mencukupi');
                }

                // 4️⃣ Kurangi stok produk baru
                $produkBaru->decrement('stock', $newQty);
            });
        });
    }
   
    /**
     * Relasi ke tabel produk
     * Satu transaksi milik satu produk
     */
    public function produk(): BelongsTo
    {
        return $this->belongsTo(Produk::class, 'produk_id');
    }

    /**
     * Relasi ke tabel promo_codes
     * Satu transaksi bisa memiliki satu promo code
     */
    public function promoCode(): BelongsTo
    {
        return $this->belongsTo(PromoCode::class, 'promo_code_id');
    }
}
