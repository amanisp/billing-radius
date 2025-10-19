<?php

namespace App\Http\Controllers;

use App\Events\ActivityLogged;
use App\Models\User;
use Google\Service\AnalyticsReporting\Activity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Http\Controllers\ActivityLogController;
use Illuminate\Validation\Rule;

class AksesController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $data = User::where('group_id', $user->group_id)->get();
        return view('pages.admin', compact('data'));
    }

    public function store(Request $request)
    {
        try {
            $user = Auth::user();
            $validated = $request->validate([
                'name'         => 'required|string|max:255|unique:users,name|unique:groups,name',
                'phone_number' => 'required|string|max:255|unique:users,phone_number',
                'email'        => 'required|string|max:255|unique:users,email',
                'username'     => 'required|unique:users,username',
                'password'     => 'required|min:8',
                'role'         => 'required',
            ]);

            $group = $user->group_id;

            $newUser = User::create([
                'name'         => $validated['name'],
                'email'        => $validated['email'],
                'phone_number' => $validated['phone_number'],
                'role'         => $validated['role'],
                'username'     => $validated['username'],
                'password'     => Hash::make($validated['password']),
                'group_id'     => $group,
            ]);

            ActivityLogController::logCreate('users', $newUser);

            return back()->with('success', 'Data Admin berhasil ditambah!');
        } catch (\Throwable $th) {
            return back()->with('error', $th->getMessage());
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $user = User::findOrFail($id);
            $oldData = $user->toArray();

            $validated = $request->validate([
                'name' => [
                    'required',
                    'string',
                    'max:255',
                    Rule::unique('users', 'name')->ignore($user->id)
                ],
                'username' => [
                    'required',
                    'string',
                    'max:255',
                    Rule::unique('users', 'username')->ignore($user->id)
                ],
                'email' => [
                    'required',
                    'email',
                    'max:255',
                    Rule::unique('users', 'email')->ignore($user->id)
                ],
                'phone_number' => [
                    'nullable',
                    'string',
                    'max:255',
                    Rule::unique('users', 'phone_number')->ignore($user->id)
                ],
                'password' => 'nullable|string|min:8',
                'role' => 'required|string',
            ]);

            $user->update([
                'name' => $validated['name'],
                'username' => $validated['username'],
                'email' => $validated['email'],
                'phone_number' => $validated['phone_number'],
                'role' => $validated['role'],
            ]);

            if (!empty($validated['password'])) {
                $user->update(['password' => Hash::make($validated['password'])]);
            }

            ActivityLogController::logUpdate($oldData, 'users', $user->fresh());

            return back()->with('success', 'Data admin berhasil diperbarui!');
        } catch (\Throwable $th) {
            return back()->with('error', $th->getMessage());
        }
    }

    public function updateProfile(Request $request)
    {
        try {
            $userId = Auth::id();
            $user = User::findOrFail($userId);
            $oldData = $user->toArray();

            $validated = $request->validate([
                'name' => [
                    'required',
                    'string',
                    'max:255',
                    Rule::unique('users', 'name')->ignore($user->id)
                ],
                'username' => [
                    'required',
                    'string',
                    'max:255',
                    Rule::unique('users', 'username')->ignore($user->id)
                ],
                'email' => [
                    'required',
                    'email',
                    'max:255',
                    Rule::unique('users', 'email')->ignore($user->id)
                ],
                'phone_number' => [
                    'nullable',
                    'string',
                    'max:255',
                    Rule::unique('users', 'phone_number')->ignore($user->id)
                ],
                'password' => 'nullable|string|min:8',
            ]);

            $user->update([
                'name' => $validated['name'],
                'username' => $validated['username'],
                'email' => $validated['email'],
                'phone_number' => $validated['phone_number'],
            ]);

            if (!empty($validated['password'])) {
                $user->update(['password' => Hash::make($validated['password'])]);
            }

            ActivityLogController::logUpdate($oldData, 'users', $user->fresh());

            return back()->with('success', 'Profile berhasil diperbarui!');
        } catch (\Throwable $th) {
            return back()->with('error', $th->getMessage());
        }
    }

    public function destroy($id)
    {
        try {
            $user = User::where('id', $id)->firstOrFail();
            $deletedData = $user;

            $user->delete();
            ActivityLogged::dispatch('DELETE', null, $deletedData);

            return redirect()->route('admin.index')->with('success', 'Data User berhasil dihapus.');
        } catch (\Throwable $th) {
            return redirect()->route('admin.index')->with('error', $th->getMessage());
        }
    }
}
