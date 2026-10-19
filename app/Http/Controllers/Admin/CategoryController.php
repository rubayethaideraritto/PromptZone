<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CategoryRequest;
use App\Models\Category;
use Illuminate\Contracts\View\View;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    /**
     * MariaDB driver code for "cannot delete or update a parent row", i.e. the
     * ON DELETE RESTRICT on prompts.category_id firing.
     */
    protected const FK_RESTRICT_DRIVER_CODE = 1451;

    /**
     * Shared message for both refusal paths, so an admin sees the same guidance
     * whichever one catches the attempt.
     */
    protected const IN_USE_MESSAGE = 'Cannot delete category with existing prompts. Deactivate it instead.';

    /**
     * The category list, plus an optional editing form.
     *
     * The row being edited is chosen with ?edit=id rather than client-side
     * JavaScript, so the editing form can sit outside the table at full width
     * while staying a plain server-rendered page whose state lives in the URL.
     */
    public function index(Request $request): View
    {
        $categories = Category::query()
            // The count is what tells an admin whether a category can be removed.
            ->withCount('prompts')
            ->orderBy('name')
            ->get();

        $editing = $request->integer('edit')
            ? $categories->firstWhere('id', $request->integer('edit'))
            : null;

        return view('admin.categories.index', [
            'categories' => $categories,
            'editing' => $editing,
        ]);
    }

    public function store(CategoryRequest $request): RedirectResponse
    {
        Category::create([
            'name' => $request->validated()['name'],
            'slug' => $request->slug(),
            'description' => $request->validated()['description'] ?? null,
            'is_active' => true,
        ]);

        return back()->with('status', 'Category created.');
    }

    public function update(CategoryRequest $request, Category $category): RedirectResponse
    {
        $category->update([
            'name' => $request->validated()['name'],
            'slug' => $request->slug(),
            'description' => $request->validated()['description'] ?? null,
        ]);

        return back()->with('status', 'Category updated.');
    }

    /**
     * Activate or deactivate, which is how a category that is in use is taken out
     * of circulation without breaking the prompts filed under it.
     *
     * is_active is mass assignable, but the value is derived here rather than
     * taken from the request so a client cannot set it directly.
     */
    public function toggleActive(Category $category): RedirectResponse
    {
        $category->is_active = ! $category->is_active;
        $category->save();

        return back()->with('status', $category->is_active
            ? $category->name.' is now active.'
            : $category->name.' is now deactivated. Existing prompts keep it, but it is no longer offered for new ones.');
    }

    /**
     * Delete a category, but only when no prompt references it.
     *
     * The schema declares prompts.category_id as ON DELETE RESTRICT and the
     * schema is the source of truth, so an in-use category genuinely cannot be
     * removed. Two layers handle this:
     *
     *   1. a pre-check, which covers every ordinary attempt and produces the
     *      explanatory message
     *   2. a driver-code catch, which covers the narrow window where a prompt is
     *      filed under the category between the check and the delete
     *
     * The foreign key is never disabled and the row is never force-deleted.
     */
    public function destroy(Category $category): RedirectResponse
    {
        if ($category->prompts()->exists()) {
            return back()->with('error', self::IN_USE_MESSAGE);
        }

        try {
            $category->delete();
        } catch (QueryException $exception) {
            if (($exception->errorInfo[1] ?? null) === self::FK_RESTRICT_DRIVER_CODE) {
                return back()->with('error', self::IN_USE_MESSAGE);
            }

            throw $exception;
        }

        return back()->with('status', $category->name.' has been deleted.');
    }
}
