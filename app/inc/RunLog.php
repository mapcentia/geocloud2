<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2026 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 */

namespace app\inc;

/**
 * In-memory copy of a scheduler run's stdout, kept for the run registry
 * (started_jobs.log). get.php feeds it from an output-buffer callback, so
 * every print/echo still reaches stdout (and the per-job log file) unchanged.
 * Only the last $maxBytes are kept; when something was dropped the contents
 * start with a line saying so.
 */
final class RunLog
{
    private string $buffer = '';
    private bool $truncated = false;

    public function __construct(private readonly int $maxBytes = SchedulerLock::LOG_MAX_BYTES)
    {
    }

    public function append(string $chunk): void
    {
        if ($chunk === '') {
            return;
        }
        $this->buffer .= $chunk;
        if (strlen($this->buffer) > $this->maxBytes) {
            $this->buffer = substr($this->buffer, -$this->maxBytes);
            $this->truncated = true;
        }
    }

    /** The captured output, with a truncation header when the head was dropped. */
    public function contents(): string
    {
        return ($this->truncated ? SchedulerLock::truncationHeader($this->maxBytes) : '') . $this->buffer;
    }

    /** Bytes currently held (without the header). */
    public function bytes(): int
    {
        return strlen($this->buffer);
    }

    public function isTruncated(): bool
    {
        return $this->truncated;
    }
}
