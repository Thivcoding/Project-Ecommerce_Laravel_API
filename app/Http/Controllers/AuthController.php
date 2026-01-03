<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthController extends Controller
{
    //
    public function register(Request $request)
    {
        $input = $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|unique:users,email',
            'password' => 'required|min:6',
            'image'    => 'nullable|image|mimes:jpg,jpeg,png'
        ]);

        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('users', 'public');
            $input['image'] = $path;
        }

        $input['password'] = Hash::make($input['password']);

        $user = User::create($input);

        return response()->json([
            'message' => 'Register successfully',
            'user'    => $user
        ], 201);
    }

    // login
    public function login(Request $request){
        // Validate input
        $credentials = $request->only('email','password');

        // Attempt login
        if (!$token = JWTAuth::attempt($credentials)) {
            return response()->json([
                'message' => 'Invalid email or password'
            ], 401);
        }

        $user = auth()->user();

        // Return token + user info
        return response()->json([
            'message' => 'Login successful',
            'token' => $token,
            'user'  => $user,
        ], 200);
    } 

    // get user
    public function profile(Request $request) {
        return response()->json($request->user());
    }

    // Update Profile
    public function updateProfile(Request $request)
    {
        try {
            $user = User::findOrFail($request->user()->id);

            $input = $request->validate([
                'name'     => 'sometimes|string|max:255',
                'email'    => 'sometimes|email|unique:users,email,' . $user->id,
                'password' => 'nullable|string|min:6',
                'image'    => 'nullable|image|mimes:jpg,jpeg,png'
            ]);

            if (isset($input['password'])) {
                $input['password'] = Hash::make($input['password']);
            }

            if ($request->hasFile('image')) {
                // Delete old image
                if ($user->image && Storage::disk('public')->exists($user->image)) {
                    Storage::disk('public')->delete($user->image);
                }
                $input['image'] = $request->file('image')->store('users', 'public');
            }

            $user->update($input);
            $user->refresh();

            $token = auth()->login($user);

            return response()->json([
                'message' => 'Profile updated successfully',
                'user'    => $user,
                'token'   => $token,
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Update failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }



}
