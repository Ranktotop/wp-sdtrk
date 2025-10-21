<?php
class Wp_Sdtrk_Public_Form_Handler
{

    /**
     * Handles form submissions in the public area.
     *
     * This function is attached to the 'public_init' action hook and is called
     * on every public page load. It is responsible for handling form submissions
     * and redirecting the user to the appropriate page after submission.
     *
     * It currently handles the creation of new products, but could be extended
     * to handle other types of form submissions in the future.
     *
     * @since 1.0.0
     */
    public function handle_public_form_callback(): void
    {
        // Nonce check
        // We don't check nonce here, because each handler uses its own nonce field

        // User check
        // We don't check if user is logged in here, because some functions might be public to all
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return;
        }
        // weitere: elseif ($_POST['wp_sdtrk_form_action'] === '...') ...
    }
}
