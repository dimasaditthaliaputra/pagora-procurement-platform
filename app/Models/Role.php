<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    use HasFactory;

    protected $table = 'role';

    public $timestamps = false;

    protected $fillable = [
        'nama_role',
        'deskripsi',
    ];

    /**
     * Relasi ke users yang memiliki role ini.
     */
    public function users()
    {
        return $this->hasMany(User::class, 'role_id');
    }
}
