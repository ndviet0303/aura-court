<?php

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\Tournament;
use App\Models\TournamentFixture;
use App\Models\TournamentParticipant;
use App\Models\TournamentStanding;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class TournamentSeeder extends Seeder
{
    public function run(): void
    {
        $timezone = config('app.timezone', 'Asia/Ho_Chi_Minh');
        $now = Carbon::now($timezone);

        $admin = User::where('role', 'admin')->first();
        $customers = User::where('role', 'customer')->get();

        if (! $admin || $customers->count() < 8) {
            return;
        }

        $tournaments = [
            [
                'name' => 'Aura Badminton Summer Championship 2026',
                'format' => 'single_elimination',
                'status' => 'in_progress',
                'starts_at' => $now->copy()->addDays(2),
                'settings' => [
                    'max_participants' => 16,
                    'sport' => 'badminton',
                    'category' => 'Cầu Lông Đơn Nam Nâng Cao',
                    'prize_pool' => '20.000.000 ₫',
                    'venue' => 'Aura Badminton Complex',
                    'city' => 'Hà Nội',
                    'image_url' => 'https://images.unsplash.com/photo-1626224583764-f87db24ac4ea?w=800&auto=format&fit=crop&q=80',
                ],
            ],
            [
                'name' => 'Aura Pickleball Dynamic Cup 2026',
                'format' => 'round_robin',
                'status' => 'open',
                'starts_at' => $now->copy()->addDays(14),
                'settings' => [
                    'max_participants' => 12,
                    'sport' => 'pickleball',
                    'category' => 'Pickleball Đôi Nam Nữ 3.5+',
                    'prize_pool' => '15.000.000 ₫',
                    'venue' => 'Aura Sport Hub Tây Hồ',
                    'city' => 'Hà Nội',
                    'image_url' => 'https://images.unsplash.com/photo-1599474924187-334a4ae5bd3c?w=800&auto=format&fit=crop&q=80',
                ],
            ],
            [
                'name' => 'Hanoi 5v5 Corporate Football League 2026',
                'format' => 'single_elimination',
                'status' => 'open',
                'starts_at' => $now->copy()->addDays(20),
                'settings' => [
                    'max_participants' => 8,
                    'sport' => 'football_5v5',
                    'category' => 'Bóng Đá Mini Sân Cỏ 5 Người',
                    'prize_pool' => '30.000.000 ₫',
                    'venue' => 'Sân Bóng Mỹ Đình Stadium',
                    'city' => 'Hà Nội',
                    'image_url' => 'https://images.unsplash.com/photo-1574629810360-7efbbe195018?w=800&auto=format&fit=crop&q=80',
                ],
            ],
            [
                'name' => 'Saigon Open Tennis Masters 2026',
                'format' => 'single_elimination',
                'status' => 'open',
                'starts_at' => $now->copy()->addDays(28),
                'settings' => [
                    'max_participants' => 32,
                    'sport' => 'tennis',
                    'category' => 'Quần Vợt Đơn Nam Chuyên Nghiệp',
                    'prize_pool' => '50.000.000 ₫',
                    'venue' => 'Aura Thảo Điền Tennis Club',
                    'city' => 'TP. Hồ Chí Minh',
                    'image_url' => 'https://images.unsplash.com/photo-1554068865-24cecd4e34b8?w=800&auto=format&fit=crop&q=80',
                ],
            ],
            [
                'name' => 'Da Nang Coastal Pickleball Open 2026',
                'format' => 'single_elimination',
                'status' => 'finalized',
                'starts_at' => $now->copy()->subDays(10),
                'settings' => [
                    'max_participants' => 16,
                    'sport' => 'pickleball',
                    'category' => 'Pickleball Bãi Biển Sơn Trà',
                    'prize_pool' => '25.000.000 ₫',
                    'venue' => 'Aura Resort Beach Courts',
                    'city' => 'Đà Nẵng',
                    'image_url' => 'https://images.unsplash.com/photo-1587280501635-68a0e82cd5ff?w=800&auto=format&fit=crop&q=80',
                ],
            ],
            [
                'name' => 'Vietnam Badminton Super Cup 2026',
                'format' => 'single_elimination',
                'status' => 'open',
                'starts_at' => $now->copy()->addDays(40),
                'settings' => [
                    'max_participants' => 64,
                    'sport' => 'badminton',
                    'category' => 'Cầu Lông Đôi Nam Toàn Quốc',
                    'prize_pool' => '100.000.000 ₫',
                    'venue' => 'Nhà Thi Đấu Cầu Giấy',
                    'city' => 'Hà Nội',
                    'image_url' => 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=800&auto=format&fit=crop&q=80',
                ],
            ],
        ];

        $targetOrgs = Organization::where('status', 'active')->get();
        if ($targetOrgs->isEmpty()) {
            $targetOrgs = Organization::all();
        }

        foreach ($targetOrgs as $currentOrg) {
            foreach ($tournaments as $tData) {
                $tournament = Tournament::updateOrCreate(
                    ['organization_id' => $currentOrg->id, 'name' => $tData['name']],
                    [
                        'created_by' => $admin->id,
                        'format' => $tData['format'],
                        'status' => $tData['status'],
                        'version' => 1,
                        'starts_at' => $tData['starts_at'],
                        'settings' => $tData['settings'],
                    ]
                );

                // Clean up previous tournament tree elements to prevent unique constraint conflicts on re-seeding
                TournamentStanding::where('tournament_id', $tournament->id)->delete();
                TournamentFixture::where('tournament_id', $tournament->id)->delete();
                TournamentParticipant::where('tournament_id', $tournament->id)->delete();

                // Add 8 participants for tournament
                $participants = [];
                foreach (range(1, 8) as $pIdx) {
                    $user = $customers[$pIdx - 1];
                    $part = TournamentParticipant::create([
                        'organization_id' => $currentOrg->id,
                        'tournament_id' => $tournament->id,
                        'user_id' => $user->id,
                        'seed' => $pIdx,
                        'status' => 'registered',
                        'checked_in_at' => in_array($tData['status'], ['in_progress', 'finalized']) ? $now : null,
                    ]);
                    $participants[] = $part;
                }

                // Create Fixtures (Quarters, Semis, Finals)
                if (count($participants) >= 8) {
                    $isDoneOrLive = in_array($tData['status'], ['in_progress', 'finalized']);

                    // Quarter Finals (Round 1)
                    $qfScores = [
                        ['p1' => 21, 'p2' => 18, 'winner' => 0],
                        ['p1' => 21, 'p2' => 19, 'winner' => 0],
                        ['p1' => 17, 'p2' => 21, 'winner' => 1],
                        ['p1' => 21, 'p2' => 15, 'winner' => 0],
                    ];

                    for ($pos = 1; $pos <= 4; $pos++) {
                        $p1Idx = ($pos - 1) * 2;
                        $p2Idx = $p1Idx + 1;
                        $cfg = $qfScores[$pos - 1];
                        $wId = $isDoneOrLive ? ($cfg['winner'] === 0 ? $participants[$p1Idx]->id : $participants[$p2Idx]->id) : null;

                        TournamentFixture::updateOrCreate(
                            ['organization_id' => $currentOrg->id, 'tournament_id' => $tournament->id, 'round' => 1, 'position' => $pos],
                            [
                                'participant_one_id' => $participants[$p1Idx]->id,
                                'participant_two_id' => $participants[$p2Idx]->id,
                                'winner_id' => $wId,
                                'score' => $isDoneOrLive ? ['p1' => $cfg['p1'], 'p2' => $cfg['p2'], 'sets' => [['p1' => $cfg['p1'], 'p2' => $cfg['p2']]]] : null,
                                'status' => $isDoneOrLive ? 'completed' : 'scheduled',
                                'version' => 1,
                            ]
                        );
                    }

                    // Semi Finals (Round 2)
                    $sfPairs = [
                        ['p1' => 0, 'p2' => 2, 'score' => ['p1' => 21, 'p2' => 17]],
                        ['p1' => 5, 'p2' => 6, 'score' => ['p1' => 19, 'p2' => 21]],
                    ];

                    for ($pos = 1; $pos <= 2; $pos++) {
                        $pair = $sfPairs[$pos - 1];
                        $wId = $tData['status'] === 'finalized' ? $participants[$pair['p1']]->id : null;
                        $isLive = $tData['status'] === 'in_progress';

                        TournamentFixture::updateOrCreate(
                            ['organization_id' => $currentOrg->id, 'tournament_id' => $tournament->id, 'round' => 2, 'position' => $pos],
                            [
                                'participant_one_id' => $participants[$pair['p1']]->id,
                                'participant_two_id' => $participants[$pair['p2']]->id,
                                'winner_id' => $wId,
                                'score' => $tData['status'] === 'finalized' ? $pair['score'] : ($isLive ? ['p1' => 14, 'p2' => 12] : null),
                                'status' => $tData['status'] === 'finalized' ? 'completed' : ($isLive ? 'live' : 'scheduled'),
                                'version' => 1,
                            ]
                        );
                    }

                    // Finals (Round 3)
                    $finalWinnerId = $tData['status'] === 'finalized' ? $participants[0]->id : null;
                    TournamentFixture::updateOrCreate(
                        ['organization_id' => $currentOrg->id, 'tournament_id' => $tournament->id, 'round' => 3, 'position' => 1],
                        [
                            'participant_one_id' => $participants[0]->id,
                            'participant_two_id' => $participants[6]->id,
                            'winner_id' => $finalWinnerId,
                            'score' => $tData['status'] === 'finalized' ? ['p1' => 21, 'p2' => 19, 'sets' => [['p1' => 21, 'p2' => 19]]] : null,
                            'status' => $tData['status'] === 'finalized' ? 'completed' : 'scheduled',
                            'version' => 1,
                        ]
                    );
                }

                // Standings
                foreach ($participants as $rank => $part) {
                    TournamentStanding::updateOrCreate(
                        ['organization_id' => $currentOrg->id, 'tournament_id' => $tournament->id, 'participant_id' => $part->id],
                        [
                            'rank' => $rank + 1,
                            'points' => (8 - $rank) * 350,
                            'wins' => max(0, 4 - (int) ($rank / 2)),
                            'losses' => min(3, (int) ($rank / 2)),
                            'draws' => 0,
                            'played' => max(1, 4 - (int) ($rank / 2)),
                        ]
                    );
                }
            }
        }
    }
}
