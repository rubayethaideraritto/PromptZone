<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Prompt;
use App\Models\User;
use Illuminate\Contracts\View\View;

class DashboardController extends Controller
{
    /**
     * Read-only summary counts for administrators.
     *
     * Category, user and content management arrive in Phase 7; this phase only
     * establishes the protected route and its verified access control.
     */
    public function index(): View
    {
        return view('admin.dashboard', [
            'userCount' => User::count(),
            'adminCount' => User::where('role', 'admin')->count(),
            'promptCount' => Prompt::count(),
            'publicPromptCount' => Prompt::where('visibility', 'public')->count(),
            'categoryCount' => Category::count(),
            'activeCategoryCount' => Category::where('is_active', true)->count(),
            'totalCopies' => (int) Prompt::sum('copy_count'),
        ]);
    }
}
