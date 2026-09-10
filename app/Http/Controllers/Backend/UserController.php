<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\User;
use Inertia\Inertia;

class UserController extends Controller
{
    public function index()
    {
        $data['tableData'] = User::with(['userInfo'])
            ->nonAdmin()
            ->paginate(10);

        return Inertia::render('Backend/Users/Index', $data);
    }
    public function view($id)
    {
        $data['user'] = User::with(['userInfo', 'bookMarks.aipolicy'])
            ->withCount('bookMarks')
            ->nonAdmin()
            ->findOrFail($id);

        return response()->json($data);
    }
}
