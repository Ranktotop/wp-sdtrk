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
        // Check for handler and run it
        $event = new Wp_Sdtrk_Tracker_Event($data['event']);
        $className = 'Wp_Sdtrk_Tracker_' . ucfirst($data['type']);
        if (class_exists($className)) {
            $tracker = new $className();
            if (method_exists($tracker, 'fireTracking_Server') && method_exists($tracker, 'setAndGetDebugMode_frontend')) {
                return array(
                    'debug' => $tracker->setAndGetDebugMode_frontend($debugMode),
                    'state' => $tracker->fireTracking_Server($event, $data['handler'], $data['data'])
                );
            }
        }
        return array(
            'state' => false,
            'debug' => false
        );
    }
}
