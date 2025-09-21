<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class BorrowController extends Controller
{
    // Borrow a publication
    public function borrowPublication(Request $request, $publicationId)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['message' => 'User not authenticated'], 401);
        }
        if (!in_array($user->role, ['student', 'faculty'])) {
            return response()->json(['message' => 'Only students and faculty can borrow publications'], 403);
        }

        return DB::transaction(function () use ($user, $publicationId) {
            // Lock publication row
            $publication = DB::selectOne('SELECT * FROM publications WHERE id = ? FOR UPDATE', [$publicationId]);
            if (!$publication) {
                return response()->json(['message' => 'Publication not found'], 404);
            }
            if ($publication->available_copies <= 0) {
                return response()->json(['message' => 'Publication is not available for borrowing'], 400);
            }

            // Already borrowed?
            $existing = DB::selectOne(
                'SELECT id FROM borrows WHERE borrowed_id = ? AND borrower_id = ? AND status IN ("borrowed","overdue")',
                [$publicationId, $user->id]
            );
            if ($existing) {
                return response()->json(['message' => 'You have already borrowed this publication'], 400);
            }

            // Borrowing limit
            $activeCount = DB::selectOne(
                'SELECT COUNT(*) AS cnt FROM borrows WHERE borrower_id = ? AND status IN ("borrowed","overdue")',
                [$user->id]
            )->cnt;
            $max = $user->role === 'faculty' ? 10 : 3;
            if ($activeCount >= $max) {
                return response()->json(['message' => "You have reached your borrowing limit of {$max} publications"], 400);
            }

            $fineRate = $user->role === 'student' ? 5.00 : 0.00;
            $borrowPeriod = $user->role === 'faculty' ? 14 : 7;
            $returnDate = Carbon::now()->addDays($borrowPeriod)->toDateString();
            $borrowDate = Carbon::now()->toDateString();

            DB::insert(
                'INSERT INTO borrows (borrowed_id, borrower_id, borrow_date, return_date, fine_rate, status, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, "borrowed", NOW(), NOW())',
                [$publicationId, $user->id, $borrowDate, $returnDate, $fineRate]
            );

            DB::update('UPDATE publications SET available_copies = available_copies - 1 WHERE id = ?', [$publicationId]);

            if ($user->role === 'student') {
                DB::update('UPDATE students SET borrowed_id = ? WHERE email = ?', [$publicationId, $user->email]);
            } elseif ($user->role === 'faculty') {
                DB::update('UPDATE faculties SET borrowed_id = ? WHERE email = ?', [$publicationId, $user->email]);
            }

            $borrow = DB::selectOne(
                'SELECT b.*, p.title AS publication_title, u.name AS borrower_name
                 FROM borrows b
                 JOIN publications p ON p.id = b.borrowed_id
                 JOIN users u ON u.id = b.borrower_id
                 WHERE b.borrowed_id = ? AND b.borrower_id = ?
                 ORDER BY b.id DESC LIMIT 1',
                [$publicationId, $user->id]
            );

            return response()->json([
                'message' => 'Publication borrowed successfully',
                'borrow' => $borrow,
                'return_date' => $returnDate,
                'fine_rate' => $fineRate
            ], 201);
        });
    }

    // Return a publication
    public function returnPublication(Request $request, $borrowId)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['message' => 'User not authenticated'], 401);
        }

        return DB::transaction(function () use ($borrowId, $user) {
            $borrow = DB::selectOne(
                'SELECT b.*, p.id AS pub_id, p.title
                 FROM borrows b
                 JOIN publications p ON p.id = b.borrowed_id
                 WHERE b.id = ? AND b.borrower_id = ? AND b.status IN ("borrowed","overdue")
                 FOR UPDATE',
                [$borrowId, $user->id]
            );
            if (!$borrow) {
                return response()->json(['message' => 'Borrow record not found or already returned'], 404);
            }

            $now = Carbon::now();
            $fine = 0;
            if ($now->gt($borrow->return_date) && $borrow->fine_rate > 0) {
                $overdueDays = $now->diffInDays($borrow->return_date);
                $fine = $overdueDays * $borrow->fine_rate;
            }

            DB::update(
                'UPDATE borrows SET actual_return_date = ?, total_fine = ?, status = "returned", updated_at = NOW() WHERE id = ?',
                [$now->toDateString(), $fine, $borrowId]
            );
            DB::update('UPDATE publications SET available_copies = available_copies + 1 WHERE id = ?', [$borrow->pub_id]);

            $final = DB::selectOne(
                'SELECT b.*, p.title AS publication_title FROM borrows b
                 JOIN publications p ON p.id = b.borrowed_id WHERE b.id = ?',
                [$borrowId]
            );

            return response()->json(['message' => 'Publication returned successfully', 'fine' => $fine, 'borrow' => $final], 200);
        });
    }

    // User borrowing history
    public function getUserBorrows(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['message' => 'User not authenticated'], 401);
        }

        // Derived overdue status in query
        $borrows = DB::select(
            'SELECT b.*,
                    p.title AS publication_title,
                    CASE 
                      WHEN b.status="borrowed" AND b.return_date<CURDATE() THEN "overdue"
                      ELSE b.status END AS computed_status
             FROM borrows b
             JOIN publications p ON p.id = b.borrowed_id
             WHERE b.borrower_id = ?
             ORDER BY b.created_at DESC',
            [$user->id]
        );

        return response()->json($borrows);
    }

   // All borrows for librarians
