<?php

namespace App\Filament\Member\Widgets;

use App\Enums\MemberStatus;
use App\Models\Member;
use App\Models\MembershipPlan;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Widgets\Widget;

class PendingActivationWidget extends Widget
{
    protected string $view = 'filament.member.widgets.pending-activation';

    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = -10;

    public ?int $planId = null;

    public static function canView(): bool
    {
        return Filament::auth()->user()?->member?->status === MemberStatus::Pending;
    }

    public function mount(): void
    {
        $this->planId = $this->member()?->selected_plan_id;
    }

    public function gantiPaket(): void
    {
        $member = $this->member();

        if ($member?->status !== MemberStatus::Pending) {
            abort(403);
        }

        $this->validate(['planId' => ['required', 'integer']], attributes: ['planId' => 'Paket']);

        $plan = MembershipPlan::active()->find($this->planId);

        if (! $plan) {
            $this->addError('planId', 'Paket tidak tersedia.');

            return;
        }

        $member->update(['selected_plan_id' => $plan->id]);

        Notification::make()->success()->title('Paket diganti')->send();
    }

    protected function getViewData(): array
    {
        $member = $this->member()?->load('selectedPlan');

        return [
            'plan' => $member?->selectedPlan,
            'plans' => MembershipPlan::active()->get(),
        ];
    }

    private function member(): ?Member
    {
        return Filament::auth()->user()?->member;
    }
}
