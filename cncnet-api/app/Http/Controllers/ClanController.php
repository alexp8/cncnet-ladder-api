<?php namespace App\Http\Controllers;

use App\Http\Requests;
use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use App\Ladder;
use App\Clan;
use App\ClanPlayer;
use App\ClanRole;
use App\ClanInvitation;
use App\Player;

class ClanController extends Controller {

    protected $ladderService = null;

    public function __construct()
    {
        $this->ladderService = new \App\Http\Services\LadderService;
    }

    public function getIndex(Request $request)
    {

        $clan_ladders = $this->ladderService->getLatestClanLadders();
        $ladders = $this->ladderService->getLatestLadders();

        return view('clans.index', [ 'ladders' => $ladders, 'clan_ladders' => $clan_ladders, ]);
    }

    public function getListing(Request $request, $ladderAbbrev, $date)
    {
        $clan_ladders = $this->ladderService->getLatestClanLadders();
        $ladders = $this->ladderService->getLatestLadders();

        $history = $this->ladderService->getActiveLadderByDate($date, $ladderAbbrev);
        $ladders_previous = $this->ladderService->getPreviousLaddersByGame($date, $ladderAbbrev);

        $search = $request->search;

        return view('clans.listing', compact('clan_ladders', 'ladders', 'history', 'ladders_previous', 'search'));
    }

    public function editLadderClan(Request $request, $ladderAbbrev, $clanId)
    {
        $clan_ladders = $this->ladderService->getLatestClanLadders();
        $ladders = $this->ladderService->getLatestLadders();
        $ladder = Ladder::where('abbreviation', '=', $ladderAbbrev)->first();

        $clan = Clan::find($clanId);
        if ($clan === null)
            abort(404);

        $user = $request->user();
        $player = $user->usernames->filter(function ($p) use ($clan)
        {
            return $p->clanPlayer !== null && $p->clanPlayer->clan_id == $clan->id;
        })->first();

        if ($player === null)
        {
            return response('Not Authorized', 403);
        }

        $invitations = $clan->invitations;

        $roles = ClanRole::all();
        return view('clans.edit', compact('clan_ladders', 'ladders', 'user', 'ladder', 'clan', 'player', 'invitations', 'roles'));
    }

    public function saveLadderClan(Request $request, $ladderAbbrev, $clanId = 'new')
    {
        $clan_ladders = $this->ladderService->getLatestClanLadders();
        $ladders = $this->ladderService->getLatestLadders();
        $ladder = Ladder::where('abbreviation', '=', $ladderAbbrev)->first();

        $this->validate($request, [ 'short' => 'required|string|max:6|unique:clans,short,'.$clanId,
                                    'name'  => 'required|string|max:255|unique:clans,name,'.$clanId,
        ]);

        $player = Player::find($request->player_id);

        $clan = Clan::find($clanId);
        if ($clan === null)
        {
            if ($player === null)
            {
                $request->session()->flash('error', "Unable to create the clan: Couldn't find the owner");
                return redirect()->back();
            }

            if ($player->clanPlayer !== null)
            {
                $request->session()->flash('error', "You can't create a new clan when you are still in a clan.");
                return redirect()->back();
            }

            $clan = new Clan;
            $clan->fill($request->all());
            $clan->save();

            $clanPlayer = new ClanPlayer;
            $clanPlayer->fill([ 'clan_id' => $clan->id, 'player_id' => $player->id ]);
            $clanPlayer->role = "Owner";
            $clanPlayer->save();

            $clanInvite = new ClanInvitation;
            $clanInvite->fill([ 'clan_id' => $clan->id, 'author_id' => $player->id, 'player_id' => $player->id, 'type' => 'invited' ]);
            $clanInvite->save();
            $clanInvite->delete();

            $request->session()->flash('success', "Successfully Created Clan.");
        }
        else
        {
            $user = $request->user();
            $requester = $user->usernames->filter(function ($p) use ($clan)
            {
                return $p->clanPlayer !== null && $p->clanPlayer->clan_id == $clan->id;
            })->first();

            if ($requester->id != $player->id)
            {
                $request->session()->flash('error', "Unable to update the clan: You are not the owner");
                return redirect()->back();
            }

            if (!$player->clanPlayer->isOwner())
            {
                $request->session()->flash('error', "Unable to update the clan: You are not the owner");
                return redirect()->back();
            }

            $clan->fill($request->all());
            $clan->save();
            $request->session()->flash('success', "Successfully updated clan.");
        }

        return redirect()->action('ClanController@editLadderClan', ['ladderAbbrev' => $ladderAbbrev, 'clanId' => $clan->id ]);
    }

