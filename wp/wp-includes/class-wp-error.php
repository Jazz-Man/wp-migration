<?php
/**
 * WordPress Error API.
 */

use JetBrains\PhpStorm\Pure;

/**
 * WordPress Error class.
 *
 * Container for checking for WordPress errors and error messages. Return
 * WP_Error and use is_wp_error() to check if this class is returned. Many
 * core WordPress functions pass this class in the event of an error and
 * if not handled properly will result in code errors.
 *
 * @since 2.1.0
 */
#[AllowDynamicProperties]
class WP_Error {

    /**
     * Stores the list of errors.
     *
     * @since 2.1.0
     */
    public array $errors = [];

    /**
     * Stores the most recently added data for each error code.
     *
     * @since 2.1.0
     */
    public array $error_data = [];

    /**
     * Stores previously added data added for error codes, oldest-to-newest by code.
     *
     * @since 5.6.0
     *
     * @var array[]
     */
    protected array $additional_data = [];

    /**
     * Initializes the error.
     *
     * If `$code` is empty, the other parameters will be ignored.
     * When `$code` is not empty, `$message` will be used even if
     * it is empty. The `$data` parameter will be used only if it
     * is not empty.
     *
     * Though the class is constructed with a single error code and
     * message, multiple codes can be added using the `add()` method.
     *
     * @param int|string $code    error code
     * @param string     $message error message
     * @param mixed      $data    Optional. Error data. Default empty string.
     *
     * @since 2.1.0
     */
    public function __construct( int|string $code = '', string $message = '', mixed $data = '' ) {
        if ( '' === $code || 0 === $code ) {
            return;
        }

        $this->add( $code, $message, $data );
    }

    /**
     * Retrieves all error codes.
     *
     * @return array list of error codes, if available
     *
     * @since 2.1.0
     */
    #[Pure]
    public function get_error_codes(): array {
        if ( ! $this->has_errors() ) {
            return [];
        }

        return array_keys( $this->errors );
    }

    /**
     * Retrieves the first error code available.
     *
     * @return string|int empty string, if no error codes
     *
     * @since 2.1.0
     */
    #[Pure]
    public function get_error_code(): int|string {
        $codes = $this->get_error_codes();

        if ( [] === $codes ) {
            return '';
        }

        return $codes[0];
    }

    /**
     * Retrieves all error messages, or the error messages for the given error code.
     *
     * @param int|string $code Optional. Error code to retrieve the messages for.
     *                         Default empty string.
     *
     * @return string[] error strings on success, or empty array if there are none
     *
     * @since 2.1.0
     */
    public function get_error_messages( int|string $code = '' ): array {
        // Return all messages if no code specified.
        if ( '' === $code || 0 === $code ) {
            $all_messages = [];

            foreach ( $this->errors as $code => $messages ) {
                $all_messages = array_merge( $all_messages, $messages );
            }

            return $all_messages;
        }

        return $this->errors[ $code ] ?? [];
    }

    /**
     * Gets a single error message.
     *
     * This will get the first message available for the code. If no code is
     * given then the first code available will be used.
     *
     * @param int|string $code Optional. Error code to retrieve the message for.
     *                         Default empty string.
     *
     * @return string the error message
     *
     * @since 2.1.0
     */
    public function get_error_message( int|string $code = '' ): string {
        if ( '' === $code || 0 === $code ) {
            $code = $this->get_error_code();
        }

        $messages = $this->get_error_messages( $code );

        if ( [] === $messages ) {
            return '';
        }

        return $messages[0];
    }

    /**
     * Retrieves the most recently added error data for an error code.
     *
     * @param int|string $code Optional. Error code. Default empty string.
     *
     * @return mixed error data, if it exists
     *
     * @since 2.1.0
     */
    #[Pure]
    public function get_error_data( int|string $code = '' ): mixed {
        if ( '' === $code || 0 === $code ) {
            $code = $this->get_error_code();
        }

        if ( isset( $this->error_data[ $code ] ) ) {
            return $this->error_data[ $code ];
        }
    }

