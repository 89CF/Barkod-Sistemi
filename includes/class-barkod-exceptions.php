<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Base exception class for Barkod Sistemi plugin
 * Requirements: 5.5, 6.1
 */
class Barkod_Exception extends Exception {
    protected $error_code;
    
    public function __construct(string $message = "", string $error_code = "", int $code = 0, Throwable $previous = null) {
        parent::__construct($message, $code, $previous);
        $this->error_code = $error_code;
    }
    
    public function getErrorCode(): string {
        return $this->error_code;
    }
}

/**
 * Validation exception for input validation errors
 * Requirements: 1.3, 4.4, 6.1
 */
class Barkod_Validation_Exception extends Barkod_Exception {
    public function __construct(string $message = "Validation error", string $error_code = "validation_error", int $code = 400, Throwable $previous = null) {
        parent::__construct($message, $error_code, $code, $previous);
    }
}

/**
 * Database exception for database operation errors
 * Requirements: 5.5, 6.1
 */
class Barkod_Database_Exception extends Barkod_Exception {
    public function __construct(string $message = "Database error", string $error_code = "database_error", int $code = 500, Throwable $previous = null) {
        parent::__construct($message, $error_code, $code, $previous);
    }
}

/**
 * Authorization exception for permission errors
 * Requirements: 6.1
 */
class Barkod_Authorization_Exception extends Barkod_Exception {
    public function __construct(string $message = "Unauthorized access", string $error_code = "unauthorized", int $code = 403, Throwable $previous = null) {
        parent::__construct($message, $error_code, $code, $previous);
    }
}

/**
 * Rate limit exception for rate limiting errors
 * Requirements: 1.1, 2.4, 4.5
 */
class Barkod_Rate_Limit_Exception extends Barkod_Exception {
    public function __construct(string $message = "Rate limit exceeded", string $error_code = "rate_limit_exceeded", int $code = 429, Throwable $previous = null) {
        parent::__construct($message, $error_code, $code, $previous);
    }
}

/**
 * SMS exception for SMS sending errors
 * Requirements: 5.5
 */
class Barkod_SMS_Exception extends Barkod_Exception {
    public function __construct(string $message = "SMS sending failed", string $error_code = "sms_failed", int $code = 500, Throwable $previous = null) {
        parent::__construct($message, $error_code, $code, $previous);
    }
}

/**
 * Kumbara exception for kumbara-related errors
 * Requirements: 4.1, 4.2, 4.3
 */
class Barkod_Kumbara_Exception extends Barkod_Exception {
    public function __construct(string $message = "Kumbara error", string $error_code = "kumbara_error", int $code = 400, Throwable $previous = null) {
        parent::__construct($message, $error_code, $code, $previous);
    }
}

/**
 * Product exception for product-related errors
 * Requirements: 1.1, 1.5, 2.4
 */
class Barkod_Product_Exception extends Barkod_Exception {
    public function __construct(string $message = "Product error", string $error_code = "product_error", int $code = 400, Throwable $previous = null) {
        parent::__construct($message, $error_code, $code, $previous);
    }
}
