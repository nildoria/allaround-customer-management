<?php

//TODO: add last generated time meta and if possible add how many generated items plus images count

class MLBulkProcess extends WP_Background_Process {

    use MLGetUserData;

    /**
     * @var string
     */
    protected $action = 'ml_bulk_process';

	// Option name to ml_bulk_process_running
    protected $option_name = 'ml_bulk_process_running';

    // Array to store task IDs processed during execution
    protected $processed_task_groups = array();

    /**
	 * Really long running process
	 *
	 * @return int
	 */
	public function really_long_running_task() {
		return sleep( 2 );
	}

    /**
     * Task
     *
     * Override this method to perform any actions required on each
     * queue item. Return the modified item for further processing
     * in the next pass through. Or, return false to remove the
     * item from the queue.
     *
     * @param mixed $item Queue item to iterate over
     *
     * @return mixed
     */
    protected function task( $item ) {
        $this->start_mockup_generation( $item );

        $this->really_long_running_task();
        $this->send_request( $item );

        return false;
    }

    public function send_request( $item ) {
        $this->log( "send_request user_id: {$item}" );
        $this->log( print_r( $item, true ) );
        if( empty( $item ) )
            return false;

        $rest_url = home_url( 'wp-json/alaround-generate/v1/user-mockups' );
        $body = [ "user_id" => $item ];
        // Make an HTTP request to an external server to get the image data
        $response = wp_remote_post($rest_url, array(
            'body' => json_encode( $body ),
            'timeout' => 50,
            'headers' => array('Content-Type' => 'application/json'),
        ));

        if ( is_wp_error($response) ) {
            // Handle error
            return $response;
        }

        $response_data = wp_remote_retrieve_body($response);
        // Decode JSON string into an associative array
        $response_data = json_decode($response_data, true);

        if ( ! is_array($response_data) || ! isset( $response_data['message'] ) || empty( $response_data['message'] ) ) {
            // Handle the case where the response data couldn't be decoded
            return new WP_Error('json_decode_error', 'Error decoding JSON response', array('status' => 500));
        }

        $this->log( $response_data['message'] );

        return rest_ensure_response( $response_data['message'] );
    }


	/**
     * Start mockup generation for user
     *
     * @param array $item Task item
     */
    protected function start_mockup_generation( $item ) {}


    protected function update_info( $user_id ) {
        // get current timestamp
        $end_time = current_time('timestamp');
		update_user_meta($user_id, 'mockup_last_generated_time', $end_time);

        // Set ml_mockup_generation_running to false
        update_user_meta( $user_id, 'ml_mockup_generation_running', false );
    }

    /**
     * Complete
     *
     * Override if applicable, but ensure that the below actions are
     * performed, or, call parent::complete().
     */
    protected function complete() {
        parent::complete();

        $this->log( "---------- Bulk Background Process Completed!" );
        // Show notice to user or perform some other arbitrary task...

        update_option( $this->option_name, false );
    }

}
