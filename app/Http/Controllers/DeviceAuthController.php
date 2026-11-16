<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;

class DeviceAuthController extends Controller
{
    public function store(Request $request)
    {
        $request->merge(['username' => mb_strtolower(trim((string) $request->input('username')))]);
        $d = $request->validate(['username' => 'required|string|max:40', 'password' => 'required|string|max:255', 'device_name' => 'required|string|max:80']);
        $user = User::where('username', $d['username'])->first();
        if (! $user || ! Hash::check($d['password'], $user->password)) {
            throw ValidationException::withMessages(['username' => 'اسم المستخدم أو كلمة المرور غير صحيحة.']);
        }
        $members = $user->companies()->where('companies.is_active', true)->wherePivot('is_active', true)->get();
        abort_unless($members->isNotEmpty(), 403);
        $user->tokens()->where('expires_at', '<', now())->delete();
        abort_if($user->tokens()->count() >= 10, 422, 'ألغِ أحد رموز الأجهزة السابقة قبل إضافة جهاز.');
        $expires = now()->addDays(7);
        $token = $user->createToken($d['device_name'], ['operations:read', 'operations:write'], $expires);

        return response()->json(['token' => $token->plainTextToken, 'expires_at' => $expires->toIso8601String(), 'companies' => $members->map(fn ($c) => ['id' => $c->id, 'name' => $c->name, 'role' => $c->pivot->role])]);
    }

    public function destroy(Request $request)
    {
        $token = $request->user()->currentAccessToken();
        abort_unless($token instanceof PersonalAccessToken, 403);
        $token->delete();

        return response()->json(['ok' => true]);
    }
}
