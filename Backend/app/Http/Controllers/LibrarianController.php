<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Requests;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Hash;
use App\Models\Librarians;
use App\Models\Users;

class LibrarianController extends Controller
{
    public function librarians(Request $request)
    {
        $librarians = Librarians::all();
        return response()->json($librarians);
    }

    public function create(Request $request)
    {
        // $request->validate([]); thing fails for some reason
        // Why? I have no idea
        // I want my 3 hours back

        // Using Validator instead
        $validator = Validator::make($request->all(), [
            'librarian_id' => 'required|unique:librarians,librarian_id',
            'name' => 'required|string|max:255',
            'designation' => 'required|string|max:255',
            'email' => 'required|email|unique:librarians,email',
            'phone' => 'nullable|string|max:20',
            'password' => [
                'required',
                'string',
                'min:8',
                'regex:/^(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z0-9]).+$/'
            ],
        ]);

        // Check if the librarian_id already exists
        $exists = DB::select('SELECT * FROM librarians WHERE librarian_id = ?', [$request->librarian_id]);
        if ($exists) {
            return response()->json([
                'message' => 'Librarian ID already exists',
                'error' => 'Duplicate Librarian ID'
            ], 409);
        }

        // Extract password before creating librarian
        $password = $request['password'];
        unset($request['password']); // Remove password from librarian data

        // Transaction to send it to user table as well
        DB::beginTransaction();
        try {
            // Create the librarian using Eloquent
            // $librarian = Librarians::create($request->all());

            $librarian = DB::insert(
                'INSERT INTO librarians (librarian_id, name, designation, email, phone) VALUES (?, ?, ?, ?, ?)',
                [
                    $request->input('librarian_id'),
                    $request->input('name'),
                    $request->input('designation'),
                    $request->input('email'),
                    $request->input('phone') ?? null // Handle null phone
                ]
            );

            // Create Users account for the librarian
            $userData = [
                'email' => $request->email,
                'password_hash' => Hash::make($password),
                'role' => 'faculty'
            ];

            DB::insert(
                'INSERT INTO users (email, password_hash, role) VALUES (?, ?, ?)',
                [
                    $userData['email'],
                    $userData['password_hash'],
                    $userData['role']
                ]
            );

            // Commit the transaction
            DB::commit();
        } catch (\Exception $e) {
            // Rollback the transaction on error
            DB::rollBack();
            return response()->json(['message' => 'Error creating librarian', 'error' => $e->getMessage()], 500);
        }

        return response()->json(['message' => 'Librarian created successfully'], 201);

    }
}
