<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CategorieDepense extends Model
{
    protected $table = 'categories_depenses';

    protected $fillable = ['libelle', 'description'];

    public function depenses(): HasMany
    {
        return $this->hasMany(Depense::class);
    }
}
