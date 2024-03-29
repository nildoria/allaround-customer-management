<?php

class MLBackgroundProcess extends WP_Background_Process {

	use MLGetUserData;

	/**
	 * @var string
	 */
	protected $action = 'ml_background_process';

	// Array to store task IDs processed during execution
    protected $processed_task_ids = array();

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
		// $user_data = $this->get_userdata( $item );

		$this->really_long_running_task();
		$this->get_mockup( $item );

		// Assume $item contains product information with a key 'task_id'
		$task_id = isset($item['task_id']) ? $item['task_id'] : null;

		if ($task_id && ! in_array($task_id, $this->processed_task_ids)) {
            $this->update_running_meta( $task_id, true );
            $this->processed_task_ids[] = $task_id; // Store the task ID
        }

		return false;
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
		foreach ($this->processed_task_ids as $task_id) {
            // Perform actions with $task_id
            // For example, update meta, log, etc.
			$this->log( "deleted taskID: " . $task_id );
            $this->update_running_meta( $task_id, false );
        }

        // Clear the array of processed task IDs
        $this->processed_task_ids = array();
	}

}