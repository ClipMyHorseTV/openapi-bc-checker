<?php

/**
 * This file is part of the OpenAPI BC Checker.
 *
 * Copyright (c) 2025 ClipMyHorse.TV Services & Development GmbH
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @license MIT
 */

declare(strict_types=1);

namespace ClipMyHorse\OpenApi\BcChecker\Model;

class HttpMethod
{
    public const GET = 'get';
    public const POST = 'post';
    public const PUT = 'put';
    public const DELETE = 'delete';
    public const PATCH = 'patch';
    public const OPTIONS = 'options';
    public const HEAD = 'head';
    public const TRACE = 'trace';

    /**
     * @return array<string>
     */
    public static function all(): array
    {
        return [
            self::GET,
            self::POST,
            self::PUT,
            self::DELETE,
            self::PATCH,
            self::OPTIONS,
            self::HEAD,
            self::TRACE,
        ];
    }
}

