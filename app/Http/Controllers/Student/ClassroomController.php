<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\ClassroomMembership;
use App\Services\ClassroomService;
use Illuminate\Http\Request;

class ClassroomController extends Controller
{
    public function index(Request $request)
    {
        $memberships = $request->user()->classroomMemberships()->with(['classroom.owner', 'classroom.assignments'])->latest()->get();
        return view('student.classes.index', ['user' => $request->user(), 'memberships' => $memberships]);
    }
    public function join(Request $request, ClassroomService $service)
    {
        $data = $request->validate(['join_code' => 'required|string|size:8']);
        $service->requestMembership($request->user(), $data['join_code']);
        return back()->with('success', 'Join request sent. Your teacher must approve it.');
    }
    public function leave(ClassroomMembership $membership, ClassroomService $service)
    {
        abort_unless((int) $membership->student_id === (int) auth()->id() && $membership->status === 'active', 403);
        $service->endMembership($membership, auth()->user(), 'left');
        return back()->with('success', 'You left the class. Previous results remain recorded.');
    }
}
