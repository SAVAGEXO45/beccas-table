<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Reservation extends Model
{
    public const STATUSES = ['pending', 'approved', 'rejected', 'cancelled'];
    protected $fillable = ['code', 'name', 'phone', 'email', 'date', 'time', 'ends_at', 'guests', 'requests', 'status'];
}

