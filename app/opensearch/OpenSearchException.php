<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2026 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 */

namespace app\opensearch;

use Exception;

class OpenSearchException extends Exception
{
    public function __construct(string $message, private readonly int $status, private readonly ?array $body = null)
    {
        parent::__construct($message, $status);
    }

    public function getStatus(): int
    {
        return $this->status;
    }

    public function getBody(): ?array
    {
        return $this->body;
    }
}
