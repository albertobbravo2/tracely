<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserController extends Controller
{
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
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', Password::defaults()],
            'company_id' => ['nullable', 'integer', 'exists:companies,id'],
        ]);

        return response()->json(User::create($data), 201);
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
        ]);

        $user->update($data);

        return response()->json($user);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(User $user): JsonResponse
    {
        $user->delete();

        return response()->json(status: 204);
    }
}
