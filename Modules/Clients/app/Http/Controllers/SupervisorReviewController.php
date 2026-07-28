<?php

namespace Modules\Clients\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Support\Http\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Checklists\Models\CaseChecklistItem;
use Modules\Clients\Http\Resources\SupervisorReviewCommentResource;
use Modules\Clients\Http\Resources\SupervisorReviewResource;
use Modules\Clients\Models\Client;
use Modules\Clients\Models\SupervisorReview;
use Modules\Clients\Models\SupervisorReviewComment;
use Modules\Workflows\Models\CaseStep;
use Modules\Workflows\Services\CaseStepService;

class SupervisorReviewController extends Controller
{
    use ApiResponse;

    /** The runtime step key this stage owns. */
    private const STEP_KEY = 'supervisor_review';

    public function __construct(protected CaseStepService $steps)
    {
    }

    public function show(Request $request, Client $client)
    {
        $current = $this->currentReview($client, $request->user()?->id);

        $history = SupervisorReview::with(['reviewer', 'assignedTo'])
            ->withCount('comments')
            ->where('client_id', $client->id)
            ->orderByDesc('round')
            ->get();

        // One thread for the whole case, not per round - the reviewer needs the
        // full back-and-forth, and each comment carries its own round.
        $comments = SupervisorReviewComment::with(['user', 'review'])
            ->where('client_id', $client->id)
            ->orderBy('created_at')
            ->get();

        return $this->ok([
            'review' => $current ? new SupervisorReviewResource($current->loadCount('comments')) : null,
            'history' => SupervisorReviewResource::collection($history),
            'comments' => SupervisorReviewCommentResource::collection($comments),
            'readiness' => $this->readiness($client),
            'send_back_options' => $this->sendBackOptions($client),
        ]);
    }

    /** Sign the case off and move it to the next stage. */
    public function approve(Request $request, Client $client)
    {
        $validated = $request->validate([
            'decision_notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $review = DB::transaction(function () use ($request, $client, $validated) {
            $review = $this->currentReview($client) ?? $this->openRound($client, 1, $request->user()->id);

            if ($review->status !== 'pending') {
                throw ValidationException::withMessages([
                    'status' => 'This review round has already been decided.',
                ]);
            }

            $review->forceFill([
                'status' => 'approved',
                'reviewer_id' => $request->user()->id,
                'reviewed_at' => now(),
                'decision_notes' => $validated['decision_notes'] ?? $review->decision_notes,
            ])->save();

            // Sign-off completes the runtime step, so approval and the case
            // moving on are one action rather than two the operator must
            // remember to do in order.
            $step = $this->reviewStep($client);
            if ($step && ! in_array($step->status, ['completed', 'skipped'], true)) {
                $this->steps->advance($step, $request->user()->id, $validated['decision_notes'] ?? null);
            }

            return $review;
        });

        return $this->ok(
            new SupervisorReviewResource($review->refresh()->load(['reviewer', 'assignedTo'])),
            'Review approved and the case moved to the next stage.',
        );
    }

    /** Reject the work and push the case back to an earlier stage for correction. */
    public function sendBack(Request $request, Client $client)
    {
        $options = collect($this->sendBackOptions($client))->pluck('key')->all();

        $validated = $request->validate([
            'stage' => ['required', 'string', 'max:100'],
            'reason' => ['required', 'string', 'max:5000'],
            'assigned_to_user_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        if ($options && ! in_array($validated['stage'], $options, true)) {
            throw ValidationException::withMessages([
                'stage' => 'A case can only be sent back to a stage that comes before Supervisor Review.',
            ]);
        }

        $next = DB::transaction(function () use ($request, $client, $validated) {
            $review = $this->currentReview($client) ?? $this->openRound($client, 1, $request->user()->id);

            if ($review->status !== 'pending') {
                throw ValidationException::withMessages([
                    'status' => 'This review round has already been decided.',
                ]);
            }

            $review->forceFill([
                'status' => 'sent_back',
                'reviewer_id' => $request->user()->id,
                'reviewed_at' => now(),
                'decision_notes' => $validated['reason'],
                'sent_back_to_stage' => $validated['stage'],
                'assigned_to_user_id' => $validated['assigned_to_user_id'] ?? null,
            ])->save();

            $this->steps->sendBackTo($client, $validated['stage'], $validated['reason']);

            // The correction will come back for review, so the next round is
            // opened straight away and the comment thread continues into it.
            return $this->openRound($client, $review->round + 1, $request->user()->id);
        });

        return $this->ok([
            'review' => new SupervisorReviewResource($next->load(['reviewer', 'assignedTo'])),
            'send_back_options' => $this->sendBackOptions($client),
        ], 'Case sent back for correction.');
    }

    public function storeComment(Request $request, Client $client)
    {
        $validated = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
        ]);

