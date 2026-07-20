<?php

namespace App\Http\Controllers;

use App\Models\BlogPost;
use Illuminate\Http\Request;

class BlogController extends Controller
{
    public function index(Request $request)
    {
        $posts = BlogPost::with('teacher')->latest('published_at')->paginate(9);

        return view('blog.index', ['user' => $request->user(), 'posts' => $posts]);
    }

    public function show(Request $request, BlogPost $blogPost)
    {
        $blogPost->load('teacher');

        return view('blog.show', ['user' => $request->user(), 'post' => $blogPost]);
    }
}
