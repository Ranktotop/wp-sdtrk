<?php

/**
 * Maps a WooCommerce order's line items to the structured per-line list the
 * engine consumes on the order-received page.
 *
 * The list is localized as wp_sdtrk_wc.order.items (see
 * Wp_Sdtrk_WC_Integration::build_order_payload()) and seeded into the event by
 * the engine's collect_eventData(). Each platform catcher turns it into its
 * own multi-product payload (Meta contents[]/content_ids, GA items[], TikTok
 * contents[]). The shared event_id (= order id) deduplicates browser + server.
 */
class Wp_Sdtrk_WC_Order_Mapper
{
    /**
     * Structured per-line list for multi-product payloads.
     *
     * @param WC_Order $order
     * @return array<int, array{id:string, name:string, qty:int, price:float}>
     */
    public function lineItems($order): array
    {
        $lines = [];
        foreach ($order->get_items() as $item) {
            $qty = (int) $item->get_quantity();
            $lines[] = [
                'id'    => (string) $item->get_product_id(),
                'name'  => $item->get_name(),
                'qty'   => $qty,
                'price' => $qty > 0 ? ((float) $item->get_total()) / $qty : (float) $item->get_total(),
            ];
        }
        return $lines;
    }

    /**
     * Single-product line for the ViewItem and AddToCart payloads.
     *
     * Mirrors the lineItems() shape ({id,name,qty,price}) so the engine and the
     * platform catchers treat product-page / add-to-cart items exactly like
     * order line items. `price` is the per-unit display price (tax handling per
     * shop settings); falls back to the raw product price outside WooCommerce.
     *
     * @param WC_Product $product
     * @param int        $qty
     * @return array{id:string, name:string, qty:int, price:float}
     */
    public function productLine($product, int $qty = 1): array
    {
        $unit = function_exists('wc_get_price_to_display')
            ? (float) wc_get_price_to_display($product)
            : (float) $product->get_price();

        return [
            'id'    => (string) $product->get_id(),
            'name'  => $product->get_name(),
            'qty'   => $qty,
            'price' => $unit,
        ];
    }
}
