<?php namespace App;

use Illuminate\Database\Eloquent\Model;
use \Illuminate\Database\Eloquent\Collection;

class Player extends Model
{
	protected $table = 'players';

	protected $fillable = ['user_id', 'username', 'win_count', 'loss_count', 'games_count',
    'dc_count', 'oos_count', 'points', 'countries', 'ladder_id'];

    protected $hidden = ['user_id', 'created_at', 'updated_at'];

    public function stats()
	{
        return $this->hasMany('App\GameStats');
	}

    public function games()
    {
        return $this->hasMany("App\PlayerGame");
    }

    public function rating()
    {
        return $this->hasMany("App\PlayerRating");
    }

    public function ladder()
    {
        return $this->belongsTo("App\Ladder");
    }

    public function rank($history, $username)
    {
        $players = new Collection();
        $ladderPlayers = \App\Player::where("ladder_id", "=", $history->ladder->id)->get();

        foreach($ladderPlayers as $player)
        {
            $player["points"] = \App\PlayerPoint::where("player_id", "=", $player->id)
            ->where("ladder_history_id", "=", $history->id)
            ->sum("points_awarded");

            $players->add($player);
        }

        $players = $players->sortByDesc('points')->values()->all();
        foreach($players as $k => $p)
        {
            if ($p->username == $username)
            {
                return $k + 1;
            }
        }
        return -1;
    }

    public function playerPoints($history, $username)
    {
        $player = \App\Player::where("username", "=", $username)
            ->where("ladder_id", "=", $history->ladder->id)->first();

        if ($player == null) return "No player";

        return \App\PlayerPoint::where("player_id", "=", $player->id)
            ->where("ladder_history_id", "=", $history->id)
            ->sum("points_awarded");
    }

    public function badge($rank)
    {
        switch ($rank)
        {
            case $rank >= 0 && $rank <= 50:
                return "rank-01-e2 badge-0";

            case $rank > 50 && $rank <= 100:
                return "rank-01-e3 badge-1";

            case $rank > 100 && $rank <= 200:
                return "rank-01-e4 badge-2";

            case $rank > 200 && $rank <= 300:
                return "rank-01-e5 badge-2";

            case $rank > 300 && $rank <= 400:
                return "rank-01-e6 badge-3";

            case $rank > 400 && $rank <= 500:
                return "rank-01-e7 badge-3";

            case $rank > 500 && $rank <= 600:
                return "rank-01-e8-1 badge-4";

            case $rank > 600 && $rank <= 700:
                return "rank-01-e8-2 badge-4";

            case $rank > 700 && $rank <= 800:
                return "rank-01-e9-1 badge-4";

            case $rank > 800 && $rank <= 900:
                return "rank-01-e9-2 badge-5";

            case $rank > 900 && $rank <= 1000:
                return "rank-01-e9-3 badge-5";

            case $rank > 1000 && $rank <= 1100:
                return "rank-02-00-e2 badge-6";

            case $rank > 1100 && $rank <= 1200:
                return "rank-02-00-e3 badge-6";

            case $rank > 1200 && $rank <= 1300:
                return "rank-02-00-e4 badge-7";

            case $rank > 1300 && $rank <= 1400:
                return "rank-02-00-e5 badge-7";

            case $rank > 1400 && $rank <= 1500:
                return "rank-02-00-e6 badge-7";

            case $rank > 1500 && $rank <= 1600:
                return "rank-02-00-e7 badge-7";

            case $rank > 1600:
                return "rank-02-00-e9-01 badge-8";

            default: "";
        }
    }
}