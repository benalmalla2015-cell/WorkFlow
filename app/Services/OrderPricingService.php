<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Setting;
use Illuminate\Support\Collection;

class OrderPricingService
{
    public function hasCompleteFactoryItemPricing(Order $order): bool
    {
        return (bool) $this->summarize($order)['has_complete_factory_item_pricing'];
    }

    public function summarize(Order $order, ?float $margin = null): array
    {
        $items = $this->items($order);
        $resolvedMargin = (float) ($margin ?? $order->profit_margin_percentage ?? Setting::get('default_profit_margin', 20));

        $lineItems = $items->map(function ($item, $index) use ($resolvedMargin) {
            $quantity = max(1, (int) ($item->quantity ?? 1));
            $supplierName = trim((string) ($item->supplier_name ?? ''));
            $productCode = trim((string) ($item->product_code ?? ''));
            $unitCost = round((float) ($item->unit_cost ?? 0), 2);
            $factoryTotal = round($unitCost * $quantity, 2);
            $salesPrice = $unitCost > 0 ? round($unitCost * (1 + ($resolvedMargin / 100)), 2) : 0.0;
            $salesTotal = round($salesPrice * $quantity, 2);

            return [
                'id' => $item->id,
                'line' => $index + 1,
                'item_name' => (string) ($item->item_name ?? ''),
                'quantity' => $quantity,
                'description' => (string) ($item->description ?? ''),
                'supplier_name' => $supplierName,
                'product_code' => $productCode,
                'unit_cost' => $unitCost,
                'factory_total' => $factoryTotal,
                'sales_price' => $salesPrice,
                'sales_total' => $salesTotal,
                'is_complete' => $supplierName !== '' && $productCode !== '' && $unitCost > 0,
            ];
        })->values();

        $quantity = max(1, (int) $lineItems->sum('quantity'));
        $totalFactoryCost = round((float) $lineItems->sum('factory_total'), 2);
        $salesTotal = round((float) $lineItems->sum('sales_total'), 2);
        $averageFactoryCost = $quantity > 0 ? round($totalFactoryCost / $quantity, 2) : 0.0;
        $averageSalesPrice = $quantity > 0 ? round($salesTotal / $quantity, 2) : 0.0;
        $suppliers = $lineItems->pluck('supplier_name')->filter()->unique()->values();
        $productCodes = $lineItems->pluck('product_code')->filter()->unique()->values();
        $complete = $lineItems->isNotEmpty() && $lineItems->every(fn (array $item) => $item['is_complete']);

        return [
            'margin' => $resolvedMargin,
            'quantity' => $quantity,
            'line_items' => $lineItems->all(),
            'suppliers' => $suppliers->all(),
            'product_codes' => $productCodes->all(),
            'supplier_name' => $this->implodeSummary($suppliers),
            'product_code' => $this->implodeSummary($productCodes),
            'factory_cost_average' => $averageFactoryCost,
            'total_factory_cost' => $totalFactoryCost,
            'sales_unit_price_average' => $averageSalesPrice,
            'sales_total' => $salesTotal,
            'net_profit' => round($salesTotal - $totalFactoryCost, 2),
            'has_complete_factory_item_pricing' => $complete,
        ];
    }

    public function syncOrderPricing(Order $order, ?float $margin = null): array
    {
        $summary = $this->summarize($order, $margin);

        $order->supplier_name = $summary['supplier_name'];
        $order->product_code = $summary['product_code'];
        $order->quantity = $summary['quantity'];
        $order->factory_cost = $summary['factory_cost_average'] > 0 ? $summary['factory_cost_average'] : null;
        $order->profit_margin_percentage = $summary['margin'];

        if ($summary['has_complete_factory_item_pricing']) {
            $order->selling_price = $summary['sales_unit_price_average'];
            $order->final_price = $summary['sales_total'];
            $order->total_price = $summary['sales_total'];
            $order->net_profit = $summary['net_profit'];
        } else {
            $order->selling_price = null;
            $order->final_price = null;
            $order->total_price = null;
            $order->net_profit = null;
            $order->manager_approval = false;
        }

        return $summary;
    }

    private function items(Order $order): Collection
    {
        $order->loadMissing('items');

        return $order->resolvedItems()->values();
    }

    private function implodeSummary(Collection $values): ?string
    {
        $values = $values->filter()->values();

        if ($values->isEmpty()) {
            return null;
        }

        return $values->implode(' / ');
    }
}
