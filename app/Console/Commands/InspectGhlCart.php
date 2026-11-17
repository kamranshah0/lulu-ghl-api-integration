<?php

namespace App\Console\Commands;

use App\DTOs\OrderData;
use App\Exceptions\NonPrintOrderException;
use App\Models\Order;
use Illuminate\Console\Command;
use Symfony\Component\Console\Output\OutputInterface;

class InspectGhlCart extends Command
{
    protected $signature = 'ghl:inspect-cart {order : Local app order ID, not Lulu job ID}
        {--products-only : Show product fields without evaluating the print selector}';

    protected $description = 'Read saved cart product identities and validate print selection; no external calls or writes';

    public function handle(): int
    {
        if (filter_var($this->argument('order'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false) {
            $this->error('Use the positive numeric LOCAL app order ID from /admin/orders/ID, not a GHL purchase or Lulu job ID.');

            return self::FAILURE;
        }
        $record = Order::findOrFail($this->argument('order'));
        $raw = $record->raw_payload;
        $payload = is_array($raw) ? ($raw['payload'] ?? $raw) : null;
        $order = is_array($payload) ? ($payload['order'] ?? $payload) : null;
        if (! is_array($payload) || ! is_array($order)) {
            $this->error('Saved order is missing structured cart data. Review the original webhook privately.');

            return self::FAILURE;
        }
        $items = $order['items'] ?? $order['line_items'] ?? $payload['items'] ?? $payload['line_items'] ?? [];
        if (! is_array($items) || $items === []) {
            $this->warn('No cart items saved; this order uses the legacy book-quantity contract.');

            return self::FAILURE;
        }

        $this->info('Saved product fields only (no customer data). No order was changed.');
        $this->line('Bare item id/_id, SKU and price IDs are diagnostic only, not product-ID mappings.');
        foreach ($items as $index => $item) {
            if (! is_array($item)) {
                $this->error('Cart contains an unstructured item.');

                return self::FAILURE;
            }
            $product = $item['product'] ?? null;
            $fields = array_intersect_key($item, array_flip(['id', '_id', 'sku', 'priceId', 'price_id', 'productId', 'product_id', 'name', 'title', 'quantity', 'qty']));
            if (is_array($product)) {
                $fields['product'] = array_intersect_key($product, array_flip(['id', '_id', 'name', 'title']));
            } elseif (is_string($product)) {
                $fields['product'] = $product;
            }
            $this->getOutput()->writeln(json_encode(['item' => $index, 'fields' => $fields], JSON_UNESCAPED_SLASHES), OutputInterface::OUTPUT_RAW);
        }

        if ($this->option('products-only')) {
            return self::SUCCESS;
        }

        try {
            $selected = OrderData::fromGhlPayload($raw);
            $this->info("Selected physical quantity: {$selected->quantity}; excluded lines: {$selected->excludedItemCount}.");

            return self::SUCCESS;
        } catch (NonPrintOrderException) {
            $this->info('Selection: configured electronic-only order; no print needed.');

            return self::SUCCESS;
        } catch (\InvalidArgumentException $e) {
            $this->error('Selection needs review. Verify product mappings, quantities and original webhook structure.');
            $this->line($e->getMessage());

            return self::FAILURE;
        }
    }
}
