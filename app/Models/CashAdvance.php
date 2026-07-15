<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CashAdvance extends Model
{
    protected $fillable = [
        'user_id',
        'date',
        'amount',
        'notes',
        'admin_id',
    ];

    protected $casts = [
        'date' => 'date',
        'amount' => 'decimal:2',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function admin()
    {
        return $this->belongsTo(User::class, 'admin_id');
    }
}