        $review = $this->currentReview($client) ?? $this->openRound($client, 1, $request->user()->id);

        $comment = SupervisorReviewComment::create([
            'supervisor_review_id' => $review->id,
            'client_id' => $client->id,
            'user_id' => $request->user()->id,
            'body' => $validated['body'],
        ]);

        return $this->created(
            new SupervisorReviewCommentResource($comment->load(['user', 'review'])),
            'Comment added.',
        );
    }

    public function destroyComment(Request $request, Client $client, SupervisorReviewComment $comment)
    {
        abort_unless($comment->client_id === $client->id, 404);

        // A thread is a record of what was said - only the author may retract
        // their own line.
        if ($comment->user_id !== $request->user()->id) {
            return $this->fail('You can only delete your own comments.', 403);
        }

        $comment->delete();

        return $this->noContent('Comment deleted.');
    }

    /**
     * The open round for this client, creating round 1 on first read so the
     * screen always has something to show once the case reaches the stage.
     */
    private function currentReview(Client $client, ?int $userId = null): ?SupervisorReview
    {
        $latest = SupervisorReview::with(['reviewer', 'assignedTo'])
            ->where('client_id', $client->id)
            ->orderByDesc('round')
            ->first();

        if ($latest) {
            return $latest;
        }

        // Nothing yet: only open a round once the case has actually reached the
        // stage, so earlier-stage clients are not littered with empty reviews.
        $step = $this->reviewStep($client);
        $reached = $client->current_stage === self::STEP_KEY
            || ($step && in_array($step->status, ['in_progress', 'on_hold', 'waiting', 'completed'], true));

        return $reached ? $this->openRound($client, 1, $userId) : null;
    }

    private function openRound(Client $client, int $round, ?int $userId): SupervisorReview
    {
        return SupervisorReview::firstOrCreate(
            ['client_id' => $client->id, 'round' => $round],
            ['status' => 'pending', 'created_by' => $userId],
        )->load(['reviewer', 'assignedTo']);
    }

    private function reviewStep(Client $client): ?CaseStep
    {
        return CaseStep::where('client_id', $client->id)->where('key', self::STEP_KEY)->first();
    }

    /**
     * What the reviewer needs to see before signing off: the checklist position
     * and whether the runtime step is actually open.
     *
     * @return array<string, mixed>
     */
    private function readiness(Client $client): array
    {
        $items = CaseChecklistItem::where('client_id', $client->id)->get();
        $step = $this->reviewStep($client);

        return [
            'current_stage' => $client->current_stage,
            'step_status' => $step?->status,
            'step_id' => $step?->id,
            'is_current_stage' => $client->current_stage === self::STEP_KEY,
            'checklist_total' => $items->count(),
            'checklist_verified' => $items->where('status', 'verified')->count(),
            'checklist_rejected' => $items->where('status', 'rejected')->count(),
            'checklist_outstanding_required' => $this->steps->blockingChecklistCount($client->id),
            'documents_missing' => $items->where('document_required', true)->whereNull('linked_file_id')->count(),
        ];
    }

    /**
     * Stages the case can be pushed back to - every runtime step before
     * Supervisor Review. Empty when the runtime has not been initialized.
     *
     * @return array<int, array<string, mixed>>
     */
    private function sendBackOptions(Client $client): array
    {
        $steps = CaseStep::where('client_id', $client->id)->orderBy('order')->get();
        $review = $steps->firstWhere('key', self::STEP_KEY);

        if (! $review) {
            return [];
        }

        return $steps
            ->where('order', '<', $review->order)
            ->map(fn (CaseStep $step) => [
                'key' => $step->key,
                'name' => $step->name,
                'owner_role' => $step->owner_role,
            ])
            ->values()
            ->all();
    }
}
