<?php

namespace App\Http\Controllers;

use App\Models\ForumThread;
use Illuminate\Http\Request;

class ForumController extends Controller
{
    public const CATEGORIES = ['Math', 'Reading & Writing', 'General', 'Off-topic'];

    public function index(Request $request)
    {
        $category = $request->query('category');

        $threads = ForumThread::with('user')
            ->withCount('replies')
            ->when($category && in_array($category, self::CATEGORIES, true), fn ($q) => $q->where('category', $category))
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return view('forum.index', [
            'user' => $request->user(),
            'threads' => $threads,
            'categories' => self::CATEGORIES,
            'activeCategory' => $category,
        ]);
    }

    public function show(Request $request, ForumThread $forumThread)
    {
        $forumThread->load('user');
        $replies = $forumThread->replies()->with('user')->oldest()->paginate(20);

        return view('forum.show', [
            'user' => $request->user(),
            'thread' => $forumThread,
            'replies' => $replies,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => 'required|string|max:150',
            'category' => 'required|in:' . implode(',', self::CATEGORIES),
            'body' => 'required|string|max:5000',
        ]);

        $thread = ForumThread::create([...$data, 'user_id' => $request->user()->id]);

        return redirect()->route('forum.show', $thread)->with('success', 'Question posted.');
    }

    public function storeReply(Request $request, ForumThread $forumThread)
    {
        $data = $request->validate([
            'body' => 'required|string|max:3000',
        ]);

        $forumThread->replies()->create([
            'user_id' => $request->user()->id,
            'body' => $data['body'],
        ]);

        return redirect()->route('forum.show', $forumThread)->with('success', 'Reply posted.');
    }
}
