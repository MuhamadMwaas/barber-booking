<?php

namespace App\Exceptions;

/**
 * Thrown by LeaveReassignmentService::grant() when the leave would cover open
 * bookings and the caller has not (or no longer) confirmed what happens to them.
 *
 * Same pattern as PushRequiredException: the caller catches it and shows the
 * plan — which bookings move to whom, which have to be cancelled — and re-calls
 * grant() with the plan's signature once the user agrees.
 *
 * $changed is true when the user DID confirm a plan, but the calendar moved
 * under it before the lock was taken (a colleague got booked, a booking was
 * cancelled). The plan carried here is the fresh one; the user confirms again.
 */
class LeaveReassignmentRequiredException extends \RuntimeException
{
    /**
     * @param  array  $plan  LeaveReassignmentService::plan() output.
     */
    public function __construct(public array $plan, public bool $changed = false)
    {
        parent::__construct($changed
            ? 'The reassignment plan changed before it could be applied'
            : 'The leave covers open bookings');
    }
}
