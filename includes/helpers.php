<?php
/**
 * Helper Functions
 * 
 * Utility functions used across the system.
 */

/**
 * Validate Malaysian phone number format
 * Must start with 0, digits only, 10-11 characters total
 *
 * @param string $phone Phone number to validate
 * @return string|false Cleaned phone number or false if invalid
 */
function validatePhone($phone) {
    $phone = preg_replace('/[\s\-]/', '', trim($phone));
    if (!preg_match('/^0[0-9]{9,10}$/', $phone)) {
        return false;
    }
    return $phone;
}
