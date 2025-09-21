<?php

namespace App\Http\Controllers;

use App\Models\Faculties;
use App\Models\Users;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class FacultyController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'faculty_id' => 'required|unique:faculties,faculty_id',
            'name' => 'required|string|max:255',
            'department' => 'required|string|max:255',
            'email' => 'required|email|unique:faculties,email',
            'phone' => 'nullable|string|max:20',
            'borrowed_id' => 'nullable|string|max:50',
            'password' => 'required|string|min:8|regex:/^(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z0-9]).+$/',
        ]);

        // Check if faculty_id already exists using raw SQL
        $exists = DB::select('SELECT * FROM faculties WHERE faculty_id = ?', [$request->faculty_id]);

        if (!empty($exists)) {
            return response()->json([
                'message' => 'Faculty ID already exists',
                'error' => 'Duplicate Faculty ID'
            ], 409);
        }

        // Extract password before creating faculty
        $password = $request->password;

        // Start transaction
        DB::beginTransaction();

        try {
            // 1. Insert into faculties table
            $facultyData = [
                'faculty_id' => $request->faculty_id,
                'name' => $request->name,
                'department' => $request->department,
                'email' => $request->email,
                'phone' => $request->phone ?? null, // Handle null phone
                'borrowed_id' => $request->borrowed_id ?? null // Handle null borrowed_id
            ];

            DB::insert(
                'INSERT INTO faculties (faculty_id, name, department, email, phone, borrowed_id) VALUES (?, ?, ?, ?, ?, ?)',
                [
                    $facultyData['faculty_id'],
                    $facultyData['name'],
                    $facultyData['department'],
                    $facultyData['email'],
                    $facultyData['phone'],
                    $facultyData['borrowed_id']
                ]
            );

            $lastFacultyId = DB::getPdo()->lastInsertId();

            // 2. Insert into users table
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


            return response()->json([
                'message' => 'Faculty created successfully',
            ], 201);

        } catch (\Exception $e) {
            // Rollback the transaction on error
            DB::rollBack();
            return response()->json([
                'message' => 'Error creating faculty',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}