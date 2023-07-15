<?php
/**
 * Plugin API: WP_Hook class.
 *
 * @since 4.7.0
 */

/**
 * Core class used to implement action and filter hook functionality.
 *
 * @since 4.7.0
 * @see Iterator
 * @see ArrayAccess
 */
#[AllowDynamicProperties]
final class WP_Hook implements \ArrayAccess, \Iterator {

    /**
     * Hook callbacks.
     *
     * @since 4.7.0
     */
    public array $callbacks = [];

    /**
     * The priority keys of actively running iterations of a hook.
     *
     * @since 4.7.0
     */
    private array $iterations = [];

    /**
     * The current priority of actively running iterations of a hook.
     *
     * @since 4.7.0
     */
    private array $current_priority = [];

    /**
     * Number of levels this hook can be recursively called.
     *
     * @since 4.7.0
     */
    private int $nesting_level = 0;

    /**
     * Flag for if we're currently doing an action, rather than a filter.
     *
     * @since 4.7.0
     */
    private bool $doing_action = false;

    /**
     * Adds a callback function to a filter hook.
     *
     * @param string                $hook_name     the name of the filter to add the callback to
     * @param callable|array|string $callback      the callback to be run when the filter is applied
     * @param int                   $priority      The order in which the functions associated with a particular filter
     *                                             are executed. Lower numbers correspond with earlier execution,
     *                                             and functions with the same priority are executed in the order
     *                                             in which they were added to the filter.
     * @param int                   $accepted_args the number of arguments the function accepts
     *
     * @since 4.7.0
     */
    public function add_filter( string $hook_name, array|callable|string $callback, int $priority, int $accepted_args ): void {
        $idx = _wp_filter_build_unique_id( $hook_name, $callback, $priority );

        $priority_existed = isset( $this->callbacks[ $priority ] );

        $this->callbacks[ $priority ][ $idx ] = [ 'function' => $callback, 'accepted_args' => $accepted_args ];

        // If we're adding a new priority to the list, put them back in sorted order.
        if ( ! $priority_existed && count( $this->callbacks ) > 1 ) {
            ksort( $this->callbacks, SORT_NUMERIC );
        }

        if ( 0 < $this->nesting_level ) {
            $this->resort_active_iterations( $priority, $priority_existed );
        }
    }

    /**
     * Removes a callback function from a filter hook.
     *
     * @param string                $hook_name the filter hook to which the function to be removed is hooked
     * @param callable|array|string $callback  The callback to be removed from running when the filter is applied.
     *                                         This method can be called unconditionally to speculatively remove
     *                                         a callback that may or may not exist.
     * @param int                   $priority  the exact priority used when adding the original filter callback
     *
     * @return bool whether the callback existed before it was removed
     *
     * @since 4.7.0
     */
    public function remove_filter( string $hook_name, array|callable|string $callback, int $priority ): bool {
        $function_key = _wp_filter_build_unique_id( $hook_name, $callback, $priority );

        $exists = isset( $this->callbacks[ $priority ][ $function_key ] );

        if ( $exists ) {
            unset( $this->callbacks[ $priority ][ $function_key ] );

            if ( ! $this->callbacks[ $priority ] ) {
                unset( $this->callbacks[ $priority ] );

                if ( 0 < $this->nesting_level ) {
                    $this->resort_active_iterations();
                }
            }
        }

        return $exists;
    }

    /**
     * Checks if a specific callback has been registered for this hook.
     *
     * When using the `$callback` argument, this function may return a non-boolean value
     * that evaluates to false (e.g. 0), so use the `===` operator for testing the return value.
     *
     * @param string                     $hook_name Optional. The name of the filter hook. Default empty.
     * @param callable|bool|array|string $callback  Optional. The callback to check for.
     *                                              This method can be called unconditionally to speculatively check
     *                                              a callback that may or may not exist. Default false.
     *
     * @return bool|int If `$callback` is omitted, returns boolean for whether the hook has
     *                  anything registered. When checking a specific function, the priority
     *                  of that hook is returned, or false if the function is not attached.
     *
     * @since 4.7.0
     */
    public function has_filter( string $hook_name = '', array|bool|callable|string $callback = false ): bool|int {
        if ( false === $callback ) {
            return $this->has_filters();
        }

        $function_key = _wp_filter_build_unique_id( $hook_name, $callback, false );

        if ( ! $function_key ) {
            return false;
        }

        foreach ( $this->callbacks as $priority => $callbacks ) {
            if ( isset( $callbacks[ $function_key ] ) ) {
                return $priority;
            }
        }

        return false;
    }

