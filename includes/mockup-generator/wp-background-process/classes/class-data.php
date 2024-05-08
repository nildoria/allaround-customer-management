<?php

trait MLGetUserData {

	/**
	 * Really long running process
	 *
	 * @return int
	 */
	public function really_long_running_task() {
		return sleep( 1 );
	}

	/**
	 * Log
	 *
	 * @param string $message
	 */
	public function log( $message ) {
		ml_error_log( print_r( $message, true ) );
	}

	/**
	 * Get lorem
	 *
	 * @param string $name
	 *
	 * @return string
	 */
	protected function get_mockup( $item ) {

		$task_group = isset($item['task_group']) ? $item['task_group'] : null;

		if( $task_group === null ) {
			return;
		}

        $this->log( "item details $task_group" );
        // $this->log( print_r( $item, true ) );

		// vercel end point as default url.
		$generate_api_url = 'https://generate-mockups-xi.vercel.app/api/create';
		
		$home_url = home_url();
		// check if home ur has localhost or mlimon.io domain
		if ( strpos( $home_url, 'localhost' ) !== false || strpos( $home_url, 'mlimon.io' ) !== false ) {
			$generate_api_url = 'http://localhost:3000/api/create';
		}

        // Make an HTTP request to an external server to get the image data
        $response = wp_remote_post($generate_api_url, array(
            'body' => json_encode( $item ),
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

        $this->log( "response $task_group" );
        // $this->log( print_r( $response_data, true ) );

        if ( ! is_array($response_data) || ! isset( $response_data['batch'] ) || empty( $response_data['batch'] ) ) {
            // Handle the case where the response data couldn't be decoded
            return new WP_Error('json_decode_error', 'Error decoding JSON response', array('status' => 500));
        } 

        $generator = new ALRN_Genrator();
		$generator->save_image_callback($response_data['batch'], 'array');

		return new WP_Error('json_decode_error', 'Error decoding JSON response', array('status' => 500));
	}

	/**
	 * Get the count of items in the queue.
	 *
	 * @return int
	 */
	protected function get_queue_count() {
		global $wpdb;

		$table = $wpdb->options;
		$column = 'option_name';

		if ( is_multisite() ) {
			$table = $wpdb->sitemeta;
			$column = 'meta_key';
		}

		$key = $wpdb->esc_like( $this->identifier . '_batch_' ) . '%';

		$count = $wpdb->get_var( $wpdb->prepare( "
			SELECT COUNT(*)
			FROM {$table}
			WHERE {$column} LIKE %s
		", $key ) );

		return $count;
	}

	/**
	 * Delete all batches.
	 *
	 * @return WC_Background_Process
	 */
	public function delete_all_batches() {
		global $wpdb;

		$table  = $wpdb->options;
		$column = 'option_name';

		if ( is_multisite() ) {
			$table  = $wpdb->sitemeta;
			$column = 'meta_key';
		}

		$key = $wpdb->esc_like( $this->identifier . '_batch_' ) . '%';

		$wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE {$column} LIKE %s", $key ) ); // @codingStandardsIgnoreLine.

		return $this;
	}

	/**
	 * Kill process.
	 *
	 * Stop processing queue items, clear cronjob and delete all batches.
	 */
	public function kill_process() {
		if ( ! $this->is_queue_empty() ) {
			$this->delete_all_batches();
			wp_clear_scheduled_hook( $this->cron_hook_identifier );
		}
	}

}