<?php

use App\Http\Controllers\Admin\CategoryController as AdminCategoryController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\PromptController as AdminPromptController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\AiController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FavoriteController;
use App\Http\Controllers\LandingController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PromptController;
use App\Http\Controllers\RatingController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public routes
|--------------------------------------------------------------------------
*/

Route::get('/', [LandingController::class, 'index'])->name('home');

Route::get('/library', [PromptController::class, 'index'])->name('library.index');

// Public, but the controller hides other users' private prompts with a 404.
Route::get('/prompts/{prompt}', [PromptController::class, 'show'])
    ->whereNumber('prompt')
    ->name('prompts.show');

/*
|--------------------------------------------------------------------------
| Guest routes
|--------------------------------------------------------------------------
| The `guest` middleware sends already-authenticated users to /dashboard.
*/

Route::middleware('guest')->group(function () {
    Route::get('/register', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('/register', [RegisteredUserController::class, 'store']);

    // Must be named `login`: Laravel redirects unauthenticated visitors here.
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store']);
});

/*
|--------------------------------------------------------------------------
| Authenticated routes
|--------------------------------------------------------------------------
*/

Route::middleware('auth')->group(function () {
    // POST only, so logging out stays CSRF protected and cannot be triggered
    // by a crafted link or image tag.
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/favorites', [FavoriteController::class, 'index'])->name('favorites.index');

    /*
    | Account settings. Two separate forms and two separate endpoints, so an
    | incomplete password change can never blank or partially write the profile.
    */
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password');

    /*
    | Prompt CRUD. `create` is declared before the wildcard route, and every
    | wildcard is constrained to digits, so /prompts/create can never be
    | swallowed by /prompts/{prompt}.
    */
    Route::get('/prompts/create', [PromptController::class, 'create'])->name('prompts.create');
    Route::post('/prompts', [PromptController::class, 'store'])->name('prompts.store');

    // Advisory duplicate check, called while the create/edit form is being typed.
    Route::post('/prompts/duplicate-check', [PromptController::class, 'checkDuplicate'])
        ->name('prompts.duplicate-check');
    Route::get('/prompts/{prompt}/edit', [PromptController::class, 'edit'])
        ->whereNumber('prompt')
        ->name('prompts.edit');
    Route::put('/prompts/{prompt}', [PromptController::class, 'update'])
        ->whereNumber('prompt')
        ->name('prompts.update');
    Route::delete('/prompts/{prompt}', [PromptController::class, 'destroy'])
        ->whereNumber('prompt')
        ->name('prompts.destroy');

    /*
    | Prompt interactions. All three require authentication, and each one also
    | verifies the viewer may read the prompt before touching anything.
    */
    Route::post('/prompts/{prompt}/favorite', [FavoriteController::class, 'toggle'])
        ->whereNumber('prompt')
        ->name('prompts.favorite');

    Route::post('/prompts/{prompt}/copy', [PromptController::class, 'copy'])
        ->whereNumber('prompt')
        ->name('prompts.copy');

    Route::post('/prompts/{prompt}/rating', [RatingController::class, 'store'])
        ->whereNumber('prompt')
        ->name('prompts.rating');

    // Replacing the text while keeping the old text in the version history.
    Route::post('/prompts/{prompt}/versions', [PromptController::class, 'saveVersion'])
        ->whereNumber('prompt')
        ->name('prompts.versions.store');

    /*
    | AI assistance. Authenticated, and additionally throttled per user because
    | every call costs a provider request.
    */
    Route::middleware('throttle:ai')->prefix('ai')->name('ai.')->group(function () {
        Route::post('/analyze', [AiController::class, 'analyze'])->name('analyze');
        Route::post('/improve', [AiController::class, 'improve'])->name('improve');
        Route::post('/suggest', [AiController::class, 'suggest'])->name('suggest');
    });

    /*
    | Administration. Nested inside the `auth` group, and the `admin` middleware
    | runs after it, so a guest is redirected to login while a signed-in
    | non-administrator receives 403.
    */
    Route::middleware('admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/', [AdminDashboardController::class, 'index'])->name('dashboard');

        // Categories. There is no bulk delete: removing one is refused while
        // prompts still reference it (prompts.category_id is ON DELETE RESTRICT).
        Route::get('/categories', [AdminCategoryController::class, 'index'])->name('categories.index');
        Route::post('/categories', [AdminCategoryController::class, 'store'])->name('categories.store');
        Route::put('/categories/{category}', [AdminCategoryController::class, 'update'])
            ->whereNumber('category')->name('categories.update');
        Route::patch('/categories/{category}/active', [AdminCategoryController::class, 'toggleActive'])
            ->whereNumber('category')->name('categories.toggle-active');
        Route::delete('/categories/{category}', [AdminCategoryController::class, 'destroy'])
            ->whereNumber('category')->name('categories.destroy');

        // Accounts and roles.
        Route::get('/users', [AdminUserController::class, 'index'])->name('users.index');
        Route::patch('/users/{user}/role', [AdminUserController::class, 'updateRole'])
            ->whereNumber('user')->name('users.update-role');

        // Moderation of public content only.
        Route::get('/prompts', [AdminPromptController::class, 'index'])->name('prompts.index');
        Route::patch('/prompts/{prompt}/featured', [AdminPromptController::class, 'toggleFeatured'])
            ->whereNumber('prompt')->name('prompts.feature');
        Route::patch('/prompts/{prompt}/status', [AdminPromptController::class, 'toggleStatus'])
            ->whereNumber('prompt')->name('prompts.status');
    });
});
