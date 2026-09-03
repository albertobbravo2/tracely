<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class UserController extends Controller
{
    /**
     * Jerarquía de roles de empleado, de menor a mayor rango.
     *
     * @var list<string>
     */
    private const ROLE_HIERARCHY = ['agente', 'administrador', 'superadministrador'];

    /**
     * Rango del usuario dentro de {@see ROLE_HIERARCHY}: 0 si no tiene
     * ninguno de esos roles, o la posición (1-indexada) del más alto que sí
     * tiene.
     */
    private function roleLevel(User $user): int
    {
        $level = 0;

        foreach (self::ROLE_HIERARCHY as $index => $role) {
            if ($user->hasRole($role)) {
                $level = $index + 1;
            }
        }

        return $level;
    }

    /**
     * Roles que quien hace la petición puede asignar a otra cuenta: el suyo
     * y los que estén por debajo. Un agente puede fichar a otro agente pero
     * nunca a un administrador; un administrador nunca puede crear ni
     * ascender a nadie a superadministrador.
     *
     * @return list<string>
     */
    private function assignableRoles(User $actor): array
    {
        return array_slice(self::ROLE_HIERARCHY, 0, $this->roleLevel($actor));
    }

    /**
     * Display a listing of the resource.
     *
     * Los roles vienen cargados porque el listado del backoffice muestra una
     * columna con ellos y, sin el eager load, cada fila sería una consulta.
     * `ilike` es de Postgres, que es la base de este proyecto.
     */
    public function index(Request $request): JsonResponse
    {
        $users = User::query()
            ->with('roles:id,name')
            ->when(
                $request->string('search')->trim()->value(),
                fn ($query, string $search) => $query->where(
                    fn ($query) => $query->where('name', 'ilike', "%{$search}%")
                        ->orWhere('email', 'ilike', "%{$search}%"),
                ),
            )
            ->latest('id')
            ->paginate(15);

        return response()->json($users);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $actor = $request->user();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', Password::defaults()],
            'company_id' => ['nullable', 'integer', 'exists:companies,id'],
            'role' => ['nullable', 'string', Rule::in($this->assignableRoles($actor))],
        ]);

        $role = Arr::pull($data, 'role');

        // Quien no es superadministrador solo gestiona su propia empresa: la
        // cuenta que crea hereda su company_id pase lo que pase en la
        // petición, en vez de confiar en que el cliente no la falsee.
        if (! $actor->hasRole('superadministrador')) {
            $data['company_id'] = $actor->company_id;
        }

        $user = User::create($data);

        if ($role !== null) {
            $user->assignRole($role);
        }

        return response()->json($user->load('roles:id,name'), 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(User $user): JsonResponse
    {
        return response()->json($user->load('roles:id,name'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, User $user): JsonResponse
    {
        $actor = $request->user();

        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => [
                'sometimes',
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($user),
            ],
            'password' => ['sometimes', 'required', Password::defaults()],
            'company_id' => ['nullable', 'integer', 'exists:companies,id'],
            'role' => ['sometimes', 'nullable', 'string', Rule::in($this->assignableRoles($actor))],
        ]);

        $changesRole = Arr::exists($data, 'role');

        // Aunque el nuevo rol pedido esté a su altura o por debajo, nadie
        // que no sea superadministrador puede tocar el rol de una cuenta que
        // ya está por encima suyo: si no, un administrador podría degradar a
        // un superadministrador con solo pedir "administrador".
        if ($changesRole && $this->roleLevel($user) > $this->roleLevel($actor)) {
            throw ValidationException::withMessages([
                'role' => __('No puedes modificar el rol de una cuenta con un rango superior al tuyo.'),
            ]);
        }

        $role = Arr::pull($data, 'role');

        if (! $actor->hasRole('superadministrador')) {
            $data['company_id'] = $actor->company_id;
        }

        $user->update($data);

        if ($changesRole) {
            $user->syncRoles($role === null ? [] : [$role]);
        }

        return response()->json($user->load('roles:id,name'));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(User $user): JsonResponse
    {
        $user->delete();

        return response()->json(status: 204);
    }

    public function shipments(Request $request): JsonResponse
    {
        $user = User::findOrFail($request->route('user'));

        return response()->json($user->shipments()->paginate(15));
    }

    public function company(User $user): JsonResponse
    {
        return response()->json($user->company);
    }

    public function myshipments(Request $request): JsonResponse
    {
        $user = $request->user('sanctum');

        // El histórico viaja con la lista, igual que en `shipments.show` y con
        // el mismo orden: el dashboard pinta la línea de tiempo de cada envío,
        // y sin esto tendría que pedir cada uno por separado.
        return response()->json(
            $user->shipments()
                ->with(['histories' => fn ($query) => $query->orderBy('recorded_at')])
                ->paginate(15)
        );
    }
}
