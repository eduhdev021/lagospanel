<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Validation\ValidationException;

final class Pricing
{
    public function quote(Product $product, array $valueIds): array
    {
        $ids = array_map('intval', $valueIds);
        sort($ids);
        if (count($ids) !== count(array_unique($ids)) || count($ids) > 20) {
            throw ValidationException::withMessages(['options' => 'Seleção de opções inválida.']);
        }
        $groups = $product->options()->where('active', true)->with(['values' => fn ($q) => $q->where('active', true)])->get();
        $seen = [];
        $configuration = [];
        $recurring = $product->price_minor;
        $setup = $product->setup_minor;
        foreach ($groups as $group) {
            $selected = $group->values->whereIn('id', $ids);
            if ($selected->count() > 1 || ($group->required && $selected->count() !== 1)) {
                throw ValidationException::withMessages(['options' => 'Escolha um valor para '.$group->name.'.']);
            }foreach ($selected as $value) {
                $seen[] = $value->id;
                $recurring += $value->recurring_minor;
                $setup += $value->setup_minor;
                $configuration[] = ['option_id' => $group->id, 'value_id' => $value->id, 'name' => $group->name, 'label' => $value->label, 'recurring_minor' => $value->recurring_minor, 'setup_minor' => $value->setup_minor];
            }
        }
        sort($seen);
        if ($seen !== $ids) {
            throw ValidationException::withMessages(['options' => 'Opção indisponível ou pertencente a outro produto.']);
        }

        return ['recurring_minor' => $recurring, 'setup_minor' => $setup, 'configuration' => $configuration, 'value_ids' => $ids];
    }
}
