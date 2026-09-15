<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PagePhotoCollection;
use App\Exceptions\Admin\PhotoLimitExceededException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StorePagePhotoRequest;
use App\Http\Requests\Admin\UpdatePagePhotoRequest;
use App\Models\PagePhoto;
use App\Services\Admin\PagePhotoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminPagePhotoController extends Controller
{
    public function __construct(
        private readonly PagePhotoService $service
    ) {}

    public function index(Request $request): View
    {
        $collection = PagePhotoCollection::tryFrom((string) $request->query('collection', PagePhotoCollection::Gallery->value))
            ?? PagePhotoCollection::Gallery;

        $photos = PagePhoto::query()
            ->where('collection', $collection)
            ->ordered()
            ->get();

        return view('admin.photos.index', compact('collection', 'photos'));
    }

    public function store(StorePagePhotoRequest $request): RedirectResponse
    {
        $collection = PagePhotoCollection::from($request->validated('collection'));

        try {
            $this->service->upload(
                $request->user(),
                $collection,
                $request->file('images', []),
                $request->validated('title'),
                $request->validated('caption'),
            );
        } catch (PhotoLimitExceededException $e) {
            return redirect()
                ->route('admin.photos.index', ['collection' => $collection->value])
                ->with('error', $e->getMessage());
        }

        return redirect()
            ->route('admin.photos.index', ['collection' => $collection->value])
            ->with('success', 'Photos uploaded. Add titles and captions so guests know what they are looking at.');
    }

    public function update(UpdatePagePhotoRequest $request, PagePhoto $page_photo): RedirectResponse
    {
        $this->service->update($request->user(), $page_photo, $request->validated());

        return redirect()
            ->route('admin.photos.index', ['collection' => $page_photo->collection->value])
            ->with('success', 'Photo details saved.');
    }

    public function destroy(Request $request, PagePhoto $page_photo): RedirectResponse
    {
        $collection = $page_photo->collection;
        $this->service->delete($request->user(), $page_photo);

        return redirect()
            ->route('admin.photos.index', ['collection' => $collection->value])
            ->with('success', 'Photo deleted.');
    }
}
