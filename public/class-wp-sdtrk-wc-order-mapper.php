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
                // Prefer the variation id so order items match the product feed and
                // the AddToCart/ViewItem ids (catalog-level consistency); fall back
                // to the parent product id for simple products.
                'id'    => (string) ($item->get_variation_id() ?: $item->get_product_id()),
                'name'  => $item->get_name(),
                'qty'   => $qty,
                'price' => $qty > 0 ? ((float) $item->get_total()) / $qty : (float) $item->get_total(),
            ];
        }
        return $lines;
    }

    /**
     * Structured per-line list for the cart (begin_checkout payload).
     *
     * Mirrors the lineItems()/productLine() shape ({id,name,qty,price}) so the
     * engine and platform catchers treat checkout items exactly like order/ATC
     * items. `price` is the per-unit value derived from the cart line total
     * (after discount, before shipping), matching how lineItems() prices an
     * order line. id prefers the variation id for catalog-level consistency.
     *
     * @param WC_Cart $cart
     * @return array<int, array{id:string, name:string, qty:int, price:float}>
     */
    public function cartLines($cart): array
    {
        $lines = [];
        foreach ($cart->get_cart() as $cart_item) {
            $qty        = (int) ($cart_item['quantity'] ?? 0);
            $line_total = isset($cart_item['line_total']) ? (float) $cart_item['line_total'] : 0.0;
            $product    = isset($cart_item['data']) ? $cart_item['data'] : null;
            $variation  = (int) ($cart_item['variation_id'] ?? 0);
            $product_id = (int) ($cart_item['product_id'] ?? 0);
            $lines[] = [
                'id'    => (string) ($variation ?: $product_id),
                'name'  => $product ? $product->get_name() : '',
                'qty'   => $qty,
                'price' => $qty > 0 ? $line_total / $qty : $line_total,
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
