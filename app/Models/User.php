<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens as SanctumHasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use SanctumHasApiTokens,HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */

    protected $fillable =
    [
        'primer_nombre',
        'segundo_nombre',
        'primer_apellido',
        'segundo_apellido',
        'telefono',
        'correo',
        'password',
        'cuenta_bancaria',
        'id_rol',
        'profile_url'
    ];

    protected $allowInclude = [
        'rol',
        'branches',
        'branches.user',
        'products',
        'service',
        'service.user'
    ];

    protected $allowFilter = [
        'id',
        'id_rol',
        'correo',
        'busqueda'
    ];
    protected $allowSort = [
        'id',
        'id_rol',
        'telefono',
        'correo'
    ];
    public function rol()
    {
        return $this->belongsTo(Rol::class,'id_rol');
    }

    public function branches()
    {
        return $this->hasMany(Branch::class,'id_usuario');
    }

    public function products()
    {
        return $this->hasMany(Product::class,'id_usuario');
    }

    public function service(){
        return $this->hasOne(Service::class, 'id_usuario');
    }

    public function images()
    {
        return $this->morphMany(\App\Models\Image::class, 'imageable');
    }

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    public function scopeIncluded(Builder $query){
        $param = request('included');
        if(empty($this->allowInclude) || empty($param)){
            return $query;
        }

        $relations = explode(',', $param);

        $allowInclude = collect($this->allowInclude);

        foreach($relations as $key => $relation){
            if(!$allowInclude->contains($relation)){
                unset($relations[$key]);
            }
        }

        $query->with($relations);
    }

    public function scopeFilter(Builder $query){
        $filters = request('filter');
        if(empty($this->allowFilter) || empty($filters)){
            return $query;
        }

        $allowFilter = collect($this->allowFilter);

        foreach($filters as $filter => $value){
            if($filter === 'busqueda') {
                $query->where(function($q) use ($value) {
                    $q->where('primer_nombre', 'like', "%$value%")
                      ->orWhere('segundo_nombre', 'like', "%$value%")
                      ->orWhere('primer_apellido', 'like', "%$value%")
                      ->orWhere('segundo_apellido', 'like', "%$value%")
                      ->orWhere('correo', 'like', "%$value%")
                      ->orWhere('telefono', 'like', "%$value%") ;
                });
            } elseif($allowFilter->contains($filter)) {
                $query->where($filter, 'LIKE', "%$value%");
            }
        }

        return $query;
    }

    public function scopeSort(Builder $query){
        if(empty($this->allowSort) || empty(request('sort'))){
            return $query;
        }

        $sortFields = explode(',', request('sort'));
        $allowSort = collect($this->allowSort);

        foreach($sortFields as $sortField){
            $direction = 'asc';
            if(substr($sortField, 0, 1) == '-'){

                $direction = 'desc';
                $sortField = substr($sortField, 1);
            }
            if($allowSort->contains($sortField)){
                $query->orderBy($sortField, $direction);
            }
        }

        return $query;
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
