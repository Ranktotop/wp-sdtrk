<?php
class Wp_Sdtrk_Public_Ajax_Handler
{
    /**
     * Ajax generic Callback-Function
     */
    public function handle_public_ajax_callback()
    {
        /**
         * Do not forget to check your nonce for security!
         *
         * @link https://codex.wordpress.org/Function_Reference/wp_verify_nonce
         */

        // Nonce check
        if (! wp_verify_nonce($_POST['_nonce'], 'security_wp-sdtrk')) {
            wp_send_json_error();
            die();
        }

        // User check
        // We don't check if user is logged in here, because some functions might be public to all

        // Check if given function exists
        $functionName = $_POST['func'];
        if (! method_exists($this, $functionName)) {
            wp_send_json_error();
            die();
        }

        $_POST['data'] = (isset($_POST['data'])) ? $_POST['data'] : array();
        $_POST['meta'] = (isset($_POST['meta'])) ? $_POST['meta'] : array();

        // Call function and send back result
        $result = $this->$functionName($_POST['data'], $_POST['meta']);
        die(json_encode($result));
    }

    /**
     * This function is called after Pageload (User-Browser)
     *
     * @param array $data
     * @param array $meta
     * @return array
     */
    public function validateTracker($data, $debugMode = false)
    {
        // Base-Checks
        if (! isset($data['event']) || ! isset($data['type']) || ! isset($data['handler']) || ! isset($data['data'])) {
            return array(
                'state' => false
            );
        }
        // Harden the dispatch inputs (untrusted, public AJAX):
        //  - type: only [a-z] before building the class name
        //  - handler: restrict to the known event categories
        //  - data: sanitize the scalar side-channel values (fbp/fbc/cid/…)
        $type    = preg_replace('/[^a-z]/', '', strtolower((string) $data['type']));
        $handler = (string) $data['handler'];
        $allowedHandlers = array('Page', 'Event', 'Scroll', 'Time', 'Click', 'Visibility');
        if (! in_array($handler, $allowedHandlers, true)) {
            return array('state' => false, 'debug' => false);
        }
        $sideData = is_array($data['data']) ? $this->sanitize_side_data($data['data']) : array();

        // Check for handler and run it. Event-field sanitization happens in the
        // Wp_Sdtrk_Tracker_Event getters (see class-wp-sdtrk-tracker-event.php).
        $event = new Wp_Sdtrk_Tracker_Event($data['event']);
        $className = 'Wp_Sdtrk_Tracker_' . ucfirst($type);
        if (class_exists($className)) {
            $tracker = new $className();
            if (method_exists($tracker, 'fireTracking_Server') && method_exists($tracker, 'setAndGetDebugMode_frontend')) {
                return array(
                    'debug' => $tracker->setAndGetDebugMode_frontend($debugMode),
                    'state' => $tracker->fireTracking_Server($event, $handler, $sideData)
                );
            }
        }
        return array(
            'state' => false,
            'debug' => false
        );
    }

    /**
     * Sanitize the scalar values of the handler side-channel data (fbp, fbc,
     * cid, gclid, ttc, ttp, hash, tag, …). Bools/ints/floats are kept as-is;
     * strings get sanitize_text_field; nested arrays are sanitized recursively.
     *
     * @param array $data
     * @return array
     */
    private function sanitize_side_data(array $data): array
    {
        $clean = array();
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $clean[$key] = $this->sanitize_side_data($value);
            } elseif (is_string($value)) {
                $clean[$key] = sanitize_text_field($value);
            } else {
                $clean[$key] = $value;
            }
        }
        return $clean;
    }
}
