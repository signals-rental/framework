<?php

namespace App\Exceptions\Auth;

use RuntimeException;

/**
 * Thrown when a magic-link sign-in link cannot be consumed (spec §8, §10).
 *
 * Deliberately reasonless: every failure path — unknown/expired/consumed token,
 * inactive user, feature disabled, or SSO now enforced — throws the same
 * exception so the consume controller can surface a single generic message and
 * never leak which check failed.
 */
class InvalidMagicLinkException extends RuntimeException {}
