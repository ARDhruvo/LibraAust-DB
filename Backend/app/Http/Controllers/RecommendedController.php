<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Requests;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use App\Models\Faculties;
use App\Models\Students;
use App\Models\Publication;

class RecommendedController extends Controller
{
    public function recommended(Request $request)
    {

        $user = $request->user();

        if ($user) {

            // Check dept

            if ($user->role === 'student') {
                // $dept = Students::where('email', $user->email)->value('department');
                $dept = DB::select('SELECT department FROM students WHERE email = ?', [$user->email]);
                $dept = $dept[0]->department; // Get the department from the first result
            } elseif ($user->role === 'faculty') {
                // $dept = Faculties::where('email', $user->email)->value('department');
                $dept = DB::select('SELECT department FROM faculties WHERE email = ?', [$user->email]);
                $dept = $dept[0]->department; // Get the department from the first result
            }
        }

        // $query = Publication::where('department', $dept)->get();
        $books = DB::select('SELECT * FROM publications WHERE department = ? AND type = ?', [$dept, 'book']);

        return response()->json($books);
    }

    public function featured()
    {
        // $books = Publication::where('type', 'book')
        //     ->orderBy('updated_at', 'desc')
        //     ->take(5)
        //     ->get();
        $books = DB::select('SELECT * FROM publications WHERE type = ? ORDER BY updated_at DESC LIMIT 5', ['book']);
        return response()->json($books);
    }
}
