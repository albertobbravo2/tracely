<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CompanyController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * El filtro es opcional: sin `search` devuelve la lista completa, igual
     * que antes. `ilike` es de Postgres, que es la base de este proyecto.
     */
    public function index(Request $request): JsonResponse
    {
        $companies = Company::query()
            ->when(
                $request->string('search')->trim()->value(),
                fn ($query, string $search) => $query->where(
                    fn ($query) => $query->where('name', 'ilike', "%{$search}%")
                        ->orWhere('slug', 'ilike', "%{$search}%")
                        ->orWhere('contact_email', 'ilike', "%{$search}%"),
                ),
            )
            ->latest('id')
            ->paginate(15);

        return response()->json($companies);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'unique:companies,slug'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            'is_active' => ['boolean'],
        ]);

        return response()->json(Company::create($data), 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Company $company): JsonResponse
    {
        return response()->json($company);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Company $company): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'slug' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                Rule::unique('companies', 'slug')->ignore($company),
            ],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            'is_active' => ['boolean'],
        ]);

        $company->update($data);

        return response()->json($company);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Company $company): JsonResponse
    {
        $company->delete();

        return response()->json(status: 204);
    }
}
