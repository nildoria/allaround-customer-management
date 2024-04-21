<?php

class MLProductProcess extends WP_Background_Process {

    use MLGetUserData;

    /**
     * @var string
     */
    protected $action = 'ml_product_process';

	// Option name to store task group completion status
    protected $option_name = 'ml_task_products_completion';

    // Array to store task IDs processed during execution
    protected $processed_task_groups = array();

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
        $product_id = $item['product_id'];
        update_post_meta( $product_id, 'ml_mockup_generation_running', true );
    }

    /**
     * Update task group completion status
     *
     * @param string $task_group Task group identifier
     */
    protected function update_task_group_completion( $item, $task_group ) {
		$task_group_completion = get_option( $this->option_name . '_' . $task_group, array() );
	
		if ( ! isset( $task_group_completion[ $task_group ] ) ) {
			$task_group_completion[ $task_group ] = 0;
		}
		
		$this->log( print_r( $task_group_completion, true ) );

		$task_group_completion[ $task_group ]++;
	
		update_option( $this->option_name . '_' . $task_group, $task_group_completion );
	
		// Check if all tasks in this group have completed
		$total_tasks = $item['task_group_total'];
		
		// Debugging statements
		$this->log("Completion count for $task_group: " . $task_group_completion[$task_group]);
		$this->log("Total tasks for $task_group: " . $total_tasks);

		if ( $task_group_completion[ $task_group ] == $total_tasks ) {
			// Trigger email notification
			$product_id = $this->get_product_id_from_task_group( $task_group );

			// Set ml_mockup_generation_running to false
            update_post_meta( $product_id, 'ml_mockup_generation_running', false );
	
			// Reset completion status for this group
			unset( $task_group_completion[ $task_group ] );
			update_option( $this->option_name . '_' . $task_group, $task_group_completion );
		}
	}

    /**
     * Get user ID from task group identifier
     *
     * @param string $task_group Task group identifier
     * @return int User ID
     */
    protected function get_product_id_from_task_group( $task_group ) {
        // Extract user ID from task group identifier (e.g., 'user_5' => 5)
        return (int) str_replace( 'product_', '', $task_group );
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
