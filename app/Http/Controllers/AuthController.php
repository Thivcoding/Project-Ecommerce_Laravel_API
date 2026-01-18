<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Tymon\JWTAuth\Facades\JWTAuth;
use CloudinaryLabs\CloudinaryLaravel\Facades\Cloudinary;

class AuthController extends Controller
{
    // REGISTER
    public function register(Request $request)
    {
        $input = $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|unique:users,email',
            'password' => 'required|min:6',
            'image'    => 'nullable|image|mimes:jpg,jpeg,png',
        ]);

        $input['role'] = 'admin'; // set role automatically

        if ($request->hasFile('image')) {
            $uploadedFile = Cloudinary::upload(
                $request->file('image')->getRealPath(),
                ['folder'=>'users/images']
            );
            $input['image'] = $uploadedFile->getSecurePath();
            $input['cloudinary_id'] = $uploadedFile->getPublicId();
        }

        $input['password'] = Hash::make($input['password']);
        $user = User::create($input);

        $token = JWTAuth::fromUser($user);

        return response()->json([
            'message' => 'Register successfully',
            'user'    => $user,
            'token'   => $token
        ], 201);
    }

    // LOGIN
    public function login(Request $request){
        $credentials = $request->only('email','password');

        if (!$token = JWTAuth::attempt($credentials)) {
            return response()->json(['message'=>'Invalid email or password'],401);
        }

        $user = auth()->user();

        return response()->json([
            'message'=>'Login successful',
            'token'=>$token,
            'user'=>$user
        ],200);
    } 

    // GET PROFILE
    public function profile(Request $request) {
        return response()->json($request->user());
    }

    // UPDATE PROFILE
    public function updateProfile(Request $request)
    {
        try {
            $user = auth()->user();

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
                // Delete old image in Cloudinary
                if ($user->cloudinary_id) {
                    Cloudinary::destroy($user->cloudinary_id);
                }

                $uploadedFile = Cloudinary::upload(
                    $request->file('image')->getRealPath(),
                    ['folder'=>'users/images']
                );

                $input['image'] = $uploadedFile->getSecurePath();
                $input['cloudinary_id'] = $uploadedFile->getPublicId();
            }

            $user->update($input);
            $user->refresh();

            $token = JWTAuth::fromUser($user);

            return response()->json([
                'message' => 'Profile updated successfully',
                'user'    => $user,
                'token'   => $token
            ],200);

        } catch (\Exception $e) {
            return response()->json([
                'message'=>'Update failed',
                'error'=>$e->getMessage()
            ],500);
        }
    }
}
