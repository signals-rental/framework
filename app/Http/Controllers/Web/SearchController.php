<?php

namespace App\Http\Controllers\Web;

use App\Enums\MembershipType;
use App\Models\Member;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class SearchController
{
    public function __invoke(Request $request): JsonResponse
    {
        Gate::authorize('members.view');

        $query = $request->string('q')->trim()->value();

        if (mb_strlen($query) < 2) {
            return response()->json(['members' => []]);
        }

        $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $query);

        $members = Member::query()
            ->where('name', 'ilike', '%'.$escaped.'%')
            ->orderBy('name')
            ->limit(8)
            ->get();

        $results = [];
        foreach ($members as $member) {
            /** @var MembershipType $type */
            $type = $member->membership_type;
            $words = preg_split('/\s+/', trim($member->name));
            $initials = mb_strtoupper(
                mb_substr($words[0] ?? '', 0, 1).mb_substr($words[1] ?? '', 0, 1)
            );
            $results[] = [
                'id' => $member->id,
                'name' => $member->name,
                'type' => $type->label(),
                'typeValue' => $type->value,
                'isActive' => $member->is_active,
                'initials' => $initials,
                'url' => route('members.show', $member->id),
            ];
        }

        return response()->json(['members' => $results]);
    }
}