    /**
     * Verifies if the instance contains errors.
     *
     * @return bool if the instance contains errors
     *
     * @since 5.1.0
     */
    public function has_errors(): bool {
        return [] !== $this->errors;
    }

    /**
     * Adds an error or appends an additional message to an existing error.
     *
     * @param int|string $code    error code
     * @param string     $message error message
     * @param mixed      $data    Optional. Error data. Default empty string.
     *
     * @since 2.1.0
     */
    public function add( int|string $code, string $message, mixed $data = '' ): void {
        $this->errors[ $code ][] = $message;

        if ( ! empty( $data ) ) {
            $this->add_data( $data, $code );
        }

        /**
         * Fires when an error is added to a WP_Error object.
         *
         * @param string|int $code     error code
         * @param string     $message  error message
         * @param mixed      $data     Error data. Might be empty.
         * @param WP_Error   $wp_error the WP_Error object
         *
         * @since 5.6.0
         */
        do_action( 'wp_error_added', $code, $message, $data, $this );
    }

    /**
     * Adds data to an error with the given code.
     *
     * @param mixed      $data error data
     * @param int|string $code error code
     *
     * @since 2.1.0
     * @since 5.6.0 Errors can now contain more than one item of error data. {@see WP_Error::$additional_data}.
     */
    public function add_data( mixed $data, int|string $code = '' ): void {
        if ( '' === $code || 0 === $code ) {
            $code = $this->get_error_code();
        }

        if ( isset( $this->error_data[ $code ] ) ) {
            $this->additional_data[ $code ][] = $this->error_data[ $code ];
        }

        $this->error_data[ $code ] = $data;
    }

    /**
     * Retrieves all error data for an error code in the order in which the data was added.
     *
     * @param int|string $code error code
     *
     * @return mixed[] array of error data, if it exists
     *
     * @since 5.6.0
     */
    #[Pure]
    public function get_all_error_data( int|string $code = '' ): array {
        if ( '' === $code || 0 === $code ) {
            $code = $this->get_error_code();
        }

        $data = [];

        if ( isset( $this->additional_data[ $code ] ) ) {
            $data = $this->additional_data[ $code ];
        }

        if ( isset( $this->error_data[ $code ] ) ) {
            $data[] = $this->error_data[ $code ];
        }

        return $data;
    }

    /**
     * Removes the specified error.
     *
     * This function removes all error messages associated with the specified
     * error code, along with any error data for that code.
     *
     * @param int|string $code error code
     *
     * @since 4.1.0
     */
    public function remove( int|string $code ): void {
        unset( $this->errors[ $code ], $this->error_data[ $code ], $this->additional_data[ $code ] );
    }

    /**
     * Merges the errors in the given error object into this one.
     *
     * @param WP_Error $error error object to merge
     *
     * @since 5.6.0
     */
    public function merge_from( self $error ): void {
        static::copy_errors( $error, $this );
    }

    /**
     * Exports the errors in this object into the given one.
     *
     * @param WP_Error $error error object to export into
     *
     * @since 5.6.0
     */
    public function export_to( self $error ): void {
        static::copy_errors( $this, $error );
    }

    /**
     * Copies errors from one WP_Error instance to another.
     *
     * @param WP_Error $from the WP_Error to copy from
     * @param WP_Error $to   the WP_Error to copy to
     *
     * @since 5.6.0
     */
    protected static function copy_errors( self $from, self $to ): void {
        foreach ( $from->get_error_codes() as $code ) {
            foreach ( $from->get_error_messages( $code ) as $error_message ) {
                $to->add( $code, $error_message );
            }

            foreach ( $from->get_all_error_data( $code ) as $data ) {
                $to->add_data( $data, $code );
            }
        }
    }
}
