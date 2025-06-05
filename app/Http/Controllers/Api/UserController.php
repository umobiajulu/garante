<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class UserController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
    }

    public function show(User $user)
    {
        return response()->json([
            'user' => $user->load('profile'),
        ]);
    }

    public function update(Request $request, User $user)
    {
        // Ensure user can only update their own profile
        if (auth()->id() !== $user->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'string|max:255',
            'phone_number' => 'string|unique:users,phone_number,' . $user->id,
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $user->update($request->only(['name', 'phone_number']));

        return response()->json([
            'message' => 'User successfully updated',
            'user' => $user,
        ]);
    }

    public function destroy(User $user)
    {
        // Ensure user can only delete their own account
        if (auth()->id() !== $user->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $user->delete();

        return response()->json([
            'message' => 'User successfully deleted',
        ]);
    }
}
