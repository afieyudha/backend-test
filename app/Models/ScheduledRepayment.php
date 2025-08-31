<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ScheduledRepayment extends Model
{
    use HasFactory, SoftDeletes;

    public const STATUS_DUE = 'due';
    public const STATUS_PARTIAL = 'partial';
    public const STATUS_REPAID = 'repaid';

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'scheduled_repayments';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'loan_id',
        'amount',
        'outstanding_amount',
        'currency_code',
        'due_date',
        'status',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'due_date' => 'date:Y-m-d',
        'amount' => 'decimal:2',
        'outstanding_amount' => 'decimal:2',
    ];

    /**
     * A Scheduled Repayment belongs to a Loan
     *
     * @return BelongsTo
     */
    public function loan()
    {
        return $this->belongsTo(Loan::class, 'loan_id');
    }

    /**
     * A Scheduled Repayment has many Received Repayments
     *
     * @return HasMany
     */
    public function receivedRepayments()
    {
        return $this->hasMany(ReceivedRepayment::class, 'scheduled_repayment_id');
    }
}