<?php

namespace App\Exceptions;

use Exception;

/**
 * Base class untuk error yang memang ditujukan untuk user (intended).
 * Setiap exception turunan akan dirender sebagai HTTP 400 dengan message-nya.
 */
class CustomException extends Exception
{
}
