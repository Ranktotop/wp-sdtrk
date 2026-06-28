<?php

/**
 * Maps a WooCommerce order to the canonical event-data array consumed by
 * Wp_Sdtrk_Tracker_Event (and, via that, by the platform trackers).
 *
 * Several keys are *lists* because Wp_Sdtrk_Tracker_Event reads them through
 * grabFirstValue() (eventName, value, prodId, prodName, userEmail, orderId,
 * userFirstName, userLastName). A few are single-valued (eventSource,
 * eventSourceAdress, eventSourceAgent) because they are read through
 * setAndFilled().
 *
 * The event_id is the WooCommerce order id (Wp_Sdtrk_Tracker_Event::getEventId()
 * returns the orderId when present), which is the shared id used to deduplicate
 * the browser and server Purchase events.
 *
 * Multi-product orders: the canonical event only carries the first product
 * (the event model is single-product). The full per-line list is available via
 * lineItems() and is fed to the platform payloads server-side.
 *
 * Design: tasks/wc-design.md
 */
class Wp_Sdtrk_WC_Order_Mapper
{
    /**
     * Translate a WC_Order into the canonical event-data array.
     *
     * @param WC_Order $order
     * @return array
     */
    public function toEventArray($order): array
    {
        $lines = $this->lineItems($order);
        $first = $lines[0] ?? null;

        $data = [
            'eventName'         => ['purchase'],
            'value'             => [(string) $order->get_total()],
            'orderId'           => [(string) $order->get_id()],
            'userEmail'         => [$order->get_billing_email()],
            'userFirstName'     => [$order->get_billing_first_name()],
            'userLastName'      => [$order->get_billing_last_name()],
            'eventSource'       => $order->get_checkout_order_received_url(),
            'eventSourceAdress' => $order->get_customer_ip_address(),
            'eventSourceAgent'  => $order->get_customer_user_agent(),
            'currency'          => $order->get_currency(),
            'utm'               => $this->utm($order),
        ];

        if ($first !== null) {
            $data['prodId']   = [$first['id']];
            $data['prodName'] = [$first['name']];
        }

        return $data;
    }

    /**
     * Structured per-line list for multi-product payloads (contents[]/items[]).
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
     * UTM parameters captured on the order (if any were persisted as order meta).
     *
     * @param WC_Order $order
     * @return array<string, string>
     */
    private function utm($order): array
    {
        if (!method_exists($order, 'get_meta')) {
            return [];
        }
        $utm = $order->get_meta('_wp_sdtrk_utm', true);
        return is_array($utm) ? $utm : [];
    }
}
