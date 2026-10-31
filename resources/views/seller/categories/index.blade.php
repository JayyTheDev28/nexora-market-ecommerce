@extends('layouts.app')

@section('title', 'Store Categories — Nexora Market')

@section('content')
<div class="w-full bg-surface py-8 min-h-[70vh]">
    <div class="max-w-none px-margin-mobile md:px-margin-desktop">

        <x-seller-nav active="categories" />

        @if(session('status'))
            <div class="mb-6 p-4 rounded-2xl bg-emerald-500/10 border border-emerald-500/30 text-emerald-700 dark:text-emerald-300 font-label-md flex items-center gap-2">
                <span class="material-symbols-outlined text-[20px]">check_circle</span>
                {{ session('status') }}
            </div>
        @endif

        @if($errors->any())
            <div class="mb-6 p-4 rounded-2xl bg-error/10 border border-error/30 text-error font-body-sm">
                <ul class="list-disc pl-5 space-y-1">
                    @foreach($errors->all() as $err)
                        <li>{{ $err }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
            <div>
                <h1 class="font-headline-lg text-headline-lg text-on-surface">Store Categories</h1>
                <p class="font-body-md text-on-surface-variant">Organize your merchandise into custom store sections and subcategories for buyers.</p>
            </div>
            <button
                type="button"
                onclick="document.getElementById('createCategoryModal').classList.remove('hidden')"
                class="flex items-center gap-2 px-5 py-2.5 rounded-full bg-primary text-on-primary hover:bg-primary/90 font-label-md transition-colors shadow-sm w-fit">
                <span class="material-symbols-outlined text-[18px]">add</span>
                Create Category
            </button>
        </div>

        @if($categories->isEmpty())
            <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant p-12 text-center">
                <span class="material-symbols-outlined text-[48px] text-outline-variant mb-2">category</span>
                <h3 class="font-headline-sm text-headline-sm text-on-surface font-bold">No Custom Categories Yet</h3>
                <p class="font-body-sm text-xs text-on-surface-variant mt-1 max-w-md mx-auto">
                    Help your customers navigate your catalog by setting up categories like "New Arrivals", "Best Sellers", or specific collections.
                </p>
                <button
                    type="button"
                    onclick="document.getElementById('createCategoryModal').classList.remove('hidden')"
                    class="mt-4 px-5 py-2 rounded-full bg-primary text-on-primary text-xs font-bold hover:bg-primary/90 transition-colors">
                    Add First Category
                </button>
            </div>
        @else
            <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant overflow-hidden shadow-sm">
                <div class="px-6 py-4 bg-surface-container-low border-b border-outline-variant flex items-center justify-between font-label-md text-xs text-on-surface-variant font-bold uppercase tracking-wider">
                    <span>Category & Subcategories</span>
                    <span>Products · Visibility · Actions</span>
                </div>
                <div class="divide-y divide-outline-variant">
                    @foreach($categories as $category)
                        <div class="p-4 sm:p-5 hover:bg-surface-container-low/50 transition-colors">
                            <div class="flex items-center justify-between gap-4 flex-wrap">
                                <div class="flex items-center gap-3">
                                    <span class="material-symbols-outlined text-primary text-[24px]">folder</span>
                                    <div>
                                        <h3 class="font-label-md text-sm font-bold text-on-surface flex items-center gap-2">
                                            {{ $category->name }}
                                            @if(!$category->is_visible)
                                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-surface-container text-on-surface-variant">Hidden</span>
                                            @endif
                                        </h3>
                                        <p class="text-[11px] text-on-surface-variant">Slug: {{ $category->slug }}</p>
                                    </div>
                                </div>
                                <div class="flex items-center gap-3">
                                    <span class="px-3 py-1 rounded-full text-xs font-semibold bg-surface-container text-on-surface">
                                        {{ $category->products_count }} {{ Str::plural('product', $category->products_count) }}
                                    </span>
                                    <form method="POST" action="{{ route('seller.categories.toggle', $category) }}">
                                        @csrf
                                        <button type="submit" class="p-1.5 rounded-lg border border-outline-variant text-on-surface-variant hover:text-on-surface hover:bg-surface-container transition-colors" title="{{ $category->is_visible ? 'Hide from store' : 'Make visible' }}">
                                            <span class="material-symbols-outlined text-[18px]">{{ $category->is_visible ? 'visibility' : 'visibility_off' }}</span>
                                        </button>
                                    </form>
                                    <button
                                        type="button"
                                        onclick="openEditCategory({{ json_encode($category) }})"
                                        class="p-1.5 rounded-lg border border-outline-variant text-on-surface-variant hover:text-on-surface hover:bg-surface-container transition-colors"
                                        title="Edit Category">
                                        <span class="material-symbols-outlined text-[18px]">edit</span>
                                    </button>
                                    <form method="POST" action="{{ route('seller.categories.destroy', $category) }}" onsubmit="return confirm('Delete category \'{{ $category->name }}\'? Any assigned products will remain safe and be unassigned.');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="p-1.5 rounded-lg border border-outline-variant text-error hover:bg-error/10 transition-colors" title="Delete Category">
                                            <span class="material-symbols-outlined text-[18px]">delete</span>
                                        </button>
                                    </form>
                                </div>
                            </div>

                            {{-- Subcategories --}}
                            @if($category->children->isNotEmpty())
                                <div class="mt-3 pl-8 sm:pl-10 space-y-2 border-l-2 border-outline-variant/60 ml-3">
                                    @foreach($category->children as $child)
                                        <div class="flex items-center justify-between p-2 rounded-xl bg-surface-container-low/70">
                                            <div class="flex items-center gap-2">
                                                <span class="material-symbols-outlined text-outline-variant text-[18px]">subdirectory_arrow_right</span>
                                                <span class="font-body-sm text-xs font-semibold text-on-surface">{{ $child->name }}</span>
                                                @if(!$child->is_visible)
                                                    <span class="px-1.5 py-0.2 rounded text-[9px] font-bold bg-surface-container text-on-surface-variant">Hidden</span>
                                                @endif
                                            </div>
                                            <div class="flex items-center gap-2">
                                                <span class="text-[11px] text-on-surface-variant">{{ $child->products_count }} products</span>
                                                <button
                                                    type="button"
                                                    onclick="openEditCategory({{ json_encode($child) }})"
                                                    class="p-1 text-on-surface-variant hover:text-on-surface">
                                                    <span class="material-symbols-outlined text-[16px]">edit</span>
                                                </button>
                                                <form method="POST" action="{{ route('seller.categories.destroy', $child) }}" onsubmit="return confirm('Delete subcategory \'{{ $child->name }}\'?');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="p-1 text-error hover:bg-error/10 rounded">
                                                        <span class="material-symbols-outlined text-[16px]">delete</span>
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

    </div>
</div>

{{-- Create Category Modal --}}
<div id="createCategoryModal" class="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs flex items-center justify-center p-4 hidden">
    <div class="bg-surface-container-lowest rounded-3xl border border-outline-variant p-6 max-w-md w-full shadow-xl">
        <div class="flex items-center justify-between mb-4">
            <h3 class="font-headline-sm text-headline-sm text-on-surface font-bold">New Store Category</h3>
            <button type="button" onclick="document.getElementById('createCategoryModal').classList.add('hidden')" class="p-1 rounded-full text-on-surface-variant hover:bg-surface-container">
                <span class="material-symbols-outlined text-[20px]">close</span>
            </button>
        </div>
        <form method="POST" action="{{ route('seller.categories.store') }}" class="space-y-4">
            @csrf
            <div>
                <label for="name" class="block font-label-md text-xs font-semibold text-on-surface mb-1">Category Name <span class="text-error">*</span></label>
                <input type="text" id="name" name="name" required placeholder="e.g. Summer Essentials" class="w-full rounded-xl border border-outline-variant bg-surface px-3 py-2 text-sm text-on-surface focus:border-primary focus:outline-none">
            </div>

            <div>
                <label for="parent_id" class="block font-label-md text-xs font-semibold text-on-surface mb-1">Parent Category (Optional)</label>
                <select id="parent_id" name="parent_id" class="w-full rounded-xl border border-outline-variant bg-surface px-3 py-2 text-sm text-on-surface focus:border-primary focus:outline-none">
                    <option value="">-- None (Top-Level Category) --</option>
                    @foreach($allCategories as $parent)
                        <option value="{{ $parent->id }}">{{ $parent->name }}</option>
                    @endforeach
                </select>
                <p class="text-[11px] text-on-surface-variant mt-1">Leave empty to create a primary section.</p>
            </div>

            <label class="flex items-center gap-2 cursor-pointer pt-1">
                <input type="checkbox" name="is_visible" value="1" checked class="w-4 h-4 rounded text-primary focus:ring-primary">
                <span class="text-xs font-semibold text-on-surface">Visible on storefront catalog</span>
            </label>

            <div class="pt-4 border-t border-outline-variant flex justify-end gap-2">
                <button type="button" onclick="document.getElementById('createCategoryModal').classList.add('hidden')" class="px-4 py-2 rounded-full border border-outline-variant text-xs font-semibold text-on-surface hover:bg-surface-container">
                    Cancel
                </button>
                <button type="submit" class="px-5 py-2 rounded-full bg-primary text-on-primary text-xs font-bold hover:bg-primary/90">
                    Create
                </button>
            </div>
        </form>
    </div>
</div>

{{-- Edit Category Modal --}}
<div id="editCategoryModal" class="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs flex items-center justify-center p-4 hidden">
    <div class="bg-surface-container-lowest rounded-3xl border border-outline-variant p-6 max-w-md w-full shadow-xl">
        <div class="flex items-center justify-between mb-4">
            <h3 class="font-headline-sm text-headline-sm text-on-surface font-bold">Edit Category</h3>
            <button type="button" onclick="document.getElementById('editCategoryModal').classList.add('hidden')" class="p-1 rounded-full text-on-surface-variant hover:bg-surface-container">
                <span class="material-symbols-outlined text-[20px]">close</span>
            </button>
        </div>
        <form id="editCategoryForm" method="POST" action="" class="space-y-4">
            @csrf
            @method('PUT')
            <div>
                <label for="edit_name" class="block font-label-md text-xs font-semibold text-on-surface mb-1">Category Name <span class="text-error">*</span></label>
                <input type="text" id="edit_name" name="name" required class="w-full rounded-xl border border-outline-variant bg-surface px-3 py-2 text-sm text-on-surface focus:border-primary focus:outline-none">
            </div>

            <div>
                <label for="edit_parent_id" class="block font-label-md text-xs font-semibold text-on-surface mb-1">Parent Category</label>
                <select id="edit_parent_id" name="parent_id" class="w-full rounded-xl border border-outline-variant bg-surface px-3 py-2 text-sm text-on-surface focus:border-primary focus:outline-none">
                    <option value="">-- None (Top-Level Category) --</option>
                    @foreach($allCategories as $parent)
                        <option value="{{ $parent->id }}">{{ $parent->name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="edit_order_index" class="block font-label-md text-xs font-semibold text-on-surface mb-1">Sort Order Index</label>
                <input type="number" id="edit_order_index" name="order_index" min="0" class="w-full rounded-xl border border-outline-variant bg-surface px-3 py-2 text-sm text-on-surface focus:border-primary focus:outline-none">
            </div>

            <label class="flex items-center gap-2 cursor-pointer pt-1">
                <input type="checkbox" id="edit_is_visible" name="is_visible" value="1" class="w-4 h-4 rounded text-primary focus:ring-primary">
                <span class="text-xs font-semibold text-on-surface">Visible on storefront catalog</span>
            </label>

            <div class="pt-4 border-t border-outline-variant flex justify-end gap-2">
                <button type="button" onclick="document.getElementById('editCategoryModal').classList.add('hidden')" class="px-4 py-2 rounded-full border border-outline-variant text-xs font-semibold text-on-surface hover:bg-surface-container">
                    Cancel
                </button>
                <button type="submit" class="px-5 py-2 rounded-full bg-primary text-on-primary text-xs font-bold hover:bg-primary/90">
                    Save Changes
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openEditCategory(cat) {
    document.getElementById('editCategoryForm').action = `/seller/categories/${cat.id}`;
    document.getElementById('edit_name').value = cat.name;
    document.getElementById('edit_parent_id').value = cat.parent_id || '';
    document.getElementById('edit_order_index').value = cat.order_index || 0;
    document.getElementById('edit_is_visible').checked = !!cat.is_visible;
    document.getElementById('editCategoryModal').classList.remove('hidden');
}
</script>
@endsection
