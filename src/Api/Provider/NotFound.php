<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Api\Provider;

use Mcp\Schema\Content\TextContent;
use Mcp\Schema\Result\CallToolResult;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Nothing to answer: a 404 for the API, an error the assistant reads for an MCP tool, which it can call again
 * otherwise (a protocol error would end its turn).
 */
final class NotFound
{
    /**
     * @param array<string, mixed> $context the context of the provider
     */
    public static function answer(string $message, array $context): CallToolResult
    {
        if (!isset($context['mcp_request'])) {
            throw new NotFoundHttpException($message);
        }

        return CallToolResult::error([new TextContent($message)]);
    }
}
