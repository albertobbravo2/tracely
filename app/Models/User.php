<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'email', 'password', 'company_id'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

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

    /**
     * Envíos vinculados a este usuario: su lista de "mis pedidos".
     *
     * No incluye los envíos que haya registrado como agente — esos cuelgan de
     * `sender_id`, que es una relación distinta.
     *
     * @return BelongsToMany<Shipment, $this>
     */
    public function shipments(): BelongsToMany
    {
        return $this->belongsToMany(Shipment::class)->withTimestamps();
    }

    /**
     * Empresa a la que pertenece este usuario.
     *
     * @return BelongsTo<Company, $this>
     */
    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Roles que dan entrada al backoffice: los mismos que protegen el bloque
     * `backoffice.` en routes/web.php.
     *
     * @var list<string>
     */
    public const BACKOFFICE_ROLES = ['agente', 'administrador', 'superadministrador'];

    /**
     * Si la empresa a la que pertenece esta cuenta sigue activa.
     *
     * Una cuenta sin empresa —los clientes finales, que se registran por la
     * web y nunca llevan `company_id`— no tiene nada que desactivar, así que
     * cuenta como activa. El superadministrador tampoco se ve afectado: es
     * el único rol que puede reactivar una empresa, y si se bloqueara a sí
     * mismo al desactivar la suya nadie podría deshacerlo.
     */
    public function belongsToActiveCompany(): bool
    {
        if ($this->hasRole('superadministrador')) {
            return true;
        }

        return $this->company === null || $this->company->is_active;
    }

    /**
     * Si esta cuenta puede entrar al backoffice.
     *
     * Dos condiciones: tener uno de los roles de empleado y que su empresa
     * no esté desactivada. Desactivar una empresa deja fuera del backoffice
     * a toda su plantilla de golpe, sin tocarle los roles.
     */
    public function canAccessBackoffice(): bool
    {
        return $this->hasAnyRole(self::BACKOFFICE_ROLES) && $this->belongsToActiveCompany();
    }

    /**
     * Get the user's initials
     */
    public function initials(): string
    {
        $initials = Str::initials($this->name, true);

        return Str::length($initials) > 1
            ? Str::substr($initials, 0, 1).Str::substr($initials, -1)
            : $initials;
    }
}