// All borrows for librarians
public function getAllBorrows(Request $request)
{
    // ✅ Role check stays at the top
    if (!auth()->user() || auth()->user()->role !== 'librarian') {
        return response()->json(['message' => 'Forbidden'], 403);
    }

    $where = "WHERE 1=1";
    $bindings = [];

    if ($request->has('status')) {
        $where .= " AND b.status = ?";
        $bindings[] = $request->status;
    }

    if ($request->has('overdue') && $request->overdue === 'true') {
        $where .= " AND b.status = 'borrowed' AND b.return_date < CURDATE()";
    }

    $borrows = DB::select(
        "SELECT 
            b.*, 
            p.id AS publication_id,
            p.title AS publication_title, 
            p.author AS publication_author,
            p.cover_url AS publication_cover_url,
            p.type AS publication_type,
            u.id AS borrower_id,
            u.email AS borrower_name,
            u.email AS borrower_email,
            u.role AS borrower_role,
            CASE 
                WHEN b.return_date < CURDATE() AND b.status = 'borrowed' 
                THEN DATEDIFF(CURDATE(), b.return_date) 
                ELSE 0 
            END AS overdue_days
        FROM borrows b
        JOIN publications p ON p.id = b.borrowed_id
        JOIN users u ON u.id = b.borrower_id
        $where
        ORDER BY b.created_at DESC
        LIMIT 50",
        $bindings
    );

    // ✅ Reshape to match frontend expectations
    $formatted = collect($borrows)->map(function ($row) {
        return [
            "id" => $row->id,
            "borrow_date" => $row->borrow_date,
            "return_date" => $row->return_date,
            "actual_return_date" => $row->actual_return_date,
            "fine_rate" => $row->fine_rate,
            "total_fine" => $row->total_fine,
            "status" => $row->status,
            "created_at" => $row->created_at,
            "updated_at" => $row->updated_at,
            "overdue_days" => $row->overdue_days,
            "publication" => [
                "id" => $row->publication_id,
                "title" => $row->publication_title,
                "author" => $row->publication_author,
                "cover_url" => $row->publication_cover_url,
                "type" => $row->publication_type,
            ],
            "borrower" => [
                "id" => $row->borrower_id,
                "name" => $row->borrower_name, // This is actually email since users table has no name column
                "email" => $row->borrower_email,
                "role" => $row->borrower_role,
            ],
        ];
    });

    return response()->json($formatted);
}


    // Borrowing stats
    public function getBorrowingStats(Request $request)
    {
        $user = Auth::user();
        if (!$user || $user->role !== 'librarian') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $stats = [
            'total_borrowed' => DB::selectOne('SELECT COUNT(*) AS c FROM borrows WHERE status IN ("borrowed","overdue")')->c,
            'total_returned' => DB::selectOne('SELECT COUNT(*) AS c FROM borrows WHERE status="returned"')->c,
            'overdue_count' => DB::selectOne('SELECT COUNT(*) AS c FROM borrows WHERE status="borrowed" AND return_date<CURDATE()')->c,
            'total_fines' => DB::selectOne('SELECT SUM(total_fine) AS s FROM borrows')->s,
            'active_borrowers' => DB::selectOne('SELECT COUNT(DISTINCT borrower_id) AS c FROM borrows WHERE status IN ("borrowed","overdue")')->c
        ];

        return response()->json($stats);
    }

    // Manual return by librarian
    public function manualReturn(Request $request, $borrowId)
    {
        $user = Auth::user();
        if (!$user || $user->role !== 'librarian') {
            return response()->json(['message' => 'Only librarians can perform manual returns'], 403);
        }

        return DB::transaction(function () use ($borrowId) {
            $borrow = DB::selectOne(
                'SELECT b.*, p.id AS pub_id FROM borrows b
                 JOIN publications p ON p.id = b.borrowed_id
                 WHERE b.id = ? AND b.status IN ("borrowed","overdue")
                 FOR UPDATE',
                [$borrowId]
            );
            if (!$borrow) {
                return response()->json(['message' => 'Borrow record not found or already returned'], 404);
            }

            $now = Carbon::now();
            $fine = 0;
            if ($now->gt($borrow->return_date) && $borrow->fine_rate > 0) {
                $fine = $now->diffInDays($borrow->return_date) * $borrow->fine_rate;
            }

            DB::update('UPDATE borrows SET actual_return_date=?, total_fine=?, status="returned", updated_at=NOW() WHERE id=?',
                [$now->toDateString(), $fine, $borrowId]);
            DB::update('UPDATE publications SET available_copies=available_copies+1 WHERE id=?', [$borrow->pub_id]);

            // clear borrowed_id from student/faculty tables
            $borrower = DB::selectOne('SELECT * FROM users WHERE id=(SELECT borrower_id FROM borrows WHERE id=?)', [$borrowId]);
            if ($borrower) {
                if ($borrower->role === 'student') {
                    DB::update('UPDATE students SET borrowed_id=NULL WHERE email=?', [$borrower->email]);
                } elseif ($borrower->role === 'faculty') {
                    DB::update('UPDATE faculties SET borrowed_id=NULL WHERE email=?', [$borrower->email]);
                }
            }

            $final = DB::selectOne('SELECT b.*,p.title FROM borrows b JOIN publications p ON p.id=b.borrowed_id WHERE b.id=?', [$borrowId]);
            return response()->json(['message' => 'Book manually returned successfully', 'fine' => $fine, 'borrow' => $final], 200);
        });
    }

    // Clear fine
    public function clearFine(Request $request, $borrowId)
    {
        $user = Auth::user();
        if (!$user || $user->role !== 'librarian') {
            return response()->json(['message' => 'Only librarians can clear fines'], 403);
        }

        return DB::transaction(function () use ($borrowId) {
            $borrow = DB::selectOne('SELECT * FROM borrows WHERE id=? AND total_fine>0 FOR UPDATE', [$borrowId]);
            if (!$borrow) {
                return response()->json(['message' => 'Borrow record not found or no fine to clear'], 404);
            }

            $prev = $borrow->total_fine;
            DB::update('UPDATE borrows SET total_fine=0, updated_at=NOW() WHERE id=?', [$borrowId]);
            $final = DB::selectOne('SELECT b.*,p.title FROM borrows b JOIN publications p ON p.id=b.borrowed_id WHERE b.id=?', [$borrowId]);

            return response()->json(['message' => 'Fine cleared successfully', 'previous_fine' => $prev, 'borrow' => $final], 200);
        });
    }

    // Extend due date
    public function extendDueDate(Request $request, $borrowId)
    {
        $user = Auth::user();
        if (!$user || $user->role !== 'librarian') {
            return response()->json(['message' => 'Only librarians can extend due dates'], 403);
        }
        $request->validate(['days' => 'required|integer|min:1|max:30']);

        return DB::transaction(function () use ($borrowId, $request) {
            $borrow = DB::selectOne('SELECT * FROM borrows WHERE id=? AND status IN ("borrowed","overdue") FOR UPDATE', [$borrowId]);
            if (!$borrow) {
                return response()->json(['message' => 'Borrow record not found or already returned'], 404);
            }

            $oldDueDate = $borrow->return_date;
            $newDueDate = Carbon::parse($borrow->return_date)->addDays($request->days)->toDateString();

            DB::update('UPDATE borrows SET return_date=?, status="borrowed", total_fine=0, updated_at=NOW() WHERE id=?', [$newDueDate, $borrowId]);

            $final = DB::selectOne('SELECT b.*,p.title FROM borrows b JOIN publications p ON p.id=b.borrowed_id WHERE b.id=?', [$borrowId]);

            return response()->json([
                'message' => 'Due date extended successfully',
                'old_due_date' => $oldDueDate,
                'new_due_date' => $newDueDate,
                'extended_by' => $request->days.' days',
                'borrow' => $final
            ], 200);
        });
    }
}
