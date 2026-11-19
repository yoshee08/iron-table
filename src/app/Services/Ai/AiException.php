<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Services\Ai;

use RuntimeException;

/**
 * An AI request failed. The message is for the moderator.
 */
class AiException extends RuntimeException {}
