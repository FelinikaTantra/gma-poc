<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;

class UserRoleController extends Controller
{
    // User CRUD
    public function index()
    {
        return response()->json(User::with('role')->get());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:6',
            'role_id' => 'nullable|exists:roles,id',
        ]);

        // Fallback for default role column for safety
        $roleName = 'admin';
        if (!empty($validated['role_id'])) {
            $role = Role::find($validated['role_id']);
            if ($role) {
                $roleName = strtolower(str_replace(' ', '_', $role->name));
            }
        }

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role_id' => $validated['role_id'] ?? null,
            'role' => $roleName,
            'company_id' => Auth::user()?->company_id,
        ]);

        return response()->json($user->load('role'), 201);
    }

    public function update(Request $request, $id)
    {
        $user = User::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email,' . $user->id,
            'password' => 'nullable|string|min:6',
            'role_id' => 'nullable|exists:roles,id',
        ]);

        $updateData = [
            'name' => $validated['name'],
            'email' => $validated['email'],
            'role_id' => $validated['role_id'] ?? null,
        ];

        if (!empty($validated['role_id'])) {
            $role = Role::find($validated['role_id']);
            if ($role) {
                $updateData['role'] = strtolower(str_replace(' ', '_', $role->name));
            }
        }

        if (!empty($validated['password'])) {
            $updateData['password'] = Hash::make($validated['password']);
        }

        $user->update($updateData);

        return response()->json($user->load('role'));
    }

    public function destroy($id)
    {
        $user = User::findOrFail($id);

        // Prevent self-deletion
        if (Auth::id() === $user->id) {
            return response()->json(['message' => 'You cannot delete your own account'], 400);
        }

        $user->delete();
        return response()->json(['message' => 'User deleted successfully']);
    }

    // Role Methods
    public function rolesIndex()
    {
        return response()->json(Role::all());
    }

    public function rolesStore(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $role = Role::create([
            'name' => $validated['name'],
            'permissions' => ['inbox'], // default permission
            'company_id' => Auth::user()?->company_id,
        ]);

        return response()->json($role, 201);
    }

    public function rolesUpdateMatrix(Request $request)
    {
        $validated = $request->validate([
            'matrix' => 'required|array',
            'matrix.*.id' => 'required|exists:roles,id',
            'matrix.*.permissions' => 'present|array',
        ]);

        foreach ($validated['matrix'] as $row) {
            $role = Role::find($row['id']);
            if ($role) {
                $role->update([
                    'permissions' => $row['permissions']
                ]);
            }
        }

        return response()->json(['message' => 'Access control matrix updated successfully']);
    }
}
