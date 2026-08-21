<?php

namespace App\Http\Resources;

use Carbon\Carbon;
use App\Models\Subscription;
use Illuminate\Http\Resources\Json\JsonResource;

class SubscriptionResouorce extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {

        // This row is "active" only if IT is currently entitled — unexpired and
        // not refunded. (Previously it was wrongly flagged active when it merely
        // happened to be the first paid row, so an old plan showed as active.)
        $active = $this->status !== 'refunded'
            && $this->expired_at !== null
            && Carbon::parse($this->expired_at)->isFuture();

        return [
            'id' => $this->id,
            'active' => $active,
            'expired_at' => Carbon::parse($this->expired_at)->format('M d, Y H:i'),
            'amount' => $this->amount,
            'subscriptionPlan' => SubscriptionPlanResource::make($this->subscriptionPlan),
        ];
    }
}
