<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\NaceAteco;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class NaceAtecoController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim($request->string('search')->toString());

        // Carica tutti i codici NACE/ATECO ordinati
        $query = NaceAteco::query()->orderBy('order');

        // Se c'è una ricerca, filtra per codice o titolo
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('code', 'like', "%{$search}%")
                    ->orWhere('title_it', 'like', "%{$search}%")
                    ->orWhere('title_en', 'like', "%{$search}%");
            });
        }

        $codes = $query->get();

        // Costruisce la struttura gerarchica
        $tree = $this->buildTree($codes, $search);

        return view('admin.nace-ateco.index', [
            'tree' => $tree,
            'search' => $search,
            'totalCodes' => $codes->count(),
            'allCodesCount' => NaceAteco::count(),
        ]);
    }

    /**
     * Costruisce un albero gerarchico dai codici NACE/ATECO
     */
    private function buildTree(Collection $codes, string $search): Collection
    {
        if ($codes->isEmpty()) {
            return collect();
        }

        // Se c'è una ricerca, include i codici genitori necessari
        if ($search !== '') {
            $codes = $this->includeParents($codes);
        }

        $tree = collect();
        $lastByHierarchy = []; // Traccia l'ultimo codice visto per ogni livello gerarchico

        foreach ($codes as $code) {
            $code->children = collect();
            $code->isSearchResult = $this->isSearchResult($code, $search);

            $level = $code->hierarchy->value;

            // Se è root (livello 1), aggiungilo al tree
            if ($level === 1) {
                $tree->push($code);
            } elseif (isset($lastByHierarchy[$level - 1])) {
                // Altrimenti, aggiungilo come figlio dell'ultimo parent del livello superiore
                $parent = $lastByHierarchy[$level - 1];
                $parent->children->push($code);
            }

            // Aggiorna l'ultimo visto per questo livello
            $lastByHierarchy[$level] = $code;
        }

        return $tree;
    }

    /**
     * Trova il parent di un codice (usato solo in includeParents)
     */
    private function findParent(NaceAteco $code, Collection $codes): ?NaceAteco
    {
        $targetHierarchy = $code->hierarchy->value - 1;

        // Itera all'indietro per trovare il primo parent valido
        foreach ($codes->reverse() as $potentialParent) {
            if ($potentialParent->order < $code->order && $potentialParent->hierarchy->value === $targetHierarchy) {
                return $potentialParent;
            }
        }

        return null;
    }

    /**
     * Include i codici genitori necessari per mantenere la gerarchia
     */
    private function includeParents(Collection $searchResults): Collection
    {
        $allCodes = NaceAteco::orderBy('order')->get()->keyBy('code');
        $resultCodes = collect();

        foreach ($searchResults as $code) {
            // Aggiungi il codice stesso
            $resultCodes->put($code->code, $code);

            // Aggiungi tutti i suoi genitori
            $current = $code;
            while ($parent = $this->findParent($current, $allCodes->values())) {
                if (! $resultCodes->has($parent->code)) {
                    $resultCodes->put($parent->code, $parent);
                }
                $current = $parent;
            }
        }

        return $resultCodes->sortBy('order')->values();
    }

    /**
     * Verifica se un codice è un risultato di ricerca diretto
     */
    private function isSearchResult(NaceAteco $code, string $search): bool
    {
        if ($search === '') {
            return false;
        }

        return str_contains(strtolower($code->code), strtolower($search))
            || str_contains(strtolower($code->title_it), strtolower($search))
            || str_contains(strtolower($code->title_en), strtolower($search));
    }
}
