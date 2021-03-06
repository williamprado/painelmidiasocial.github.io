<?php

namespace App\Modules\SmmPro\Controllers\Admin;

use App\Modules\SmmPro\Requests\CategorySaveRequest;
use App\Modules\SmmPro\Models\Category;
use App\Http\Controllers\Controller;
use App\Modules\SmmPro\Models\Service;
use App\Modules\SmmPro\Models\Order;
use Illuminate\Http\Request;

/**
 * Class CategoriesController
 * @package App\Http\Controllers\Admin
 */
class CategoriesController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        return view('smmpro::categories.index', [
            'categories' => Category::defaultOrder()->get()->toTree(),
        ]);
    }

    public function terms_children($data, $parent = null)
    {
        $weight = 0;

        foreach ($data as $item) {
            $weight++;

            $term = $this->term()->find($item->id);
            $term->weight = $weight;
            $term->parent_id = $parent;

            if (!empty($item->children)) {
                $this->terms_children($item->children, $item->id);
            }
            if ($term) {
                $term->save();
            }
        }
    }

    /**
     * Ajax callback to rebuild the tree.
     *
     * @param Request $request
     * @return array
     */
    public function ajaxRebuildTree(Request $request)
    {
        if ($request->has('data')) {
            $data = json_decode($request->data, true);

            Category::rebuildTree($data);

            return [
                'status' => 1,
                'message' => 'Структура обновлена'
            ];

            if (!empty($data)) {
                $this->terms_children($data);
                return [
                    'status' => 1,
                    'message' => __('Structure updated.')
                ];
            }
            return [
                'status' => 0,
                'message' => __('Could not update structure.')
            ];
        }

        $node = $this->term()->find($request->input('node')['id']);
        $target = $this->term()->find($request->input('target')['id']);

        if ($node && $target) {
            $node->appendToNode($target)->save();

            return [
                'status' => 1,
                'message' => __('Structure updated.')
            ];
        }

        return [
            'status' => 0,
            'message' => __('Could not update structure.')
        ];
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        return view('smmpro::categories.create', [
            'parentCategories' => Category::defaultOrder()->whereNull('parent_id')->get()
        ]);
    }

    /**
     * @param CategorySaveRequest $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(CategorySaveRequest $request)
    {
        $category = new Category();

        $category->name = $request->input('name');
        $category->description = $request->input('description');
        $category->alias = $request->input('alias');

        $file = $request->file('icon');

        if ($file) {
            $avatar = self::storeAvatar($file);
            $category->icon = $avatar;

        } else {
            $category->icon = null;
        }
        $category->active = $request->input('active');

        $rootCategory = $request->input('root_category');
        $rootCategory2 = $request->input('category_id');

        if ($rootCategory != 0) {
            $parentCategory = Category::findOrFail($rootCategory);
            if ($rootCategory2 != 0) {
                $parent2Category = Category::findOrFail($rootCategory2);
                $category->appendToNode($parent2Category)->save();
            } else {
                $category->appendToNode($parentCategory)->save();
            }
        } else {
            $category->saveAsRoot();
        }

        return redirect()->route('categories.index')->with('success', 'Категория добавлена!');
    }

    /**
     * @param $id
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function edit($id)
    {
        return view('smmpro::categories.edit', [
            'category' => Category::findOrFail($id),
            'parentCategories' => Category::defaultOrder()->whereNull('parent_id')->get()
        ]);
    }

    /**
     * @param CategorySaveRequest $request
     * @param $id
     * @return \Illuminate\Http\RedirectResponse
     */
    public function update(CategorySaveRequest $request, $id)
    {
        $category = Category::findOrFail($id);
        $category->name = $request->input('name');
        $category->description = $request->input('description');
        $category->active = $request->input('active');

        $file = $request->file('icon');

        if (filled($file)) {
            $avatar = self::storeAvatar($file);
            $category->icon = $avatar;
        }

        $rootCategory = $request->input('root_category');
        $rootCategory2 = $request->input('category_id');

        if ($rootCategory != 0) {
            $parentCategory = Category::findOrFail($rootCategory);
            if ($rootCategory2 != 0) {
                $parent2Category = Category::findOrFail($rootCategory2);
                $category->appendToNode($parent2Category)->save();
            } else {
                $category->appendToNode($parentCategory)->save();
            }
        } else {
            $category->saveAsRoot();
        }

        return redirect()->route('categories.index')->with('success', 'Все изменения сохранены!');
    }

    /**
     * @param $id
     * @return \Illuminate\Http\RedirectResponse
     * @throws \Exception
     */
    public function destroy($id)
    {
        $category = Category::find($id);

        $services = Service::where('category_id', $id)->get();
        foreach ($services as $service) {
            $orders = Order::where('service_id', $service->id)->delete();
            $service->delete();
        }
//        $services->delete();
//        if ($category->services()) {
//            $category->services()->delete();
//
//        }

        $category->delete();
        return response()->json([
            'status' => 1,
            'message' => 'Категория удалена'
        ]);
    }

    public function ajaxGetDescendants(Request $request)
    {
        $descendants = Category::defaultOrder()->descendantsOf($request->root)->toTree();

        return response()->json(['categories' => $descendants]);
    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function ajaxGetAncestors(Request $request)
    {
        $ancestors = Category::ancestorsOf($request->input('id'));
        $root = null;

        foreach ($ancestors as $node) {
            if (is_null($node->parent_id)) {
                $root = $node;

                break;
            }
        }

        return response()->json(['root' => $root]);
    }

    public function ajaxGetCategories(Request $request)
    {
        $root = Category::defaultOrder()->whereNull('parent_id')->get();

        return response()->json([
            'rootCategories' => $root
        ]);
    }

    /**
     * @param $file
     * @return string
     */
    public static function storeAvatar($file)
    {
        $ext = $file->getClientOriginalExtension();
        $filename = 'icon' . time() . '.' . $ext;
        $file->storeAs('uploads/icons', $filename);
        return $filename;
    }
}
