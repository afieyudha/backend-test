<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ReceivedRepayment extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'received_repayments';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'loan_id',
        'amount',
        'currency_code',
        'received_at',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'received_at' => 'datetime:Y-m-d H:i:s',
        'amount' => 'decimal:2',
    ];

    /**
     * A Received Repayment belongs to a Loan
     *
     * @return BelongsTo
     */
    public function loan()
    {
        return $this->belongsTo(Loan::class, 'loan_id');
    }
}