    public function saveInvitation(Request $request, $ladderAbbrev, $clanId)
    {
        $ladder = Ladder::where('abbreviation', '=', $ladderAbbrev)->first();

        if ($ladder === null)
            abort(404);

        $clan = Clan::find($clanId);
        if ($clan === null)
        {
            $request->session()->flash('error', "Unable to locate the clan.");
            return redirect()->back();
        }

        $player = Player::where('username', '=', $request->playerName)->where('ladder_id', '=', $ladder->id)->first();

        if ($player === null)
        {
            $request->session()->flash('error', "Unable to find the player: {$request->playerName}");
            return redirect()->back();
        }

        $user = $request->user();
        $requester = $user->usernames->filter(function ($p) use ($clan)
        {
            return $p->clanPlayer !== null && $p->clanPlayer->clan_id == $clan->id;
        })->first();

        if ($requester === null)
        {
            $request->session()->flash('error', "You're not a member of this clan.");
            return redirect()->back();
        }

        if (! ($requester->clanPlayer->isOwner() || $requester->clanPlayer->isManager()))
        {
            $request->session()->flash('error', "You don't have permission to invite players. You have to be an Owner or Manager");
            return redirect()->back();
        }

        $invitation = ClanInvitation::firstOrNew( [ 'clan_id' => $clan->id, 'player_id' => $player->id, 'type' => 'invited' ]);
        if ($invitation->id !== null)
        {
            $request->session()->flash('error', "This player has already been invited.");
            return redirect()->back();
        }

        $invitation->author_id = $requester->id;
        $invitation->save();

        $request->session()->flash('success', "The invitation has been sent to {$request->playerName}.");
        return redirect()->back();
    }

    public function cancelInvitation(Request $request, $ladderAbbrev, $clanId)
    {
        $invite = ClanInvitation::find($request->id);

        if ($invite === null)
        {
            $request->session()->flash('error', "This invitation no longer exists");
            return redirect()->back();
        }

        $player = \App\Player::find($request->player_id);

        if ($player === null)
        {
            $request->session()->flash('error', "You do not have permission.");
            return redirect()->back();
        }

        $user = $request->user();

        if ($user === null || $player->user_id != $user->id)
        {
            $request->session()->flash('error', "You do not have permission.");
            return redirect()->back();
        }


        $log = new ClanInvitation;
        $log->fill([ 'clan_id' => $invite->clan_id, 'author_id' => $player->id, 'player_id' => $invite->player_id, 'type' => 'cancelled' ]);
        $log->save();
        $log->delete();

        $invite->delete();

        $request->session()->flash('success', "The invitation has been cancelled.");
        return redirect()->back();
    }

    public function processInvitation(Request $request, $ladderAbbrev, $clanId)
    {
        $ladder = Ladder::where('abbreviation', '=', $ladderAbbrev)->first();

        if ($ladder === null)
            abort(404);

        $invite = ClanInvitation::find($request->id);
        if ($invite === null)
        {
            $request->session()->flash('error', "Unable to locate the inviation.");
            return redirect()->back();
        }

        $clan = Clan::find($invite->clan_id);
        if ($clan === null)
        {
            $request->session()->flash('error', "Unable to locate the clan.");
            return redirect()->back();
        }

        $player = $invite->player;

        $user = $request->user();

        if ($user === null)
        {
            $request->session()->flash('error', "You have to be logged in to do that.");
            return redirect()->back();
        }

        if ($user->id !== $player->user_id)
        {
            $request->session()->flash('error', "You don't have permission to use this invitation.");
            return redirect()->back();
        }

        if ($player->ladder_id != $invite->clan->ladder_id)
        {
            $invite->delete();
            $request->session()->flash('error', "Invalid invitation.");
            return redirect()->back();
        }

        if ($request->submit == 'accept')
        {
            $clanPlayer = ClanPlayer::firstOrNew([ 'clan_id' => $clan->id, 'player_id' => $player->id ]);
            $clanPlayer->role = 'Member';
            $clanPlayer->save();
            $invite->delete();

            $invite = new ClanInvitation;
            $invite->fill([ 'clan_id' => $clan->id, 'author_id' => $player->id, 'player_id' => $player->id, 'type' => 'joined' ]);
            $invite->save();
            $invite->delete();
            $request->session()->flash('success', "You are now a member of {$clan->short}.");
        }
        else if ($request->submit == 'reject')
        {
            $invite->delete();
            $request->session()->flash('success', "Invitation rejected from {$clan->short}.");
        }

        return redirect()->back();
    }

