<?php

declare(strict_types=1);

namespace App\Modules\OwnerApp\Http\Controllers;

use App\Modules\Identity\Contracts\StaffDirectory;
use App\Modules\Notifications\Contracts\Notifications;
use App\Modules\OwnerApp\Broadcasting\ApprovalDecided;
use App\Modules\OwnerApp\Broadcasting\ApprovalRequested;
use App\Modules\OwnerApp\Contracts\ApprovalKind;
use App\Modules\OwnerApp\Models\ApprovalRequest;
use App\Modules\OwnerApp\Support\ApprovalToken;
use App\Support\Audit\Auditor;
use App\Support\Exceptions\DomainRuleException;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * «اطلب موافقة»: the cashier sends the signed request the API refused with, the owner / a manager
 * answers from the app (push + live), or a manager types their PIN on the cashier's screen.
 */
final class ApprovalController
{
    public function __construct(
        private readonly CurrentTenant $tenant,
        private readonly Auditor $audit,
    ) {}

    /** The cashier asks. The same pending request again (a double tap) is returned, not repeated. */
    public function store(Request $request, Notifications $notifier): JsonResponse
    {
        $data = $this->token($request);
        $user = $request->user();

        $existing = ApprovalRequest::query()->where('requested_by', $user->id)->where('payload_hash', $data['hash'])
            ->where('status', 'pending')->where('expires_at', '>', now())->first();
        if ($existing !== null) {
            return response()->json(['data' => $existing->toPublic()]);
        }

        $approval = DB::transaction(function () use ($data, $user, $notifier): ApprovalRequest {
            $approval = ApprovalRequest::create([
                'tenant_id' => $this->tenant->idOrFail(),
                'branch_id' => $data['branch'],
                'kind' => $data['kind'],
                'summary' => $data['summary'],
                'amount' => $data['amount'],
                'payload_hash' => $data['hash'],
                'requested_by' => $user->id,
                'requested_by_name' => $user->name,
                'expires_at' => now()->addMinutes(ApprovalRequest::MINUTES),
            ]);
            $notifier->notify(
                'approval.requested',
                "{$user->name} محتاج موافقة: {$approval->kind->label()}",
                $approval->summary,
                'i-lucide-shield-question',
                '/owner?approval='.$approval->id,
                'owner_app.approve',
                urgent: true,
            );

            return $approval;
        });
        broadcast(new ApprovalRequested($approval->tenant_id, $approval->toPublic()));

        return response()->json(['data' => $approval->toPublic()], 201);
    }

    /** Waiting requests (approvers), newest first. */
    public function index(Request $request): JsonResponse
    {
        $status = (string) $request->query('status', 'pending');
        $rows = ApprovalRequest::query()
            ->when($status === 'pending', fn ($q) => $q->where('status', 'pending')->where('expires_at', '>', now()))
            ->latest()->limit(50)->get();

        return response()->json(['data' => $rows->map(fn (ApprovalRequest $a) => $a->toPublic())->values()]);
    }

    /** The requester polls this while their WebSocket is down; approvers may read any. */
    public function show(Request $request, ApprovalRequest $approval): JsonResponse
    {
        abort_unless($approval->requested_by === $request->user()->id || $request->user()->can('owner_app.approve'), 403);

        return response()->json(['data' => $approval->toPublic()]);
    }

    public function approve(Request $request, ApprovalRequest $approval): JsonResponse
    {
        return $this->decide($request, $approval, 'approved');
    }

    public function deny(Request $request, ApprovalRequest $approval): JsonResponse
    {
        return $this->decide($request, $approval, 'denied');
    }

    /** A manager at the counter types their PIN on the cashier's screen: approved at once. */
    public function pin(Request $request, StaffDirectory $staff): JsonResponse
    {
        $request->validate(['pin' => ['required', 'string', 'regex:/^\d{4,6}$/']]);
        $data = $this->token($request);
        $approver = $staff->matchPin('owner_app.approve', (string) $request->input('pin'))
            ?? throw new DomainRuleException('الـ PIN غلط، أو صاحبه مش معاه صلاحية الموافقة.', 'pin_incorrect');
        $user = $request->user();

        $approval = DB::transaction(function () use ($data, $user, $approver): ApprovalRequest {
            $approval = ApprovalRequest::create([
                'tenant_id' => $this->tenant->idOrFail(),
                'branch_id' => $data['branch'],
                'kind' => $data['kind'],
                'summary' => $data['summary'],
                'amount' => $data['amount'],
                'payload_hash' => $data['hash'],
                'requested_by' => $user->id,
                'requested_by_name' => $user->name,
                'status' => 'approved',
                'via' => 'pin',
                'decided_by' => $approver['id'],
                'decided_by_name' => $approver['name'],
                'decided_at' => now(),
                'expires_at' => now()->addMinutes(ApprovalRequest::MINUTES),
            ]);
            $this->audit->record('approvals.approved', "{$approver['name']} وافق بالـ PIN لـ {$user->name}: {$approval->summary}", $approval);

            return $approval;
        });

        return response()->json(['data' => $approval->toPublic()], 201);
    }

    private function decide(Request $request, ApprovalRequest $approval, string $status): JsonResponse
    {
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:300']]);
        $user = $request->user();

        $approval = DB::transaction(function () use ($approval, $status, $data, $user): ApprovalRequest {
            $locked = ApprovalRequest::query()->lockForUpdate()->findOrFail($approval->id);
            if ($locked->currentStatus() !== 'pending') {
                throw new DomainRuleException(
                    $locked->currentStatus() === 'expired' ? 'الطلب ده خلص وقته.' : "الطلب ده اترد عليه قبل كده ({$locked->decided_by_name}).",
                    'approval_closed',
                    409,
                    ['approval' => $locked->toPublic()],
                );
            }
            $locked->fill([
                'status' => $status,
                'via' => 'app',
                'decided_by' => $user->id,
                'decided_by_name' => $user->name,
                'decided_at' => now(),
                'reason' => $data['reason'] ?? null,
                // An approval stays usable a few minutes after it's given.
                'expires_at' => now()->addMinutes(ApprovalRequest::MINUTES),
            ])->save();
            $this->audit->record(
                "approvals.{$status}",
                ($status === 'approved' ? "وافق لـ {$locked->requested_by_name}: " : "رفض طلب {$locked->requested_by_name}: ").$locked->summary.($locked->reason ? " — {$locked->reason}" : ''),
                $locked,
            );

            return $locked;
        });
        broadcast(new ApprovalDecided($approval->tenant_id, $approval->requested_by, $approval->toPublic()));

        return response()->json(['data' => $approval->toPublic()]);
    }

    /**
     * @return array{kind: string, hash: string, summary: string, amount: int, branch: string|null, user: string, exp: int}
     */
    private function token(Request $request): array
    {
        $request->validate(['token' => ['required', 'string', 'max:4000']]);
        $data = ApprovalToken::read((string) $request->input('token'));
        if ($data === null || $data['user'] !== $request->user()->id) {
            throw new DomainRuleException('الطلب ده مش صالح أو قديم. جرّب العملية تاني.', 'approval_token_invalid');
        }
        ApprovalKind::from($data['kind']);

        return $data;
    }
}
