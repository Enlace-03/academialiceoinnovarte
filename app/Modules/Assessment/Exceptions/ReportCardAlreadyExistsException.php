<?php

declare(strict_types=1);

namespace App\Modules\Assessment\Exceptions;

use RuntimeException;

/**
 * Ya existe el boletín 'total' de ese estudiante para ese grado y año
 * lectivo; regenerarlo exige pedirlo explícitamente (flag regenerate).
 */
final class ReportCardAlreadyExistsException extends RuntimeException {}
