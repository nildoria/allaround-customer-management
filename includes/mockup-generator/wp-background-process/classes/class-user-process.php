<?php

//TODO: add last generated time meta and if possible add how many generated items plus images count

class MLUserProcess extends WP_Background_Process {

    use MLGetUserData;

    /**
     * @var string
     */
    protected $action = 'ml_user_process';

	// Option name to store task group completion status
    protected $option_name = 'ml_task_group_completion';

    // Array to store task IDs processed during execution
    protected $processed_task_groups = array();

    /**
	 * Identifier
	 *
	 * @var mixed
	 * @access protected
	 */
	public $identifier;

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
        $this->get_mockup( $item );

        // Update task group completion status
        $task_group = isset($item['task_group']) ? $item['task_group'] : null;

		if( $task_group === null ) {
			return false;
		}

		$this->log( "task_group $task_group" );

        if ($task_group && ! in_array($task_group, $this->processed_task_groups)) {
            $this->processed_task_groups[] = $task_group; // Store the task ID
        }

		$this->log( "update_task_group_completion" );
        $this->update_task_group_completion( $item, $task_group );

        return false;
    }

	/**
     * Start mockup generation for user
     *
     * @param array $item Task item
     */
    protected function start_mockup_generation( $item ) {
        $user_id = $item['user_id'];
		$this->log("start_mockup_generation for user_id: {$user_id}");
        update_user_meta( $user_id, 'ml_mockup_generation_running', true );
    }

    /**
	 * Is queue empty
	 *
	 * @return bool
	 */
	public function is_queue_empty() {
		global $wpdb;

		$table  = $wpdb->options;
		$column = 'option_name';

        $this->identifier = $this->prefix . '_' . $this->action;

		if ( is_multisite() ) {
			$table  = $wpdb->sitemeta;
			$column = 'meta_key';
		}

		$key = $wpdb->esc_like( $this->identifier . '_batch_' ) . '%';

		$count = $wpdb->get_var( $wpdb->prepare( "
			SELECT COUNT(*)
			FROM {$table}
			WHERE {$column} LIKE %s
		", $key ) );

		return ( $count > 0 ) ? false : true;
	}

    /**
     * Update task group completion status
     *
     * @param string $task_group Task group identifier
     */
    protected function update_task_group_completion( $item, $task_group ) {
		$task_group_completion = get_option( $this->option_name . '_' . $task_group, '' );
	
		if ( empty( $task_group_completion ) ) {
			$task_group_completion = 0;
		}

        $task_group_completion = (int) $task_group_completion;
		
		$this->log( "increament $task_group_completion" );

		$task_group_completion++;
	
		update_option( $this->option_name . '_' . $task_group, $task_group_completion );
	
		// Check if all tasks in this group have completed
		$total_tasks = $item['task_group_total'];
		
		// Debugging statements
		$this->log("Completion count for $task_group: " . $task_group_completion);
		$this->log("Total tasks for $task_group: " . $total_tasks);

		if ( $task_group_completion == $total_tasks ) {
			// Trigger email notification
			$user_id = $this->get_user_id_from_task_group( $task_group );
			
            $this->update_info( $user_id );
            
			$this->send_email_notification( $user_id );

			update_option( $this->option_name . '_' . $task_group, '' );
		}
	}

    protected function update_info( $user_id ) {
        // get current timestamp
        $end_time = time();
		update_user_meta($user_id, 'mockup_last_generated_time', $end_time);

        // Set ml_mockup_generation_running to false
        update_user_meta( $user_id, 'ml_mockup_generation_running', false );
    }

    /**
     * Get user ID from task group identifier
     *
     * @param string $task_group Task group identifier
     * @return int User ID
     */
    protected function get_user_id_from_task_group( $task_group ) {
        // Extract user ID from task group identifier (e.g., 'user_5' => 5)
        return (int) str_replace( 'user_', '', $task_group );
    }

    /**
     * Send email notification to user
     *
     * @param int $user_id User ID
     */
    protected function send_email_notification( $user_id ) {
        // Code to send email notification to user
        // You can use WordPress functions like wp_mail() to send the email
        $this->send_notification( $user_id );
		$this->log( "Email notification sent to user $user_id" );
		$this->log( "------------------------ $user_id ------------------------" );
        

		update_user_meta( $user_id, 'ml_mockup_generation_running', false );
        update_user_meta( $user_id, 'ml_mockup_generation_queue', false );
    }

    public function send_notification($user_id)
    {
        // Get the saved webhook endpoints (comma-separated)
        $ml_user_notification_endpoint = get_option('ml_user_notification_endpoint');
        // Fallback default if nothing saved
        $default_rest_url = "https://hook.eu1.make.com/f7ua6raebs2vwfguv1bfl8xqxlr613kf";

        // Parse the endpoints into an array
        // e.g. "https://hook1.com, https://hook2.com" => ["https://hook1.com", "https://hook2.com"]
        $endpoints = array_map('trim', explode(',', $ml_user_notification_endpoint));
        // If option is empty or invalid, use the fallback
        if (empty($ml_user_notification_endpoint)) {
            $endpoints = [$default_rest_url];
        }

        // Get user data so we can include user email in the body
        $user_info = get_userdata($user_id);
        if (!$user_info) {
            $this->log("User ID $user_id not found");
            return new WP_Error(
                'user_not_found',
                "User ID $user_id not found.",
                array('status' => 404)
            );
        }

        // Prepare request body
        $body = [
            "user_id" => $user_id,
            "user_email" => $user_info->user_email,
        ];

        // We'll collect responses or errors in an array
        $results = [];

        // Send to each endpoint
        foreach ($endpoints as $rest_url) {
            // If still empty or invalid, skip
            if (empty($rest_url)) {
                $this->log("No valid REST URL for user $user_id");
                $results[] = [
                    'endpoint' => $rest_url,
                    'result' => 'skipped_empty_url',
                ];
                continue;
            }

            // Perform request
            $response = wp_remote_post(esc_url($rest_url), [
                'body' => json_encode($body),
                'timeout' => 50,
                'headers' => ['Content-Type' => 'application/json'],
            ]);

            // Check for errors
            if (is_wp_error($response)) {
                $this->log("send_notification for $user_id failed to $rest_url: " . $response->get_error_message());
                $results[] = [
                    'endpoint' => $rest_url,
                    'result' => 'error',
                    'error' => $response->get_error_message(),
                ];
            } else {
                // Optionally inspect $response['body'] or $response['response']['code']
                $results[] = [
                    'endpoint' => $rest_url,
                    'result' => 'success',
                ];
            }
        }

        // Return aggregated result
        // Optionally, you could return just a message: "Notifications sent."
        return rest_ensure_response([
            'message' => "Attempted to send notification to user $user_id at all endpoints.",
            'results' => $results,
        ]);
    }

    protected function update_running_meta( $task_id, $value ) {
		if ($task_id) {
			// make array by "_" underscore separator and convert to array of $task_id
			$task_array = explode('_', $task_id);
			$item_type = isset( $task_array[0] ) ? $task_array[0] : '';
			$item_id = isset( $task_array[1] ) ? $task_array[1] : '';	

			if( ! empty( $task_id ) && ! empty( $item_type ) ) {
				switch ( $item_type ) {
					case 'product':
						update_post_meta( $item_id, 'ml_mockup_generation_running', $value );
						break;
					default:
						update_user_meta( $item_id, 'ml_mockup_generation_running', $value  );
				}
			}
		}
	}

    /**
     * Complete
     *
     * Override if applicable, but ensure that the below actions are
     * performed, or, call parent::complete().
     */
    protected function complete() {
        parent::complete();

        $this->log( "Background process completed!" );
        // Show notice to user or perform some other arbitrary task...

        // Process the completed task IDs
		foreach ($this->processed_task_groups as $task_group) {
            // Perform actions with $task_group
            // For example, update meta, log, etc.
			$this->log( "deleted taskGroup: " . $task_group );
            $this->update_running_meta( $task_group, false );

            update_option( $this->option_name . '_' . $task_group, '' );
        }

        // Clear the array of processed task IDs
        $this->processed_task_groups = array();

        
    }

}