    /**
     * Checks if any callbacks have been registered for this hook.
     *
     * @return bool true if callbacks have been registered for the current hook, otherwise false
     *
     * @since 4.7.0
     */
    public function has_filters(): bool {
        foreach ( $this->callbacks as $callbacks ) {
            if ( $callbacks ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Removes all callbacks from the current filter.
     *
     * @param bool|int $priority Optional. The priority number to remove. Default false.
     *
     * @since 4.7.0
     */
    public function remove_all_filters( bool|int $priority = false ): void {
        if ( ! $this->callbacks ) {
            return;
        }

        if ( false === $priority ) {
            $this->callbacks = [];
        } elseif ( isset( $this->callbacks[ $priority ] ) ) {
            unset( $this->callbacks[ $priority ] );
        }

        if ( 0 < $this->nesting_level ) {
            $this->resort_active_iterations();
        }
    }

    /**
     * Calls the callback functions that have been added to a filter hook.
     *
     * @param mixed $value the value to filter
     * @param array $args  Additional parameters to pass to the callback functions.
     *                     This array is expected to include $value at index 0.
     *
     * @return mixed the filtered value after all hooked functions are applied to it
     *
     * @since 4.7.0
     */
    public function apply_filters( mixed $value, array $args ): mixed {
        if ( ! $this->callbacks ) {
            return $value;
        }

        $nesting_level = $this->nesting_level++;

        $this->iterations[ $nesting_level ] = array_keys( $this->callbacks );
        $num_args = count( $args );

        do {
            $this->current_priority[ $nesting_level ] = current( $this->iterations[ $nesting_level ] );
            $priority = $this->current_priority[ $nesting_level ];

            foreach ( $this->callbacks[ $priority ] as $the_ ) {
                if ( ! $this->doing_action ) {
                    $args[0] = $value;
                }

                // Avoid the array_slice() if possible.
                if ( 0 === $the_['accepted_args'] ) {
                    $value = call_user_func( $the_['function'] );
                } elseif ( $the_['accepted_args'] >= $num_args ) {
                    $value = call_user_func_array( $the_['function'], $args );
                } else {
                    $value = call_user_func_array( $the_['function'], array_slice( $args, 0, (int) $the_['accepted_args'] ) );
                }
            }
        } while ( false !== next( $this->iterations[ $nesting_level ] ) );

        unset( $this->iterations[ $nesting_level ], $this->current_priority[ $nesting_level ] );

        --$this->nesting_level;

        return $value;
    }

    /**
     * Calls the callback functions that have been added to an action hook.
     *
     * @param array $args parameters to pass to the callback functions
     *
     * @since 4.7.0
     */
    public function do_action( array $args ): void {
        $this->doing_action = true;
        $this->apply_filters( '', $args );

        // If there are recursive calls to the current action, we haven't finished it until we get to the last one.
        if ( 0 === $this->nesting_level ) {
            $this->doing_action = false;
        }
    }

    /**
     * Processes the functions hooked into the 'all' hook.
     *
     * @param array $args Arguments to pass to the hook callbacks. Passed by reference.
     *
     * @since 4.7.0
     */
    public function do_all_hook( array &$args ): void {
        $nesting_level = $this->nesting_level++;
        $this->iterations[ $nesting_level ] = array_keys( $this->callbacks );

        do {
            $priority = current( $this->iterations[ $nesting_level ] );

            foreach ( $this->callbacks[ $priority ] as $the_ ) {
                call_user_func_array( $the_['function'], $args );
            }
        } while ( false !== next( $this->iterations[ $nesting_level ] ) );

        unset( $this->iterations[ $nesting_level ] );
        --$this->nesting_level;
    }

    /**
     * Return the current priority level of the currently running iteration of the hook.
     *
     * @return int|false If the hook is running, return the current priority level.
     *                   If it isn't running, return false.
     *
     * @since 4.7.0
     */
    public function current_priority(): bool|int {
        if ( false === current( $this->iterations ) ) {
            return false;
        }

        return current( current( $this->iterations ) );
    }

    /**
     * Normalizes filters set up before WordPress has initialized to WP_Hook objects.
     *
     * The `$filters` parameter should be an array keyed by hook name, with values
     * containing either:
     *
     *  - A `WP_Hook` instance
     *  - An array of callbacks keyed by their priorities
     *
     * Examples:
     *
     *     $filters = array(
     *         'wp_fatal_error_handler_enabled' => array(
     *             10 => array(
     *                 array(
     *                     'accepted_args' => 0,
     *                     'function'      => function() {
     *                         return false;
     *                     },
     *                 ),
     *             ),
     *         ),
     *     );
     *
     * @param array $filters Filters to normalize. See documentation above for details.
     *
     * @return WP_Hook[] array of normalized filters
     *
     * @since 4.7.0
     */
    public static function build_preinitialized_hooks( array $filters ): array {
        /** @var WP_Hook[] $normalized */
        $normalized = [];

        foreach ( $filters as $hook_name => $callback_groups ) {
            if ( is_object( $callback_groups ) && $callback_groups instanceof self ) {
                $normalized[ $hook_name ] = $callback_groups;

                continue;
            }

            $hook = new self();

            // Loop through callback groups.
            foreach ( $callback_groups as $priority => $callbacks ) {
                // Loop through callbacks.
                foreach ( $callbacks as $cb ) {
                    $hook->add_filter( $hook_name, $cb['function'], $priority, $cb['accepted_args'] );
                }
            }

            $normalized[ $hook_name ] = $hook;
        }

        return $normalized;
    }

    /**
     * Determines whether an offset value exists.
     *
     * @param mixed $offset an offset to check for
     *
     * @return bool true if the offset exists, false otherwise
     *
     * @since 4.7.0
     * @see https://www.php.net/manual/en/arrayaccess.offsetexists.php
     */
    #[ReturnTypeWillChange]
    public function offsetExists( mixed $offset ): bool {
        return isset( $this->callbacks[ $offset ] );
    }

    /**
     * Retrieves a value at a specified offset.
     *
     * @param mixed $offset the offset to retrieve
     *
     * @return mixed if set, the value at the specified offset, null otherwise
     *
     * @since 4.7.0
     * @see https://www.php.net/manual/en/arrayaccess.offsetget.php
     */
    #[ReturnTypeWillChange]
    public function offsetGet( mixed $offset ): mixed {
        return $this->callbacks[ $offset ] ?? null;
    }

    /**
     * Sets a value at a specified offset.
     *
     * @param mixed $offset the offset to assign the value to
     * @param mixed $value  the value to set
     *
     * @since 4.7.0
     * @see https://www.php.net/manual/en/arrayaccess.offsetset.php
     */
    #[ReturnTypeWillChange]
    public function offsetSet( mixed $offset, mixed $value ): void {
        if ( null === $offset ) {
            $this->callbacks[] = $value;
        } else {
            $this->callbacks[ $offset ] = $value;
        }
    }

    /**
     * Unsets a specified offset.
     *
     * @param mixed $offset the offset to unset
     *
     * @see https://www.php.net/manual/en/arrayaccess.offsetunset.php
     * @since 4.7.0
     */
    #[ReturnTypeWillChange]
    public function offsetUnset( mixed $offset ): void {
        unset( $this->callbacks[ $offset ] );
    }

    /**
     * Returns the current element.
     *
     * @return array of callbacks at current priority
     *
     * @see https://www.php.net/manual/en/iterator.current.php
     * @since 4.7.0
     */
    #[ReturnTypeWillChange]
    public function current(): mixed {
        return current( $this->callbacks );
    }

    /**
     * Moves forward to the next element.
     *
     * @return array of callbacks at next priority
     *
     * @see https://www.php.net/manual/en/iterator.next.php
     * @since 4.7.0
     */
    #[ReturnTypeWillChange]
    public function next(): void {
        next( $this->callbacks );
    }

    /**
     * Returns the key of the current element.
     *
     * @return mixed Returns current priority on success, or NULL on failure
     *
     * @see https://www.php.net/manual/en/iterator.key.php
     * @since 4.7.0
     */
    #[ReturnTypeWillChange]
    public function key(): mixed {
        return key( $this->callbacks );
    }

    /**
     * Checks if current position is valid.
     *
     * @return bool whether the current position is valid
     *
     * @see https://www.php.net/manual/en/iterator.valid.php
     * @since 4.7.0
     */
    #[ReturnTypeWillChange]
    public function valid(): bool {
        return key( $this->callbacks ) !== null;
    }

    /**
     * Rewinds the Iterator to the first element.
     *
     * @since 4.7.0
     * @see https://www.php.net/manual/en/iterator.rewind.php
     */
    #[ReturnTypeWillChange]
    public function rewind(): void {
        reset( $this->callbacks );
    }

    /**
     * Handles resetting callback priority keys mid-iteration.
     *
     * @param bool|int $new_priority     Optional. The priority of the new filter being added. Default false,
     *                                   for no priority being added.
     * @param bool     $priority_existed Optional. Flag for whether the priority already existed before the new
     *                                   filter was added. Default false.
     *
     * @since 4.7.0
     */
    private function resort_active_iterations( bool|int $new_priority = false, bool $priority_existed = false ): void {
        $new_priorities = array_keys( $this->callbacks );

        // If there are no remaining hooks, clear out all running iterations.
        if ( [] === $new_priorities ) {
            foreach ( array_keys( $this->iterations ) as $index ) {
                $this->iterations[ $index ] = $new_priorities;
            }

            return;
        }

        $min = min( $new_priorities );

        foreach ( $this->iterations as $index => &$iteration ) {
            $current = current( $iteration );

            // If we're already at the end of this iteration, just leave the array pointer where it is.
            if ( false === $current ) {
                continue;
            }

            $iteration = $new_priorities;

            if ( $current < $min ) {
                array_unshift( $iteration, $current );

                continue;
            }

            while ( current( $iteration ) < $current ) {
                if ( false === next( $iteration ) ) {
                    break;
                }
            }

            // If we have a new priority that didn't exist, but ::apply_filters() or ::do_action() thinks it's the current priority...
            if ( $new_priority === $this->current_priority[ $index ] && ! $priority_existed ) {
                /*
                 * ...and the new priority is the same as what $this->iterations thinks is the previous
                 * priority, we need to move back to it.
                 */

                $prev = false === current( $iteration ) ? end( $iteration ) : prev( $iteration );

                if ( false === $prev ) {
                    // Start of the array. Reset, and go about our day.
                    reset( $iteration );
                } elseif ( $new_priority !== $prev ) {
                    // Previous wasn't the same. Move forward again.
                    next( $iteration );
                }
            }
        }

        unset( $iteration );
    }
}
