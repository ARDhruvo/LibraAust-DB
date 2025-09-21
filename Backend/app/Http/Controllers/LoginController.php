<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use DB;
use Hash;
use Illuminate\Http\Request;
use App\Models\Users;
use App\Models\Students;
use App\Models\Faculties;
use App\Models\Librarians; // Uncomment if you add a Librarians model

class LoginController extends Controller
{
    public function __invoke(Request $request)
    {
        $request->validate([
            'email' => [
                'required',
                'email',
                'regex:/^[\w\.-]+@aust\.edu$/i',
            ],
            'password' => [
                'required',
                'min:8',
                'regex:/^(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z0-9]).+$/',
            ],
        ]);

        $user = DB::select('SELECT * FROM users WHERE email = ?', [$request->email]);

        // If the user is not found
        if (empty($user)) {
            return response()->json([
                'message' => 'Invalid credentials',
                'errors' => 'Invalid email or password',
            ], 401);
        }

        $user = $user[0]; // Since select returns an array of results, get the first one

        // User found, check password hashing
        if (!Hash::check($request->password, $user->password_hash)) {
            return response()->json([
                'message' => 'Invalid credentials',
                'errors' => 'Invalid email or password',
            ], 401);
        }

        // Password matched yippee

        // $token = auth()->user()->createToken('auth_token')->plainTextToken;
        // This doesnt work for some reason?????

        // $user = auth()->user();
        // $token = $user->createToken('auth_token')->plainTextToken;
        // This doesnt work either bc its null or something i guess

        $userModel = Users::find($user->id);
        // $userModel = DB::select('SELECT * FROM users WHERE id = ?', [$user->id]);
        // $userModel = $userModel[0]; // Get the first result from the array
        $token = $userModel->createToken('auth-token', expiresAt: now()->addDays(2))->plainTextToken;
        // Note to future self: This works because it has model on $userModel
        // Why is this different? I have no idea but just know that sanctum requires model 

        if ($user->role === 'student') {
            // $name = Students::where('email', $user->email)->value('name');
            $name = DB::select('SELECT name FROM students WHERE email = ?', [$user->email]);
            $name = $name[0]->name; // Get the name from the first result
        } elseif ($user->role === 'faculty') {
            // $name = Faculties::where('email', $user->email)->value('name');
            $name = DB::select('SELECT name FROM faculties WHERE email = ?', [$user->email]);
            $name = $name[0]->name; // Get the name from the first result
        }
        // Uncomment when yall add Librarians
        else {
            // $name = Librarians::where('email', $user->email)->value('name');
            $name = DB::select('SELECT name FROM librarians WHERE email = ?', [$user->email]);
            $name = $name[0]->name; // Get the name from the first result
        }


        return response()->json([
            'role' => $user->role,
            'name' => $name,
            'access_token' => $token
        ], 200);
    }
}
