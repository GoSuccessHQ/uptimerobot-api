<?php

declare(strict_types=1);

namespace GoSuccess\UptimeRobot\Resource\Handwritten;

use DateTimeInterface;
use GoSuccess\UptimeRobot\Enum\UptimeTimeFrame;
use GoSuccess\UptimeRobot\Http\Method;
use GoSuccess\UptimeRobot\Model\UptimeStats;
use GoSuccess\UptimeRobot\Resource\MonitorResource;
use InvalidArgumentException;

/**
 * Hand-written methods of {@see MonitorResource}.
 */
trait MonitorOperations
{
    /**
     * Get the uptime statistics of all monitors.
     *
     * Aggregated over every monitor of the account, with the downtimes of the
     * time frame, newest first. The overall uptime is a fraction from 0 to 1,
     * unlike the percentage of uptime(). For a time frame of your own, pass
     * UptimeTimeFrame::Custom with $start and $end, which are sent as Unix
     * seconds; the API ignores them for the other time frames (verified live),
     * so passing them there is rejected.
     *
     * `GET /monitors/uptime-stats`
     *
     * @param UptimeTimeFrame        $timeFrame The period; the API matches it case-sensitively.
     * @param DateTimeInterface|null $start     Start of a Custom time frame.
     * @param DateTimeInterface|null $end       End of a Custom time frame, after $start (the API
     *                                          rejects an equal one, verified live).
     * @param int|null               $logLimit  The maximum number of downtimes in logs (1-500; the
     *                                          API's default is 50).
     *
     * @throws InvalidArgumentException If $start and $end do not fit the time frame.
     */
    public function uptimeStats(
        UptimeTimeFrame $timeFrame,
        ?DateTimeInterface $start = null,
        ?DateTimeInterface $end = null,
        ?int $logLimit = null,
    ): UptimeStats {
        if ($timeFrame !== UptimeTimeFrame::Custom) {
            if ($start !== null || $end !== null) {
                throw new InvalidArgumentException('$start and $end only apply to UptimeTimeFrame::Custom; the API ignores them for other time frames.');
            }
        } elseif ($start === null || $end === null) {
            throw new InvalidArgumentException('UptimeTimeFrame::Custom needs $start and $end.');
        } elseif ($start->getTimestamp() >= $end->getTimestamp()) {
            throw new InvalidArgumentException('$start must be at least one second before $end.');
        }

        $data = $this->connection->json(Method::Get, 'monitors/uptime-stats', [
            'timeFrame' => $timeFrame,
            'start' => $start?->getTimestamp(),
            'end' => $end?->getTimestamp(),
            'logLimit' => $logLimit,
        ]);

        return self::toModel(UptimeStats::class, $data);
    }
}
