<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Services\Audit;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class KnowledgeController extends Controller
{
    public function index(Request $r)
    {
        $v = $r->validate(['q' => 'nullable|string|max:100']);
        $q = Article::where('published', true);
        if (! empty($v['q'])) {
            $q->where(fn ($b) => $b->where('title', 'like', '%'.$v['q'].'%')->orWhere('body', 'like', '%'.$v['q'].'%'));
        }

        return view('knowledge.index', ['articles' => $q->orderBy('category')->orderBy('title')->paginate(15)->withQueryString(), 'search' => $v['q'] ?? '']);
    }

    public function show(string $slug)
    {
        return view('knowledge.article', ['article' => Article::where('slug', $slug)->where('published', true)->firstOrFail()]);
    }

    public function admin(?Article $article = null)
    {
        return view('admin.knowledge', ['articles' => Article::latest()->paginate(15), 'editing' => $article]);
    }

    public function save(Request $r, ?Article $article = null)
    {
        $v = $r->validate(['title' => 'required|string|max:180', 'slug' => ['required', 'alpha_dash', 'max:180', Rule::unique('articles')->ignore($article?->id)], 'category' => 'required|string|max:100', 'body' => 'required|string|max:50000']);
        $data = $v + ['published' => $r->boolean('published'), 'author_id' => $r->user()->id];
        if ($article?->exists) {
            $article->update($data);
        } else {
            $article = Article::create($data);
        }Audit::record('knowledge.saved', 'article:'.$article->id, [], $r->user()->id);

        return redirect()->route('admin.knowledge')->with('status', 'Artigo salvo. O conteúdo é exibido como texto, sem execução de HTML.');
    }
}
