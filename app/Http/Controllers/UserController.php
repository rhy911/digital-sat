<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    public function get_data()
    {
        $students = User::where('role', 'student')->get();

        return response()->json([
            'status' => 'success',
            'message' => 'Lấy danh sách học sinh thành công',
            'data' => $students
        ]);
    }

    public function insert_data(Request $request)
    {
        $user = User::create([
            'name' => 'Trần Đức Trí',
            'email' => 'tri.tran@bluebook.com',
            'password' => Hash::make('password123'),
            'role' => 'student'
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Tạo user thành công',
            'data' => $user
        ], 201);
    }
}
