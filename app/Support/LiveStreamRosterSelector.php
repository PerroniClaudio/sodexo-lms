<?php

namespace App\Support;

class LiveStreamRosterSelector
{
    /**
     * @param  array<int, array{id:int,user_id:int}>  $participants
     * @param  array<int, int>  $pinnedUserIds
     * @return array<int, array{id:int,user_id:int}>
     */
    public function forTeacher(array $participants, array $pinnedUserIds, int $seed, ?int $dominantSpeakerUserId = null): array
    {
        return $this->select(
            participants: $participants,
            limit: 9,
            pinnedUserIds: $pinnedUserIds,
            seed: $seed,
            dominantSpeakerUserId: $dominantSpeakerUserId,
        );
    }

    /**
     * @param  array<int, array{id:int,user_id:int}>  $participants
     * @return array<int, array{id:int,user_id:int}>
     */
    public function forViewer(array $participants, int $seed): array
    {
        return $this->select(
            participants: $participants,
            limit: 5,
            pinnedUserIds: [],
            seed: $seed,
            dominantSpeakerUserId: null,
        );
    }

    /**
     * @param  array<int, array{id:int,user_id:int}>  $participants
     * @param  array<int, int>  $pinnedUserIds
     * @return array<int, array{id:int,user_id:int}>
     */
    private function select(array $participants, int $limit, array $pinnedUserIds, int $seed, ?int $dominantSpeakerUserId): array
    {
        $byUserId = [];

        foreach ($participants as $participant) {
            $byUserId[$participant['user_id']] = $participant;
        }

        $selected = [];

        foreach ($pinnedUserIds as $userId) {
            if (isset($byUserId[$userId])) {
                $selected[$userId] = $byUserId[$userId];
            }

            if (count($selected) >= $limit) {
                return array_values($selected);
            }
        }

        if ($dominantSpeakerUserId !== null && isset($byUserId[$dominantSpeakerUserId])) {
            $selected[$dominantSpeakerUserId] = $byUserId[$dominantSpeakerUserId];
        }

        foreach ($this->shuffle($participants, $seed) as $participant) {
            $selected[$participant['user_id']] = $participant;

            if (count($selected) >= $limit) {
                break;
            }
        }

        return array_values($selected);
    }

    /**
     * @param  array<int, array{id:int,user_id:int}>  $participants
     * @return array<int, array{id:int,user_id:int}>
     */
    private function shuffle(array $participants, int $seed): array
    {
        usort($participants, function (array $left, array $right) use ($seed): int {
            return strcmp(
                sha1(sprintf('%s:%s', $seed, $left['user_id'])),
                sha1(sprintf('%s:%s', $seed, $right['user_id'])),
            );
        });

        return $participants;
    }
}