    public function role(Request $request, $ladderAbbrev, $clanId)
    {
        $ladder = Ladder::where('abbreviation', '=', $ladderAbbrev)->first();

        if ($ladder === null)
            abort(404);

        $clan = Clan::find($clanId);
        if ($clan === null)
        {
            $request->session()->flash('error', "Unable to locate the clan.");
            return redirect()->back();
        }

    }

    public function kick(Request $request, $ladderAbbrev, $clanId)
    {
        $ladder = Ladder::where('abbreviation', '=', $ladderAbbrev)->first();

        if ($ladder === null)
            abort(404);

        $clan = Clan::find($clanId);
        if ($clan === null)
        {
            $request->session()->flash('error', "Unable to locate the clan.");
            return redirect()->back();
        }

    }

    public function leave(Request $request, $ladderAbbrev, $clanId)
    {
        $ladder = Ladder::where('abbreviation', '=', $ladderAbbrev)->first();

        if ($ladder === null)
            abort(404);

        $clan = Clan::find($clanId);
        if ($clan === null)
        {
            $request->session()->flash('error', "Unable to locate the clan.");
            return redirect()->back();
        }

        $clanPlayer = ClanPlayer::find($request->id);
        if ($clanPlayer === null)
        {
            $request->session()->flash('error', "That player is not a member of the clan.");
            return redirect()->back();
        }

        $user = $request->user();
        if ($user === null || $user->id != $clanPlayer->player->user_id)
        {
            $request->session()->flash('error', "You don't have permission.");
            return redirect()->back();
        }

        if ($clan->id != $clanPlayer->clan_id)
        {
            $request->session()->flash('error', "You don't have permission.");
            return redirect()->back();
        }

        $invite = new ClanInvitation;
        $invite->fill([ 'clan_id' => $clan->id, 'author_id' => $clanPlayer->player->id, 'player_id' => $clanPlayer->player->id, 'type' => 'left' ]);
        $invite->save();
        $invite->delete();

        $clanPlayer->delete();

        if ($clan->owners->count() < 1)
        {
            $owner = $clan->nextOwner($clanPlayer->player);
            if ($owner !== null)
            {
                $owner->role = 'Owner';
                $owner->save();
            }
        }

        $request->session()->flash('success', "You have left the clan {$clan->short}.");
        return redirect()->back();
    }

    public function saveMembers(Request $request, $ladderAbbrev, $clanId)
    {
        $ladder = Ladder::where('abbreviation', '=', $ladderAbbrev)->first();

        if ($ladder === null)
            abort(404);

        $clan = Clan::find($clanId);
        if ($clan === null)
        {
            $request->session()->flash('error', "Unable to locate the clan.");
            return redirect()->back();
        }

        $player = \App\Player::find($request->player_id);

        if ($player === null || !$player->clanPlayer->isOwner())
        {
            $request->session()->flash('error', "You don't have permission to do that.");
            return redirect()->back();
        }

        $user = $request->user();

        if ($player->user_id != $user->id)
        {
            $request->session()->flash('error', "You don't have permission to do that.");
            return redirect()->back();
        }

        foreach ($request->role as $id => $role_id)
        {
            $cp = ClanPlayer::find($id);

            if ($cp->clan_id == $clan->id)
            {
                $ci = new ClanInvitation;
                $ci->clan_id = $clan->id;
                $ci->author_id = $player->id;
                $ci->player_id = $cp->player_id;

                if ($role_id == 'kick')
                {
                    $ci->type = 'kicked';
                    $ci->save();
                    $ci->delete();
                    $cp->delete();
                }
                else if ($cp->clan_role_id != $role_id)
                {
                    $ci->type = $cp->clan_role_id > $role_id ? 'promoted' : 'demoted';
                    $cp->clan_role_id = $role_id;
                    $ci->save();
                    $ci->delete();
                    $cp->save();
                }
            }
        }

        if ($clan->owners->count() < 1)
        {
            $next = $clan->nextOwner(null);
            $next->role = 'Owner';
            $next->save();
        }

        $request->session()->flash('success', "Sucessfully updated.");
        return redirect()->back();
    }
}
